<?php

declare(strict_types=1);

namespace ledgehq\craftledge;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use ledgehq\craftledge\console\controllers\DefaultController;
use ledgehq\craftledge\models\Settings;
use ledgehq\craftledge\services\AcquireService;
use ledgehq\craftledge\services\DependenciesService;
use ledgehq\craftledge\services\HealthService;
use yii\base\Event;

/**
 * @method static Ledge getInstance()
 * @method Settings getSettings()
 * @property-read HealthService $health
 * @property-read AcquireService $acquire
 * @property-read DependenciesService $dependencies
 */
class Ledge extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = false;

    public static function config(): array
    {
        return [
            'components' => [
                'health' => HealthService::class,
                'acquire' => AcquireService::class,
                'dependencies' => DependenciesService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function() {
            $this->registerRoutes();
            $this->registerConsoleCommands();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        $settings = new Settings();
        $fileConfig = Craft::$app->getConfig()->getConfigFromFile('ledge');

        if (is_array($fileConfig)) {
            $settings->setAttributes($fileConfig, false);
        }

        return $settings;
    }

    private function registerRoutes(): void
    {
        $settings = $this->getSettings();
        $endpointPath = $settings->endpointPath;
        $dependenciesPath = $settings->dependenciesPath;
        $urisPath = $settings->isUrisEnabled() ? $settings->urisPath : null;
        $acquirePath = $settings->isAcquireEnabled() ? $settings->acquirePath : null;

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) use ($endpointPath, $dependenciesPath, $urisPath, $acquirePath) {
                $event->rules[$endpointPath] = 'ledge/health/index';
                $event->rules["GET {$dependenciesPath}"] = 'ledge/dependencies/index';

                if ($urisPath !== null) {
                    $event->rules["GET {$urisPath}"] = 'ledge/uris/index';
                }

                if ($acquirePath !== null) {
                    $event->rules["POST {$acquirePath}"] = 'ledge/acquire/index';
                    $event->rules["GET {$acquirePath}/<runId:[A-Za-z0-9\\-]{1,64}>"] = 'ledge/acquire/status';
                }
            }
        );
    }

    private function registerConsoleCommands(): void
    {
        if (Craft::$app instanceof ConsoleApplication) {
            Craft::$app->controllerMap['ledge'] = DefaultController::class;
        }
    }
}
