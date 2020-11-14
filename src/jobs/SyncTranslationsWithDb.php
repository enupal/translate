<?php

namespace enupal\translate\jobs;

use craft\db\Query;
use enupal\translate\Translate as TranslatePlugin;
use craft\queue\BaseJob;
use Craft;

class SyncTranslationsWithDb extends BaseJob
{
    /**
     * Returns the default description for this job.
     *
     * @return string
     */
    protected function defaultDescription(): string
    {
        return Craft::t('enupal-translate','Syncing translations with DB');
    }

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $this->setProgress($queue, 1/4);
        $sourceMessageTable = "{{%enupaltranslate_sourcemessage}}";
        $messageTable = "{{%enupaltranslate_message}}";
        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);
        $translations = [];
        $sourceMessages = [];

        $rows = (new Query())
            ->select('message')
            ->from($sourceMessageTable)
            ->limit(null)
            ->all();

        $rows = $this->getLevel2Keys($rows);

        foreach ($siteLocales as $siteLocale) {
            // Determine locale's translation destination file
            $file = TranslatePlugin::$app->translate->getSitePath($siteLocale->id);
            // Get current translation
            $current = @include($file);
            if (is_array($current)) {
                $translations[$siteLocale->id] = $current;
                $sourceMessage = array_keys($current);
                $sourceMessages = array_unique(array_merge($sourceMessages, $sourceMessage), SORT_REGULAR);
            }
        }

        $newSources = array_diff($sourceMessages, $rows);
        $rowsToInsert = [];
        foreach ($newSources as $newSource) {
            $rowToInsert = [
                $newSource,
                'site'
            ];
            $rowsToInsert[] = $rowToInsert;
        }
        $connection = Craft::$app->getDb();
        $this->setProgress($queue, 2/4);

        $totalRows = $connection->createCommand()->batchInsert($sourceMessageTable, ['message', 'category'], $rowsToInsert)->execute();
        Craft::info('Added '.$totalRows.' new rows to the enupaltranslate_sourcemessage table', __METHOD__);

        $rows = (new Query())
            ->select(['message', 'id'])
            ->from($sourceMessageTable)
            ->limit(null)
            ->all();

        $sourceMessagesHashMap = [];
        foreach ($rows as $row) {
            $sourceMessagesHashMap[$row['message']] = $row['id'];
        }

        foreach ($siteLocales as $siteLocale) {
            if (!isset($translations[$siteLocale->id])) {
                continue;
            }
            $translationsByLanguage = $translations[$siteLocale->id];
            foreach ($translationsByLanguage as $sourceMessage => $translation) {
                if(!isset($sourceMessagesHashMap[$sourceMessage])) {
                    Craft::error('A source message was not found in the db: '.$sourceMessage, __METHOD__);
                    continue;
                }
                $rowToInsert = [
                    'id' => $sourceMessagesHashMap[$sourceMessage],
                    'translation' => $translation,
                    'language' => $siteLocale->id
                ];
                $connection->createCommand()->upsert($messageTable, $rowToInsert)->execute();
            }
        }
        $this->setProgress($queue, 3/4);

        // SYNC THE DATABASE OVER FILES
        $rows = (new Query())
            ->select(['message', 'translation' , 'language'])
            ->innerJoin('{{%enupaltranslate_message}} AS message', 'sourceMessage.id = message.id')
            ->from('{{%enupaltranslate_sourcemessage}} AS sourceMessage')
            ->limit(null)
            ->all();

        $finalTranslations = [];
        foreach ($rows as $row) {
            $finalTranslations[$row['language']][$row['message']] = $row['translation'];
        }

        foreach ($finalTranslations as $language => $finalTranslationsByLanguage) {
            $file = TranslatePlugin::$app->translate->getSitePath($language);
            // Get current translation
            $current = @include($file);
            if (is_array($current)) {
                $finalTranslationsByLanguage = array_merge($finalTranslationsByLanguage, $current);
            }
            TranslatePlugin::$app->translate->writeToFile($finalTranslationsByLanguage, $file);
        }

        $this->setProgress($queue, 1);

        return true;
    }

    private function getLevel2Keys($array)
    {
        $result = [];
        foreach($array as $key => $sub) {
            $result[] = $sub['message'];
        }

        return $result;
    }
}