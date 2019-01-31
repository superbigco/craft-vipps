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

use craft\helpers\Template;
use craft\helpers\UrlHelper;
use superbig\vipps\Vipps;

use Craft;
use craft\base\Component;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Express extends Component
{
    // Public Methods
    // =========================================================================

    public function getButton(): string
    {
        $view    = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('vipps/_components/express/button', [
            'nonceUrl' => UrlHelper::siteUrl(''),
            'title'    => '',
        ]);

        $view->setTemplateMode($oldMode);

        return Template::raw($html);
    }
}
