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
 * @package   Snapshot
 * @since     1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $yandexApi = '';

    /**
     * @var string
     */
    public $googleApi = '';
}
