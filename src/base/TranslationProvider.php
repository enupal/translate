<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\base;

use Craft;
use craft\base\Component;
use enupal\translate\models\TranslationResult;
use enupal\translate\Translate as TranslatePlugin;
use Throwable;

/**
 * Base class for every translation provider.
 *
 * Subclasses only have to know how to translate one chunk of strings
 * ({@see performTranslation()}). Everything that every provider needs —
 * de-duplication, chunking, retries and usage accounting — lives here so the
 * providers stay small.
 */
abstract class TranslationProvider extends Component
{
    /**
     * Machine name used in settings and metrics, e.g. 'openai'.
     */
    abstract public static function handle(): string;

    /**
     * Name shown in the control panel.
     *
     * Craft's Component already declares this concretely, so it is overridden
     * rather than re-declared abstract. Every provider is expected to supply
     * its own.
     */
    public static function displayName(): string
    {
        return static::handle();
    }

    /**
     * Translate a single chunk of strings.
     *
     * @param array $texts Strings keyed by an arbitrary key.
     * @return TranslationResult Translations keyed by the same keys.
     */
    abstract protected function performTranslation(array $texts, string $targetLanguage, ?string $sourceLanguage): TranslationResult;

    /**
     * Whether the provider has everything it needs to run.
     */
    abstract public function isConfigured(): bool;

    /**
     * Path to the settings fieldset for this provider, or null if it has none.
     */
    public function getSettingsTemplate(): ?string
    {
        return null;
    }

    /**
     * Whether credentials actually work. Providers that can check cheaply
     * should override this.
     */
    public function isConnected(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $result = $this->translate(['test' => 'Hello'], 'es', 'en');
            return $result->success && !empty($result->translations['test']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Largest payload, in characters, to put in a single provider request.
     * Providers with tighter limits should lower this.
     */
    protected function getMaxChunkCharacters(): int
    {
        return 20000;
    }

    /**
     * Largest number of strings to put in a single provider request.
     */
    protected function getMaxChunkStrings(): int
    {
        return 100;
    }

    protected function getMaxAttempts(): int
    {
        return 3;
    }

    /**
     * Milliseconds to wait between consecutive requests to this provider.
     * Zero for APIs that are happy to be called back to back.
     */
    protected function getRequestDelayMs(): int
    {
        return 0;
    }

    /**
     * Translate a keyed set of strings.
     *
     * Identical strings are only sent to the provider once and the result is
     * fanned back out, which matters a lot for LLM providers where every
     * repeated string is billed again.
     *
     * @param array $texts Strings keyed by an arbitrary key.
     * @return TranslationResult Translations keyed by the same keys.
     */
    public function translate(array $texts, string $targetLanguage, ?string $sourceLanguage = null): TranslationResult
    {
        $result = new TranslationResult([
            'provider' => static::handle(),
            'model' => $this->getModelName(),
            'apiCalls' => 0,
        ]);

        // Drop anything there is nothing to translate in, but remember it so
        // the caller still gets a value back for every key it passed in.
        $translatable = [];
        foreach ($texts as $key => $text) {
            if (is_string($text) && trim($text) !== '') {
                $translatable[$key] = $text;
            }
        }

        if (empty($translatable)) {
            return $result;
        }

        // Collapse duplicates: one entry per distinct string.
        $uniqueTexts = [];
        $keysByHash = [];
        foreach ($translatable as $key => $text) {
            $hash = md5($text);
            if (!isset($uniqueTexts[$hash])) {
                $uniqueTexts[$hash] = $text;
            }
            $keysByHash[$hash][] = $key;
        }

        $result->uniqueStrings = count($uniqueTexts);
        $result->characterCount = array_sum(array_map('mb_strlen', $uniqueTexts));

        $translatedByHash = [];

        foreach ($this->chunk($uniqueTexts) as $chunk) {
            $chunkResult = $this->translateChunkWithRetries($chunk, $targetLanguage, $sourceLanguage);

            $result->inputTokens += $chunkResult->inputTokens;
            $result->outputTokens += $chunkResult->outputTokens;
            $result->apiCalls += $chunkResult->apiCalls;

            if (!$chunkResult->success) {
                $result->success = false;
                $result->errorMessage = trim($result->errorMessage . "\n" . $chunkResult->errorMessage);
            }

            // Keep whatever did come back. A provider that fails halfway still
            // did the work for the strings it got through, and throwing those
            // away means paying for them again on the retry.
            $translatedByHash += $chunkResult->translations;
        }

        // Fan the de-duplicated translations back out to the original keys.
        foreach ($translatedByHash as $hash => $translation) {
            foreach ($keysByHash[$hash] ?? [] as $key) {
                $result->translations[$key] = $translation;
            }
        }

        return $result;
    }

    /**
     * Split the strings into chunks that respect both the character and the
     * string-count limit.
     *
     * A single string that is longer than the character limit still gets its
     * own chunk rather than being dropped or truncated.
     *
     * @return array[]
     */
    protected function chunk(array $texts): array
    {
        $maxCharacters = $this->getMaxChunkCharacters();
        $maxStrings = $this->getMaxChunkStrings();

        $chunks = [];
        $current = [];
        $currentLength = 0;

        foreach ($texts as $key => $text) {
            $length = mb_strlen($text);

            if ($current !== [] && ($currentLength + $length > $maxCharacters || count($current) >= $maxStrings)) {
                $chunks[] = $current;
                $current = [];
                $currentLength = 0;
            }

            $current[$key] = $text;
            $currentLength += $length;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Run one chunk, retrying with exponential backoff on failure.
     */
    protected function translateChunkWithRetries(array $chunk, string $targetLanguage, ?string $sourceLanguage): TranslationResult
    {
        $maxAttempts = $this->getMaxAttempts();
        $lastError = null;
        $apiCalls = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $this->performTranslation($chunk, $targetLanguage, $sourceLanguage);
                // Count every attempt, not just the one that succeeded — a
                // retried call still costs money.
                $result->apiCalls = $apiCalls + max($result->apiCalls, 1);

                return $result;
            } catch (Throwable $e) {
                $apiCalls++;
                $lastError = $e;

                Craft::warning(sprintf(
                    '%s translation attempt %d/%d failed: %s',
                    static::handle(),
                    $attempt,
                    $maxAttempts,
                    $e->getMessage()
                ), __METHOD__);

                if ($attempt < $maxAttempts && $this->isRetryable($e)) {
                    // 1s, 2s, 4s ...
                    sleep(2 ** ($attempt - 1));
                    continue;
                }

                break;
            }
        }

        Craft::error(sprintf(
            '%s translation failed: %s',
            static::handle(),
            $lastError?->getMessage() ?? 'unknown error'
        ), __METHOD__);

        return new TranslationResult([
            'provider' => static::handle(),
            'model' => $this->getModelName(),
            'success' => false,
            'apiCalls' => $apiCalls,
            'errorMessage' => $lastError?->getMessage() ?? 'Unknown error',
        ]);
    }

    /**
     * Whether it is worth trying the request again. Rate limits and server
     * errors are; a bad API key or an exhausted quota is not.
     */
    protected function isRetryable(Throwable $e): bool
    {
        $status = $this->getStatusCode($e);

        if ($status === 429) {
            // A 429 usually means "slow down", but providers also use it for
            // billing problems, which will never resolve by waiting.
            return !$this->isQuotaError($e);
        }

        if ($status !== null) {
            return $status >= 500;
        }

        // No status at all: a connection-level failure, worth another go.
        return true;
    }

    /**
     * Whether the error is about credit or quota rather than request rate.
     */
    protected function isQuotaError(Throwable $e): bool
    {
        $body = strtolower($this->getResponseBody($e) ?? $e->getMessage());

        foreach ([
            'insufficient_quota',
            'no credits',
            'billing',
            'exceeded your current quota',
            'credit balance is too low',
        ] as $needle) {
            if (str_contains($body, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * HTTP status behind an exception, or null if it isn't an HTTP error.
     */
    protected function getStatusCode(Throwable $e): ?int
    {
        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();

            if ($response) {
                return $response->getStatusCode();
            }
        }

        $code = (int)$e->getCode();

        return $code >= 100 && $code < 600 ? $code : null;
    }

    protected function getResponseBody(Throwable $e): ?string
    {
        if (!method_exists($e, 'getResponse')) {
            return null;
        }

        $response = $e->getResponse();

        if (!$response) {
            return null;
        }

        $body = $response->getBody();
        $body->rewind();

        return $body->getContents();
    }

    /**
     * Model identifier recorded against metrics. Non-LLM providers have none.
     */
    public function getModelName(): ?string
    {
        return null;
    }

    /**
     * Reduce 'en-US' to 'en'. Most APIs want the bare language.
     */
    protected function shortLanguage(?string $language): ?string
    {
        if (empty($language)) {
            return null;
        }

        return explode('-', str_replace('_', '-', $language))[0];
    }

    /**
     * Human readable language name, which is what LLMs work best with.
     */
    protected function languageName(?string $language): ?string
    {
        if (empty($language)) {
            return null;
        }

        $name = locale_get_display_name(str_replace('_', '-', $language), 'en');

        return $name ?: $language;
    }

    protected function getSettings(): \enupal\translate\models\Settings
    {
        return TranslatePlugin::$app->settings->getSettings();
    }
}
