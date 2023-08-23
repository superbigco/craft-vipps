<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\migrations;

use Craft;
use craft\db\Migration;

use superbig\vipps\records\PaymentRecord;
use superbig\vipps\Vipps;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Install extends Migration
{
    /**
     * @var string The database driver to use
     */
    public string $driver;


    public function safeUp(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();

            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }


    public function safeDown(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    /**
     * @return bool
     */
    protected function createTables(): bool
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(PaymentRecord::tableName());
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                PaymentRecord::tableName(),
                [
                    'id' => $this->primaryKey(),
                    'orderId' => $this->integer()->notNull(),
                    'transactionReference' => $this->string(255)->notNull()->defaultValue(''),
                    'shortId' => $this->string(255)->notNull()->defaultValue(''),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes(): void
    {
        $this->createIndex(
            $this->db->getIndexName(),
            PaymentRecord::tableName(),
            'shortId',
            true
        );

        $this->createIndex(
            $this->db->getIndexName(),
            PaymentRecord::tableName(),
            'transactionReference',
            true
        );
    }

    /**
     * @return void
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            $this->db->getForeignKeyName(),
            PaymentRecord::tableName(),
            'orderId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * @return void
     */
    protected function removeTables(): void
    {
        $this->dropTableIfExists(PaymentRecord::tableName());
    }
}
