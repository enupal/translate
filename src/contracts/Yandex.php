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
use Craft;

class Yandex
{
    /**
     * @var string
     */
    private $api;

    public function __construct()
    {
        // @todo get this from the settings
        $this->api = 'trnsl.1.1.20180217T233458Z.f9ffe1f4a31bf54f.471de6e287d2a689aeb698f007a62b62273b9110';
    }

    /**
     * Translate message
     * @param string|array $text The text to translate.
     * @param string $language The translation language.
     * @return []
     */
    public function translate($text, $language)
    {
        $result = $text;
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