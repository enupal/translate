<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\controllers;

use Craft;
use craft\web\Controller as BaseController;
use enupal\translate\Translate;

class SettingsController extends BaseController
{
    /**
     * Save Plugin Settings
     *
     * @return null
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $settings = $request->getBodyParam('settings');

        if (!Translate::$app->settings->saveSettings($settings)) {
            Craft::$app->getSession()->setError(Translate::t('Couldn’t save settings.'));

            // Send the settings back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('enupal-translate','Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
