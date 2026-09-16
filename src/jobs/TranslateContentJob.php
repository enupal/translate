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
use Throwable;

/**
 * Translates element content into one or more target sites.
 */
class TranslateContentJob extends BaseJob
{
    /**
     * @var int[]
     */
    public array $elementIds = [];

    public string $elementType = '';

    public int $sourceSiteId = 0;

    /**
     * @var int[]
     */
    public array $targetSiteIds = [];

    /**
     * Limit to these field handles, or null for everything translatable.
     *
     * @var string[]|null
     */
    public ?array $fieldHandles = null;

    protected function defaultDescription(): ?string
    {
        return Craft::t('enupal-translate', 'Translating {count} elements', [
            'count' => count($this->elementIds),
        ]);
    }

    public function execute($queue): void
    {
        $sourceSite = Craft::$app->getSites()->getSiteById($this->sourceSiteId);

        if ($sourceSite === null) {
            throw new \RuntimeException("Site $this->sourceSiteId no longer exists.");
        }

        $total = max(1, count($this->elementIds) * count($this->targetSiteIds));
        $step = 0;
        $errors = [];

        foreach ($this->targetSiteIds as $targetSiteId) {
            $targetSite = Craft::$app->getSites()->getSiteById($targetSiteId);

            if ($targetSite === null) {
                continue;
            }

            foreach ($this->elementIds as $elementId) {
                $step++;

                $this->setProgress($queue, $step / $total, Craft::t('enupal-translate', 'Translating {step} of {total} into {site}', [
                    'step' => $step,
                    'total' => $total,
                    'site' => $targetSite->name,
                ]));

                /** @var \craft\base\ElementInterface|null $element */
                $element = $this->elementType::find()
                    ->id($elementId)
                    ->siteId($sourceSite->id)
                    ->status(null)
                    ->drafts(null)
                    ->one();

                if ($element === null) {
                    continue;
                }

                try {
                    TranslatePlugin::$app->content->translateElement(
                        $element,
                        $sourceSite,
                        $targetSite,
                        $this->fieldHandles
                    );
                } catch (Throwable $e) {
                    // Keep going: one bad element shouldn't abandon the rest
                    // of the batch, but the job should still end up failed.
                    Craft::error(sprintf(
                        'Could not translate element %d into %s: %s',
                        $elementId,
                        $targetSite->handle,
                        $e->getMessage()
                    ), __METHOD__);

                    $errors[] = "#$elementId → {$targetSite->handle}: {$e->getMessage()}";
                }
            }
        }

        $this->setProgress($queue, 1);

        if ($errors) {
            throw new \RuntimeException(Craft::t('enupal-translate', '{count} elements failed to translate. Check the logs.', [
                'count' => count($errors),
            ]) . "\n" . implode("\n", array_slice($errors, 0, 5)));
        }
    }
}
