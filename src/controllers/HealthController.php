<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\controllers;

use bordersdev\craftpulse\Pulse;
use Craft;
use craft\web\Controller;
use yii\web\Response;

class HealthController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if ($action->id === 'index' && !$this->validateSecretKey()) {
            Craft::$app->getResponse()->setStatusCode(401);
            Craft::$app->getResponse()->data = Craft::$app->getResponse()->content = json_encode([
                'error' => 'Unauthorized',
            ]);
            Craft::$app->getResponse()->format = Response::FORMAT_JSON;
            return false;
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $healthData = Pulse::getInstance()->health->runChecks();

        $statusCode = match ($healthData['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 200,
        };

        Craft::$app->getResponse()->setStatusCode($statusCode);

        return $this->asJson($healthData);
    }

    private function validateSecretKey(): bool
    {
        $settings = Pulse::getInstance()->getSettings();
        $configuredKey = $settings->getSecretKey();

        if (empty($configuredKey)) {
            return false;
        }

        $request = Craft::$app->getRequest();
        $providedKey = $request->getHeaders()->get('X-Pulse-Key')
            ?? $request->getQueryParam('key');

        return hash_equals($configuredKey, $providedKey ?? '');
    }
}
