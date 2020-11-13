<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translate your website templates and plugins into multiple languages. Bulk translation with Google Translate or Yandex.
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate;

use Craft;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use enupal\translate\services\App;
use yii\base\Event;
use craft\db\Query;

use enupal\translate\variables\TranslateVariable;
use enupal\translate\models\Settings;

class Translate extends Plugin
{
    /**
     * Enable use of Translate::$app-> in place of Craft::$app->
     *
     * @var App
     */
    public static $app;

    public $hasCpSection = true;

    public $hasCpSettings = true;

    public $schemaVersion = '1.2.1';

    private function getL2Keys($array)
    {
        $result = array();
        foreach($array as $sub) {
            $result = array_merge($result, $sub);
        }
        return array_values($result);
    }

    public function init()
    {
        parent::init();
        self::$app = $this->get('app');

        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);
        $translations = [];
        $sourceMessages = [];

        $rows = (new Query())
            ->select('message')
            ->from('{{%enupaltranslate_sourcemessage}}')
            ->limit(null)
            ->all();

        $rows = $this->getL2Keys($rows);

        foreach ($siteLocales as $siteLocale) {
            // Determine locale's translation destination file
            $file = self::$app->translate->getSitePath($siteLocale->id);
            // Get current translation
            $current = @include($file);
            if (is_array($current)) {
                $translations[$siteLocale->id] = $current;
                $sourceMessage = array_keys($current);
                $sourceMessages = array_unique(array_merge($sourceMessages, $sourceMessage), SORT_REGULAR);
            }
        }

        $newSources = array_diff($sourceMessages, $rows);

        Craft::dd($newSources);

        $settings = $this->getSettings();

        if ($settings->pluginNameOverride){
            $this->name = $settings->pluginNameOverride;
        }

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('enupaltranslate', TranslateVariable::class);
            }
        );
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    public function getCpNavItem()
    {
        $parent = parent::getCpNavItem();
        return array_merge($parent, [
            'subnav' => [
                'translates' => [
                    "label" => Craft::t('enupal-translate',"Translations"),
                    "url" => 'enupal-translate/index'
                ],
                'settings' => [
                    "label" => Craft::t('enupal-translate',"Settings"),
                    "url" => 'enupal-translate/settings'
                ]
            ]
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate('enupal-translate/settings/index');
    }
}

