<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\contracts;

use Beeyev\YaTranslate\Translate;
use enupal\translate\Translate as TranslatePlugin;
use Craft;

class Yandex
{
    /**
     * @var string
     */
    private $api;

    public function __construct()
    {
        $settings = TranslatePlugin::$app->translate->getPluginSettings();
        $this->api = $settings->yandexApi;
    }

    /**
     * Translate message
     *
     * @param string|array $text     The text to translate.
     * @param string       $language The translation language.
     *
     * @return bool|object []
     */
    public function translate($text, $language)
    {
        $result = false;
        try {
            $tr = new Translate($this->api);
            $result = $tr->translate($text, $language);
        } catch (\Beeyev\YaTranslate\TranslateException $e) {
            //Handle exception
            Craft::error($e->getMessage(), __METHOD__);
        }

        return $result;
    }

}