<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\behaviors;

use craft\commerce\models\Transaction;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use superbig\vipps\gateways\Gateway;
use superbig\vipps\models\ErrorModel;
use superbig\vipps\responses\CallbackResponse;
use yii\base\Behavior;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class TransactionBehavior extends Behavior
{
    /** @var Transaction */
    public $owner;

    public function getVippsError()
    {
        $transaction = $this->owner;

        if (!$this->isVippsGateway()) {
            return null;
        }

        return new ErrorModel([
            'response' => $this->getResponse(),
        ]);
    }

    public function isCancelled()
    {
        if (!$this->isVippsGateway()) {
            return false;
        }

        return ArrayHelper::getValue($this->getResponse(), 'transactionInfo.status') === CallbackResponse::STATUS_CANCELLED;
    }

    public function getResponse()
    {
        return Json::decode($this->owner->response);
    }

    public function isVippsGateway()
    {
        return $this->owner->getGateway() instanceof Gateway;
    }
}
