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
use GoogleTranslate\Client as GoogleClient;
use RuntimeException;

/**
 * Google Cloud Translation API v2 (paid, API key).
 */
class GoogleCloudProvider extends TranslationProvider
{
    public static function handle(): string
    {
        return 'googleCloud';
    }

    public static function displayName(): string
    {
        return 'Google Cloud Translate';
    }

    public function isConfigured(): bool
    {
        $settings = $this->getSettings();

        return (bool)$settings->enableGoogleApi && !empty($settings->googleApi);
    }

    protected function getMaxChunkStrings(): int
    {
        return 100;
    }

    protected function getMaxChunkCharacters(): int
    {
        return 9000;
    }

    protected function performTranslation(array $texts, string $targetLanguage, ?string $sourceLanguage): TranslationResult
    {
        $settings = $this->getSettings();

        $from = $this->shortLanguage($sourceLanguage ?: Craft::$app->getSites()->getPrimarySite()->language);
        $to = $this->shortLanguage($targetLanguage);

        $client = new GoogleClient($settings->googleApi);
        $translated = $client->translate(array_values($texts), $to, $from);

        if (!is_array($translated)) {
            $translated = [$translated];
        }

        $keys = array_keys($texts);

        if (count($translated) !== count($keys)) {
            throw new RuntimeException(sprintf(
                'Google returned %d translations for %d strings.',
                count($translated),
                count($keys)
            ));
        }

        $translated = array_map(static fn($value) => html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $translated);

        return new TranslationResult([
            'provider' => static::handle(),
            'translations' => array_combine($keys, $translated),
            'apiCalls' => 1,
        ]);
    }
}
