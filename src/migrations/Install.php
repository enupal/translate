<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */


namespace enupal\translate\migrations;

use craft\db\Migration;
use Craft;
/**
 * Installation Migration
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $sourceMessage = '{{%enupaltranslate_sourcemessage}}';
        $message = '{{%enupaltranslate_message}}';

        $this->createTable($sourceMessage, [
            'id' => $this->primaryKey(),
            'category' => $this->string()->defaultValue('site'),
            'message' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        if ($this->db->driverName === 'mysql') {
            $this->createTable($message, [
                'id' => $this->primaryKey(),
                'language' => $this->string(),
                'translation' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        } else {
            $this->createTable($message, [
                'id' => $this->integer()->notNull(),
                'language' => $this->string(),
                'translation' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        if ($this->db->driverName === 'mysql') {
            $name = $this->db->getIndexName($sourceMessage, ['message'], false);
            $this->execute("ALTER TABLE ".$sourceMessage." ADD FULLTEXT INDEX ".$name." (`message`)");

            $name = $this->db->getIndexName($message, ['translation'], false);
            $this->execute("ALTER TABLE ".$message." ADD FULLTEXT INDEX ".$name." (`translation`)");
        }

        $this->createIndex(null, $sourceMessage, 'category');
        $this->createIndex(null, $message, ['language', 'id'], true);

        $this->addForeignKey(null, $message, ['id'], $sourceMessage, ['id'], 'CASCADE', 'RESTRICT');

        $this->createMetricsTable();

        return true;
    }

    /**
     * Usage metrics behind the translation dashboard.
     */
    private function createMetricsTable(): void
    {
        $metrics = '{{%enupaltranslate_metrics}}';

        if ($this->db->tableExists($metrics)) {
            return;
        }

        $this->createTable($metrics, [
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

        $this->createIndex(null, $metrics, ['dateCreated']);
        $this->createIndex(null, $metrics, ['provider']);
        $this->createIndex(null, $metrics, ['type']);
        $this->createIndex(null, $metrics, ['targetLanguage']);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%enupaltranslate_metrics}}');
        $this->dropTableIfExists('{{%enupaltranslate_message}}');
        $this->dropTableIfExists('{{%enupaltranslate_sourcemessage}}');

        return true;
    }
}