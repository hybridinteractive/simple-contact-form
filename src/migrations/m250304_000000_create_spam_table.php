<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\migrations;

use craft\db\Migration;

/**
 * Creates the spam submissions table.
 */
class m250304_000000_create_spam_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%simplecontactform_spam}}';

        if ($this->db->schema->getTableSchema($table) === null) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'form' => $this->string()->null(),
                'subject' => $this->string()->null(),
                'fromName' => $this->string()->null(),
                'fromEmail' => $this->string()->null(),
                'message' => $this->text()->notNull(),
                'reason' => $this->string()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, $table, ['dateCreated'], false);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%simplecontactform_spam}}');

        return true;
    }
}
