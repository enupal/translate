<?php

namespace enupal\translate\integrations;

use enupal\translate\contracts\BaseSearchMethod;

/**
 * Class PhpSearch
 */
class PhpSearch extends BaseSearchMethod
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Php search method';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return "Php search method";
    }

    /**
     * @inheritdoc
     */
    public function getSingleQuoteRegex(): string
    {
        return '/Craft::(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/';
    }

    /**
     * @inheritdoc
     */
    public function getDoubleQuoteRegex(): string
    {
        return '/Craft::(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/';
    }

    /**
     * @inheritdoc
     */
    public function getFileExtension(): string
    {
        return 'php';
    }

    /**
     * @inheritdoc
     */
    public function getMatchPosition(): int
    {
        return 3;
    }
}
