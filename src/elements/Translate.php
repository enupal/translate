<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use enupal\translate\elements\actions\GoogleCloudTranslate;
use enupal\translate\elements\actions\GoogleTranslate;
use enupal\translate\elements\actions\Yandex;
use enupal\translate\Translate as TranslatePlugin;
use enupal\translate\elements\db\TranslateQuery;
use craft\helpers\FileHelper;

class Translate extends Element
{
    /**
     * Status constants.
     */
    const TRANSLATED = 'live';
    const PENDING = 'pending';

    public $original;
    public $translation;
    public $source;
    public $file;
    public $locale = 'en_us';
    public $field;
    public $translateStatus;

    /**
     * Return element type name.
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('enupal-translate','Translations');
    }

    /**
     * Use the name as the string representation.
     *
     * @return string
     */
    /** @noinspection PhpInconsistentReturnPointsInspection */
    public function __toString()
    {
        try
        {
            // @todo - For some reason the Title returns null possible Craft3 bug
            return $this->original;
        } catch (\Exception $e) {
            ErrorHandler::convertExceptionToError($e);
        }
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * Define statuses.
     *
     * @return array
     */
    public static function statuses(): array
    {
        return [
            self::TRANSLATED => Craft::t('enupal-translate','Done'),
            self::PENDING => Craft::t('enupal-translate','Pending'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        if ($this->original != $this->translation) {
            return static::TRANSLATED;
        }

        return static::PENDING;
    }

    public static function find(): ElementQueryInterface
    {
        return new TranslateQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes['original'] = ['label' => Craft::t('enupal-translate','Original')];
        $attributes['field']     = ['label' => Craft::t('enupal-translate','Field')];

        return $attributes;
    }

    /**
     * Returns the default table attributes.
     *
     * @param string $source
     *
     * @return array
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['original', 'field'];
    }

    /**
     * Don't encode the attribute html.
     *
     * @param string           $attribute
     *
     * @return string
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        return $this->$attribute;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return [
            'original',
            'translation',
            'source',
            'file',
            'status',
            'locale'
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [];

        $sources[] = ['heading' => Craft::t('enupal-translate','Template Status')];

        $key = 'status:' . self::PENDING;
        $sources[] = [
            'status'   => self::PENDING,
            'key'      => $key,
            'label'    => Craft::t('enupal-translate', 'Pending'),
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath()
                ],
                'translateStatus' => self::PENDING
            ],
        ];

        $key = 'status:' . self::TRANSLATED;
        $sources[] = [
            'status'   => self::TRANSLATED,
            'key'      => $key,
            'label'    => Craft::t('enupal-translate', 'Translated'),
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath()
                ],
                'translateStatus' => self::TRANSLATED
            ],
        ];

        $pluginSources = array();
        $plugins = Craft::$app->plugins->getAllPlugins();
        foreach ($plugins as $path => $plugin) {
            $pluginSources['plugins:'.$path] = [
                'label' => $plugin->name,
                'key' => 'plugins:'.$plugin->getHandle(),
                'criteria' => [
                    'source' => [
                        $plugin->getBasePath()
                    ],
                ],
            ];
        }

        // Get template sources
        $templateSources = array();
        $options = [
            'recursive' => true,
            'only' => ['*.html','*.twig','*.js','*.json','*.atom','*.rss'],
            #'except' => 'vendor|node_modules'
        ];
        $templates = FileHelper::findFiles(Craft::$app->path->getSiteTemplatesPath(), $options);
        foreach ($templates as $template) {
            // If matches, get template name
            $fileName = basename($template);
            // Add template source
            $templateSources['templates:'.$fileName] = [
                'label' => $fileName,
                'key' => 'templates:'.$template,
                'criteria' => [
                    'source' => [
                        $template
                    ],
                ],
            ];
        }

        $sources[] = ['heading' => Craft::t('enupal-translate','Default')];

        $sources[] = [
            'label'    => Craft::t('enupal-translate', 'Templates'),
            'key'      => 'templates',
            'criteria' => [
                'source' => [
                    Craft::$app->path->getSiteTemplatesPath()
                ]
            ],
            'nested' => $templateSources
        ];

        $sources[] = [
            'label'    => Craft::t('enupal-translate', 'Plugins'),
            'key' => 'plugins',
            'criteria' => [
                'source' => [
                ],
            ],
            'nested' => $pluginSources
        ];

        // @todo add hook

        // Return sources
        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function indexHtml(ElementQueryInterface $elementQuery, array $disabledElementIds = null, array $viewState, string $sourceKey = null, string $context = null, bool $includeContainer, bool $showCheckboxes): string
    {
        // just 1 locale enabled
        if (empty($elementQuery->siteId)) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $elementQuery->siteId = $primarySite->id;
        }

        if ($elementQuery->translateStatus) {
            $elementQuery->status = $elementQuery->translateStatus;
        }

        $elements = TranslatePlugin::$app->translate->get($elementQuery);

        $variables = [
            'viewMode' => $viewState['mode'],
            'context' => $context,
            'disabledElementIds' => $disabledElementIds,
            'attributes' => Craft::$app->getElementIndexes()->getTableAttributes(static::class, $sourceKey),
            'elements' => $elements,
            'showCheckboxes' => $showCheckboxes
        ];

        // Better UI
        Craft::$app->view->registerJs("$('table.fullwidth thead th').css('width', '50%');");
        Craft::$app->view->registerJs("$('.buttons.hidden').removeClass('hidden');");

        $template = '_elements/'.$viewState['mode'].'view/'.($includeContainer ? 'container' : 'elements');

        return Craft::$app->view->renderTemplate($template, $variables);
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $settings = TranslatePlugin::$app->translate->getPluginSettings();

        if ($settings->enableYandex && $settings->yandexApi){
            // Yandex
            $actions[] = Craft::$app->getElements()->createAction([
                'type' => Yandex::class,
            ]);
        }

        if ($settings->enableGoogleApi && $settings->googleApi){
            // Google Cloud Translate
            $actions[] = Craft::$app->getElements()->createAction([
                'type' => GoogleCloudTranslate::class,
            ]);
        }

        if ($settings->enableFreeGoogleApi) {
            // Google Translate Free
            $actions[] = Craft::$app->getElements()->createAction([
                'type' => GoogleTranslate::class,
            ]);
        }

        return $actions;
    }

    public function getLocale()
    {
        $site = Craft::$app->getSites()->getSiteById($this->siteId);

        return $site->language;
    }
}
