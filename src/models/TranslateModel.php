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

use Craft;
use craft\base\Element;

class TranslateModel extends Element
{
    /**
     * Status constants.
     */
    const DONE = 'live';
    const PENDING = 'pending';

    /**
     * Element type.
     *
     * @var string
     */
    protected $elementType = 'Translate';

    /**
     * Return this model's title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->original;
    }

    /**
     * Return this model's status.
     *
     * @return string
     */
    public function getStatus()
    {
        if ($this->original != $this->translation) {
            return static::DONE;
        } else {
            return static::PENDING;
        }
    }

    /**
     * Define model attributes.
     *
     * @return array
     */
    protected function defineAttributes()
    {
        return array_merge(parent::defineAttributes(), array(
            'id' => AttributeType::String,
            'original' => AttributeType::String,
            'translation' => AttributeType::String,
            'source' => AttributeType::Mixed,
            'file' => AttributeType::String,
            'locale' => array(AttributeType::String, 'default' => 'en_us'),
            'field' => AttributeType::Mixed,
        ));
    }
}
