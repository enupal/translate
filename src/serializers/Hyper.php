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
 * Verbb's Hyper field: a repeatable list of links.
 *
 * Only the visible link text is translated. Links pointing at an element in
 * the source site are re-pointed at the target site, so a translated page
 * links to the translated destination.
 */
class Hyper extends FieldSerializer
{
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $values = $this->serializedLinks($element);

        $data = [];

        foreach ($values as $i => $link) {
            $text = $link['linkText'] ?? null;

            if (is_string($text) && $text !== '') {
                $data[$i] = ['text' => $text];
            }
        }

        return $data;
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        $serialized = $this->serializedLinks($source);

        foreach ($serialized as $i => $link) {
            if (!empty($value[$i]['text'])) {
                $serialized[$i]['linkText'] = $value[$i]['text'];
            }

            // Follow the link to the target site's copy of the same element.
            if (!empty($link['linkSiteId']) && (int)$link['linkSiteId'] === $source->siteId) {
                $serialized[$i]['linkSiteId'] = $target->siteId;
            }
        }

        return $serialized;
    }

    private function serializedLinks(ElementInterface $element): array
    {
        $values = $element->getSerializedFieldValues()[$this->field->handle] ?? [];

        return is_array($values) ? $values : [];
    }
}
