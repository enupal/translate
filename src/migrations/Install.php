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

        $this->createTable($message, [
            'id' => $this->integer()->notNull(),
            'language' => $this->string(),
            'translation' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        if ($this->db->driverName === 'mysql') {
            $name = $this->db->getIndexName($sourceMessage, ['message'], false);
            $this->execute("ALTER TABLE ".$sourceMessage." ADD FULLTEXT INDEX ".$name." (`message`)");

            $name = $this->db->getIndexName($message, ['translation'], false);
            $this->execute("ALTER TABLE ".$message." ADD FULLTEXT INDEX ".$name." (`translation`)");
        }

        $this->createIndex(null, $sourceMessage, 'category');
        $this->createIndex(null, $message, ['language', 'id'], true);

        $this->addForeignKey(null, $message, ['id'], $sourceMessage, ['id'], 'CASCADE', 'RESTRICT');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%enupaltranslate_message}}');
        $this->dropTableIfExists('{{%enupaltranslate_sourcemessage}}');

        return true;
    }
}