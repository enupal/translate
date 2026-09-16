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
use Stichoza\GoogleTranslate\GoogleTranslate as FreeGoogleClient;

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

        foreach ($texts as $key => $text) {
            $translations[$key] = $client->translate($text);
            $calls++;
        }

        return new TranslationResult([
            'provider' => static::handle(),
            'translations' => $translations,
            'apiCalls' => $calls,
        ]);
    }
}
