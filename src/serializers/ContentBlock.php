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
use enupal\translate\Translate as TranslatePlugin;

/**
 * Craft's Content Block field — a single nested element rather than a list.
 */
class ContentBlock extends FieldSerializer
{
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $block = $element->getFieldValue($this->field->handle);

        if (!$block instanceof ElementInterface) {
            return ['fields' => null];
        }

        return [
            'fields' => TranslatePlugin::$app->content->serializeElementFields($block, $sourceSite, $targetSite),
        ];
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        $block = $source->getFieldValue($this->field->handle);

        if (!$block instanceof ElementInterface || empty($value['fields'])) {
            return null;
        }

        $serialized = $this->field->serializeValue($block, $source);
        $translated = TranslatePlugin::$app->content->setElementFieldsTranslations($block, $target, $value['fields']);

        if (!is_array($serialized)) {
            return $translated;
        }

        return array_merge($serialized, $translated);
    }
}
