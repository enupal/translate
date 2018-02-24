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

use Craft;
use GoogleTranslate\Client;
use enupal\translate\Translate as TranslatePlugin;

class GoogleCloudTranslate
{
    private $api;

    public function __construct()
    {
        $settings = TranslatePlugin::$app->translate->getPluginSettings();
        $this->api = $settings->googleApi;
    }

    /**
     * Translate message
     *
     * @param string|array $text The text to translate.
     * @param              $from
     * @param              $to
     *
     * @return bool|[]
     */
    public function translate($text, $from, $to)
    {
        $result = false;
        try {
            $client = new Client($this->api);
            $result = $client->translate($text, $to, $from);
        } catch (\Exception $e) {
            //Handle exception
            Craft::error($e->getMessage(), __METHOD__);
        }

        return $result;
    }

}