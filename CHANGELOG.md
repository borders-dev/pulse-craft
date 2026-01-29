# Release Notes for Pulse

## 0.5.0
- Drop Craft 3 support (use v3 branch / 1.x releases for Craft 3)
- Restore typed properties on plugin class and controller
- Remove Craft 3 compatibility shims
- Remove Freeform 3 support (Freeform 4+ only)
- Use `App::parseEnv()` and `App::env()` instead of deprecated `Craft::parseEnv()`
- Normalize edition values for consistent JSON output across Craft 4/5
- Require Craft 4.0+ or 5.0+

## 0.4.0
- Split combined "forms" check into separate "formie" and "freeform" checks
- Return degraded status when form plugin data cannot be queried

## 0.3.3
- Set plugin display name to "Pulse"

## 0.3.2
- Only report degraded status for plugins with critical updates
- Add OS version and database version to environment check

## 0.3.1
- Change default endpoint from `/health` to `/_pulse/health`

## 0.2.1
- Remove type declarations from plugin properties for Craft 3 compatibility
- Remove type declaration from `$allowAnonymous` controller property for Craft 3 compatibility
- Add `method_exists()` check for `onInit()` (not available in Craft 3)
- Use `setComponents()` for Craft 3 component registration compatibility
- Fix `getSecretKey()` to properly detect missing env var
- Improve error messages for authentication failures
- Add Freeform 3 support using native Freeform APIs with error details in response
- Remove time window filter from form checks (report all errors)

## 0.2.0
- Add Craft 3.7+ support
- Add `method_exists()` check for `getLicensedEdition()` (not available in Craft 3)

## 0.1.0
- Initial release
- Health endpoint with secret key authentication
- Database connectivity check
- Queue monitoring (pending, stuck, failed jobs)
- Disk space monitoring
- Memory usage monitoring
- Craft CMS version check with update detection
- Plugin version check with update detection
- Debug mode detection
- Failed login attempt monitoring
- License status check
- Environment variable validation
- Form plugin monitoring (Formie/Freeform failed notifications)
- Craft 4 and 5 support
- PHP 8.0.2+ support
