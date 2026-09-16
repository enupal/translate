<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\records;

use craft\db\ActiveRecord;

/**
 * One row per batch sent to a provider.
 *
 * @property int $id
 * @property string $provider
 * @property string|null $model
 * @property string $type
 * @property string|null $sourceLanguage
 * @property string $targetLanguage
 * @property int|null $siteId
 * @property int|null $elementId
 * @property string|null $elementType
 * @property int $stringCount
 * @property int $characterCount
 * @property int $inputTokens
 * @property int $outputTokens
 * @property int $apiCalls
 * @property int $durationMs
 * @property bool $success
 * @property string|null $errorMessage
 * @property string $dateCreated
 */
class Metric extends ActiveRecord
{
    public const TYPE_STATIC = 'static';
    public const TYPE_CONTENT = 'content';

    public static function tableName(): string
    {
        return '{{%enupaltranslate_metrics}}';
    }
}
