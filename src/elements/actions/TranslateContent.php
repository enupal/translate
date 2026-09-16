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
use craft\models\Site;
use enupal\translate\jobs\TranslateContentJob;
use enupal\translate\Translate as TranslatePlugin;
use yii\web\ForbiddenHttpException;

/**
 * Bulk-translates element content (entries, assets, products…) into other sites.
 *
 * Every selected target site is covered by a single queue job, so the user gets
 * one progress bar instead of one per site.
 */
class TranslateContent extends ElementAction
{
    /**
     * Handle of the site the elements are being translated from.
     */
    public string $sourceSiteHandle = '';

    /**
     * A site handle, or '*' for every site the user can edit.
     */
    public string $targetSiteHandle = '';

    public static function displayName(): string
    {
        return Craft::t('enupal-translate', 'Translate');
    }

    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: true,
        validateSelection: () => true,
    });
})();
JS, [static::class]);

        return Craft::$app->getView()->renderTemplate('enupal-translate/_actions/translate-content');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $this->requirePermission();

        $sourceSite = $this->resolveSourceSite($query);

        if ($sourceSite === null) {
            $this->setMessage(Craft::t('enupal-translate', 'Could not determine the source site.'));
            return false;
        }

        if (TranslatePlugin::$app->providers->getContentProvider() === null) {
            $this->setMessage(Craft::t('enupal-translate', 'No translation provider is enabled. Check the plugin settings.'));
            return false;
        }

        $targetSites = $this->resolveTargetSites($sourceSite);

        if (empty($targetSites)) {
            $this->setMessage(Craft::t('enupal-translate', 'No eligible target sites.'));
            return false;
        }

        $elementIds = $query->ids();

        if (empty($elementIds)) {
            $this->setMessage(Craft::t('enupal-translate', 'Nothing to translate.'));
            return false;
        }

        Craft::$app->getQueue()->push(new TranslateContentJob([
            'elementIds' => $elementIds,
            'elementType' => $query->elementType,
            'sourceSiteId' => $sourceSite->id,
            'targetSiteIds' => array_map(static fn(Site $site) => $site->id, $targetSites),
        ]));

        $this->setMessage(Craft::t('enupal-translate', 'Translating {elements} elements into {sites} sites. Added to the queue.', [
            'elements' => count($elementIds),
            'sites' => count($targetSites),
        ]));

        return true;
    }

    /**
     * @return Site[]
     */
    private function resolveTargetSites(Site $sourceSite): array
    {
        $user = Craft::$app->getUser()->getIdentity();
        $sites = Craft::$app->getSites();

        $canEdit = static fn(Site $site): bool => $user !== null && $user->can('editSite:' . $site->uid);

        // No explicit choice, or "all sites": every other site the user can edit.
        if ($this->targetSiteHandle === '' || $this->targetSiteHandle === '*') {
            return array_values(array_filter(
                $sites->getAllSites(),
                static fn(Site $site) => $site->id !== $sourceSite->id && $canEdit($site)
            ));
        }

        $target = $sites->getSiteByHandle($this->targetSiteHandle);

        if ($target === null || $target->id === $sourceSite->id) {
            return [];
        }

        if (!$canEdit($target)) {
            throw new ForbiddenHttpException(Craft::t('enupal-translate', 'You are not allowed to edit the site “{site}”.', [
                'site' => $target->name,
            ]));
        }

        return [$target];
    }

    private function resolveSourceSite(ElementQueryInterface $query): ?Site
    {
        $sites = Craft::$app->getSites();

        if ($this->sourceSiteHandle !== '') {
            $site = $sites->getSiteByHandle($this->sourceSiteHandle);

            if ($site) {
                return $site;
            }
        }

        $siteId = $query->siteId;

        if (is_array($siteId)) {
            $siteId = reset($siteId);
        }

        return $siteId ? $sites->getSiteById((int)$siteId) : $sites->getCurrentSite();
    }

    private function requirePermission(): void
    {
        if (!Craft::$app->getUser()->checkPermission('enupal-translate:translateContent')) {
            throw new ForbiddenHttpException(Craft::t('enupal-translate', 'You are not allowed to translate content.'));
        }
    }
}
