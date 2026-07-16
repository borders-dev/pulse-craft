<?php

declare(strict_types=1);

namespace ledgehq\craftledge\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

class DefaultController extends Controller
{
    private const OPTIONAL_ENV_VARS = [
        'LEDGE_ACQUIRE_ENABLED' => 'false',
        'LEDGE_ACQUIRE_ALLOWED_HOSTS' => 'ledgehq.app,*.ledgehq.app',
        'LEDGE_ACQUIRE_ENV_DENYLIST' => 'DB_*,CRAFT_DB_*,*_PASSWORD*,*_SECRET*,*_TOKEN*,*_API_KEY*',
        'LEDGE_URIS_ENABLED' => 'false',
    ];

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

        $this->appendOptionalEnvVars($path);

        return ExitCode::OK;
    }

    private function appendOptionalEnvVars(string $path): void
    {
        try {
            $contents = is_file($path) ? file_get_contents($path) : false;
            if ($contents === false) {
                return;
            }

            $lines = [];
            foreach (self::OPTIONAL_ENV_VARS as $name => $value) {
                if (!str_contains($contents, $name)) {
                    $lines[] = "# {$name}={$value}";
                }
            }

            if ($lines === []) {
                return;
            }

            $block = "\n# Ledge (optional; uncomment to override the defaults)\n" . implode("\n", $lines) . "\n";
            if (!str_ends_with($contents, "\n")) {
                $block = "\n" . $block;
            }

            file_put_contents($path, $block, FILE_APPEND | LOCK_EX);
            $this->stdout('Added commented-out Ledge env options to ', Console::FG_YELLOW);
            $this->stdout("{$path}\n");
        } catch (\Throwable $e) {
            $this->stderr("Unable to append optional Ledge env vars to {$path}: {$e->getMessage()}\n", Console::FG_RED);
        }
    }
}
