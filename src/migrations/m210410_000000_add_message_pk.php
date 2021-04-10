<?php

namespace enupal\translate\migrations;

use craft\db\Migration;

/**
 * m210410_000000_add_message_pk migration.
 */
class m210410_000000_add_message_pk extends Migration
{
    /**
     * @return bool
     * @throws \yii\base\NotSupportedException
     */
    public function safeUp()
    {
        $message = '{{%enupaltranslate_message}}';

        $this->addPrimaryKey(null, $message, 'id');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210410_000000_add_message_pk cannot be reverted.\n";

        return false;
    }
}
