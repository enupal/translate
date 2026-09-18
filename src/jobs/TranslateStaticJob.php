<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\jobs;

use Craft;
use craft\queue\BaseJob;
use enupal\translate\Translate as TranslatePlugin;

/**
 * Translates static strings in the background.
 */
class TranslateStaticJob extends BaseJob
{
    /**
     * @var string[]
     */
    public array $originals = [];

    public string $providerHandle = '';

    public int $siteId = 0;

    public ?string $translatePath = null;

    protected function defaultDescription(): ?string
    {
        return Craft::t('enupal-translate', 'Translating {count} strings', [
            'count' => count($this->originals),
        ]);
    }

    public function execute($queue): void
    {
        $this->setProgress($queue, 0);

        $site = Craft::$app->getSites()->getSiteById($this->siteId);

        if ($site === null) {
            throw new \RuntimeException("Site $this->siteId no longer exists.");
        }

        $result = TranslatePlugin::$app->translate->translateStatic(
            $this->originals,
            $this->providerHandle,
            $site,
            $this->translatePath
        );

        $this->setProgress($queue, 1);

        if (!$result->success) {
            throw new \RuntimeException($result->errorMessage ?? 'Translation failed.');
        }
    }
}
