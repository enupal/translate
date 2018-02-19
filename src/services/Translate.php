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
use craft\base\Component;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ElementHelper;
use craft\helpers\FileHelper;
use enupal\translate\contracts\GoogleCloudTranslate;
use enupal\translate\contracts\GoogleTranslate;
use enupal\translate\contracts\Yandex;
use enupal\translate\elements\Translate as TranslateElement;
use Craft;

class Translate extends Component
{
    /**
     * Translate REGEX.
     * @credits to boboldehampsink
     * @var []
     */
    private $_expressions = array(
        // Regex for Craft::t('category', '..')
        'php' => array(
            // Single quotes
            '/Craft::(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
            // Double quotes
            '/Craft::(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
        ),

        // Regex for |t('category')
        'twig' => array(
            // Single quotes
            '/(\{\{\s*|\{\%.*?|:\s*)\'(.*?)\'.*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/',
            // Double quotes
            '/(\{\{\s*|\{\%.*?|:\s*)"(.*?)".*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/',
        ),

        // Regex for Craft.t('category', '..')
        'js' => array(
            // Single quotes
            '/Craft\.(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
            // Double quotes
            '/Craft\.(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
        )
    );

    /**
     * Initialize service.
     *
     * @codeCoverageIgnore
     */
    public function init()
    {
        parent::init();

        $this->_expressions['html'] = $this->_expressions['twig'];
        $this->_expressions['json'] = $this->_expressions['twig'];
        $this->_expressions['atom'] = $this->_expressions['twig'];
        $this->_expressions['rss'] = $this->_expressions['twig'];
    }

    /**
     * Set translations.
     *
     * @param string $locale
     * @param array  $translations
     *
     * @throws Exception if unable to write to file
     * @throws \yii\base\ErrorException
     * @throws \Exception
     */
    public function set($locale, array $translations)
    {
        // Determine locale's translation destination file
        $file = Craft::getAlias('@enupal/translate/translations/'.$locale.'/enupal-translate.php');

        // Get current translation
        if ($current = @include($file)) {
            $translations = array_merge($current, $translations);
        }

        // Prepare php file
        $php = "<?php\r\n\r\nreturn ";

        // Get translations as php
        $php .= var_export($translations, true);

        // End php file
        $php .= ';';

        // Convert double space to tab (as in Craft's own translation files)
        $php = str_replace("  '", "\t'", $php);

        // Save code to file
        try {
            FileHelper::writeToFile($file, $php);
        }catch (\Throwable $e) {
            throw new \Exception(Craft::t('enupal-translate','Something went wrong while saving your translations: '.$e->getMessage()));
        }

        return true;
    }

    /**
     * Get translations by Element Query.
     *
     * @param ElementQueryInterface $query
     *
     * @return array
     */
    public function get(ElementQueryInterface $query)
    {
        if (!is_array($query->source)) {
            $query->source = [$query->source];
        }

        $translations = [];

        // Loop through paths
        foreach ($query->source as $path) {
            // Check if this is a folder or a file
            $isDir = is_dir($path);

            if ($isDir) {
                $options = [
                    'recursive' => true,
                    'only' => ['*.php','*.html','*.twig','*.js','*.json','*.atom','*.rss'],
                    'except' => ['vendor/', 'node_modules/']
                ];

                $files = FileHelper::findFiles($path, $options);

                // Loop through files and find translate occurences
                foreach ($files as $file) {

                    // Parse file
                    $elements = $this->_processFile($path, $file, $query);

                    // Collect in array
                    $translations = array_merge($translations, $elements);
                }
            } elseif (file_exists($path)) {

                // Parse file
                $elements = $this->_processFile($path, $path, $query);

                // Collect in array
                $translations = array_merge($translations, $elements);
            }
        }

        return $translations;
    }

    /**
     * Apply regex search into file
     *
     * @param string                $path
     * @param string                $file
     * @param ElementQueryInterface $query
     *
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \yii\base\Exception
     */
    private function _processFile($path, $file, ElementQueryInterface $query)
    {
        $translations = array();
        $contents     = file_get_contents($file);
        $extension    = pathinfo($file, PATHINFO_EXTENSION);

        // Process the file
        foreach ($this->_expressions[$extension] as $regex) {
            // Do it!
            if (preg_match_all($regex, $contents, $matches)) {
                $pos = 2;
                // Js and php files goes to 3
                if ($extension == 'js' || $extension == 'php'){
                    $pos = 3;
                }
                foreach ($matches[$pos] as $original) {
                    // Apply the Craft Translate
                    $site = Craft::$app->getSites()->getSiteById($query->siteId);
                    $translation = Craft::t('enupal-translate', $original, null, $site->language);

                    $view = Craft::$app->getView();
                    $elementId = ElementHelper::createSlug($original);

                    $field = $view->renderTemplate('_includes/forms/text', [
                        'id' => $elementId,
                        'name' => 'translation['.$original.']',
                        'value' => $translation,
                        'placeholder' => $translation,
                    ]);

                    // Let's create our translate element with all the info
                    $element = new TranslateElement([
                        'id' => ElementHelper::createSlug($original),
                        'original' => $original,
                        'translation' => $translation,
                        'source' => $path,
                        'file' => $file,
                        'siteId' => $query->siteId,
                        'field' => $field,
                    ]);

                    // Continue when Searching
                    if ($query->search && !stristr($element->original, $query->search) && !stristr($element->translation, $query->search)) {
                        continue;
                   }
                    // Continue when filter by status
                    if ($query->status && $query->status != $element->getStatus()) {
                        continue;
                    }
                    // add actions occurrences
                    if ($query->id)
                    {
                        foreach ($query->id as $id) {
                            if ($element->id == $id) {
                                $translations[$element->original] = $element;
                            }
                        }
                    }
                    else{
                        $translations[$element->original] = $element;
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * @param      $text
     * @param      $language
     * @param null $from
     *
     * @return bool|object
     * @throws \craft\errors\SiteNotFoundException
     */
    public function yandexTranslate($text, $language, $from = null)
    {
        // @todo - add a setting to select the primary site
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $from = $from ?? $primarySite->language;
        $language = $from.'-'.$language;
        $yandex = new Yandex();
        $result = $yandex->translate($text, $language);

        return $result;
    }

    /**
     * @param      $text
     * @param      $language
     * @param null $from
     *
     * @return bool|object
     * @throws \craft\errors\SiteNotFoundException
     */
    public function googleTranslate($text, $language, $from = null)
    {
        // @todo - add a setting to select the primary site
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $from = $from ?? $primarySite->language;
        $googleTranslate = new GoogleTranslate();
        $result = $googleTranslate->translate($text, $from, $language);

        return $result;
    }

    /**
     * @param      $text
     * @param      $language
     * @param null $from
     *
     * @return bool|object
     * @throws \craft\errors\SiteNotFoundException
     */
    public function googleCloudTranslate($text, $language, $from = null)
    {
        // @todo - add a setting to select the primary site
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $from = $from ?? $primarySite->language;
        $googleTranslate = new GoogleCloudTranslate();
        $result = $googleTranslate->translate($text, $from, $language);

        return $result;
    }

    /**
     * @return \craft\base\Model|null
     */
    public function getPluginSettings()
    {
        $plugin = Craft::$app->getPlugins()->getPlugin('enupal-translate');

        return $plugin->getSettings();
    }
}
