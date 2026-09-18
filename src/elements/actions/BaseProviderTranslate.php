<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use enupal\translate\jobs\TranslateStaticJob;
use enupal\translate\Translate as TranslatePlugin;
use Throwable;

/**
 * Bulk-translates the selected static strings with one provider.
 *
 * Craft matches a posted element action by class name, so each provider gets
 * its own thin subclass rather than one class carrying a handle.
 */
abstract class BaseProviderTranslate extends ElementAction
{
    /**
     * @var string|null
     */
    public $confirmationMessage;

    /**
     * @var string|null
     */
    public $successMessage;

    /**
     * Handle of the provider this action runs.
     */
    abstract public static function providerHandle(): string;

    public function getTriggerLabel(): string
    {
        $provider = TranslatePlugin::$app->providers->getProviderByHandle(static::providerHandle());

        return Craft::t('enupal-translate', 'Translate with {provider}', [
            'provider' => $provider ? $provider::displayName() : static::providerHandle(),
        ]);
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $site = Craft::$app->getSites()->getSiteById($query->siteId);

        if ($site === null) {
            $this->setMessage(Craft::t('enupal-translate', 'Could not determine the target site.'));
            return false;
        }

        $elements = TranslatePlugin::$app->translate->get($query);

        $originals = [];
        foreach ($elements as $element) {
            $originals[] = $element->original;
        }

        if (empty($originals)) {
            $this->setMessage(Craft::t('enupal-translate', 'Nothing to translate.'));
            return false;
        }

        $translatePath = TranslatePlugin::$app->translate->getPluginPath($query, $site->language);
        $threshold = (int)TranslatePlugin::$app->settings->getSettings()->staticQueueThreshold;

        // Large batches can easily outlive a web request, so hand them to the
        // queue and let the user carry on.
        if ($threshold > 0 && count($originals) > $threshold) {
            Craft::$app->getQueue()->push(new TranslateStaticJob([
                'originals' => $originals,
                'providerHandle' => static::providerHandle(),
                'siteId' => $site->id,
                'translatePath' => $translatePath,
            ]));

            $this->setMessage(Craft::t('enupal-translate', '{count} translations were added to the queue.', [
                'count' => count($originals),
            ]));

            return true;
        }

        try {
            $result = TranslatePlugin::$app->translate->translateStatic(
                $originals,
                static::providerHandle(),
                $site,
                $translatePath
            );
        } catch (Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            $this->setMessage($e->getMessage());

            return false;
        }

        if (!$result->success && empty($result->translations)) {
            $this->setMessage($result->errorMessage ?: Craft::t('enupal-translate', 'API error — please check your logs.'));

            return false;
        }

        $this->setMessage(TranslatePlugin::$app->translate->getSuccessMessage(count($result->translations)));

        return true;
    }
}
