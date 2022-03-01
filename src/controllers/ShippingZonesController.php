<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Zone Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZonesController extends BaseShippingSettingsController
{
    public function actionIndex(): Response
    {
        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones();
        return $this->renderTemplate('commerce/shipping/shippingzones/index', compact('shippingZones'));
    }

    /**
     * @param int|null $id
     * @param ShippingAddressZone|null $shippingZone
     * @throws HttpException
     */
    public function actionEdit(int $id = null, ShippingAddressZone $shippingZone = null): Response
    {
        $variables = compact('id', 'shippingZone');

        if (!$variables['shippingZone']) {
            if ($variables['id']) {
                $variables['shippingZone'] = Plugin::getInstance()->getShippingZones()->getShippingZoneById($variables['id']);

                if (!$variables['shippingZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingZone'] = new ShippingAddressZone();
            }
        }

        if ($variables['shippingZone']->id) {
            $variables['title'] = $variables['shippingZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a shipping zone');
        }

        return $this->renderTemplate('commerce/shipping/shippingzones/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shippingZone = new ShippingAddressZone();

        // Shared attributes
        $shippingZone->id = Craft::$app->getRequest()->getBodyParam('shippingZoneId');
        $shippingZone->name = Craft::$app->getRequest()->getBodyParam('name');
        $shippingZone->description = Craft::$app->getRequest()->getBodyParam('description');
        $shippingZone->isCountryBased = (bool)Craft::$app->getRequest()->getBodyParam('isCountryBased');
        $shippingZone->countryCode = (bool)Craft::$app->getRequest()->getBodyParam('countryCode', 'US');
        $shippingZone->zipCodeConditionFormula = Craft::$app->getRequest()->getBodyParam('zipCodeConditionFormula');
        $shippingZone->default = (bool)Craft::$app->getRequest()->getBodyParam('default');
        $countries = Craft::$app->getRequest()->getBodyParam('countries',[]) ?: [];
        $shippingZone->setCountries($countries);
        $administrativeAreas = Craft::$app->getRequest()->getBodyParam('administrativeAreas',[]) ?: [];
        $shippingZone->setAdministrativeAreas($administrativeAreas);

        if ($shippingZone->validate() && Plugin::getInstance()->getShippingZones()->saveShippingZone($shippingZone)) {
            return $this->asModelSuccess(
                $shippingZone,
                Craft::t('commerce', 'Shipping zone saved.'),
                'shippingZone',
                data: [
                    'id' => $shippingZone->id,
                    'name' => $shippingZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $shippingZone,
            Craft::t('commerce', 'Couldn’t save shipping zone.'),
            'shippingZone'
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getShippingZones()->deleteShippingZoneById($id);
        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws LoaderError
     * @throws SyntaxError
     * @since 2.2
     */
    public function actionTestZip(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $zipCodeFormula = (string)Craft::$app->getRequest()->getRequiredBodyParam('zipCodeConditionFormula');
        $testZipCode = (string)Craft::$app->getRequest()->getRequiredBodyParam('testZipCode');

        $params = ['zipCode' => $testZipCode];
        if (Plugin::getInstance()->getFormulas()->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asSuccess();
        }

        return $this->asFailure('failed');
    }
}
