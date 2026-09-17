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
            return $this->failure($element, Craft::t('enupal-translate', 'No translation provider is enabled. Check the Enupal Translate settings.'));
        }

        $targetSites = $this->resolveTargetSites($element, $sourceSite, $targetSiteId);

        if (empty($targetSites)) {
            return $this->failure($element, Craft::t('enupal-translate', 'There are no other sites this entry can be translated into.'));
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

            return $this->success($element, Craft::t('enupal-translate', 'Translating into {count} sites. Added to the queue.', [
                'count' => count($targetSites),
            ]));
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

            return $this->failure($element, $e->getMessage());
        }

        if ($translated === null) {
            return $this->failure($element, Craft::t('enupal-translate', 'There was nothing translatable on this entry.'));
        }

        if ($translated->hasErrors()) {
            return $this->failure($element, Craft::t('enupal-translate', 'Translated, but the entry could not be saved cleanly. Check the logs.'));
        }

        $isDraft = $translated->getIsDraft();
        $time = Craft::$app->getFormatter()->asTime(new \DateTime(), 'short');

        $message = $isDraft
            ? Craft::t('enupal-translate', 'Translated into {site} and saved as a draft at {time}.', [
                'site' => $targetSite->name,
                'time' => $time,
            ])
            : Craft::t('enupal-translate', 'Translated into {site} at {time}.', [
                'site' => $targetSite->name,
                'time' => $time,
            ]);

        return $this->success($element, $message, [
            'targetUrl' => $translated->getCpEditUrl(),
            'targetLabel' => $isDraft
                ? Craft::t('enupal-translate', 'Review draft')
                : Craft::t('enupal-translate', 'View translation'),
            'isDraft' => $isDraft,
        ]);
    }

    /**
     * The sidebar posts over AJAX so that it never has to nest a form inside
     * Craft's own; anything posting a plain form still gets a redirect.
     */
    private function success(ElementInterface $element, string $message, array $data = []): Response
    {
        if ($this->request->getAcceptsJson()) {
            return $this->asJson(array_merge(['success' => true, 'message' => $message], $data));
        }

        $this->setSuccessFlash($message);

        return $this->redirect($data['targetUrl'] ?? $element->getCpEditUrl());
    }

    private function failure(ElementInterface $element, string $message): Response
    {
        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => false, 'message' => $message]);
        }

        $this->setFailFlash($message);

        return $this->redirectToPostedUrl($element);
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
