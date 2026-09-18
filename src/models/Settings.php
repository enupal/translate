<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\models;

use craft\base\Model;
use enupal\translate\integrations\OptimizedTwigSearch;

/**
 * @author    Enupal
 * @package   Translate
 * @since     1.0.0
 */
class Settings extends Model
{
    /**
     * @var string
     */
    public $pluginNameOverride;

    // Static translation search
    // =========================================================================

    /**
     * @var string
     */
    public $createPluginTranslationFolder = 0;

    /**
     * @var bool
     */
    public $displayPlugins = 1;

    /**
     * @var string
     */
    public $twigRegexMethod = OptimizedTwigSearch::class;

    // Yandex
    // =========================================================================

    /**
     * @var bool
     */
    public $enableYandex = 0;

    /**
     * @var string
     */
    public $yandexApi = '';

    // Google
    // =========================================================================

    /**
     * @var bool
     */
    public $enableFreeGoogleApi = 0;

    /**
     * @var bool
     */
    public $enableGoogleApi = 0;

    /**
     * @var string
     */
    public $googleApi = '';

    // OpenAI
    // =========================================================================

    /**
     * @var bool
     */
    public $enableOpenAi = 0;

    /**
     * @var string
     */
    public $openAiApiKey = '';

    /**
     * Leave empty for api.openai.com. Set it to target any OpenAI-compatible
     * endpoint (Azure, Groq, Mistral, Ollama…).
     *
     * @var string
     */
    public $openAiBaseUrl = '';

    /**
     * @var string
     */
    public $openAiModel = 'gpt-5.6-terra';

    /**
     * @var string
     */
    public $openAiCustomModel = '';

    /**
     * @var float
     */
    public $openAiTemperature = 0.3;

    /**
     * @var string
     */
    public $openAiPrompt = '';

    // Claude
    // =========================================================================

    /**
     * @var bool
     */
    public $enableClaude = 0;

    /**
     * @var string
     */
    public $claudeApiKey = '';

    /**
     * @var string
     */
    public $claudeBaseUrl = '';

    /**
     * @var string
     */
    public $claudeModel = 'claude-opus-5';

    /**
     * @var string
     */
    public $claudeCustomModel = '';

    /**
     * Only sent for models that still accept it; current Claude models reject
     * the parameter outright.
     *
     * @var float
     */
    public $claudeTemperature = 0.3;

    /**
     * @var int
     */
    public $claudeMaxTokens = 16000;

    /**
     * Reasoning depth. Translation is shallow work, so this defaults low.
     *
     * @var string
     */
    public $claudeEffort = 'low';

    /**
     * @var string
     */
    public $claudePrompt = '';

    // Shared LLM behaviour
    // =========================================================================

    /**
     * Terms to leave untranslated, one per line (brand names, product names…).
     *
     * @var string
     */
    public $llmProtectedTerms = '';

    /**
     * Extra style guidance appended to every LLM prompt.
     *
     * @var string
     */
    public $llmToneInstructions = '';

    // Content translation
    // =========================================================================

    /**
     * Provider handle used when translating element content.
     *
     * @var string
     */
    public $contentProvider = '';

    /**
     * @var bool
     */
    public $enableContentTranslation = 1;

    /**
     * Save the translated entry as a draft instead of overwriting the live one.
     *
     * @var bool
     */
    public $saveAsDraft = 1;

    /**
     * @var bool
     */
    public $translateTitle = 1;

    /**
     * Clear the slug so Craft regenerates it from the translated title.
     *
     * @var bool
     */
    public $resetSlug = 0;

    /**
     * @var bool
     */
    public $translateDisabledMatrixElements = 0;

    /**
     * Re-point Craft reference tags at the target site.
     *
     * @var bool
     */
    public $updateInternalLinks = 1;

    /**
     * Let the provider detect the source language instead of declaring it.
     *
     * @var bool
     */
    public $detectSourceLanguage = 0;

    /**
     * Field handles to never translate, one per line.
     *
     * @var string
     */
    public $excludedFieldHandles = '';

    /**
     * Static translation batches larger than this are handed to the queue
     * instead of running in the request.
     *
     * @var int
     */
    public $staticQueueThreshold = 25;

    // Metrics
    // =========================================================================

    /**
     * @var bool
     */
    public $enableMetrics = 1;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['claudeMaxTokens'], 'integer', 'min' => 1024],
            [['openAiTemperature', 'claudeTemperature'], 'number', 'min' => 0, 'max' => 2],
            [['claudeEffort'], 'in', 'range' => ['low', 'medium', 'high', 'xhigh', 'max']],
        ];
    }

    /**
     * Field handles the user has excluded, as an array.
     *
     * @return string[]
     */
    public function getExcludedFieldHandles(): array
    {
        if (empty($this->excludedFieldHandles)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $this->excludedFieldHandles))));
    }
}
