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

class TranslateController extends BaseController
{
    /**
     * Download translations.
     */
    public function actionDownload()
    {
        // Get params
        $locale = Craft::$app->request->getParam('locale');

        // Set criteria
        $criteria = Craft::$app->elements->getCriteria('Translate');
        $criteria->search = false;
        $criteria->status = false;
        $criteria->locale = $locale;
        $criteria->source = array(
            Craft::$app->path->getPluginsPath(),
            Craft::$app->path->getSiteTemplatesPath(),
        );

        // Get occurences
        $occurences = Craft::$app->translate->get($criteria);

        // Re-order data
        $data = StringHelper::convertToUTF8('"'.Craft::t('Original').'","'.Craft::t('Translation')."\"\r\n");
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
        Craft::$app->userSession->setNotice(Craft::t('The translations have been updated.'));

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
        // Get params
        $locale = Craft::$app->request->getRequiredPost('locale');
        $translations = Craft::$app->request->getRequiredPost('translation');

        // Save to translation file
        Craft::$app->translate->set($locale, $translations);

        // Set a flash message
        Craft::$app->userSession->setNotice(Craft::t('The translations have been updated.'));

        // Redirect back to page
        return $this->redirectToPostedUrl();
    }
}
