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
 * Plain text fields.
 */
class Text extends FieldSerializer
{
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $value = parent::serialize($element, $sourceSite, $targetSite);

        return ['text' => is_string($value) ? $value : null];
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        return $value['text'] ?? null;
    }
}
