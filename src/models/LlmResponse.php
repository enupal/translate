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
 * One chat completion, normalized across LLM vendors.
 *
 * OpenAI reports usage as prompt_tokens/completion_tokens and Anthropic as
 * input_tokens/output_tokens; both are mapped onto the same two properties
 * here so the rest of the plugin never has to care which vendor replied.
 */
class LlmResponse extends Model
{
    public string $content = '';

    public int $inputTokens = 0;

    public int $outputTokens = 0;

    public ?string $model = null;
}
