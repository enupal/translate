<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate;

use Craft;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use craft\events\DefineComponentsEvent;
use yii\base\Event;

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

    public $hasCpSettings = true;

    public function init()
    {
        parent::init();
        self::$app = $this->get('app');

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
                    "label" => Craft::t('enupal-translate',"Translates"),
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

