<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\errors\UnsupportedSiteException;
use craft\models\Site;
use enupal\translate\base\FieldSerializer;
use enupal\translate\events\RegisterSerializersEvent;
use enupal\translate\helpers\ArrayFlattener;
use enupal\translate\records\Metric;
use enupal\translate\serializers\ContentBlock as ContentBlockSerializer;
use enupal\translate\serializers\Link as LinkSerializer;
use enupal\translate\serializers\Matrix as MatrixSerializer;
use enupal\translate\serializers\RichText as RichTextSerializer;
use enupal\translate\serializers\Table as TableSerializer;
use enupal\translate\serializers\Text as TextSerializer;
use enupal\translate\Translate as TranslatePlugin;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Translates element content — native fields and plugin fields alike.
 */
class Content extends Component
{
    public const EVENT_REGISTER_SERIALIZERS = 'registerSerializers';

    /**
     * Field classes holding nested elements. These are always descended into,
     * even when the field itself isn't marked translatable, because the blocks
     * inside it may well be.
     */
    public static array $nestedElementFields = [
        'craft\fields\Matrix',
        'benf\neo\Field',
        'verbb\supertable\fields\SuperTableField',
    ];

    /**
     * @var array<string, string> serializer class keyed by field class
     */
    private array $serializers = [];

    public function init(): void
    {
        parent::init();

        $this->registerSerializers();
    }

    // Translation
    // =========================================================================

    /**
     * Translate one element into a target site.
     *
     * @param string[]|null $fieldHandles Limit to these fields.
     */
    public function translateElement(
        ElementInterface $source,
        Site $sourceSite,
        Site $targetSite,
        ?array $fieldHandles = null,
        bool $isRoot = true
    ): ?ElementInterface {
        $settings = TranslatePlugin::$app->settings->getSettings();
        $provider = TranslatePlugin::$app->providers->getContentProvider();

        if ($provider === null) {
            throw new \RuntimeException(Craft::t('enupal-translate', 'No translation provider is enabled. Check the plugin settings.'));
        }

        $serialized = $this->serializeElement($source, $sourceSite, $targetSite);

        if ($fieldHandles !== null) {
            $serialized['fields'] = array_intersect_key($serialized['fields'] ?? [], array_flip($fieldHandles));
        }

        [$values, $paths] = ArrayFlattener::flatten($serialized);

        if (empty($values)) {
            Craft::info('Nothing translatable on element ' . $source->id, __METHOD__);
            return null;
        }

        $sourceLanguage = $settings->detectSourceLanguage ? null : $sourceSite->language;

        $startedAt = microtime(true);
        $result = $provider->translate($values, $targetSite->language, $sourceLanguage);
        $durationMs = (int)((microtime(true) - $startedAt) * 1000);

        TranslatePlugin::$app->metrics->record($result, Metric::TYPE_CONTENT, $targetSite->language, [
            'sourceLanguage' => $sourceSite->language,
            'siteId' => $targetSite->id,
            'elementId' => $source->id,
            'elementType' => get_class($source),
            'durationMs' => $durationMs,
        ]);

        if (!$result->success && empty($result->translations)) {
            throw new \RuntimeException($result->errorMessage ?? 'Translation failed.');
        }

        $translated = ArrayFlattener::unflatten($result->translations, $paths);

        $target = $this->findOrCreateTargetElement($source, $targetSite);
        $this->setElementTranslation($source, $target, $translated);

        $this->saveTarget($source, $target, $sourceSite, $targetSite, $isRoot, $translated);

        return $target;
    }

    /**
     * Save the target, as a draft when configured to.
     */
    private function saveTarget(
        ElementInterface $source,
        ElementInterface $target,
        Site $sourceSite,
        Site $targetSite,
        bool $isRoot,
        array $translated
    ): void {
        $settings = TranslatePlugin::$app->settings->getSettings();

        $notes = Craft::t('enupal-translate', 'Translated by Enupal Translate from {source} to {target}.', [
            'source' => $sourceSite->name,
            'target' => $targetSite->name,
        ]);

        $draftName = Craft::t('enupal-translate', 'Translated draft ({source} → {target})', [
            'source' => $sourceSite->getLocale()->getLanguageID(),
            'target' => $targetSite->getLocale()->getLanguageID(),
        ]);

        if ($target instanceof Entry && $target->getIsDraft()) {
            Craft::$app->getDrafts()->saveElementAsDraft($target, null, $draftName, $notes);
        } elseif ($isRoot && $target instanceof Entry && $settings->saveAsDraft) {
            $target = Craft::$app->getDrafts()->createDraft($target, null, $draftName, $notes);
            // The draft is a fresh element, so the values have to be applied again.
            $this->setElementTranslation($source, $target, $translated);
            Craft::$app->getElements()->saveElement($target);
        } else {
            if (method_exists($target, 'setRevisionNotes')) {
                $target->setRevisionNotes($notes);
            }
            Craft::$app->getElements()->saveElement($target);
        }

        if (!empty($target->getErrors())) {
            Craft::error([
                'message' => 'Validation errors while saving a translated element.',
                'elementId' => $target->id,
                'siteId' => $target->siteId,
                'errors' => $target->getErrors(),
            ], __METHOD__);
        }
    }

    // Serialization
    // =========================================================================

    /**
     * An element as a nested array of translatable strings.
     */
    public function serializeElement(ElementInterface $element, Site $sourceSite, Site $targetSite): array
    {
        $settings = TranslatePlugin::$app->settings->getSettings();
        $excluded = $settings->getExcludedFieldHandles();

        $serialized = [];

        if ($settings->translateTitle
            && !empty($element->title)
            && !in_array('title', $excluded, true)
            && (!method_exists($element, 'getIsTitleTranslatable') || $element->getIsTitleTranslatable())) {
            $serialized['title'] = $element->title;
        }

        // Assets can carry translatable alt text.
        if ($element instanceof Asset
            && !empty($element->alt)
            && $element->getVolume()->altTranslationMethod !== Field::TRANSLATION_METHOD_NONE) {
            $serialized['alt'] = $element->alt;
        }

        $serialized['fields'] = $this->serializeElementFields($element, $sourceSite, $targetSite);

        return $serialized;
    }

    /**
     * Every translatable custom field on an element.
     */
    public function serializeElementFields(ElementInterface $element, Site $sourceSite, Site $targetSite): array
    {
        $serialized = [];

        foreach ($this->getTranslatableFields($element) as $handle => $field) {
            $serializer = $this->getSerializer($field);

            if ($serializer === null) {
                continue;
            }

            try {
                $serialized[$handle] = $serializer->serialize($element, $sourceSite, $targetSite);
            } catch (Throwable $e) {
                // One awkward field shouldn't sink the whole element.
                Craft::warning(sprintf(
                    'Could not serialize field "%s" (%s) on element %s: %s',
                    $handle,
                    get_class($field),
                    $element->id,
                    $e->getMessage()
                ), __METHOD__);
            }
        }

        return $serialized;
    }

    /**
     * Apply translated values to the target element.
     */
    public function setElementTranslation(ElementInterface $source, ElementInterface $target, array $translated): void
    {
        $settings = TranslatePlugin::$app->settings->getSettings();

        if (!empty($translated['title'])) {
            $target->title = $translated['title'];

            if ($settings->resetSlug && !empty($target->slug)) {
                // Cleared so Craft regenerates it from the translated title.
                $target->slug = null;
            }
        }

        if (!empty($translated['alt']) && $target instanceof Asset) {
            $target->alt = $translated['alt'];
        }

        if (!empty($translated['fields'])) {
            $target->setFieldValues($this->setElementFieldsTranslations($source, $target, $translated['fields']));
        }
    }

    /**
     * Map translated field data back into values Craft can save.
     */
    public function setElementFieldsTranslations(ElementInterface $source, ElementInterface $target, array $translatedFields): array
    {
        $values = [];

        foreach ($this->getTranslatableFields($source) as $handle => $field) {
            if (empty($translatedFields[$handle])) {
                continue;
            }

            $serializer = $this->getSerializer($field);

            if ($serializer === null) {
                continue;
            }

            try {
                $value = $serializer->setFieldData($source, $target, $translatedFields[$handle]);

                if ($value !== null) {
                    $values[$handle] = $value;
                }
            } catch (Throwable $e) {
                Craft::warning(sprintf(
                    'Could not apply translation for field "%s" on element %s: %s',
                    $handle,
                    $source->id,
                    $e->getMessage()
                ), __METHOD__);
            }
        }

        return $values;
    }

    /**
     * Translatable custom fields, keyed by handle.
     *
     * @return Collection<string, FieldInterface>
     */
    public function getTranslatableFields(ElementInterface $element): Collection
    {
        $excluded = TranslatePlugin::$app->settings->getSettings()->getExcludedFieldHandles();
        $fieldLayout = $element->getFieldLayout();

        if ($fieldLayout === null) {
            return collect();
        }

        return collect($fieldLayout->getCustomFields())
            ->filter(fn(FieldInterface $field) => !in_array($field->handle, $excluded, true))
            ->filter(function (FieldInterface $field) {
                // Always descend into nested-element fields; the blocks inside
                // can be translatable even when the container isn't.
                if (in_array(get_class($field), self::$nestedElementFields, true)) {
                    return true;
                }

                return ($field->translationMethod ?? Field::TRANSLATION_METHOD_NONE) !== Field::TRANSLATION_METHOD_NONE;
            })
            ->filter(fn(FieldInterface $field) => isset($this->serializers[get_class($field)]))
            ->keyBy(fn(FieldInterface $field) => $field->handle);
    }

    public function getSerializer(FieldInterface $field): ?FieldSerializer
    {
        $class = $this->serializers[get_class($field)] ?? null;

        return $class ? new $class($field) : null;
    }

    // Target resolution
    // =========================================================================

    /**
     * Find the element's counterpart in the target site, creating it when the
     * section's propagation settings mean it doesn't exist yet.
     */
    public function findOrCreateTargetElement(ElementInterface $source, Site $targetSite): ElementInterface
    {
        $class = get_class($source);

        $existing = $class::find()
            ->id($source->id)
            ->siteId($targetSite->id)
            ->status(null)
            ->drafts(null)
            ->one();

        if ($existing) {
            return $existing;
        }

        if (!$source instanceof Entry) {
            // Nothing sensible to create for non-entries; fall back to the source.
            return $source;
        }

        // Craft throws a bare UnsupportedSiteException if the section isn't
        // enabled for the target site. Catch it here so the user gets told
        // what to fix rather than a stack trace.
        if (!$this->supportsSite($source, $targetSite)) {
            throw new UnsupportedSiteException($source, $targetSite->id, Craft::t('enupal-translate',
                'The section “{section}” is not enabled for the site “{site}”. Enable it in Settings → Sections before translating.', [
                    'section' => $source->getSection()?->name ?? $source->getRootOwner()::displayName(),
                    'site' => $targetSite->name,
                ]));
        }

        $propagationMethod = $source->getSection()?->propagationMethod;

        if ($propagationMethod === PropagationMethod::Custom) {
            // Enable the entry for the target site, keeping the existing flags.
            $enabled = $source->getEnabledForSite();
            $enabled = is_array($enabled)
                ? $enabled + [$targetSite->id => $source->enabledForSite]
                : [$source->siteId => $source->enabledForSite, $targetSite->id => $source->enabledForSite];

            $source->setEnabledForSite($enabled);

            if ($source->getIsDraft()) {
                Craft::$app->getDrafts()->saveElementAsDraft($source);
            } else {
                Craft::$app->getElements()->saveElement($source);
            }

            $target = Entry::find()->id($source->id)->siteId($targetSite->id)->status(null)->drafts(null)->one();

            if ($target) {
                return $target;
            }
        }

        if ($propagationMethod === PropagationMethod::All) {
            return Craft::$app->getElements()->propagateElement($source, $targetSite->id, false);
        }

        return Craft::$app->getElements()->duplicateElement($source, ['siteId' => $targetSite->id]);
    }

    /**
     * Whether the element's own settings allow it to exist in a site.
     */
    public function supportsSite(ElementInterface $element, Site $site): bool
    {
        foreach ($element->getSupportedSites() as $supported) {
            $siteId = is_array($supported) ? ($supported['siteId'] ?? null) : $supported;

            if ((int)$siteId === $site->id) {
                return true;
            }
        }

        return false;
    }

    // Serializer registry
    // =========================================================================

    private function registerSerializers(): void
    {
        foreach (self::$nestedElementFields as $class) {
            $this->serializers[$class] = MatrixSerializer::class;
        }

        $this->serializers['craft\fields\PlainText'] = TextSerializer::class;
        $this->serializers['craft\fields\Table'] = TableSerializer::class;
        $this->serializers['craft\fields\ContentBlock'] = ContentBlockSerializer::class;
        $this->serializers['craft\fields\Link'] = LinkSerializer::class;

        // Rich text, first party and third party.
        $this->serializers['craft\ckeditor\Field'] = RichTextSerializer::class;
        $this->serializers['craft\redactor\Field'] = RichTextSerializer::class;
        $this->serializers['abmat\tinymce\Field'] = RichTextSerializer::class;
        $this->serializers['verbb\vizy\fields\VizyField'] = RichTextSerializer::class;
        $this->serializers['verbb\doxter\fields\Doxter'] = TextSerializer::class;

        $this->serializers['presseddigital\linkit\fields\LinkitField'] = LinkSerializer::class;
        $this->serializers['verbb\hyper\fields\HyperField'] = LinkSerializer::class;

        $event = new RegisterSerializersEvent(['serializers' => $this->serializers]);
        $this->trigger(self::EVENT_REGISTER_SERIALIZERS, $event);

        $this->serializers = $event->serializers;
    }
}
