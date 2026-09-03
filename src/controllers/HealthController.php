<?php

declare(strict_types=1);

namespace ledgehq\craftledge\controllers;

use Craft;
use craft\web\Controller;
use ledgehq\craftledge\Ledge;
use yii\web\Response;

class HealthController extends Controller
{
    use AuthenticatesWithSecretKey;

    protected array|bool|int $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Craft::$app->getResponse()->setNoCacheHeaders();

        if ($action->id === 'index') {
            $error = $this->validateSecretKey();
            if ($error !== null) {
                return $this->rejectWithSecretKeyError($error);
            }
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $healthData = Ledge::getInstance()->health->runChecks();

        Craft::$app->getResponse()->setStatusCode(
            $healthData['status'] === 'unhealthy' ? 503 : 200
        );

        return $this->asJson($healthData);
    }
}
