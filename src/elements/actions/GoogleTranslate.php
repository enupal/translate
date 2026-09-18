<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\elements\actions;

class GoogleTranslate extends BaseProviderTranslate
{
    public static function providerHandle(): string
    {
        return 'googleFree';
    }
}
