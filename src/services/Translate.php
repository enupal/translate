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
use enupal\translate\elements\Translate as TranslateElement;
use Craft;

class Translate extends Component
{
    /**
     * Translate tag finding regular expressions.
     *
     * @var array
     */
    protected $_expressions = array(
        // Expressions for Craft::t() variants
        'php' => array(
            // Single quotes
            '/Craft::(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
            // Double quotes
            '/Craft::(t|translate)\(.*?"(.*?)".*?\,.*?"(.*?)".*?\)/',
        ),

        // Expressions for |t() variants
        'html' => array(
            // Single quotes
            '/(\{\{\s*|\{\%.*?|:\s*)\'(.*?)\'.*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/',
            // Double quotes
            '/(\{\{\s*|\{\%.*?|:\s*)"(.*?)".*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/',
        ),

        // Expressions for |t() variants
        'twig' => array(
            // Single quotes
            '/(\{\{\s*|\{\%.*?|:\s*)\'(.*?)\'.*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/',
            // Double quotes
            '/(\{\{\s*|\{\%.*?|:\s*)"(.*?)".*?\|.*?(t|translate)(\(.*?\)|).*?(\}\}|\%\}|,)/',
        ),

        // Expressions for Craft.t() variants
        'js' => array(
            // Single quotes
            '/Craft\.(t|translate)\(.*?\'(.*?)\'.*?\,.*?\'(.*?)\'.*?\)/',
            // Double quotes
            '/Craft\.(t|translate)\(.*?\"(.*?)\".*?\,.*?"(.*?)".*?\)/',
        ),

    );

    /**
     * Initialize service.
     *
     * @codeCoverageIgnore
     */
    public function init()
    {
        parent::init();

        // Also use html expressions for twig/json/atom/rss templates
        $this->_expressions['twig'] = $this->_expressions['html'];
        $this->_expressions['json'] = $this->_expressions['html'];
        $this->_expressions['atom'] = $this->_expressions['html'];
        $this->_expressions['rss'] = $this->_expressions['html'];
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
    }

    /**
     * Get translations by criteria.
     *
     * @param ElementQueryInterface $criteria
     *
     * @return array
     */
    public function get(ElementQueryInterface $criteria)
    {
        // Ensure source is an array
        if (!is_array($criteria->source)) {
            $criteria->source = [$criteria->source];
        }

        // Gather all translatable strings
        $occurences = [];

        // Loop through paths
        foreach ($criteria->source as $path) {

            // Check if this is a folder or a file
            $isDir = is_dir($path);

            // If its not a file
            if ($isDir) {

                // Set filter - no vendor folders, only template files
                #$filter = '^((?!vendor|node_modules).)*(\.(php|html|twig|js|json|atom|rss)?)$';

                // Get files
                $options = [
                    'recursive' => true,
                    'only' => ['*.php','*.html','*.twig','*.js','*.json','*.atom','*.rss'],
                    #'except' => 'vendor|node_modules'
                ];

                $files = FileHelper::findFiles($path, $options);

                // Loop through files and find translate occurences
                foreach ($files as $file) {

                    // Parse file
                    $elements = $this->_parseFile($path, $file, $criteria);

                    // Collect in array
                    $occurences = array_merge($occurences, $elements);
                }
            } elseif (file_exists($path)) {

                // Parse file
                $elements = $this->_parseFile($path, $path, $criteria);

                // Collect in array
                $occurences = array_merge($occurences, $elements);
            }
        }

        return $occurences;
    }

    /**
     * Open file and parse translate tags.
     *
     * @param string               $path
     * @param string               $file
     * @param ElementQueryInterface $criteria
     *
     * @return array
     */
    protected function _parseFile($path, $file, ElementQueryInterface $criteria)
    {
        // Collect matches in file
        $occurences = array();

        // Get file contents
        $contents = file_get_contents($file);

        // Get extension
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        // Get matches per extension
        foreach ($this->_expressions[$extension] as $regex) {

            // Match translation functions
            if (preg_match_all($regex, $contents, $matches)) {

                // Collect
                foreach ($matches[2] as $original) {

                    // Translate
                    $site = Craft::$app->getSites()->getSiteById($criteria->siteId);
                    $translation = Craft::t('enupal-translate', $original, null, $site->language);

                    // Show translation in textfield
                    $view = Craft::$app->getView();

                    $field = $view->renderTemplate('_includes/forms/text', [
                        'id' => ElementHelper::createSlug($original),
                        'name' => 'translation['.$original.']',
                        'value' => $translation,
                        'placeholder' => $translation,
                    ]);

                    // Fill element with translation data
                    $element = new TranslateElement([
                        'id' => ElementHelper::createSlug($original),
                        'original' => $original,
                        'translation' => $translation,
                        'source' => $path,
                        'file' => $file,
                        'siteId' => $criteria->siteId,
                        'field' => $field,
                    ]);

                    // If searching, only return matches
                   # if ($criteria->search && !stristr($element->original, $criteria->search) && !stristr($element->translation, $criteria->search)) {
                   #     continue;
                   # }

                    // If wanting one status, ditch the rest
                    if ($criteria->status && $criteria->status != $element->getStatus()) {
                        continue;
                    }

                    // Collect in array
                    $occurences[] = $element;
                }
            }
        }

        // Return occurences
        return $occurences;
    }
}
