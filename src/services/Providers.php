<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\services;

use Craft;
use craft\base\Component;
use enupal\translate\base\LlmTranslationProvider;
use enupal\translate\base\TranslationProvider;
use enupal\translate\events\RegisterProvidersEvent;
use enupal\translate\providers\ClaudeProvider;
use enupal\translate\providers\GoogleCloudProvider;
use enupal\translate\providers\GoogleFreeProvider;
use enupal\translate\providers\OpenAiProvider;
use enupal\translate\providers\YandexProvider;
use enupal\translate\Translate as TranslatePlugin;

/**
 * Registry of the available translation providers.
 */
class Providers extends Component
{
    public const EVENT_REGISTER_PROVIDERS = 'registerProviders';

    /**
     * @var TranslationProvider[]|null
     */
    private ?array $providers = null;

    /**
     * Every registered provider, keyed by handle, configured or not.
     *
     * @return TranslationProvider[]
     */
    public function getAllProviders(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $classes = [
            YandexProvider::class,
            GoogleFreeProvider::class,
            GoogleCloudProvider::class,
            OpenAiProvider::class,
            ClaudeProvider::class,
        ];

        $event = new RegisterProvidersEvent(['providers' => $classes]);
        $this->trigger(self::EVENT_REGISTER_PROVIDERS, $event);

        $this->providers = [];

        foreach ($event->providers as $class) {
            /** @var TranslationProvider $instance */
            $instance = Craft::createObject(['class' => $class]);
            $this->providers[$class::handle()] = $instance;
        }

        return $this->providers;
    }

    /**
     * Only the providers the user has switched on and given credentials to.
     *
     * @return TranslationProvider[]
     */
    public function getEnabledProviders(): array
    {
        return array_filter($this->getAllProviders(), static fn(TranslationProvider $provider) => $provider->isConfigured());
    }

    /**
     * Just the LLM-backed providers.
     *
     * @return LlmTranslationProvider[]
     */
    public function getLlmProviders(): array
    {
        return array_filter($this->getAllProviders(), static fn(TranslationProvider $provider) => $provider instanceof LlmTranslationProvider);
    }

    public function getProviderByHandle(?string $handle): ?TranslationProvider
    {
        if (empty($handle)) {
            return null;
        }

        return $this->getAllProviders()[$handle] ?? null;
    }

    /**
     * The provider used for element content, falling back to the first enabled
     * one when nothing has been chosen.
     */
    public function getContentProvider(): ?TranslationProvider
    {
        $settings = TranslatePlugin::$app->settings->getSettings();

        $provider = $this->getProviderByHandle($settings->contentProvider);

        if ($provider && $provider->isConfigured()) {
            return $provider;
        }

        $enabled = $this->getEnabledProviders();

        return $enabled ? reset($enabled) : null;
    }

    /**
     * Enabled providers as handle => label, for settings dropdowns.
     */
    public function getEnabledProviderOptions(): array
    {
        $options = [];

        foreach ($this->getEnabledProviders() as $handle => $provider) {
            $options[$handle] = $provider::displayName();
        }

        return $options;
    }
}
