<?php

declare(strict_types=1);

namespace ledgehq\craftledge\controllers;

use Craft;
use ledgehq\craftledge\Ledge;
use yii\web\Response;

/**
 * Shared-key check for the read-only endpoints (health, dependencies, uris).
 * The key is read from the `keyHeader` header (default `X-Ledge-Key`) only, unless the operator
 * opts in to `allowQueryKey`, in which case a `queryKeyParam` query param is accepted
 * as a fallback. Any other query string (e.g. a cache-buster) is ignored.
 */
trait AuthenticatesWithSecretKey
{
    protected function validateSecretKey(): ?string
    {
        $settings = Ledge::currentSettings();
        $configuredKey = $settings->getSecretKey();

        if (empty($configuredKey)) {
            return 'LEDGE_SECRET_KEY is not configured';
        }

        $request = Craft::$app->getRequest();
        $providedKey = $request->getHeaders()->get($settings->keyHeader);

        if ($providedKey === null && $settings->allowQueryKey) {
            $providedKey = $request->getQueryParam($settings->queryKeyParam);
        }

        if (!is_string($providedKey) || $providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
            return 'Unauthorized';
        }

        return null;
    }

    protected function rejectWithSecretKeyError(string $error): bool
    {
        $response = Craft::$app->getResponse();
        $response->setStatusCode(401);
        $response->format = Response::FORMAT_JSON;
        $response->data = ['error' => $error];

        return false;
    }
}
