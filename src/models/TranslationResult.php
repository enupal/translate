<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\models;

use craft\base\Model;

/**
 * The normalized result of a batch translation, whatever the provider.
 *
 * Token counts are zero for providers that don't report usage (Yandex,
 * Google), so callers can always sum them without special-casing.
 */
class TranslationResult extends Model
{
    /**
     * Translations keyed by the same keys that were passed in.
     *
     * @var array
     */
    public array $translations = [];

    /**
     * @var string|null
     */
    public ?string $provider = null;

    /**
     * @var string|null
     */
    public ?string $model = null;

    public int $inputTokens = 0;

    public int $outputTokens = 0;

    /**
     * How many requests the provider actually made. A single batch may be
     * split into several chunks, so this is not always 1.
     *
     * @var int
     */
    public int $apiCalls = 0;

    /**
     * Strings that were sent to the provider, after de-duplication.
     *
     * @var int
     */
    public int $uniqueStrings = 0;

    public int $characterCount = 0;

    public bool $success = true;

    public ?string $errorMessage = null;

    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Fold another result into this one. Used when a batch is split across
     * several provider calls.
     */
    public function merge(self $other): self
    {
        $this->translations = array_replace($this->translations, $other->translations);
        $this->inputTokens += $other->inputTokens;
        $this->outputTokens += $other->outputTokens;
        $this->apiCalls += $other->apiCalls;

        if (!$other->success) {
            $this->success = false;
            $this->errorMessage = trim($this->errorMessage . "\n" . $other->errorMessage);
        }

        return $this;
    }
}
