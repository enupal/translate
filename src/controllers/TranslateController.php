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

use craft\web\Controller as BaseController;
use Craft;
use enupal\translate\Translate;
use enupal\translate\elements\Translate as ElementTranslate;

class TranslateController extends BaseController
{
    /**
     * Download translations.
     */
    public function actionDownload()
    {
        // Get params
        $siteId = Craft::$app->request->getRequiredBodyParam('siteId');
        $site = Craft::$app->getSites()->getSiteById($siteId);

        // Call Craft.elementIndex.sourceKey to get the source key on Js
        // So we can return specific file and not all the translates
        // Set criteria
        $query = ElementTranslate::find();
        $query->search = false;
        $query->status = false;
        $query->siteId = $siteId;
        $query->source = array(
            Craft::$app->path->getPluginsPath(),
            Craft::$app->path->getSiteTemplatesPath(),
        );

        // Get occurences
        $occurences = Craft::$app->translate->get($query);

        // Re-order data
        $data = StringHelper::convertToUTF8('"'.Craft::t('enupal-translate','Original').'","'.Craft::t('enupal-translate','Translation')."\"\r\n");
        foreach ($occurences as $element) {
            $data .= StringHelper::convertToUTF8('"'.$element->original.'","'.$element->translation."\"\r\n");
        }

        // Download the file
        Craft::$app->request->sendFile('translations_'.$locale.'.csv', $data, array('forceDownload' => true, 'mimeType' => 'text/csv'));
    }

    /**
     * Upload translations.
     */
    public function actionUpload()
    {
        // Get params
        $locale = Craft::$app->request->getRequiredPost('locale');

        // Get file
        $file = \CUploadedFile::getInstanceByName('translations-upload');

        // Get filepath
        $path = Craft::$app->path->getTempUploadsPath().$file->getName();

        // Save file to Craft's temp folder
        $file->saveAs($path);

        // Open file and parse csv rows
        $translations = array();
        $handle = fopen($path, 'r');
        while (($row = fgetcsv($handle)) !== false) {
            $translations[$row[0]] = $row[1];
        }
        fclose($handle);

        // Save
        Craft::$app->translate->set($locale, $translations);

        // Set a flash message
        Craft::$app->getSession()->setNotice(Craft::t('enupal-translate','The translations have been updated.'));

        // Redirect back to page
        $this->redirectToPostedUrl();
    }

    /**
     * Save translations.
     *
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requireAcceptsJson();
        $response = [
            'success' => true,
            'errors' => []
        ];
        $siteId = Craft::$app->request->getRequiredBodyParam('siteId');
        $site = Craft::$app->getSites()->getSiteById($siteId);

        $translations = Craft::$app->request->getRequiredBodyParam('translation');

        // Save to translation file
        Translate::$app->translate->set($site->language, $translations);

        // Redirect back to page
        return $this->asJson($response);
    }
}
