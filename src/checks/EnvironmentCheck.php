<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use ledgehq\craftledge\Ledge;
use Craft;
use craft\helpers\App;
use Throwable;

class EnvironmentCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'environment';
    }

    public function run(): ?CheckResult
    {
        try {
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
                    if ($this->isEnvDefined($varName)) {
                        $definedVars[$varName] = true;
                    } else {
                        $missingVars[$varName] = true;
                    }
                }

                preg_match_all('/getenv\([\'"]([^\'"]+)[\'"]\)/', $content, $matches);
                foreach ($matches[1] as $varName) {
                    if ($this->isEnvDefined($varName)) {
                        $definedVars[$varName] = true;
                    } else {
                        $missingVars[$varName] = true;
                    }
                }
            }

            $instance = Ledge::getInstance();
            $ignored = $instance !== null ? $instance->getSettings()->ignoredEnvVars : [];
            $missing = array_values(array_diff(array_keys($missingVars), array_keys($definedVars), $ignored));
            $defined = array_keys($definedVars);

            $meta = [
                'missing' => $missing,
                'defined' => count($defined),
                'php' => PHP_VERSION,
                'os' => php_uname('s') . ' ' . php_uname('r'),
                'database' => $this->getDatabaseVersion(),
            ];

            if (!empty($missing)) {
                return CheckResult::degraded(
                    $this->getName(),
                    $meta,
                    count($missing) . ' environment variable(s) referenced but not defined'
                );
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable $e) {
            Craft::error('Ledge environment check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
    }

    private function isEnvDefined(string $varName): bool
    {
        if (App::env($varName) !== null) {
            return true;
        }

        $alt = str_starts_with($varName, 'CRAFT_')
            ? substr($varName, 6)
            : 'CRAFT_' . $varName;

        return $alt !== '' && App::env($alt) !== null;
    }

    private function getDatabaseVersion(): ?string
    {
        try {
            $db = Craft::$app->getDb();
            $version = $db->getSchema()->getServerVersion();
            $driver = $db->getDriverName();

            return $driver . ' ' . $version;
        } catch (Throwable) {
            return null;
        }
    }
}
