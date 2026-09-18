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

        // titleRaw is a string on some versions and an array keyed by site id
        // on others. Both are passed through as-is: the flattener walks nested
        // arrays, so either shape ends up translated and rebuilt correctly.
        $data = [];

        foreach (['titleRaw', 'descriptionRaw'] as $key) {
            $raw = $value->$key ?? null;

            if (is_string($raw) && $raw !== '') {
                $data[$key] = $raw;
            } elseif (is_array($raw) && $raw !== []) {
                $strings = array_filter($raw, static fn($v) => is_string($v) && $v !== '');

                if ($strings !== []) {
                    $data[$key] = $strings;
                }
            }
        }

        return $data;
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        // serializeValue() hands back a SeoData model rather than an array, so
        // the translated strings are written onto a copy of that model and the
        // model itself is returned — everything not translated (keywords,
        // social, advanced, the score) travels with it untouched.
        $data = $source->getFieldValue($this->field->handle);

        if (!is_object($data)) {
            return null;
        }

        $data = clone $data;

        foreach (['titleRaw', 'descriptionRaw'] as $key) {
            if (empty($value[$key])) {
                continue;
            }

            $current = $data->$key ?? null;

            // titleRaw is a keyed array of title parts on current versions and
            // a plain string on older ones.
            if (is_array($current) && is_array($value[$key])) {
                $data->$key = array_replace($current, $value[$key]);
            } elseif (!is_array($current) && !is_array($value[$key])) {
                $data->$key = $value[$key];
            } elseif (is_array($value[$key])) {
                $data->$key = reset($value[$key]);
            }
        }

        return $data;
    }
}
