<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\services;

use Craft;
use craft\db\Query;
use enupal\translate\integrations\LegacyTwigSearch;
use enupal\translate\integrations\OptimizedTwigSearch;
use yii\base\Component;

class Settings extends Component
{
    /**
     * Saves Settings
     *
     * @param string $scenario
     * @param array  $postSettings
     *
     * @return bool
     */
    public function saveSettings(array $postSettings, string $scenario = null): bool
    {
        $plugin = $this->getPlugin();

        $plugin->getSettings()->setAttributes($postSettings, false);

        if ($scenario) {
            $plugin->getSettings()->setScenario($scenario);
        }

        // Validate them, now that it's a model
        if ($plugin->getSettings()->validate() === false) {
            return false;
        }

        $success = Craft::$app->getPlugins()->savePluginSettings($plugin, $postSettings);

        return $success;
    }

    /**
     * @return \craft\base\Model|null
     */
    public function getSettings()
    {
        $translatePlugin = $this->getPlugin();

        return $translatePlugin->getSettings();
    }

    /**
     * @return \craft\base\PluginInterface|null
     */
    public function getPlugin()
    {
        return Craft::$app->getPlugins()->getPlugin('enupal-translate');
    }

    /**
     * @return array
     */
    public function getTwigSearchMethods()
    {
        $legacy = new LegacyTwigSearch();

        $optimized = new OptimizedTwigSearch();
        $methods = [
            LegacyTwigSearch::class => $legacy->getName(),
            OptimizedTwigSearch::class => $optimized->getName(),
        ];

        return $methods;
    }
}
