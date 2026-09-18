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
 * Anthropic Claude, via the Messages API.
 */
class ClaudeProvider extends LlmTranslationProvider
{
    public const DEFAULT_MODEL = 'claude-opus-5';

    public const API_VERSION = '2023-06-01';

    public static function handle(): string
    {
        return 'claude';
    }

    public static function displayName(): string
    {
        return 'Claude (Anthropic)';
    }

    public function getSettingsTemplate(): ?string
    {
        return 'enupal-translate/settings/_providers/claude';
    }

    public static function getAvailableModels(): array
    {
        return [
            'claude-opus-5' => 'Claude Opus 5 — most capable',
            'claude-sonnet-5' => 'Claude Sonnet 5 — balanced',
            'claude-haiku-4-5' => 'Claude Haiku 4.5 — fastest / cheapest',
            'claude-opus-4-8' => 'Claude Opus 4.8',
            'custom' => 'Custom…',
        ];
    }

    public function isConfigured(): bool
    {
        $settings = $this->getSettings();

        return (bool)$settings->enableClaude && !empty($this->parseEnv($settings->claudeApiKey));
    }

    public function getModelName(): ?string
    {
        $settings = $this->getSettings();
        $model = $settings->claudeModel ?: self::DEFAULT_MODEL;

        if ($model === 'custom') {
            $custom = $this->parseEnv($settings->claudeCustomModel);
            return !empty($custom) ? $custom : self::DEFAULT_MODEL;
        }

        return $model;
    }

    protected function getPromptTemplate(): string
    {
        $settings = $this->getSettings();

        if (!empty($settings->claudePrompt)) {
            return $settings->claudePrompt;
        }

        return Craft::t('enupal-translate', 'You are a professional translator. Translate {count} strings from {source} into {target}. Produce natural, idiomatic translations suitable for a website.');
    }

    protected function getRequestHeaders(): array
    {
        return [
            'x-api-key' => (string)$this->parseEnv($this->getSettings()->claudeApiKey),
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ];
    }

    protected function getBaseUrl(): string
    {
        $baseUrl = $this->parseEnv($this->getSettings()->claudeBaseUrl);

        return !empty($baseUrl) ? rtrim($baseUrl, '/') : 'https://api.anthropic.com/v1';
    }

    protected function sendChatRequest(string $systemPrompt, string $userPrompt): LlmResponse
    {
        $settings = $this->getSettings();
        $model = $this->getModelName();

        $body = [
            'model' => $model,
            'max_tokens' => max(1024, (int)$settings->claudeMaxTokens),
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];

        // Current Claude models reject temperature outright, so only send it
        // for the older models that still accept it.
        if ($this->modelSupportsTemperature($model)) {
            $body['temperature'] = (float)$settings->claudeTemperature;
        }

        // Translation is a shallow task, so cap reasoning effort to keep it
        // fast and cheap on the models that support the setting.
        if ($this->modelSupportsEffort($model)) {
            $body['output_config'] = [
                'effort' => $settings->claudeEffort ?: 'low',
            ];
        }

        $data = $this->postJson($this->getBaseUrl() . '/messages', $body);

        // A refusal comes back as HTTP 200 with no usable content, so it has
        // to be checked before reading the content blocks.
        if (($data['stop_reason'] ?? null) === 'refusal') {
            $explanation = $data['stop_details']['explanation'] ?? 'no explanation given';
            throw new RuntimeException('Claude declined to translate this content: ' . $explanation);
        }

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        if (($data['stop_reason'] ?? null) === 'max_tokens') {
            throw new RuntimeException('Claude hit the max token limit before finishing. Lower the batch size or raise "Max tokens".');
        }

        return new LlmResponse([
            'content' => $text,
            'model' => $data['model'] ?? $model,
            'inputTokens' => (int)($data['usage']['input_tokens'] ?? 0),
            'outputTokens' => (int)($data['usage']['output_tokens'] ?? 0),
        ]);
    }

    /**
     * Opus 4.6+ and Sonnet 4.6+ reject temperature with a 400.
     */
    public function modelSupportsTemperature(string $model): bool
    {
        return !preg_match('/^claude-(opus-(4-6|4-7|4-8|5)|sonnet-(4-6|5)|fable|mythos)/i', $model);
    }

    /**
     * output_config.effort errors on Haiku 4.5 and older models.
     */
    public function modelSupportsEffort(string $model): bool
    {
        return (bool)preg_match('/^claude-(opus-(4-7|4-8|5)|sonnet-5|fable|mythos)/i', $model);
    }
}
