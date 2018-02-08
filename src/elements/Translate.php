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
        return Craft::t('enupal-translate','enupal-translate','Translations');
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
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

    /**
     * Define available table column names.
     *
     * @return array
     */
    public function defineAvailableTableAttributes()
    {
        return [
            'original' => ['label' => Craft::t('enupal-translate','Original')],
            'field' => ['label' => Craft::t('enupal-translate','Translation')],
        ];
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
        // Get plugin sources
        $sources = [
            [
                'key'   => '*',
                'label' => Craft::t('enupal-translate','All Translations'),
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
     * Return the html.
     *
     * @param array  $criteria
     * @param array  $disabledElementIds
     * @param array  $viewState
     * @param string $sourceKey
     * @param string $context
     * @param bool   $includeContainer
     * @param bool   $showCheckboxes
     *
     * @return string

    public function getIndexHtml($criteria, $disabledElementIds, $viewState, $sourceKey, $context, $includeContainer, $showCheckboxes)
    {
        // If the site only has 1 locale enabled, set the translated locale to the primary (and only) locale
        if (empty($criteria['locale'])) {
            $criteria['locale'] = craft()->i18n->getPrimarySiteLocale();
        }

        $variables = array(
            'viewMode' => $viewState['mode'],
            'context' => $context,
            'elementType' => new ElementTypeVariable($this),
            'disabledElementIds' => $disabledElementIds,
            'attributes' => $this->getTableAttributesForSource($sourceKey),
            'elements' => craft()->translate->get($criteria),
            'showCheckboxes' => $showCheckboxes,
        );

        // Inject some custom js also
        craft()->templates->includeJs("$('table.fullwidth thead th').css('width', '50%');");
        craft()->templates->includeJs("$('.buttons.hidden').removeClass('hidden');");

        $template = '_elements/'.$viewState['mode'].'view/'.($includeContainer ? 'container' : 'elements');

        return craft()->templates->render($template, $variables);
    }
     */
}
