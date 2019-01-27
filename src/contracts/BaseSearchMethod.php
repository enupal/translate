<?php

namespace enupal\translate\contracts;

use Craft;

/**
 * Class BaseSearchMethod
 */
abstract class BaseSearchMethod
{
    /**
     * The name of the Search Method
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * A description of the Search Method behavior
     *
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * What file extension you want the regex be applied: js, twig or php
     * @return string
     */
    abstract public function getFileExtension(): string;

    /**
     * @return string
     */
    abstract public function getSingleQuoteRegex(): string;

    /**
     * @return string
     */
    abstract public function getDoubleQuoteRegex(): string;

    /**
     * Array position of the matches strings from the preg_match_all array response
     * @return int
     */
    abstract public function getMatchPosition(): int;
}
