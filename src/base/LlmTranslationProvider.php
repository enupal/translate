<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\base;

use Craft;
use craft\helpers\App;
use enupal\translate\models\LlmResponse;
use enupal\translate\models\TranslationResult;
use enupal\translate\errors\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

/**
 * Shared behaviour for chat-completion style providers (OpenAI, Claude).
 *
 * The two vendors differ only in endpoint, auth header and the shape of the
 * request/response envelope, so subclasses implement {@see sendChatRequest()}
 * and inherit the rest: the batch protocol, prompt construction, JSON parsing
 * and token accounting.
 *
 * Strings are sent as a JSON object keyed by a short index rather than as one
 * concatenated blob, so a translation can never be mis-attributed to the wrong
 * source string — which is the failure mode of delimiter-based batching.
 */
abstract class LlmTranslationProvider extends TranslationProvider
{
    protected ?Client $client = null;

    /**
     * Perform one chat completion.
     */
    abstract protected function sendChatRequest(string $systemPrompt, string $userPrompt): LlmResponse;

    /**
     * Models offered in the settings dropdown, as value => label.
     */
    abstract public static function getAvailableModels(): array;

    /**
     * The instruction template. Supports {source}, {target} and {count}.
     */
    abstract protected function getPromptTemplate(): string;

    /**
     * LLMs handle fewer, larger requests better than many small ones, but an
     * over-long response risks hitting the output token ceiling.
     */
    protected function getMaxChunkCharacters(): int
    {
        return 8000;
    }

    protected function getMaxChunkStrings(): int
    {
        return 50;
    }

    protected function performTranslation(array $texts, string $targetLanguage, ?string $sourceLanguage): TranslationResult
    {
        // The incoming keys are content hashes; sending them would burn tokens
        // for no benefit, so index the payload and map back afterwards.
        $keys = array_keys($texts);
        $payload = [];
        foreach (array_values($texts) as $index => $text) {
            $payload[(string)($index + 1)] = $text;
        }

        $systemPrompt = $this->buildSystemPrompt($sourceLanguage, $targetLanguage, count($payload));
        $userPrompt = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->sendChatRequest($systemPrompt, $userPrompt);
        $decoded = $this->decodeJsonObject($response->content);

        $translations = [];
        foreach ($keys as $index => $key) {
            $value = $decoded[(string)($index + 1)] ?? null;

            if (is_string($value) && $value !== '') {
                $translations[$key] = $value;
            }
        }

        if (empty($translations)) {
            throw new RuntimeException('The model returned no usable translations.');
        }

        return new TranslationResult([
            'provider' => static::handle(),
            'model' => $response->model ?? $this->getModelName(),
            'translations' => $translations,
            'inputTokens' => $response->inputTokens,
            'outputTokens' => $response->outputTokens,
            'apiCalls' => 1,
        ]);
    }

    /**
     * Build the instruction sent as the system prompt.
     */
    protected function buildSystemPrompt(?string $sourceLanguage, string $targetLanguage, int $count): string
    {
        $settings = $this->getSettings();

        $source = $this->languageName($sourceLanguage);
        $target = $this->languageName($targetLanguage) ?? $targetLanguage;

        $prompt = strtr($this->getPromptTemplate(), [
            '{source}' => $source ?? 'the source language (detect it)',
            '{target}' => $target,
            '{count}' => (string)$count,
        ]);

        // The protocol contract is appended rather than templated so a user
        // who customises the prompt cannot accidentally break parsing.
        $prompt .= "\n\n" . implode("\n", [
            'You will receive a JSON object whose values are the strings to translate.',
            'Respond with a JSON object only — no markdown, no code fences, no commentary.',
            'The response must use exactly the same keys as the input.',
            'Translate only the values. Never translate, reorder or drop the keys.',
            'Preserve HTML tags, attributes, entities and whitespace exactly as they appear.',
            'Preserve placeholders such as {name}, {{ twig }}, %s and Craft reference tags like {entry:1@2:url} verbatim.',
            'If a value cannot be translated, return it unchanged rather than omitting its key.',
        ]);

        if (!empty($settings->llmProtectedTerms)) {
            $terms = array_filter(array_map('trim', explode("\n", $settings->llmProtectedTerms)));
            if ($terms) {
                $prompt .= "\n" . 'Leave these terms untranslated: ' . implode(', ', $terms) . '.';
            }
        }

        if (!empty($settings->llmToneInstructions)) {
            $prompt .= "\n" . trim($settings->llmToneInstructions);
        }

        return $prompt;
    }

    /**
     * Pull a JSON object out of a model response.
     *
     * Models sometimes wrap JSON in code fences or add a sentence around it,
     * so fall back to extracting the outermost braces before giving up.
     */
    protected function decodeJsonObject(string $content): array
    {
        $content = trim($content);

        if ($content === '') {
            throw new RuntimeException('The model returned an empty response.');
        }

        // Strip a ```json ... ``` fence if present.
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-zA-Z]*\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
            $content = trim((string)$content);
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            $start = strpos($content, '{');
            $end = strrpos($content, '}');

            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            }
        }

        if (!is_array($decoded)) {
            Craft::error('Could not parse model response as JSON: ' . mb_substr($content, 0, 500), __METHOD__);
            throw new RuntimeException('The model did not return valid JSON.');
        }

        return $decoded;
    }

    /**
     * Guzzle client with the vendor's auth headers already applied.
     */
    protected function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = Craft::createGuzzleClient([
                'headers' => $this->getRequestHeaders(),
                'timeout' => $this->getTimeout(),
                'http_errors' => true,
            ]);
        }

        return $this->client;
    }


    /**
     * POST a JSON body and return the decoded response.
     *
     * Guzzle's own exception message embeds the whole HTTP response, which is
     * unreadable in a queue job or a flash message, so the provider's error
     * text is pulled out and re-thrown on its own. The status code is kept so
     * the retry logic can still see it.
     */
    protected function postJson(string $url, array $body): array
    {
        try {
            $response = $this->getClient()->post($url, ['json' => $body]);
        } catch (RequestException $e) {
            $message = $this->extractApiError($e);

            if ($message === null) {
                throw $e;
            }

            throw new ApiException($message, $e->getResponse()?->getStatusCode() ?? 0, $e);
        }

        $data = json_decode((string)$response->getBody(), true);

        if (!is_array($data)) {
            throw new RuntimeException(static::displayName() . ' returned a malformed response.');
        }

        return $data;
    }

    /**
     * Pull the human-readable error out of a provider error response.
     * Both OpenAI and Anthropic nest it under an "error" object.
     */
    protected function extractApiError(RequestException $e): ?string
    {
        $response = $e->getResponse();

        if (!$response) {
            return null;
        }

        $stream = $response->getBody();
        $stream->rewind();
        $decoded = json_decode($stream->getContents(), true);

        $message = $decoded['error']['message'] ?? $decoded['message'] ?? null;

        if (!is_string($message) || $message === '') {
            return null;
        }

        return sprintf('%s API error (HTTP %d): %s', static::displayName(), $response->getStatusCode(), $message);
    }

    abstract protected function getRequestHeaders(): array;

    protected function getTimeout(): int
    {
        return 120;
    }

    /**
     * Resolve an API key that may be stored as an environment variable.
     */
    protected function parseEnv(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return App::parseEnv($value);
    }
}
