<?php

declare(strict_types=1);

namespace ledgehq\craftledge\controllers;

use Craft;
use craft\web\Controller;
use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\Ledge;
use Throwable;
use yii\web\Response;

class AcquireController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index', 'status'];
    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Craft::$app->getResponse()->setNoCacheHeaders();

        $settings = Ledge::getInstance()->getSettings();

        if (!$settings->isAcquireEnabled()) {
            return $this->rejectBeforeAction('not_enabled', 404);
        }

        $configuredKey = $settings->getSecretKey();
        $providedKey = Craft::$app->getRequest()->getHeaders()->get('X-Ledge-Key');

        if (empty($configuredKey) || empty($providedKey) || !hash_equals($configuredKey, $providedKey)) {
            return $this->rejectBeforeAction('invalid_key', 401);
        }

        return true;
    }

    public function actionIndex(): Response
    {
        try {
            $command = Ledge::getInstance()->acquire->accept($this->request->getRawBody());
        } catch (AcquireException $e) {
            Craft::warning(
                "Ledge acquire command rejected: {$e->reason}" . ($e->detail !== null ? " ({$e->detail})" : ''),
                __METHOD__
            );
            Craft::$app->getResponse()->setStatusCode($e->httpStatus);

            return $this->asJson(['accepted' => false, 'reason' => $e->reason]);
        } catch (Throwable $e) {
            Craft::error("Ledge acquire command failed unexpectedly: {$e->getMessage()}", __METHOD__);
            Craft::$app->getResponse()->setStatusCode(500);

            return $this->asJson(['accepted' => false, 'reason' => 'unexpected_error']);
        }

        Craft::$app->getResponse()->setStatusCode(202);

        return $this->asJson(['accepted' => true, 'run_id' => $command->runId]);
    }

    public function actionStatus(string $runId): Response
    {
        $status = Ledge::getInstance()->acquire->getStatus($runId);

        if ($status === null) {
            Craft::$app->getResponse()->setStatusCode(404);

            return $this->asJson(['reason' => 'unknown_run_id']);
        }

        return $this->asJson($status);
    }

    private function rejectBeforeAction(string $reason, int $statusCode): bool
    {
        Craft::warning("Ledge acquire request rejected: {$reason}", __METHOD__);

        $response = Craft::$app->getResponse();
        $response->setStatusCode($statusCode);
        $response->format = Response::FORMAT_JSON;
        $response->data = ['accepted' => false, 'reason' => $reason];

        return false;
    }
}
