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

use craft\helpers\Path;
use craft\helpers\StringHelper;
use craft\web\Controller as BaseController;
use Craft;
use craft\web\Response;
use enupal\translate\Translate;
use enupal\translate\elements\Translate as ElementTranslate;
use yii\web\NotFoundHttpException;

class TranslateController extends BaseController
{
    /**
     * Download translations.
     *
     * @throws \yii\base\Exception
     */
    public function actionDownload()
    {
        $this->requireAcceptsJson();
        $siteId = Craft::$app->request->getRequiredBodyParam('siteId');
        $sourceKey = Craft::$app->request->getRequiredBodyParam('sourceKey');
        $statusSubString = 'status:';
        $templateSubString = 'templates/templates:';
        $pluginSubString = 'plugins/plugins:';

        $sources = [];
        $query = ElementTranslate::find();
        $query->status = null;
        // Get params
        // Process Template Status
        if (strpos($sourceKey, $statusSubString) !== false) {
            $criteria = explode($statusSubString, $sourceKey);
            $query->status = $criteria[1] ?? null;
            $sources[] = Craft::$app->path->getSiteTemplatesPath();
        }
        // Process Templates Status
        if (strpos($sourceKey, $templateSubString) !== false) {
            $criteria = explode($templateSubString, $sourceKey);
            $sources[] = $criteria[1] ?? Craft::$app->path->getSiteTemplatesPath();
        }
        // Process Plugin Status
        if (strpos($sourceKey, $pluginSubString) !== false) {
            $criteria = explode($pluginSubString, $sourceKey);
            $plugin = Craft::$app->plugins->getPlugin($criteria[1]);
            $sources[] = $plugin->getBasePath() ?? '';
        }

        $site = Craft::$app->getSites()->getSiteById($siteId);
        // @todo add support for search
        $query->search = false;
        $query->siteId = $siteId;
        $query->source = $sources;

        // Get occurences
        $occurences = Translate::$app->translate->get($query);

        // Re-order data
        $data = StringHelper::convertToUTF8('"'.Craft::t('enupal-translate','Source {language}',['language'=> $site->language]).'","'.Craft::t('enupal-translate','Translation')."\"\r\n");
        foreach ($occurences as $element) {
            $data .= StringHelper::convertToUTF8('"'.$element->original.'","'.$element->translation."\"\r\n");
        }

        $file = Craft::$app->getPath()->getTempPath().DIRECTORY_SEPARATOR.StringHelper::toLowerCase('translations_'.$site->language.'.csv');
        $fd = fopen ($file, "w");
        fputs($fd, $data);
        fclose($fd);

        // Download the file
        //Craft::$app->getResponse()->sendFile('translations_'.$site->language.'.csv', $data, ['forceDownload' => true, 'mimeType' => 'text/csv']);
        $response = [
            'success'=> true,
            'filePath' => $file
        ];

        return $this->asJson($response);
    }

    /**
     * Returns Translate csv file
     *
     * @return Response
     * @throws ForbiddenHttpException if the user doesn't have access to the DB Backup utility
     * @throws NotFoundHttpException if the requested backup cannot be found
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDownloadCsvFile(): Response
    {
        $filePath = Craft::$app->getRequest()->getRequiredQueryParam('filepath');

        if (!is_file($filePath) || !Path::ensurePathIsContained($filePath)) {
            throw new NotFoundHttpException(Craft::t('enupal-translate', 'Invalid Translate File path'));
        }

        return Craft::$app->getResponse()->sendFile($filePath);
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
