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

use Stichoza\GoogleTranslate\TranslateClient;
use Craft;

class GoogleTranslate
{
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
            $transBlock = implode(' || ', $text);
            $tr = new TranslateClient($from, $to);
            $results = $tr->translate($transBlock);
            $result = explode(' || ',$results);
            // removes white spaces
            $result=array_map('trim',$result);
            // something went wrong
            if (count($result) != count($text)){
                Craft::error('The array sizes does not match', __METHOD__);
                $result = false;
            }
        } catch (\Exception $e) {
            //Handle exception
            Craft::error($e->getMessage(), __METHOD__);
        }

        return $result;
    }

}