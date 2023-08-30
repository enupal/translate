<?php

namespace enupal\translate\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use enupal\translate\Translate;
use yii\console\ExitCode;
use Craft;

class TranslateController extends Controller
{
    /**
     * Sync translations from DB
     *
     * @return int
     */
    public function actionSync(): int
    {
        $this->stdout(Craft::t('app', 'Syncing translations from DB:') . PHP_EOL, \yii\helpers\Console::FG_GREEN);
        $this->stdout(Craft::t('app', 'Adding translations to the queue:') . PHP_EOL, \yii\helpers\Console::FG_YELLOW);
        Translate::$app->translate->runSync();
        $this->stdout(Craft::t('app', 'Running queued-up jobs') . PHP_EOL, \yii\helpers\Console::FG_YELLOW);
        Craft::$app->queue->run();
        $this->stdout(Craft::t('app', 'Syncing translations from DB job started') . PHP_EOL, \yii\helpers\Console::FG_GREEN);
        return ExitCode::OK;
    }
}