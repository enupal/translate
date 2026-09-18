<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\migrations;

use craft\db\Migration;

/**
 * Adds the table backing the translation dashboard.
 */
class m260916_000000_add_metrics_table extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%enupaltranslate_metrics}}';

        if ($this->db->tableExists($table)) {
            return true;
        }

        $this->createTable($table, [
            'id' => $this->primaryKey(),
            'provider' => $this->string(64)->notNull(),
            'model' => $this->string(128),
            'type' => $this->string(32)->notNull()->defaultValue('static'),
            'sourceLanguage' => $this->string(32),
            'targetLanguage' => $this->string(32)->notNull(),
            'siteId' => $this->integer(),
            'elementId' => $this->integer(),
            'elementType' => $this->string(255),
            'stringCount' => $this->integer()->notNull()->defaultValue(0),
            'characterCount' => $this->integer()->notNull()->defaultValue(0),
            'inputTokens' => $this->integer()->notNull()->defaultValue(0),
            'outputTokens' => $this->integer()->notNull()->defaultValue(0),
            'apiCalls' => $this->integer()->notNull()->defaultValue(0),
            'durationMs' => $this->integer()->notNull()->defaultValue(0),
            'success' => $this->boolean()->notNull()->defaultValue(true),
            'errorMessage' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // The dashboard always filters on a date range, usually narrowed
        // further by provider or type.
        $this->createIndex(null, $table, ['dateCreated']);
        $this->createIndex(null, $table, ['provider']);
        $this->createIndex(null, $table, ['type']);
        $this->createIndex(null, $table, ['targetLanguage']);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%enupaltranslate_metrics}}');

        return true;
    }
}
