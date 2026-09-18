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
use DateTime;
use enupal\translate\services\Metrics;
use enupal\translate\records\Metric;
use enupal\translate\Translate as TranslatePlugin;
use yii\web\Response;

/**
 * Translation usage dashboard.
 */
class DashboardController extends BaseController
{
    /**
     * Rows per page in the recent activity table.
     */
    private const RECENT_PER_PAGE = 10;

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
            'recent' => $metrics->getRecentPage(
                $filters,
                (int)Craft::$app->getRequest()->getParam('page', 1),
                self::RECENT_PER_PAGE
            ),
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

    /**
     * The active filters, with the date range always resolved.
     *
     * Craft's date fields post an array of parts rather than a string, so the
     * bounds go through the metrics service to be normalized. An empty range
     * falls back to the last month, which keeps the chart and the totals
     * showing something useful on a first visit.
     */
    private function resolveFilters(): array
    {
        $request = Craft::$app->getRequest();
        $metrics = TranslatePlugin::$app->metrics;

        $end = $metrics->toDateTime($request->getParam('end')) ?? new DateTime('now');
        $start = $metrics->toDateTime($request->getParam('start'))
            ?? (clone $end)->modify('-' . (Metrics::DEFAULT_RANGE_DAYS - 1) . ' days');

        // Tolerate a range entered back to front rather than returning nothing.
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $filters = [
            'start' => $start->setTime(0, 0),
            'end' => $end->setTime(23, 59, 59),
        ];

        foreach (['provider', 'type', 'targetLanguage'] as $key) {
            $value = $request->getParam($key);

            if (is_string($value) && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
