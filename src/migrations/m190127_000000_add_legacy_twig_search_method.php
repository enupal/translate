<?php

namespace enupal\translate\migrations;

use craft\db\Migration;
use Craft;
use craft\services\Plugins;
use enupal\translate\integrations\LegacyTwigSearch;
use enupal\translate\Translate;

/**
 * m190127_000000_add_legacy_twig_search_method migration.
 */
class m190127_000000_add_legacy_twig_search_method extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $plugin = Translate::getInstance();
        $settings = $plugin->getSettings();

        $settings->twigRegexMethod = LegacyTwigSearch::class;

        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->set(\craft\services\ProjectConfig::PATH_PLUGINS . '.' . $plugin->handle . '.settings', $settings->toArray());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190127_000000_add_legacy_twig_search_method cannot be reverted.\n";

        return false;
    }
}
