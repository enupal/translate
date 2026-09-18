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
 * Redactor, CKEditor, TinyMCE and friends.
 *
 * The markup goes to the provider intact — every provider is instructed to
 * preserve tags — and reference tags are re-pointed at the target site
 * afterwards.
 */
class RichText extends FieldSerializer
{
    private ?Site $sourceSite = null;
    private ?Site $targetSite = null;

    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $this->sourceSite = $sourceSite;
        $this->targetSite = $targetSite;

        $value = parent::serialize($element, $sourceSite, $targetSite);

        if (is_array($value)) {
            // CKEditor stores nested entries alongside the markup.
            return ['html' => $value['value'] ?? null];
        }

        return ['html' => is_string($value) ? $value : null];
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        $html = $value['html'] ?? null;

        if (!is_string($html)) {
            return null;
        }

        $sourceSite = $this->sourceSite ?? $source->getSite();
        $targetSite = $this->targetSite ?? $target->getSite();

        return $this->translateReferenceTags($html, $sourceSite, $targetSite);
    }
}
