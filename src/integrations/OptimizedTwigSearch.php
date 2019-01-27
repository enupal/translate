<?php

namespace enupal\translate\integrations;

use enupal\translate\contracts\BaseSearchMethod;

/**
 * Class OptimizedTwigSearch
 */
class OptimizedTwigSearch extends BaseSearchMethod
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Optimized twig search method';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return "This method requires that the translate twig filter be the first in your strings, e.g {{ 'Hello world'|t|upper }}";
    }

    /**
     * @inheritdoc
     */
    public function getSingleQuoteRegex(): string
    {
        return '/\'((?:[^\']|\\\\\')*)\'\s*\|\s*t(?:ranslate)?\b/';
    }

    /**
     * @inheritdoc
     */
    public function getDoubleQuoteRegex(): string
    {
        return '/"((?:[^"]|\\\\")*)"\s*\|\s*t(?:ranslate)?\b/';
    }

    /**
     * @inheritdoc
     */
    public function getFileExtension(): string
    {
        return 'twig';
    }

    /**
     * @inheritdoc
     */
    public function getMatchPosition(): int
    {
        return 1;
    }
}
