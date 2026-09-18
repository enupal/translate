<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\serializers;

use craft\base\ElementInterface;
use craft\enums\PropagationMethod;
use craft\models\Site;
use enupal\translate\base\FieldSerializer;
use enupal\translate\Translate as TranslatePlugin;

/**
 * Matrix, Neo and Super Table — anything holding nested elements.
 *
 * Each nested element is serialized recursively, so translatable fields any
 * number of levels deep are picked up.
 */
class Matrix extends FieldSerializer
{
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $children = [];

        foreach ($this->getNestedElements($element) as $nested) {
            $children[$nested->id] = TranslatePlugin::$app->content->serializeElement($nested, $sourceSite, $targetSite);
        }

        return ['children' => $children];
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        $query = $source->getFieldValue($this->field->handle);
        $serialized = $this->field->serializeValue($query, $source);

        if (!is_array($serialized)) {
            return null;
        }

        $translatedChildren = $value['children'] ?? [];

        foreach ($this->getNestedElements($source) as $nested) {
            $translated = $translatedChildren[$nested->id] ?? null;

            if (!is_array($translated) || !isset($serialized[$nested->id])) {
                continue;
            }

            if (!empty($translated['title'])) {
                $serialized[$nested->id]['title'] = $translated['title'];
            }

            if (!empty($translated['fields'])) {
                $translatedFields = TranslatePlugin::$app->content->setElementFieldsTranslations($nested, $target, $translated['fields']);

                // Merge rather than replace, so fields that weren't translated
                // keep their original values instead of being wiped.
                $serialized[$nested->id]['fields'] = array_merge(
                    $serialized[$nested->id]['fields'] ?? [],
                    $translatedFields
                );
            }
        }

        // Neo overwrites blocks across every site unless the value is wrapped.
        if (get_class($this->field) === 'benf\neo\Field'
            && ($this->field->propagationMethod ?? null) !== PropagationMethod::None) {
            return ['blocks' => $serialized];
        }

        return $serialized;
    }

    /**
     * @return ElementInterface[]
     */
    private function getNestedElements(ElementInterface $element): array
    {
        $query = $element->getFieldValue($this->field->handle);

        if (!is_object($query) || !method_exists($query, 'all')) {
            return [];
        }

        if (TranslatePlugin::$app->settings->getSettings()->translateDisabledMatrixElements
            && method_exists($query, 'status')) {
            $query->status(null);
        }

        return $query->all();
    }
}
