<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\serializers;

use craft\base\ElementInterface;
use craft\models\Site;
use enupal\translate\base\FieldSerializer;

/**
 * Table fields. Only the text-bearing column types are sent for translation;
 * numbers, dates, checkboxes and so on are copied across untouched.
 */
class Table extends FieldSerializer
{
    private const TEXT_COLUMN_TYPES = ['singleline', 'multiline', 'heading'];

    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $rows = parent::serialize($element, $sourceSite, $targetSite);

        if (!is_array($rows)) {
            return ['rows' => null];
        }

        $textColumns = $this->getTextColumns();
        $translatable = [];

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $column => $value) {
                if (in_array($column, $textColumns, true) && is_string($value)) {
                    $translatable[$rowIndex][$column] = $value;
                }
            }
        }

        return ['rows' => $translatable];
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        $sourceRows = $this->field->serializeValue($source->getFieldValue($this->field->handle), $source);

        if (!is_array($sourceRows)) {
            return null;
        }

        $translatedRows = $value['rows'] ?? [];
        $textColumns = $this->getTextColumns();
        $result = [];

        foreach ($sourceRows as $rowIndex => $row) {
            if (!is_array($row)) {
                $result[] = $row;
                continue;
            }

            $newRow = [];

            foreach ($row as $column => $columnValue) {
                if (in_array($column, $textColumns, true)) {
                    // Fall back to the original when the provider skipped a cell,
                    // so a partial response never blanks out the table.
                    $newRow[$column] = $translatedRows[$rowIndex][$column] ?? $columnValue;
                } else {
                    $newRow[$column] = $columnValue;
                }
            }

            $result[] = $newRow;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function getTextColumns(): array
    {
        $columns = [];

        foreach ($this->field->columns ?? [] as $name => $config) {
            if (in_array($config['type'] ?? null, self::TEXT_COLUMN_TYPES, true)) {
                $columns[] = $name;
            }
        }

        return $columns;
    }
}
