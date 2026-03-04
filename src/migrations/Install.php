<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\migrations;

use Craft;
use craft\db\Migration;

/**
 * Simple Contact Form Install Migration.
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return bool return a false value to indicate the migration fails
     *              and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return bool return a false value to indicate the migration fails
     *              and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin.
     *
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $schema = Craft::$app->db->schema;
        $newTable = '{{%simplecontactform_submissions}}';
        $oldTable = '{{%contactform_submissions}}';

        // Prefer reusing the old contactform_submissions table if it exists
        $newTableSchema = $schema->getTableSchema($newTable);
        if ($newTableSchema === null) {
            $oldTableSchema = $schema->getTableSchema($oldTable);

            if ($oldTableSchema !== null) {
                // Rename the existing table from the old plugin
                $this->renameTable($oldTable, $newTable);
            } else {
                // Otherwise, create a fresh table for this plugin
                $tablesCreated = true;
                $this->createTable(
                    $newTable,
                    [
                        'id'          => $this->integer()->notNull(),
                        'form'        => $this->string()->null(),
                        'subject'     => $this->string()->null(),
                        'fromName'    => $this->string()->null(),
                        'fromEmail'   => $this->string()->null(),
                        'message'     => $this->text()->notNull(),
                        'dateCreated' => $this->dateTime()->notNull(),
                        'dateUpdated' => $this->dateTime()->notNull(),
                        'uid'         => $this->uid(),
                        'PRIMARY KEY(id)',
                    ]
                );
            }
        }

        return $tablesCreated;
    }

    /**
     * Creates the foreign keys needed for the Records used by the plugin.
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        // contactform_submissions table
        $this->addForeignKey(
            null,
            '{{%simplecontactform_submissions}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );
    }

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData()
    {
    }

    /**
     * Removes the tables needed for the Records used by the plugin.
     *
     * @return void
     */
    protected function removeTables()
    {
        // contactform_submissions table
        $this->dropTableIfExists('{{%simplecontactform_submissions}}');
        // spam table
        $this->dropTableIfExists('{{%simplecontactform_spam}}');
    }
}
