<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use Craft;
use craft\helpers\App;

class EnvironmentCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'environment';
    }

    public function run(): CheckResult
    {
        $missingVars = [];
        $definedVars = [];

        $configFiles = [
            Craft::$app->getPath()->getConfigPath() . '/general.php',
            Craft::$app->getPath()->getConfigPath() . '/db.php',
            Craft::$app->getPath()->getConfigPath() . '/app.php',
            Craft::$app->getPath()->getConfigPath() . '/app.web.php',
            Craft::$app->getPath()->getConfigPath() . '/app.console.php',
        ];

        foreach ($configFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            preg_match_all('/App::env\([\'"]([^\'"]+)[\'"]\)/', $content, $matches);
            foreach ($matches[1] as $varName) {
                $value = App::env($varName);
                if ($value === null) {
                    $missingVars[$varName] = true;
                } else {
                    $definedVars[$varName] = true;
                }
            }

            preg_match_all('/getenv\([\'"]([^\'"]+)[\'"]\)/', $content, $matches);
            foreach ($matches[1] as $varName) {
                $value = App::env($varName);
                if ($value === null) {
                    $missingVars[$varName] = true;
                } else {
                    $definedVars[$varName] = true;
                }
            }
        }

        $missing = array_keys($missingVars);
        $defined = array_keys($definedVars);

        $meta = [
            'missing' => $missing,
            'defined' => count($defined),
            'php' => PHP_VERSION,
        ];

        if (!empty($missing)) {
            return CheckResult::unhealthy(
                $this->getName(),
                $meta,
                count($missing) . ' environment variable(s) referenced but not defined'
            );
        }

        return CheckResult::healthy($this->getName(), $meta);
    }
}
