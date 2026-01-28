<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\controllers;

use bordersdev\craftpulse\Pulse;
use Craft;
use craft\web\Controller;
use yii\web\Response;

class HealthController extends Controller
{
    protected $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if ($action->id === 'index') {
            $error = $this->validateSecretKey();
            if ($error !== null) {
                Craft::$app->getResponse()->setStatusCode(401);
                Craft::$app->getResponse()->data = Craft::$app->getResponse()->content = json_encode([
                    'error' => $error,
                ]);
                Craft::$app->getResponse()->format = Response::FORMAT_JSON;
                return false;
            }
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

    private function validateSecretKey(): ?string
    {
        $settings = Pulse::getInstance()->getSettings();
        $configuredKey = $settings->getSecretKey();

        if (empty($configuredKey)) {
            return 'PULSE_SECRET_KEY is not configured';
        }

        $request = Craft::$app->getRequest();
        $providedKey = $request->getHeaders()->get('X-Pulse-Key')
            ?? $request->getQueryParam('key');

        if (empty($providedKey) || !hash_equals($configuredKey, $providedKey)) {
            return 'Unauthorized';
        }

        return null;
    }
}
