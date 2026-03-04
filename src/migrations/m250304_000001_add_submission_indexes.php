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
 * Adds indexes to the submissions table for better query performance.
 */
class m250304_000001_add_submission_indexes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%simplecontactform_submissions}}';

        // Index on form - used for filtering by form handle (e.g. sidebar sources)
        $this->createIndexIfMissing($table, ['form'], false);

        // Index on dateCreated - used for sorting (most recent first)
        $this->createIndexIfMissing($table, ['dateCreated'], false);

        // Index on fromEmail - used for filtering and deduplication lookups
        $this->createIndexIfMissing($table, ['fromEmail'], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = '{{%simplecontactform_submissions}}';

        $this->dropIndexIfExists($table, ['form'], false);
        $this->dropIndexIfExists($table, ['dateCreated'], false);
        $this->dropIndexIfExists($table, ['fromEmail'], false);

        return true;
    }
}
