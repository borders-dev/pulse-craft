# Pulse - Craft CMS Health Monitoring Plugin

## Project Overview

Pulse is a Craft CMS 5 plugin that exposes a secured `/health` endpoint returning standardized health check data. It's part of a two-part monitoring system—this plugin runs on client sites, while a separate Pulse Service polls and aggregates the data.

**Plugin dev URL:** https://pulse-craft.ddev.site/
**Host Craft site:** https://sauer-brands.ddev.site/

## Tech Stack

- **PHP:** 8.0.2+
- **Craft CMS:** 3.7+ / 4.x / 5.x
- **DDEV:** Local development environment
- **Namespace:** `bordersdev\craftpulse`
- **Plugin Handle:** `pulse`

## DDEV Commands

```bash
ddev start                  # Start the environment
ddev stop                   # Stop the environment
ddev composer install       # Install dependencies
ddev composer check-cs      # Check code style (ECS)
ddev composer fix-cs        # Fix code style
ddev composer phpstan       # Run PHPStan static analysis
```

## Project Structure

```
src/
├── Pulse.php              # Main plugin class
├── checks/
│   ├── CheckInterface.php     # Contract for health checks
│   ├── CheckResult.php        # Standardized result object
│   ├── CraftVersionCheck.php  # Craft CMS update check
│   ├── DatabaseCheck.php      # DB connectivity check
│   ├── DebugModeCheck.php     # Dev mode detection
│   ├── DiskSpaceCheck.php     # Disk usage monitoring
│   ├── EnvironmentCheck.php   # Missing env vars check
│   ├── FailedLoginsCheck.php  # Failed login attempts
│   ├── FormCheck.php          # Formie/Freeform monitoring
│   ├── LicenseCheck.php       # License status check
│   ├── MemoryCheck.php        # PHP memory monitoring
│   ├── PluginVersionsCheck.php # Plugin updates check
│   └── QueueCheck.php         # Queue monitoring check
├── controllers/
│   └── HealthController.php # /health endpoint
├── models/
│   └── Settings.php       # Plugin settings
├── services/
│   └── HealthService.php  # Orchestrates all checks
└── templates/
    └── _settings.twig     # Settings UI

notes/
├── plugin.md              # Plugin specification
└── service.md             # Pulse Service specification
```

## Craft CMS 5 Plugin Conventions

### Plugin Class Pattern
- Extends `craft\base\Plugin`
- Use `Craft::$app->onInit()` for deferred initialization
- Settings via `createSettingsModel()` and `settingsHtml()`
- Register components in `config()` static method

### Registering Routes
```php
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

Event::on(
    UrlManager::class,
    UrlManager::EVENT_REGISTER_SITE_URL_RULES,  // Note: includes "URL"
    function(RegisterUrlRulesEvent $event) {
        $event->rules['health'] = 'pulse/health/index';
    }
);
```

### Services
- Register in `config()` components array
- Access via `Pulse::getInstance()->serviceName`

### Controllers
- Extend `craft\web\Controller`
- Set `protected array|bool|int $allowAnonymous = true;` for public endpoints
- Return JSON with `$this->asJson($data)`

## Health Endpoint Spec

**Endpoint:** `/health` (configurable)
**Auth:** `X-Pulse-Key` header with secret key
**Response format:**
```json
{
  "status": "healthy|degraded|unhealthy",
  "checks": { ... }
}
```

## Health Checks Implementation Status

### Phase 1 (Core) ✓
- [x] Database connectivity
- [x] Queue status (pending, stuck, failed jobs)
- [x] Secret key authentication

### Phase 2 (Extended) ✓
- [x] Disk space usage
- [x] Memory usage
- [x] Craft/plugin versions
- [x] Debug mode detection
- [x] Failed login attempts
- [x] License status

### Phase 3 (Advanced) ✓
- [x] Form plugin monitoring (Formie/Freeform)
- [x] Missing environment variables

### Phase 4 (Future)
- [ ] Email delivery verification
- [ ] Configurable status level for non-critical updates (healthy vs degraded)

## Code Style

- PHP 8.0+ features (typed properties, match expressions, constructor promotion)
- No comments unless logic is non-obvious
- `declare(strict_types=1);` in all files
- Follow Craft's ECS configuration (no space before closure parentheses)
- Use constructor property promotion where appropriate
- Import order: project namespace first, then Craft, then vendor, then PHP
- Avoid `readonly` properties (PHP 8.1+) to maintain Craft 4 compatibility

## Environment Variables

- `PULSE_SECRET_KEY` - Health endpoint authentication key

## Testing the Plugin

Access the health endpoint:
```bash
curl -H "X-Pulse-Key: your-secret-key" https://sauer-brands.ddev.site/health
```

## Related Documentation

- [Craft CMS Plugin Development](https://craftcms.com/docs/5.x/extend/)
- [Craft Events Reference](https://craftcms.com/docs/5.x/extend/events.html)
- See `notes/plugin.md` for full specification
- See `notes/service.md` for Pulse Service specification
