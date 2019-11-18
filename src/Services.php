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

use superbig\vipps\services\Api;
use superbig\vipps\services\Express;
use superbig\vipps\services\Payments;

/**
 * Trait Services
 *
 * @package superbig\vipps
 *
 * @property  Payments $payments
 * @property  Express  $express
 * @property  Api      $api
 */
trait Services
{
    public function initComponents()
    {
        $this->setComponents([
            'payments' => Payments::class,
            'express'  => Express::class,
            'api'      => Api::class,
        ]);
    }

    /**
     * @return Payments The Payments service
     */
    public function getPayments()
    {
        return $this->get('payments');
    }

    /**
     * @return Api The Api service
     */
    public function getApi()
    {
        return $this->get('api');
    }

    /**
     * @return Express The Express service
     */
    public function getExpress()
    {
        return $this->get('express');
    }
}