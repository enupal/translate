<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\controllers;

use Craft;
use craft\web\Controller as BaseController;
use enupal\translate\records\Metric;
use enupal\translate\Translate as TranslatePlugin;
use yii\web\Response;

/**
 * Translation usage dashboard.
 */
class DashboardController extends BaseController
{
    public function actionIndex(): Response
    {
        $this->requirePermission('accessPlugin-enupal-translate');

        $filters = $this->resolveFilters();

        $metrics = TranslatePlugin::$app->metrics;

        return $this->renderTemplate('enupal-translate/dashboard/index', [
            'filters' => $filters,
            'summary' => $metrics->getSummary($filters),
            'series' => $metrics->getDailySeries($filters),
            'byProvider' => $metrics->getBreakdown('provider', $filters),
            'byLanguage' => $metrics->getBreakdown('targetLanguage', $filters),
            'byModel' => $metrics->getBreakdown('model', $filters),
            'recent' => $metrics->getRecent($filters),
            'options' => $metrics->getFilterOptions(),
            'providers' => TranslatePlugin::$app->providers->getAllProviders(),
            'typeOptions' => [
                '' => Craft::t('enupal-translate', 'All types'),
                Metric::TYPE_STATIC => Craft::t('enupal-translate', 'Static (templates)'),
                Metric::TYPE_CONTENT => Craft::t('enupal-translate', 'Content (elements)'),
            ],
        ]);
    }

    /**
     * Delete metrics, honouring whatever filters are active so a user can
     * clear just one provider or date range rather than everything.
     */
    public function actionPurge(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('accessPlugin-enupal-translate');

        $purgeAll = (bool)Craft::$app->getRequest()->getBodyParam('purgeAll');
        $filters = $purgeAll ? [] : $this->resolveFilters();

        $deleted = TranslatePlugin::$app->metrics->purge($filters);

        Craft::$app->getSession()->setNotice(Craft::t('enupal-translate', '{count} metric records deleted.', [
            'count' => $deleted,
        ]));

        return $this->redirectToPostedUrl();
    }

    private function resolveFilters(): array
    {
        $request = Craft::$app->getRequest();

        return array_filter([
            'start' => $request->getParam('start'),
            'end' => $request->getParam('end'),
            'provider' => $request->getParam('provider'),
            'type' => $request->getParam('type'),
            'targetLanguage' => $request->getParam('targetLanguage'),
        ], static fn($value) => $value !== null && $value !== '');
    }
}
