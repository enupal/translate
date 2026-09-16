<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * Translate your website templates, plugins and content into multiple
 * languages. Bulk translation with Google, Yandex, OpenAI or Claude.
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use enupal\translate\elements\actions\TranslateContent;
use enupal\translate\models\Settings;
use enupal\translate\services\App;
use enupal\translate\variables\TranslateVariable;
use yii\base\Event;

class Translate extends Plugin
{
    /**
     * Enable use of Translate::$app-> in place of Craft::$app->
     *
     * @var App
     */
    public static $app;

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    public string $schemaVersion = '4.0.0';

    public function init()
    {
        parent::init();

        self::$app = $this->get('app');

        $settings = $this->getSettings();

        if ($settings->pluginNameOverride) {
            $this->name = $settings->pluginNameOverride;
        }

        $this->registerVariables();
        $this->registerCpRoutes();
        $this->registerPermissions();
        $this->registerContentTranslationActions();
    }

    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    public function getCpNavItem(): ?array
    {
        $parent = parent::getCpNavItem();

        return array_merge($parent, [
            'subnav' => [
                'translates' => [
                    'label' => Craft::t('enupal-translate', 'Translations'),
                    'url' => 'enupal-translate/index',
                ],
                'dashboard' => [
                    'label' => Craft::t('enupal-translate', 'Dashboard'),
                    'url' => 'enupal-translate/dashboard',
                ],
                'settings' => [
                    'label' => Craft::t('enupal-translate', 'Settings'),
                    'url' => 'enupal-translate/settings',
                ],
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('enupal-translate/settings/index');
    }

    private function registerVariables(): void
    {
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

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function (RegisterUrlRulesEvent $event) {
                $event->rules['enupal-translate/dashboard'] = 'enupal-translate/dashboard/index';
            }
        );
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('enupal-translate', 'Enupal Translate'),
                    'permissions' => [
                        'enupal-translate:translateContent' => [
                            'label' => Craft::t('enupal-translate', 'Translate element content'),
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * Add the bulk "Translate to" action to the element indexes that can
     * usefully be translated.
     */
    private function registerContentTranslationActions(): void
    {
        if (!$this->getSettings()->enableContentTranslation) {
            return;
        }

        $elementTypes = [
            Entry::class,
            Asset::class,
            'craft\commerce\elements\Product',
            'craft\elements\Category',
        ];

        foreach ($elementTypes as $elementType) {
            if (!class_exists($elementType)) {
                continue;
            }

            Event::on(
                $elementType,
                Element::EVENT_REGISTER_ACTIONS,
                static function (RegisterElementActionsEvent $event) {
                    // Pointless with a single site, and noisy in the UI.
                    if (count(Craft::$app->getSites()->getAllSites()) < 2) {
                        return;
                    }

                    $event->actions[] = TranslateContent::class;
                }
            );
        }
    }
}
