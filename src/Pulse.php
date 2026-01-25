<?php

declare(strict_types=1);

namespace bordersdev\craftpulse;

use bordersdev\craftpulse\models\Settings;
use bordersdev\craftpulse\services\HealthService;
use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * @method static Pulse getInstance()
 * @method Settings getSettings()
 * @property-read HealthService $health
 */
class Pulse extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'health' => HealthService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function() {
            $this->registerRoutes();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('pulse/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function registerRoutes(): void
    {
        $settings = $this->getSettings();
        $endpointPath = $settings->endpointPath;

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) use ($endpointPath) {
                $event->rules[$endpointPath] = 'pulse/health/index';
            }
        );
    }
}
