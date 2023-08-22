<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps;

use Craft;
use superbig\vipps\records\PaymentRecord;
use superbig\vipps\services\Api;
use superbig\vipps\services\Express;
use superbig\vipps\services\Payments;

/**
 * Trait Services
 *
 * @package superbig\vipps
 *
 * @property  Payments $payments
 * @property  Express $express
 * @property  Api $api
 */
trait Services
{
    public function initComponents(): void
    {
        $this->setComponents([
            'payments' => Payments::class,
            'express' => Express::class,
            'api' => Api::class,
        ]);
    }

    /**
     * @return Payments The Payments service
     */
    public function getPayments(): Payments
    {
        return $this->get('payments');
    }

    /**
     * @return Api The Api service
     */
    public function getApi(): Api
    {
        return $this->get('api');
    }

    /**
     * @return Express The Express service
     */
    public function getExpress(): Express
    {
        return $this->get('express');
    }

    /**
     * Determine whether our table schema exists or not; this is needed because
     * migrations such as the install migration and base_install migration may
     * not have been run by the time our init() method has been called
     *
     * @return bool
     */
    public function migrationsAndSchemaReady(): bool
    {
        $pluginsService = Craft::$app->getPlugins();
        if ($pluginsService->isPluginUpdatePending(self::$plugin)) {
            return false;
        }
        if (Craft::$app->db->schema->getTableSchema(PaymentRecord::tableName()) === null) {
            return false;
        }

        return true;
    }
}
