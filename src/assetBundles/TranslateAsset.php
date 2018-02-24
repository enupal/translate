<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class TranslateAsset extends AssetBundle
{
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@enupal/translate/resources/';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        // define the dependencies
        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'js/enupalTranslate.js'
        ];

        parent::init();
    }
}