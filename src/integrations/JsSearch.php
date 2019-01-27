<?php

namespace enupal\translate\integrations;

use enupal\translate\contracts\BaseSearchMethod;

/**
 * Class JsSearch
 */
class JsSearch extends BaseSearchMethod
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Js search method';
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return "Javascript search method";
    }

    /**
     * @inheritdoc
     */
    public function getSingleQuoteRegex(): string
    {
        return '/Craft\.(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/';
    }

    /**
     * @inheritdoc
     */
    public function getDoubleQuoteRegex(): string
    {
        return '/Craft\.(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/';
    }

    /**
     * @inheritdoc
     */
    public function getFileExtension(): string
    {
        return 'js';
    }

    /**
     * @inheritdoc
     */
    public function getMatchPosition(): int
    {
        return 3;
    }
}
