<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class TranslateQuery extends ElementQuery
{

    // General - Properties
    // =========================================================================
    public $id;
    public $source;
    public $translateStatus;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function source($value)
    {
        $this->source = $value;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        return false;
    }
}
