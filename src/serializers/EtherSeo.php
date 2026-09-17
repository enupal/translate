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
 * Ether's SEO field.
 *
 * Only the raw title and description are translated; everything else on the
 * model is either derived or not language-specific.
 */
class EtherSeo extends FieldSerializer
{
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $value = $element->getFieldValue($this->field->handle);

        if (!is_object($value)) {
            return [];
        }

        return array_filter([
            'titleRaw' => is_string($value->titleRaw ?? null) ? $value->titleRaw : null,
            'descriptionRaw' => $value->descriptionRaw ?? null,
        ], static fn($v) => $v !== null && $v !== '');
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        $data = [];

        foreach (['titleRaw', 'descriptionRaw'] as $key) {
            if (!empty($value[$key])) {
                $data[$key] = $value[$key];
            }
        }

        return $data ?: null;
    }
}
