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
use enupal\translate\Translate as TranslatePlugin;
use enupal\translate\elements\db\TranslateQuery;

class Translate extends Element
{
    /**
     * Status constants.
     */
    const DONE = 'live';
    const PENDING = 'pending';

    public $original;
    public $translation;
    public $source;
    public $file;
    public $locale = 'en_us';
    public $field;

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
            self::DONE => Craft::t('enupal-translate','Done'),
            self::PENDING => Craft::t('enupal-translate','Pending'),
        ];
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
        $test = $this->$attribute;

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
        // Get plugin sources

        $sources = [
            [
                'key'   => '*',
                'label' => Craft::t('enupal-translate','All Translations'),
                'criteria' => [
                    'source' => [
                        //Craft::$app->path->getPluginsPath(),
                        Craft::$app->path->getSiteTemplatesPath(),
                    ],
                ],
            ]
        ];
        /*
        $pluginSources = array();
        $plugins = craft()->plugins->getPlugins();
        foreach ($plugins as $path => $plugin) {
            $pluginSources['plugins:'.$path] = array(
                'label' => $plugin->classHandle,
                'criteria' => array(
                    'source' => craft()->path->getPluginsPath().$path,
                ),
            );
        }

        // Get template sources
        $templateSources = array();
        $templates = IOHelper::getFolderContents(craft()->path->getSiteTemplatesPath(), false);
        foreach ($templates as $template) {

            // Get path/name of template files and folders
            if (preg_match('/(.*)\/(.*?)(\.(html|twig|js|json|atom|rss)|\/)$/', $template, $matches)) {

                // If matches, get template name
                $path = $matches[2];

                // Add template source
                $templateSources['templates:'.$path] = array(
                    'label' => $path,
                    'criteria' => array(
                        'source' => $template,
                    ),
                );
            }
        }

        // Get default sources
        $sources = array(
            '*' => array(
                'label' => Craft::t('enupal-translate','All translations'),
                'criteria' => array(
                    'source' => array(
                        craft()->path->getPluginsPath(),
                        craft()->path->getSiteTemplatesPath(),
                    ),
                ),
            ),
            array('heading' => Craft::t('enupal-translate','Default')),
            'plugins' => array(
                'label' => Craft::t('enupal-translate','Plugins'),
                'criteria' => array(
                    'source' => craft()->path->getPluginsPath(),
                ),
                'nested' => $pluginSources,
            ),
            'templates' => array(
                'label' => Craft::t('enupal-translate','Templates'),
                'criteria' => array(
                    'source' => craft()->path->getSiteTemplatesPath(),
                ),
                'nested' => $templateSources,
            ),
        );

        // Get sources by hook
        $plugins = craft()->plugins->call('registerTranslateSources');
        if (count($plugins)) {
            $sources[] = array('heading' => Craft::t('enupal-translate','Custom'));
            foreach ($plugins as $plugin) {

                // Add as own source
                $sources = array_merge($sources, $plugin);

                // Add to "All translations"
                foreach ($plugin as $key => $values) {
                    $sources['*']['criteria']['source'][] = $values['criteria']['source'];
                }
            }
        }
        */
        // Return sources
        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function indexHtml(ElementQueryInterface $elementQuery, array $disabledElementIds = null, array $viewState, string $sourceKey = null, string $context = null, bool $includeContainer, bool $showCheckboxes): string
    {
        // If the site only has 1 locale enabled, set the translated locale to the primary (and only) locale
        if (empty($elementQuery->siteId)) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $elementQuery->siteId = $primarySite->id;
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

        // Inject some custom js also
        Craft::$app->view->registerJs("$('table.fullwidth thead th').css('width', '50%');");
        Craft::$app->view->registerJs("$('.buttons.hidden').removeClass('hidden');");

        $template = '_elements/'.$viewState['mode'].'view/'.($includeContainer ? 'container' : 'elements');

        return Craft::$app->view->renderTemplate($template, $variables);
    }
}
