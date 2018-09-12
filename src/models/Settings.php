<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\models;

use craft\base\Model;

/**
 * @author    Enupal
 * @package   Translate
 * @since     1.0.0
 */
class Settings extends Model
{
    /**
     * @var string
     */
    public $pluginNameOverride;

    /**
     * @var bool
     */
    public $enableYandex = 0;

    /**
     * @var string
     */
    public $yandexApi = '';

    /**
     * @var bool
     */
    public $enableFreeGoogleApi = 0;

    /**
     * @var bool
     */
    public $enableGoogleApi = 0;

    /**
     * @var string
     */
    public $googleApi = '';

    /**
     * @var string
     */
    public $createPluginTranslationFolder = 0;
}
