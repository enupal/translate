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
 * SEOmatic's SEO Settings field.
 *
 * Only fields the author has actually overridden are translated. SEOmatic
 * otherwise inherits its values from the entry (title, an asset's description,
 * and so on), and writing a translated string into an inherited field would
 * switch it to a custom value — freezing today's text in place and quietly
 * breaking the inheritance for everything that follows.
 */
class Seomatic extends FieldSerializer
{
    /**
     * The translatable meta fields, as stored under metaGlobalVars.
     */
    private const TEXT_FIELDS = [
        'seoTitle',
        'seoDescription',
        'seoKeywords',
        'seoImageDescription',
        'twitterTitle',
        'twitterDescription',
        'twitterImageDescription',
        'ogTitle',
        'ogDescription',
        'ogImageDescription',
    ];

    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $serialized = parent::serialize($element, $sourceSite, $targetSite);

        if (!is_array($serialized)) {
            return [];
        }

        $data = [];

        foreach (self::TEXT_FIELDS as $handle) {
            if ($this->isCustom($serialized, $handle)) {
                $data[$handle] = $serialized['metaGlobalVars'][$handle];
            }
        }

        return $data;
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        $serialized = parent::serialize($source, $source->getSite(), $target->getSite());

        if (!is_array($serialized)) {
            return null;
        }

        foreach (self::TEXT_FIELDS as $handle) {
            if ($this->isCustom($serialized, $handle) && !empty($value[$handle])) {
                $serialized['metaGlobalVars'][$handle] = $value[$handle];
            }
        }

        return $serialized;
    }

    /**
     * Whether this meta field holds an author-supplied value rather than one
     * inherited from the element.
     */
    private function isCustom(array $serialized, string $handle): bool
    {
        return !empty($serialized['metaGlobalVars'][$handle])
            && ($serialized['metaBundleSettings'][$handle . 'Source'] ?? null) === 'fromCustom';
    }
}
