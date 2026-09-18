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
 * Presseddigital's Linkit field.
 *
 * The only translatable part is the custom link text, and only when the field
 * is configured to allow it.
 */
class Linkit extends FieldSerializer
{
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        if (empty($this->field->allowCustomText)) {
            return [];
        }

        $value = $element->getFieldValue($this->field->handle);
        $text = is_object($value) ? ($value->customText ?? null) : null;

        return !empty($text) ? ['text' => $text] : [];
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        if (empty($this->field->allowCustomText) || empty($value['text'])) {
            return null;
        }

        $serialized = parent::serialize($source, $source->getSite(), $target->getSite());

        if (!is_array($serialized)) {
            return null;
        }

        $serialized['customText'] = $value['text'];

        return $serialized;
    }
}
