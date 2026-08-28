<?php

declare(strict_types=1);

namespace ledgehq\craftledge\controllers;

use Craft;
use craft\web\Controller;
use ledgehq\craftledge\Ledge;
use Throwable;
use yii\web\Response;

class UrisController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Craft::$app->getResponse()->setNoCacheHeaders();

        if (!Ledge::getInstance()->getSettings()->isUrisEnabled()) {
            $response = Craft::$app->getResponse();
            $response->setStatusCode(404);
            $response->format = Response::FORMAT_JSON;
            $response->data = ['error' => 'Not enabled'];
            return false;
        }

        $error = $this->validateSecretKey();
        if ($error !== null) {
            $response = Craft::$app->getResponse();
            $response->setStatusCode(401);
            $response->format = Response::FORMAT_JSON;
            $response->data = ['error' => $error];
            return false;
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $acquire = Ledge::getInstance()->acquire;
        $uris = $acquire->getPublicUris();

        $payload = [
            'count' => count($uris),
            'uris' => $uris,
        ];

        // Omit `sites` entirely on failure (never emit an empty list) so the
        // endpoint keeps serving `uris` and consumers fall back to single-site.
        try {
            $payload['sites'] = $acquire->getSites();
        } catch (Throwable $e) {
            Craft::warning('Ledge site enumeration failed: ' . $e->getMessage(), __METHOD__);
        }

        return $this->asJson($payload);
    }

    private function validateSecretKey(): ?string
    {
        $settings = Ledge::getInstance()->getSettings();
        $configuredKey = $settings->getSecretKey();

        if (empty($configuredKey)) {
            return 'LEDGE_SECRET_KEY is not configured';
        }

        $request = Craft::$app->getRequest();
        $providedKey = $request->getHeaders()->get('X-Ledge-Key')
            ?? $request->getQueryParam('key');

        if (empty($providedKey) || !hash_equals($configuredKey, $providedKey)) {
            return 'Unauthorized';
        }

        return null;
    }
}
