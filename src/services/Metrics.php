<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use DateTime;
use DateTimeZone;
use enupal\translate\models\TranslationResult;
use enupal\translate\records\Metric;
use enupal\translate\Translate as TranslatePlugin;
use Throwable;

/**
 * Records and reports on translation usage.
 */
class Metrics extends Component
{
    public const TABLE = '{{%enupaltranslate_metrics}}';

    /**
     * Store the outcome of one batch.
     *
     * Metrics are strictly bookkeeping, so a failure here must never break the
     * translation that just succeeded.
     */
    public function record(TranslationResult $result, string $type, string $targetLanguage, array $context = []): void
    {
        if (!TranslatePlugin::$app->settings->getSettings()->enableMetrics) {
            return;
        }

        try {
            $record = new Metric();
            $record->provider = (string)($result->provider ?? 'unknown');
            $record->model = $result->model;
            $record->type = $type;
            $record->sourceLanguage = $context['sourceLanguage'] ?? null;
            $record->targetLanguage = $targetLanguage;
            $record->siteId = $context['siteId'] ?? null;
            $record->elementId = $context['elementId'] ?? null;
            $record->elementType = $context['elementType'] ?? null;
            $record->stringCount = count($result->translations);
            $record->characterCount = $result->characterCount;
            $record->inputTokens = $result->inputTokens;
            $record->outputTokens = $result->outputTokens;
            $record->apiCalls = $result->apiCalls;
            $record->durationMs = (int)($context['durationMs'] ?? 0);
            $record->success = $result->success;
            $record->errorMessage = $result->errorMessage;
            $record->save(false);
        } catch (Throwable $e) {
            Craft::error('Could not record translation metrics: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Headline totals for the dashboard cards.
     */
    public function getSummary(array $filters = []): array
    {
        $row = $this->createQuery($filters)
            ->select([
                'totalBatches' => 'COUNT(*)',
                'totalStrings' => 'COALESCE(SUM([[stringCount]]), 0)',
                'totalCharacters' => 'COALESCE(SUM([[characterCount]]), 0)',
                'inputTokens' => 'COALESCE(SUM([[inputTokens]]), 0)',
                'outputTokens' => 'COALESCE(SUM([[outputTokens]]), 0)',
                'apiCalls' => 'COALESCE(SUM([[apiCalls]]), 0)',
            ])
            ->one();

        $summary = [
            'totalBatches' => (int)($row['totalBatches'] ?? 0),
            'totalStrings' => (int)($row['totalStrings'] ?? 0),
            'totalCharacters' => (int)($row['totalCharacters'] ?? 0),
            'inputTokens' => (int)($row['inputTokens'] ?? 0),
            'outputTokens' => (int)($row['outputTokens'] ?? 0),
            'apiCalls' => (int)($row['apiCalls'] ?? 0),
        ];

        $summary['totalTokens'] = $summary['inputTokens'] + $summary['outputTokens'];

        // Split the string count by what was translated.
        foreach ([Metric::TYPE_STATIC, Metric::TYPE_CONTENT] as $type) {
            $summary[$type] = (int)$this->createQuery(array_merge($filters, ['type' => $type]))
                ->sum('[[stringCount]]');
        }

        $summary['failures'] = (int)$this->createQuery($filters)
            ->andWhere(['success' => false])
            ->count();

        return $summary;
    }

    /**
     * Daily series for the chart, with empty days filled in so the x-axis is
     * continuous rather than jumping over quiet days.
     */
    public function getDailySeries(array $filters = []): array
    {
        $rows = $this->createQuery($filters)
            ->select([
                'day' => $this->dayExpression(),
                'strings' => 'COALESCE(SUM([[stringCount]]), 0)',
                'tokens' => 'COALESCE(SUM([[inputTokens]]) + SUM([[outputTokens]]), 0)',
                'calls' => 'COALESCE(SUM([[apiCalls]]), 0)',
            ])
            ->groupBy(['day'])
            ->orderBy(['day' => SORT_ASC])
            ->all();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $row;
        }

        [$start, $end] = $this->resolveRange($filters);

        $labels = [];
        $strings = [];
        $tokens = [];
        $calls = [];

        $cursor = (clone $start)->setTime(0, 0);
        $last = (clone $end)->setTime(0, 0);

        // Guard against a pathological range locking the page up.
        $maxDays = 400;

        while ($cursor <= $last && count($labels) < $maxDays) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $key;
            $strings[] = (int)($byDay[$key]['strings'] ?? 0);
            $tokens[] = (int)($byDay[$key]['tokens'] ?? 0);
            $calls[] = (int)($byDay[$key]['calls'] ?? 0);

            $cursor->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'strings' => $strings,
            'tokens' => $tokens,
            'calls' => $calls,
        ];
    }

    /**
     * Totals grouped by a column, for the breakdown tables and pie chart.
     */
    public function getBreakdown(string $column, array $filters = []): array
    {
        if (!in_array($column, ['provider', 'model', 'type', 'targetLanguage'], true)) {
            throw new \InvalidArgumentException("Cannot group metrics by '$column'.");
        }

        return $this->createQuery($filters)
            ->select([
                'label' => "[[$column]]",
                'strings' => 'COALESCE(SUM([[stringCount]]), 0)',
                'tokens' => 'COALESCE(SUM([[inputTokens]]) + SUM([[outputTokens]]), 0)',
                'calls' => 'COALESCE(SUM([[apiCalls]]), 0)',
                'batches' => 'COUNT(*)',
            ])
            ->groupBy(["[[$column]]"])
            ->orderBy(['strings' => SORT_DESC])
            ->all();
    }

    /**
     * Most recent batches, for the dashboard activity table.
     */
    public function getRecent(array $filters = [], int $limit = 25): array
    {
        return $this->createQuery($filters)
            ->select(['*'])
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Delete metrics. With no filters this empties the table.
     *
     * @return int rows deleted
     */
    public function purge(array $filters = []): int
    {
        $condition = $this->buildConditions($filters);

        return Craft::$app->getDb()->createCommand()
            ->delete(self::TABLE, $condition ?: '1=1')
            ->execute();
    }

    /**
     * Distinct values present in the table, so the filter dropdowns only offer
     * options that would actually return something.
     */
    public function getFilterOptions(): array
    {
        return [
            'providers' => $this->distinct('provider'),
            'languages' => $this->distinct('targetLanguage'),
            'models' => $this->distinct('model'),
        ];
    }

    private function distinct(string $column): array
    {
        return (new Query())
            ->select([$column])
            ->from(self::TABLE)
            ->where(['not', [$column => null]])
            ->andWhere(['not', [$column => '']])
            ->distinct()
            ->orderBy([$column => SORT_ASC])
            ->column();
    }

    private function createQuery(array $filters = []): Query
    {
        $query = (new Query())->from(self::TABLE);

        $condition = $this->buildConditions($filters);

        if ($condition) {
            $query->where($condition);
        }

        return $query;
    }

    /**
     * @return array yii condition, or [] for "everything"
     */
    private function buildConditions(array $filters): array
    {
        $conditions = ['and'];

        if (!empty($filters['provider'])) {
            $conditions[] = ['provider' => $filters['provider']];
        }

        if (!empty($filters['type'])) {
            $conditions[] = ['type' => $filters['type']];
        }

        if (!empty($filters['targetLanguage'])) {
            $conditions[] = ['targetLanguage' => $filters['targetLanguage']];
        }

        if (!empty($filters['model'])) {
            $conditions[] = ['model' => $filters['model']];
        }

        if (!empty($filters['start'])) {
            $conditions[] = ['>=', 'dateCreated', Db::prepareDateForDb($this->toDateTime($filters['start']))];
        }

        if (!empty($filters['end'])) {
            // The end date is inclusive, so cover the whole day.
            $end = $this->toDateTime($filters['end']);
            $end->setTime(23, 59, 59);
            $conditions[] = ['<=', 'dateCreated', Db::prepareDateForDb($end)];
        }

        return count($conditions) > 1 ? $conditions : [];
    }

    /**
     * Default to the last 30 days when the request specifies no range.
     *
     * @return DateTime[] [start, end]
     */
    private function resolveRange(array $filters): array
    {
        $end = !empty($filters['end']) ? $this->toDateTime($filters['end']) : new DateTime('now');

        if (!empty($filters['start'])) {
            $start = $this->toDateTime($filters['start']);
        } else {
            $start = (clone $end)->modify('-29 days');
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function toDateTime($value): DateTime
    {
        if ($value instanceof DateTime) {
            return clone $value;
        }

        return new DateTime((string)$value);
    }

    /**
     * Group by calendar day in a way both MySQL and Postgres understand.
     */
    private function dayExpression(): string
    {
        if (Craft::$app->getDb()->getIsMysql()) {
            return 'DATE([[dateCreated]])';
        }

        return "TO_CHAR([[dateCreated]], 'YYYY-MM-DD')";
    }
}
