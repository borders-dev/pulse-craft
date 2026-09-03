<?php

declare(strict_types=1);

namespace ledgehq\craftledge\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

class DefaultController extends Controller
{
    /**
     * Overwrite an existing config/ledge.php when publishing.
     */
    public bool $force = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'publish-config') {
            $options[] = 'force';
        }

        return $options;
    }

    public function actionGenerateKey(): int
    {
        $key = Craft::$app->getSecurity()->generateRandomString(32);

        $this->stdout('Generating Ledge secret key ... ', Console::FG_YELLOW);

        $configService = Craft::$app->getConfig();
        $path = $configService->getDotEnvPath();

        try {
            $configService->setDotEnvVar('LEDGE_SECRET_KEY', $key);
        } catch (\Throwable $e) {
            $this->stderr("failed\n", Console::FG_RED);
            $this->stderr("Unable to save to {$path}: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("done\n", Console::FG_GREEN);
        $this->stdout("LEDGE_SECRET_KEY={$key}\n");

        return ExitCode::OK;
    }

    /**
     * Copies the annotated example config to config/ledge.php.
     */
    public function actionPublishConfig(): int
    {
        $source = dirname(__DIR__, 2) . '/config.php';
        $target = Craft::$app->getPath()->getConfigPath() . '/ledge.php';

        if (!is_file($source)) {
            $this->stderr("Example config not found at {$source}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (is_file($target) && !$this->force) {
            $this->stderr("{$target} already exists. Re-run with --force to overwrite it.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!@copy($source, $target)) {
            $this->stderr("Unable to write {$target}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('Published Ledge config to ', Console::FG_GREEN);
        $this->stdout("{$target}\n");

        return ExitCode::OK;
    }
}
