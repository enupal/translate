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
 * Lets other plugins map their own field classes to a serializer.
 */
class RegisterSerializersEvent extends Event
{
    /**
     * Serializer class names keyed by field class name.
     *
     * @var array<string, string>
     */
    public array $serializers = [];
}
