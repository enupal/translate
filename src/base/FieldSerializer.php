<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\base;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Site;
use enupal\translate\Translate as TranslatePlugin;

/**
 * Turns one field's value into translatable strings and back again.
 */
abstract class FieldSerializer
{
    protected FieldInterface $field;

    public function __construct(FieldInterface $field)
    {
        $this->field = $field;
    }

    /**
     * The field value, as a nested array of translatable strings.
     */
    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        return $this->field->serializeValue($element->getFieldValue($this->field->handle), $element);
    }

    /**
     * Turn the translated strings back into a value Craft can save.
     */
    abstract public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed;

    /**
     * Re-point Craft reference tags at the target site, so an internal link in
     * the Spanish entry resolves to the Spanish URL rather than the English one.
     */
    protected function translateReferenceTags(string $value, Site $sourceSite, Site $targetSite): string
    {
        if (!TranslatePlugin::$app->settings->getSettings()->updateInternalLinks) {
            return $value;
        }

        // e.g. {entry:123@1:url||https://example.com/slug}
        return preg_replace_callback(
            '/\{(entry|asset|variant|product|category):(\d+)@(\d+):/i',
            static function (array $match) use ($sourceSite, $targetSite) {
                [$full, $type, $elementId, $siteId] = $match;

                if ((int)$siteId !== $sourceSite->id) {
                    return $full;
                }

                $class = match (strtolower($type)) {
                    'entry' => Entry::class,
                    'asset' => Asset::class,
                    'category' => 'craft\elements\Category',
                    'variant' => 'craft\commerce\elements\Variant',
                    'product' => 'craft\commerce\elements\Product',
                    default => null,
                };

                if ($class === null || !class_exists($class)) {
                    return $full;
                }

                $exists = $class::find()
                    ->siteId($targetSite->id)
                    ->status(null)
                    ->id($elementId)
                    ->exists();

                if (!$exists) {
                    return $full;
                }

                return sprintf('{%s:%s@%d:', $type, $elementId, $targetSite->id);
            },
            $value
        ) ?? $value;
    }
}
