<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\web;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Charting for the translation dashboard.
 */
class DashboardAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@enupal/translate/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'https://cdn.jsdelivr.net/npm/apexcharts@3.46',
        ];

        $this->css = [
            'css/dashboard.css',
        ];

        parent::init();
    }
}
