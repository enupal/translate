<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\models\Site;
use craft\web\Controller as BaseController;
use enupal\translate\jobs\TranslateContentJob;
use enupal\translate\Translate as TranslatePlugin;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Translating a single element from its edit screen.
 */
class ContentController extends BaseController
{
    /**
     * Translate one element into one site, or into every site it supports.
     *
     * A single target runs inline so the author sees the result straight away.
     * Several targets go to the queue, because that can take a while.
     */
    public function actionTranslate(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('enupal-translate:translateContent');

        $request = Craft::$app->getRequest();

        $elementId = (int)$request->getRequiredBodyParam('elementId');
        $elementType = (string)$request->getRequiredBodyParam('elementType');
        $sourceSiteId = (int)$request->getRequiredBodyParam('sourceSiteId');
        $targetSiteId = $request->getBodyParam('targetSiteId');
        $providerHandle = $request->getBodyParam('providerHandle') ?: null;

        if (!class_exists($elementType) || !is_subclass_of($elementType, ElementInterface::class)) {
            throw new BadRequestHttpException('Invalid element type.');
        }

        $sites = Craft::$app->getSites();
        $sourceSite = $sites->getSiteById($sourceSiteId);

        if ($sourceSite === null) {
            throw new BadRequestHttpException('Invalid source site.');
        }

        /** @var ElementInterface|null $element */
        $element = $elementType::find()
            ->id($elementId)
            ->siteId($sourceSite->id)
            ->status(null)
            ->drafts(null)
            ->one();

        if ($element === null) {
            throw new BadRequestHttpException('Element not found.');
        }

        if (TranslatePlugin::$app->providers->getContentProvider() === null) {
            $this->setFailFlash(Craft::t('enupal-translate', 'No translation provider is enabled. Check the Enupal Translate settings.'));

            return $this->redirectToPostedUrl($element);
        }

        $targetSites = $this->resolveTargetSites($element, $sourceSite, $targetSiteId);

        if (empty($targetSites)) {
            $this->setFailFlash(Craft::t('enupal-translate', 'There are no other sites this entry can be translated into.'));

            return $this->redirectToPostedUrl($element);
        }

        // More than one target is queued; the author shouldn't sit on a
        // spinner while several sites are translated one after another.
        if (count($targetSites) > 1) {
            Craft::$app->getQueue()->push(new TranslateContentJob([
                'elementIds' => [$element->id],
                'elementType' => $elementType,
                'sourceSiteId' => $sourceSite->id,
                'targetSiteIds' => array_map(static fn(Site $site) => $site->id, $targetSites),
                'providerHandle' => $providerHandle,
            ]));

            $this->setSuccessFlash(Craft::t('enupal-translate', 'Translating into {count} sites. Added to the queue.', [
                'count' => count($targetSites),
            ]));

            return $this->redirectToPostedUrl($element);
        }

        $targetSite = reset($targetSites);

        try {
            $translated = TranslatePlugin::$app->content->translateElement(
                $element,
                $sourceSite,
                $targetSite,
                null,
                true,
                $providerHandle
            );
        } catch (Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            $this->setFailFlash($e->getMessage());

            return $this->redirectToPostedUrl($element);
        }

        if ($translated === null) {
            $this->setFailFlash(Craft::t('enupal-translate', 'There was nothing translatable on this entry.'));

            return $this->redirectToPostedUrl($element);
        }

        if ($translated->hasErrors()) {
            $this->setFailFlash(Craft::t('enupal-translate', 'Translated, but the entry could not be saved cleanly. Check the logs.'));

            return $this->redirectToPostedUrl($element);
        }

        $this->setSuccessFlash(Craft::t('enupal-translate', 'Translated into {site}.', [
            'site' => $targetSite->name,
        ]));

        // Drop the author straight into what was just produced.
        return $this->redirect($translated->getCpEditUrl() ?? $element->getCpEditUrl());
    }

    /**
     * @return Site[]
     */
    private function resolveTargetSites(ElementInterface $element, Site $sourceSite, $targetSiteId): array
    {
        $user = Craft::$app->getUser()->getIdentity();
        $content = TranslatePlugin::$app->content;

        $eligible = array_values(array_filter(
            Craft::$app->getSites()->getAllSites(),
            static fn(Site $site) => $site->id !== $sourceSite->id
                && $user?->can('editSite:' . $site->uid)
                && $content->supportsSite($element, $site)
        ));

        if ($targetSiteId === null || $targetSiteId === '' || $targetSiteId === '*') {
            return $eligible;
        }

        foreach ($eligible as $site) {
            if ($site->id === (int)$targetSiteId) {
                return [$site];
            }
        }

        return [];
    }
}
