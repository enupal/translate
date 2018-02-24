<?php

namespace enupal\translate\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use enupal\translate\Translate as TranslatePlugin;

class GoogleTranslate extends ElementAction
{
    // Properties
    // =========================================================================

    /**
     * @var string|null The confirmation message that should be shown before the elements get deleted
     */
    public $confirmationMessage;

    /**
     * @var string|null The message that should be shown after the elements get deleted
     */
    public $successMessage;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('enupal-translate', 'Google Translate (Free)');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $site = Craft::$app->getSites()->getSiteById($query->siteId);

        $elements = $elements = TranslatePlugin::$app->translate->get($query);

        // prepare for google
        $texts = [];
        foreach ($elements as $element) {
            $texts[] = $element->original;
        }

        $results = TranslatePlugin::$app->translate->googleTranslate($texts, $site->language);

        if (!$results){
            $message = Craft::t('enupal-translate','Api error - Please check your logs');
            $this->setMessage($message);
            return false;
        }

        $translations = $results;
        $enupalTranslations = [];
        $pos = 0;
        foreach($elements as $element) {
            $enupalTranslations[$element->original] = $translations[$pos];
            $pos++;
        }

        $translatePluginPath = TranslatePlugin::$app->translate->getPluginPath($query, $site->language) ?? null;
        // Save to translation file
        $response = TranslatePlugin::$app->translate->set($site->language, $enupalTranslations, $translatePluginPath);

        $message = TranslatePlugin::$app->translate->getSuccessMessage(count($enupalTranslations));

        if (!$response) {
            $message = Craft::t('enupal-translate','Something went wrong');
        }

        $this->setMessage($message);

        return true;
    }
}
