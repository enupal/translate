<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\variables;

use Craft;
use enupal\translate\Translate;

/**
 * EnupalTranslate provides an API for accessing information about sliders. It is accessible from templates via `craft.enupaltranslate`.
 *
 */
class TranslateVariable
{

	/**
	 * @return string
	 */
	public function getName()
	{
		$plugin = Craft::$app->plugins->getPlugin('enupal-translate');

		return $plugin->getName();
	}

	/**
	 * @return string
	 */
	public function getVersion()
	{
		$plugin = Craft::$app->plugins->getPlugin('enupal-translate');

		return $plugin->getVersion();
	}

	/**
	 * @return string
	 */
	public function getSettings()
	{
		return Translate::$app->settings->getSettings();
	}

    /**
     * @return array
     */
    public function getTwigSearchMethods()
    {
        return Translate::$app->settings->getTwigSearchMethods();
    }

    /**
     * Model options for a provider's settings dropdown.
     *
     * @return array value => label
     */
    public function getProviderModels(string $handle): array
    {
        $provider = Translate::$app->providers->getProviderByHandle($handle);

        if ($provider === null || !method_exists($provider, 'getAvailableModels')) {
            return [];
        }

        return $provider::getAvailableModels();
    }

    /**
     * Enabled providers, for the content translation dropdown.
     *
     * @return array handle => label
     */
    public function getContentProviderOptions(): array
    {
        $options = ['' => Craft::t('enupal-translate', 'Automatic (first enabled)')];

        return $options + Translate::$app->providers->getEnabledProviderOptions();
    }

    /**
     * Every registered provider, so templates can show connection state.
     *
     * @return \enupal\translate\base\TranslationProvider[]
     */
    public function getProviders(): array
    {
        return Translate::$app->providers->getAllProviders();
    }
}
