<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\events;

use yii\base\Event;

/**
 * Lets other plugins add their own translation providers.
 */
class RegisterProvidersEvent extends Event
{
    /**
     * Fully qualified class names extending TranslationProvider.
     *
     * @var string[]
     */
    public array $providers = [];
}
