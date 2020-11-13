<?php

namespace enupal\translate\jobs;

use craft\db\Query;
use enupal\translate\Translate as TranslatePlugin;
use craft\queue\BaseJob;
use Craft;

class SyncTranslationsWithDb extends BaseJob
{
    public $translations;
    public $language;

    /**
     * Returns the default description for this job.
     *
     * @return string
     */
    protected function defaultDescription(): string
    {
        return StripePlugin::t('Syncing translations with DB');
    }

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);
        $translations = [];
        $sourceMessages = [];

        foreach ($siteLocales as $siteLocale) {
            // Determine locale's translation destination file
            $file = TranslatePlugin::$app->translate->getSitePath($siteLocale->id);
            // Get current translation
            $current = @include($file);
            if (is_array($current)) {
                $translations[$siteLocale->id] = $current;
                $sourceMessage = array_keys($current);
                $sourceMessages = array_merge($sourceMessages, $sourceMessage);
            }
        }

        $this->setProgress($queue, $step / $totalSteps);

        return true;
    }

    private function getUsersByUserGroupId()
    {
        $settings = StripePlugin::$app->settings->getSettings();

        if (!$settings->vendorUserGroupId) {
            return [];
        }

        $userQuery = User::find();

        $userQuery->innerJoin('{{%usergroups_users}} usergroups_users', '[[usergroups_users.userId]] = [[users.id]]');
        $userQuery->andWhere(['usergroups_users.groupId' => (int)$settings->vendorUserGroupId]);

        return $userQuery->all();
    }

    private function getUsersByUserFieldId()
    {
        $settings = StripePlugin::$app->settings->getSettings();

        if (!$settings->vendorUserFieldId) {
            return [];
        }

        $field = (new Query())
            ->select(['handle'])
            ->from(['{{%fields}}'])
            ->andWhere(['id' => (int)$settings->vendorUserFieldId])
            ->one();

        $handle = $field['handle'] ?? null;

        $users = User::findAll([$handle => true]);

        return $users;
    }
}