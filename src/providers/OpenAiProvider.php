<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\providers;

use Craft;
use enupal\translate\base\LlmTranslationProvider;
use enupal\translate\models\LlmResponse;
use RuntimeException;

/**
 * OpenAI, and any OpenAI-compatible endpoint (Azure, Groq, Mistral, Ollama…)
 * via the base URL setting.
 */
class OpenAiProvider extends LlmTranslationProvider
{
    public const DEFAULT_MODEL = 'gpt-5.6-terra';

    public static function handle(): string
    {
        return 'openai';
    }

    public static function displayName(): string
    {
        return 'ChatGPT (OpenAI)';
    }

    public function getSettingsTemplate(): ?string
    {
        return 'enupal-translate/settings/_providers/openai';
    }

    public static function getAvailableModels(): array
    {
        return [
            'gpt-5.6-terra' => 'GPT 5.6 Terra — recommended',
            'gpt-5.6-sol' => 'GPT 5.6 Sol',
            'gpt-5.6-luna' => 'GPT 5.6 Luna',
            'gpt-5.4' => 'GPT 5.4',
            'gpt-5.4-mini' => 'GPT 5.4 Mini',
            'gpt-5.4-nano' => 'GPT 5.4 Nano',
            'gpt-4.1' => 'GPT 4.1',
            'gpt-4.1-mini' => 'GPT 4.1 Mini',
            'gpt-4o' => 'GPT 4o',
            'gpt-4o-mini' => 'GPT 4o Mini',
            'custom' => 'Custom…',
        ];
    }

    public function isConfigured(): bool
    {
        $settings = $this->getSettings();

        return (bool)$settings->enableOpenAi && !empty($this->parseEnv($settings->openAiApiKey));
    }

    /**
     * Shown in the CP so it is obvious when a custom endpoint is in play.
     */
    public function getDisplayLabel(): string
    {
        $baseUrl = $this->getBaseUrl();

        if (!str_contains($baseUrl, 'api.openai.com')) {
            $host = parse_url($baseUrl, PHP_URL_HOST);
            return Craft::t('enupal-translate', 'OpenAI Compatible') . ($host ? " ($host)" : '');
        }

        return static::displayName();
    }

    public function getModelName(): ?string
    {
        $settings = $this->getSettings();
        $model = $settings->openAiModel ?: self::DEFAULT_MODEL;

        if ($model === 'custom') {
            $custom = $this->parseEnv($settings->openAiCustomModel);
            return !empty($custom) ? $custom : self::DEFAULT_MODEL;
        }

        return $model;
    }

    protected function getPromptTemplate(): string
    {
        $settings = $this->getSettings();

        if (!empty($settings->openAiPrompt)) {
            return $settings->openAiPrompt;
        }

        return Craft::t('enupal-translate', 'You are a professional translator. Translate {count} strings from {source} into {target}. Produce natural, idiomatic translations suitable for a website.');
    }

    protected function getRequestHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . (string)$this->parseEnv($this->getSettings()->openAiApiKey),
            'Content-Type' => 'application/json',
        ];
    }

    protected function getBaseUrl(): string
    {
        $baseUrl = $this->parseEnv($this->getSettings()->openAiBaseUrl);

        return !empty($baseUrl) ? rtrim($baseUrl, '/') : 'https://api.openai.com/v1';
    }

    protected function sendChatRequest(string $systemPrompt, string $userPrompt): LlmResponse
    {
        $settings = $this->getSettings();
        $model = $this->getModelName();

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            // Guarantees parseable output instead of relying on the prompt.
            'response_format' => ['type' => 'json_object'],
        ];

        if ($this->modelSupportsTemperature($model)) {
            $body['temperature'] = (float)$settings->openAiTemperature;
        }

        $response = $this->getClient()->post($this->getBaseUrl() . '/chat/completions', [
            'json' => $body,
        ]);

        $data = json_decode((string)$response->getBody(), true);

        if (!is_array($data)) {
            throw new RuntimeException('OpenAI returned a malformed response.');
        }

        $choice = $data['choices'][0] ?? null;

        if (($choice['finish_reason'] ?? null) === 'length') {
            throw new RuntimeException('OpenAI hit the output token limit before finishing. Lower the batch size.');
        }

        return new LlmResponse([
            'content' => (string)($choice['message']['content'] ?? ''),
            'model' => $data['model'] ?? $model,
            'inputTokens' => (int)($data['usage']['prompt_tokens'] ?? 0),
            'outputTokens' => (int)($data['usage']['completion_tokens'] ?? 0),
        ]);
    }

    /**
     * The GPT-5 family only accepts the default temperature.
     */
    public function modelSupportsTemperature(string $model): bool
    {
        return !preg_match('/^gpt-5/i', $model);
    }

    public function isConnected(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            return $this->getClient()->get($this->getBaseUrl() . '/models')->getStatusCode() === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
