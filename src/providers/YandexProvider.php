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
use Enupal\YaTranslate\Translate as YandexClient;
use RuntimeException;

/**
 * Yandex Translate.
 *
 * The API takes an array of strings and answers with an array in the same
 * order, so a batch maps back by index with no delimiter tricks.
 */
class YandexProvider extends TranslationProvider
{
    public static function handle(): string
    {
        return 'yandex';
    }

    public static function displayName(): string
    {
        return 'Yandex Translate';
    }

    public function isConfigured(): bool
    {
        $settings = $this->getSettings();

        return (bool)$settings->enableYandex && !empty($settings->yandexApi);
    }

    protected function getMaxChunkStrings(): int
    {
        return 50;
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

        $client = new YandexClient($settings->yandexApi);
        $response = $client->translate(array_values($texts), $from . '-' . $to, 'auto');

        $translated = $response->translation();

        if (!is_array($translated)) {
            throw new RuntimeException('Yandex returned an unexpected response.');
        }

        $keys = array_keys($texts);

        if (count($translated) !== count($keys)) {
            throw new RuntimeException(sprintf(
                'Yandex returned %d translations for %d strings.',
                count($translated),
                count($keys)
            ));
        }

        return new TranslationResult([
            'provider' => static::handle(),
            'translations' => array_combine($keys, $translated),
            'apiCalls' => 1,
        ]);
    }
}
