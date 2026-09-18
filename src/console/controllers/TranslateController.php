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
     * Actually call the provider APIs rather than just reading settings.
     *
     * @var bool
     */
    public bool $live = false;

    /**
     * Provider handle to use, e.g. 'claude'.
     *
     * @var string|null
     */
    public ?string $provider = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), match ($actionID) {
            'providers' => ['live'],
            'test' => ['provider'],
            default => [],
        });
    }

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

    /**
     * Report the status of every translation provider.
     *
     * Use --live to actually call each configured provider's API, which
     * verifies the credentials really work (and costs a few tokens).
     *
     * @return int
     */
    public function actionProviders(): int
    {
        $this->stdout('Translation providers' . PHP_EOL . PHP_EOL, Console::FG_GREEN);

        foreach (Translate::$app->providers->getAllProviders() as $handle => $provider) {
            $configured = $provider->isConfigured();

            $this->stdout(str_pad($handle, 14));
            $this->stdout(str_pad($provider::displayName(), 30));

            if (!$configured) {
                $this->stdout('not configured' . PHP_EOL, Console::FG_GREY);
                continue;
            }

            $model = $provider->getModelName();

            if (!$this->live) {
                $this->stdout('configured', Console::FG_GREEN);
                $this->stdout($model ? " ($model)" : '');
                $this->stdout(PHP_EOL);
                continue;
            }

            if ($provider->isConnected()) {
                $this->stdout('connected', Console::FG_GREEN);
                $this->stdout($model ? " ($model)" : '');
                $this->stdout(PHP_EOL);
            } else {
                $this->stdout('FAILED — check credentials and logs' . PHP_EOL, Console::FG_RED);
            }
        }

        return ExitCode::OK;
    }

    /**
     * Translate a single string, to sanity check a provider end to end.
     *
     * craft enupal-translate/translate/test "Hello world" es --provider=claude
     *
     * @param string $text The string to translate.
     * @param string $language The target language, e.g. 'es'.
     * @return int
     */
    public function actionTest(string $text, string $language): int
    {
        $handle = $this->provider ?: (Translate::$app->providers->getContentProvider()?->handle());

        if (!$handle) {
            $this->stderr('No provider is enabled.' . PHP_EOL, Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $provider = Translate::$app->providers->getProviderByHandle($handle);

        if ($provider === null || !$provider->isConfigured()) {
            $this->stderr("Provider '$handle' is not enabled." . PHP_EOL, Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $result = $provider->translate(['sample' => $text], $language);

        if (!$result->success) {
            $this->stderr('Failed: ' . $result->errorMessage . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('Source:      ' . $text . PHP_EOL);
        $this->stdout('Translation: ' . ($result->translations['sample'] ?? '(none)') . PHP_EOL, Console::FG_GREEN);
        $this->stdout(sprintf(
            'Provider: %s  Model: %s  Tokens: %d in / %d out  Calls: %d' . PHP_EOL,
            $result->provider,
            $result->model ?? 'n/a',
            $result->inputTokens,
            $result->outputTokens,
            $result->apiCalls
        ), Console::FG_GREY);

        return ExitCode::OK;
    }

    /**
     * Report which fields in this install can be translated.
     *
     * Shows every custom field, the serializer that will handle it, and why
     * anything is being skipped — so a site's coverage can be checked without
     * running a translation.
     *
     * @return int
     */
    public function actionFields(): int
    {
        $content = Translate::$app->content;
        $excluded = Translate::$app->settings->getSettings()->getExcludedFieldHandles();

        $supported = [];
        $unsupported = [];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            $class = get_class($field);
            $serializer = $content->getSerializer($field);
            $short = fn(?string $c) => $c ? substr((string)strrchr($c, '\\'), 1) : '-';

            $row = [
                'handle' => $field->handle,
                'name' => $field->name,
                'class' => $class,
                'serializer' => $serializer ? $short(get_class($serializer)) : null,
            ];

            if (!$serializer) {
                $row['reason'] = 'no serializer for ' . $short($class);
                $unsupported[] = $row;
                continue;
            }

            if (in_array($field->handle, $excluded, true)) {
                $row['reason'] = 'excluded in settings';
                $unsupported[] = $row;
                continue;
            }

            $isNested = in_array($class, \enupal\translate\services\Content::$nestedElementFields, true);
            $method = $field->translationMethod ?? \craft\base\Field::TRANSLATION_METHOD_NONE;

            if (!$isNested && $method === \craft\base\Field::TRANSLATION_METHOD_NONE) {
                $row['reason'] = 'field is not translatable (Translation Method: None)';
                $unsupported[] = $row;
                continue;
            }

            $supported[] = $row;
        }

        $this->stdout(PHP_EOL . 'Translatable (' . count($supported) . ')' . PHP_EOL, Console::FG_GREEN);
        foreach ($supported as $r) {
            $this->stdout(sprintf("  %-28s %-22s %s\n", $r['handle'], $r['serializer'], $r['name']));
        }

        $this->stdout(PHP_EOL . 'Skipped (' . count($unsupported) . ')' . PHP_EOL, Console::FG_YELLOW);
        foreach ($unsupported as $r) {
            $this->stdout(sprintf("  %-28s %s\n", $r['handle'], $r['reason']));
        }

        $this->stdout(PHP_EOL . 'Field types this plugin knows how to translate:' . PHP_EOL);
        foreach ($content->getSupportedFieldClasses() as $class => $serializer) {
            $installed = class_exists($class) ? 'installed' : 'not installed';
            $this->stdout(sprintf("  %-46s %-16s %s\n", $class, substr((string)strrchr($serializer, '\\'), 1), $installed));
        }

        return ExitCode::OK;
    }
}
