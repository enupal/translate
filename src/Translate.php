<?php
/**
 * Snapshot plugin for Craft CMS 3.x
 *
 * Snapshot or PDF generation from a url or a html page.
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate;

use Craft;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;

use enupal\translate\variables\TranslateVariable;
use enupal\translate\models\Settings;

class Translate extends Plugin
{
    /**
     * Enable use of Translate::$app-> in place of Craft::$app->
     *
     * @var [type]
     */
    public static $app;

    public $hasCpSection = true;

    public function init()
    {
        parent::init();
        self::$app = $this->get('app');

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, $this->getCpUrlRules());
        }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_DEFINE_COMPONENTS,
            function(DefineComponentsEvent $event) {
                $event->components['enupaltranslate'] = TranslateVariable::class;
            }
        );
    }

    protected function afterInstall()
    {
        //self::$app->translates->installDefaultValues();
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
                    "label" => Translate::t("Translates"),
                    "url" => 'enupal-translate/translates'
                ],
                'settings' => [
                    "label" => Translate::t("Settings"),
                    "url" => 'enupal-translate/settings'
                ]
            ]
        ]);
    }

    public static function log($message, $type = 'info')
    {
        Craft::$type(self::t($message), __METHOD__);
    }

    public static function info($message)
    {
        Craft::info(self::t($message), __METHOD__);
    }

    public static function error($message)
    {
        Craft::error(self::t($message), __METHOD__);
    }

    /**
     * @return array
     */
    private function getCpUrlRules()
    {
        return [
            'enupal-translate/run' =>
                'enupal-translate/translates/run',

            'enupal-translate' =>
                'enupal-translate/translates/index',

            'enupal-translate/translate/new' =>
                'enupal-translate/translates/edit-translate',

            'enupal-translate/translate/edit/<translateId:\d+>' =>
                'enupal-translate/translates/edit-translate',
        ];
    }
}

