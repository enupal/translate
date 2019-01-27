<?php

namespace enupal\translate\integrations;

use enupal\translate\contracts\BaseSearchMethod;

/**
 * Class LegacyTwigSearch
 */
class LegacyTwigSearch extends BaseSearchMethod
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Legacy twig search method';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Legacy search translations strings';
    }

    /**
     * @inheritdoc
     */
    public function getSingleQuoteRegex(): string
    {
        return '/(\{\{\s*|\{\%.*?|:\s*)\'(.*?)\'.*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/';
    }

    /**
     * @inheritdoc
     */
    public function getDoubleQuoteRegex(): string
    {
        return '/(\{\{\s*|\{\%.*?|:\s*)"(.*?)".*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/';
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
        return 2;
    }
}
