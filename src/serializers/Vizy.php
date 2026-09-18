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
 * Verbb's Vizy field.
 *
 * Vizy is not a string of markup: it is a tree of nodes, some of which are
 * nested block elements with their own fields. Text lives on the leaf nodes,
 * so the tree is walked and only the `text` values are collected — which keeps
 * marks, attributes and structure untouched on the way back.
 */
class Vizy extends FieldSerializer
{
    private const BLOCK_NODE = 'verbb\vizy\nodes\VizyBlock';

    public function serialize(ElementInterface $element, Site $sourceSite, Site $targetSite): mixed
    {
        $nodes = [];

        foreach ($this->getNodes($element) as $i => $node) {
            if (get_class($node) === self::BLOCK_NODE) {
                $blockElement = $node->getBlockElement();

                $nodes[$i] = [
                    'element' => TranslatePlugin::$app->content->serializeElement($blockElement, $sourceSite, $targetSite),
                ];
                continue;
            }

            $nodes[$i] = $this->serializeNode($node->serializeValue($element));
        }

        return $nodes;
    }

    public function setFieldData(ElementInterface $source, ElementInterface $target, mixed $value): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        $nodes = [];

        foreach ($this->getNodes($source) as $i => $node) {
            $translated = $value[$i] ?? null;

            if (get_class($node) === self::BLOCK_NODE) {
                $blockElement = $node->getBlockElement();

                if (!empty($translated['element']['fields'])) {
                    $blockElement->setFieldValues(
                        TranslatePlugin::$app->content->setElementFieldsTranslations(
                            $blockElement,
                            $target,
                            $translated['element']['fields']
                        )
                    );
                }

                if (!empty($translated['element']['title'])) {
                    $blockElement->title = $translated['element']['title'];
                }

                $nodes[] = $node->serializeValue($blockElement);
                continue;
            }

            $serialized = $node->serializeValue($source);

            // Keep the node even when nothing came back for it, so the
            // document keeps its shape.
            $nodes[] = $translated === null
                ? $serialized
                : $this->applyNode($serialized, $translated);
        }

        return $nodes;
    }

    /**
     * Collect the translatable text out of a node and its children.
     */
    private function serializeNode(array $node): array
    {
        $data = [];

        if (!empty($node['text'])) {
            $data['text'] = $node['text'];
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $child) {
                if (is_array($child)) {
                    $data['content'][$i] = $this->serializeNode($child);
                }
            }
        }

        return $data;
    }

    /**
     * Put translated text back on a node, leaving everything else alone.
     */
    private function applyNode(array $node, $translated): array
    {
        if (!is_array($translated)) {
            return $node;
        }

        if (!empty($translated['text'])) {
            $node['text'] = $translated['text'];
        }

        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $i => $child) {
                if (is_array($child)) {
                    $node['content'][$i] = $this->applyNode($child, $translated['content'][$i] ?? null);
                }
            }
        }

        return $node;
    }

    private function getNodes(ElementInterface $element): array
    {
        $value = $element->getFieldValue($this->field->handle);

        if (!is_object($value) || !method_exists($value, 'all')) {
            return [];
        }

        return $value->all();
    }
}
