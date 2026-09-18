<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\providers;

use Craft;
use enupal\translate\base\TranslationProvider;
use enupal\translate\models\TranslationResult;
use RuntimeException;
use Stichoza\GoogleTranslate\GoogleTranslate as FreeGoogleClient;
use Throwable;

/**
 * The free Google Translate endpoint, via web scraping.
 *
 * Unlike the paid API this has no batch endpoint. The previous implementation
 * joined every string with ' || ' and split the answer back apart, which
 * silently mis-aligned every translation whenever Google dropped or altered a
 * separator. Each string is now sent on its own request instead: slower, but
 * a translation can never be attributed to the wrong source string.
 */
class GoogleFreeProvider extends TranslationProvider
{
    public static function handle(): string
    {
        return 'googleFree';
    }

    public static function displayName(): string
    {
        return 'Google Translate (Free)';
    }

    public function isConfigured(): bool
    {
        return (bool)$this->getSettings()->enableFreeGoogleApi;
    }

    /**
     * One request per string, so chunking is handled here instead.
     */
    protected function getMaxChunkStrings(): int
    {
        return 25;
    }

    /**
     * This endpoint is scraped, not an API, and Google throttles bursts hard.
     * Pacing the requests is what keeps a batch from being cut off partway.
     */
    protected function getRequestDelayMs(): int
    {
        return 500;
    }

    /**
     * Retrying a throttled scrape immediately just deepens the block, and
     * Google's answer is an HTML page rather than a machine-readable error,
     * so treat any 429 here as terminal for this run.
     */
    protected function isRetryable(Throwable $e): bool
    {
        if ($this->getStatusCode($e) === 429) {
            return false;
        }

        return parent::isRetryable($e);
    }

    protected function getMaxChunkCharacters(): int
    {
        return PHP_INT_MAX;
    }

    protected function performTranslation(array $texts, string $targetLanguage, ?string $sourceLanguage): TranslationResult
    {
        $from = $this->shortLanguage($sourceLanguage ?: Craft::$app->getSites()->getPrimarySite()->language);
        $to = $this->shortLanguage($targetLanguage);

        $client = new FreeGoogleClient($to, $from);

        $translations = [];
        $calls = 0;
        $delay = $this->getRequestDelayMs() * 1000;

        foreach ($texts as $key => $text) {
            if ($calls > 0 && $delay > 0) {
                usleep($delay);
            }

            try {
                $translations[$key] = $client->translate($text);
            } catch (Throwable $e) {
                if ($this->getStatusCode($e) !== 429) {
                    throw $e;
                }

                // Hand back what was translated before the block, so a long
                // run isn't lost entirely.
                return new TranslationResult([
                    'provider' => static::handle(),
                    'translations' => $translations,
                    'apiCalls' => $calls + 1,
                    'success' => false,
                    'errorMessage' => \Craft::t('enupal-translate',
                        'Google rate-limited this server after {done} of {total} strings. The free endpoint is scraped rather than an official API, so Google throttles it without warning — wait a while and retry a smaller batch, or use Google Cloud Translate or an AI provider for bulk work.', [
                            'done' => $calls,
                            'total' => count($texts),
                        ]),
                ]);
            }

            $calls++;
        }

        return new TranslationResult([
            'provider' => static::handle(),
            'translations' => $translations,
            'apiCalls' => $calls,
        ]);
    }
}
