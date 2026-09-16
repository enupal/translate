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
 * Link fields. Only the visible label is translated; the URL is left alone.
 */
class Link extends FieldSerializer
{
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $value = parent::serialize($element, $sourceSite, $targetSite);

        if (!is_array($value)) {
            return ['label' => null];
        }

        return [
            'label' => $value['label'] ?? null,
            'title' => $value['title'] ?? null,
        ];
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        $serialized = $this->field->serializeValue($source->getFieldValue($this->field->handle), $source);

        if (!is_array($serialized)) {
            return null;
        }

        foreach (['label', 'title'] as $key) {
            if (!empty($value[$key])) {
                $serialized[$key] = $value[$key];
            }
        }

        return $serialized;
    }
}
