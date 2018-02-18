<?php

namespace enupal\translate\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use enupal\translate\Translate as TranslatePlugin;

class Yandex extends ElementAction
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
        return Craft::t('enupal-translate', 'Translate with Yandex');
    }

    /**
     * @inheritdoc
     */
    public static function isDestructive(): bool
    {
        return false;
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

        // prepare for yandex
        $texts = [];
        foreach ($elements as $element) {
            $texts[] = $element->original;
        }

        $results = TranslatePlugin::$app->translate->translateWithYandex($texts, $site->language);

        if (!$results){
            $message = Craft::t('enupal-translate','Api error - Please check your logs');
            $this->setMessage($message);
            return false;
        }

        $translations = $results->translation();
        $enupalTranslations = [];
        $pos = 0;
        foreach($elements as $element) {
            $enupalTranslations[$element->original] = $translations[$pos];
            $pos++;
        }
        // Save to translation file
        $response = TranslatePlugin::$app->translate->set($site->language, $enupalTranslations);

        $message = Craft::t('enupal-translate','{total} Translations have been saved successfully.', ['total' => count($enupalTranslations)]);

        if (!$response) {
            $message = Craft::t('enupal-translate','Something went wrong');
        }

        $this->setMessage($message);

        return true;
    }
}
