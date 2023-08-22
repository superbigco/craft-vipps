<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\services;

use Craft;
use craft\base\Component;
use craft\commerce\adjusters\Shipping;
use craft\commerce\Plugin;
use craft\events\RegisterComponentTypesEvent;

use craft\helpers\UrlHelper;
use superbig\vipps\Vipps;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use function is_numeric;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Express extends Component
{
    // Public Methods
    // =========================================================================

    public function onRegisterOrderAdjusters(RegisterComponentTypesEvent $e): void
    {
        if (Vipps::$plugin->getPayments()->getIsExpress()) {
            // When Commerce calls `Plugin::getInstance()->getOrderAdjustments()->getAdjusters()`
            // it will get the first shipping adjuster if none is set
            // This removes the adjustment, making sure the shipping is applied by the gateway
            foreach ($e->types as $key => $adjuster) {
                if ($adjuster === Shipping::class || $adjuster instanceof Shipping) {
                    unset($e->types[ $key ]);
                }
            }
        }
    }

    /**
     * @param null  $purchasable
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function getButton($purchasable = null, array $config = []): string
    {
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        if (is_numeric($purchasable)) {
            $purchasable = Plugin::getInstance()->getVariants()->getVariantById($purchasable);
        }

        $class = $config['class'] ?? null;
        $title = $config['title'] ?? '';

        $url = $this->getCheckoutUrl($purchasable, $config);
        $html = $view->renderTemplate('vipps/_components/express/button', [
            'url' => $url,
            'class' => $class,
            'title' => $title,
            'purchasable' => $purchasable,
        ]);

        $view->setTemplateMode($oldMode);

        return $html;
    }

    public function getCheckoutUrl($purchasable = null, array $config = []): string
    {
        $data = array_filter([
            'id' => $purchasable->id ?? null,
            'qty' => $config['quantity'] ?? $config['qty'] ?? 1,
            'note' => $config['note'] ?? null,
            'options' => $config['options'] ?? [],
        ]);
        $payload = [
            'purchasables' => [
                1 => $data,
            ],
        ];

        if (!$purchasable) {
            $payload = [];
        }

        return UrlHelper::siteUrl('vipps/express/checkout', $payload);
    }

    /**
     * @param null  $purchasable
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function getFormButton($purchasable = null, array $config = []): string
    {
        $view = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        $data = array_filter([
            'id' => $purchasable->id ?? null,
            'qty' => $config['quantity'] ?? $config['qty'] ?? 1,
            'note' => $config['note'] ?? null,
            'options' => $config['options'] ?? [],
        ]);
        $class = $config['class'] ?? null;
        $title = $config['title'] ?? '';

        $html = $view->renderTemplate('vipps/_components/express/form-button', [
            'class' => $class,
            'title' => $title,
            'purchasable' => $purchasable,
            'config' => $data,
        ]);

        $view->setTemplateMode($oldMode);

        return $html;
    }
}
