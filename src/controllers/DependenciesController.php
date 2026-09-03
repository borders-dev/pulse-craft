<?php

declare(strict_types=1);

namespace ledgehq\craftledge\controllers;

use Craft;
use craft\web\Controller;
use ledgehq\craftledge\Ledge;
use yii\web\Response;

class DependenciesController extends Controller
{
    use AuthenticatesWithSecretKey;

    protected array|bool|int $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Craft::$app->getResponse()->setNoCacheHeaders();

        $error = $this->validateSecretKey();
        if ($error !== null) {
            return $this->rejectWithSecretKeyError($error);
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $dependencies = Ledge::getInstance()->dependencies;
        $packages = $dependencies->getPackages();

        return $this->asJson([
            'composer' => $packages,
            'generatedAt' => date('c'),
            'hash' => $dependencies->getHash($packages),
        ]);
    }
}
