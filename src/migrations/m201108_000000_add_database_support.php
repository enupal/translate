<?php

namespace enupal\translate\migrations;

use craft\db\Migration;

/**
 * m201108_000000_add_database_support migration.
 */
class m201108_000000_add_database_support extends Migration
{
    /**
     * @return bool
     * @throws \yii\base\NotSupportedException
     */
    public function safeUp()
    {
        $sourceMessage = '{{%enupaltranslate_sourcemessage}}';
        $message = '{{%enupaltranslate_message}}';

        $this->createTable($sourceMessage, [
            'id' => $this->primaryKey(),
            'category' => $this->string(),
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
            $this->createIndex(null, $sourceMessage, 'category');

            $name = $this->db->getIndexName($message, ['translation'], false);
            $this->execute("ALTER TABLE ".$message." ADD FULLTEXT INDEX ".$name." (`translation`)");
            $this->createIndex(null, $message, 'language');
        }

        $this->addForeignKey(null, $message, ['id'], $sourceMessage, ['id'], 'CASCADE', 'RESTRICT');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201108_000000_add_database_support cannot be reverted.\n";

        return false;
    }
}
