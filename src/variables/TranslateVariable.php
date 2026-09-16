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

    /**
     * The sites an element can be translated into, with whether a translation
     * already exists there.
     *
     * @return array[] each: ['site' => Site, 'exists' => bool]
     */
    public function getTranslationTargets(\craft\base\ElementInterface $element): array
    {
        $user = Craft::$app->getUser()->getIdentity();
        $content = Translate::$app->content;
        $sourceSiteId = $element->siteId;
        $elementType = get_class($element);
        $canonicalId = $element->getCanonicalId();

        $targets = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->id === $sourceSiteId) {
                continue;
            }

            if (!$user?->can('editSite:' . $site->uid)) {
                continue;
            }

            if (!$content->supportsSite($element, $site)) {
                continue;
            }

            $targets[] = [
                'site' => $site,
                'exists' => $elementType::find()
                    ->id($canonicalId)
                    ->siteId($site->id)
                    ->status(null)
                    ->exists(),
            ];
        }

        return $targets;
    }

    /**
     * Whether content translation is ready to use: switched on, with a
     * provider configured.
     */
    public function isContentTranslationReady(): bool
    {
        $settings = Translate::$app->settings->getSettings();

        return (bool)$settings->enableContentTranslation
            && Translate::$app->providers->getContentProvider() !== null;
    }

    /**
     * Enabled providers as handle => label, for the sidebar picker.
     */
    public function getEnabledProviders(): array
    {
        return Translate::$app->providers->getEnabledProviderOptions();
    }

    /**
     * Handle of the provider content translation will use by default.
     */
    public function getContentProviderHandle(): ?string
    {
        $provider = Translate::$app->providers->getContentProvider();

        return $provider ? $provider::handle() : null;
    }

    /**
     * Label of the provider that content translation will use.
     */
    public function getContentProviderName(): ?string
    {
        $provider = Translate::$app->providers->getContentProvider();

        return $provider ? $provider::displayName() : null;
    }
}
