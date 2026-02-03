<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

class DefaultController extends Controller
{
    public function actionGenerateKey(): int
    {
        $key = Craft::$app->getSecurity()->generateRandomString(32);

        $this->stdout('Generating Pulse secret key ... ', Console::FG_YELLOW);

        $configService = Craft::$app->getConfig();
        $path = $configService->getDotEnvPath();

        try {
            $configService->setDotEnvVar('PULSE_SECRET_KEY', $key);
        } catch (\Throwable $e) {
            $this->stderr("failed\n", Console::FG_RED);
            $this->stderr("Unable to save to {$path}: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("done\n", Console::FG_GREEN);
        $this->stdout("PULSE_SECRET_KEY={$key}\n");

        return ExitCode::OK;
    }
}
