# Release Notes for Ledge

## Unreleased
- Added the acquire capability: Ledge can command the site to produce an encrypted bundle (full DB dump + env manifest) and push it to object storage for automated update testing
  - Off by default: the whole capability is gated behind an `acquireEnabled` setting (env fallback `LEDGE_ACQUIRE_ENABLED`); while disabled the acquire routes are not registered (404) and the plugin behaves exactly as before
  - `POST /_ledge/acquire` accepts Ed25519-signed commands (`{command, signature}`, detached signature over canonical JSON minus `callback_token`); signing keys are discovered from Ledge's `/.well-known/ledge-keys` keyset (keyed by `key_id`) — the keyset base URL (`ledgeBaseUrl`) is file-config only and never derived from a command. Learned key pins never expire: a known `key_id` is never re-mapped to different key material (rotation = new `key_id`); only the fetched-keyset discovery cache has a TTL (24h)
  - Verification chain: shared key → key discovery → signature → expiry → replay protection (persisted `run_id`s in a new `ledge_acquisitions` table) → host allowlist (`acquireAllowedHosts`) for both `upload_url` and `callback_url`; failures return machine-readable reasons and are logged; with no allowlist configured every command is rejected
  - Bundling runs as `AcquireBundleJob` on Craft's queue: preflight (dump command, disk headroom, tmp dir, sodium) → `mysqldump` via Craft's backup pipeline → env/facts manifest (all env vars by default, narrowed by an operator-configured `acquireEnvDenylist`; the plugin's own `LEDGE_*` auth material is always excluded; plus a complete `uris` list of every crawlable URL enumerated across all URL-enabled element types and sites via Craft's element API, so the runner can crawl for regressions without raw SQL) → gzipped tar encrypted with a sealed-key + secretstream construction to the command's `bundle_pubkey` → streamed PUT to `upload_url`; temp files cleaned up on success and failure
  - Best-effort bearer-authenticated callbacks (`acquire.accepted|started|progress|completed|failed`) plus a `GET /_ledge/acquire/<run_id>` status endpoint as pull fallback
  - See `docs/acquire-protocol.md` for the wire protocol, bundle format, and signing snippets
- `/health` root now includes: `platform` (`{name: "craftcms", version, edition}` so Ledge can identify the site type/version without digging into the checks), the project-config `configVersion` (staleness signal for open update PRs), and `acquireEnabled` (whether acquire is configured and runnable on this site, so Ledge knows per-project whether it can issue acquire commands)
- Added a PHPUnit test suite covering the acquire protocol core (`composer test`)

## 5.0.7.2
- Queue check now resolves the queue's effective `channel` the way Craft does internally (falling back to the application component ID when `Queue::$channel` is null) instead of reading the public `$channel` property directly. Previously the property was `null`, so the stale-jobs query filtered on an empty channel and matched no rows — reporting `stale: 0` and `healthy` even when hundreds of jobs were stuck
- Queue check now also flags reserved-but-abandoned jobs as stale (a job whose reservation has expired, `timeUpdated + ttr <= now`), not just jobs that were never reserved

## 5.0.7
- Health endpoint now sends no-cache headers (`Response::setNoCacheHeaders()`) so it is never served from Craft Cloud's static page cache; the cache key ignores the `X-Ledge-Key` header, so a cached `200` would otherwise return stale data and bypass authentication.

## 5.0.6
- Formie check now excludes trashed (soft-deleted) sent notifications from its failed-notification count by joining `elements` and filtering on `dateDeleted`, so resolving failures by trashing them in the Formie control panel clears the check

## 5.0.5
- Environment check no longer reports a var as missing when another scanned config file resolves it (cross-file resolution)
- Freeform and environment checks now explicitly null-guard `Freeform::getInstance()` / `Ledge::getInstance()` and report a clearer degraded result on cold-path failures instead of relying on the outer `Throwable` catch

## 5.0.4
- Freeform check now reads error counts from Freeform's logs via `LoggerService` (`getCombinedLogLineCount(['error'])`) instead of querying nonexistent `freeform_*` tables, fixing the bogus `degraded` "Unable to query Freeform data" result; per-log line totals are reported in the output as context

## 5.0.3
- License check now reads real plugin statuses via `Plugins::getPluginInfo()` (Craft 5's `getPluginLicenseKeyStatus()` always returns `Unknown`)
- Craft license status now read from the `licenseInfo` cache (`App::CACHE_KEY_LICENSE_INFO`) instead of a nonexistent cache key, fixing the bogus `status: false`
- Plugin `licenseKeyStatus` of `unknown` is now resolved to `none` (no license key) or `unverified` (key present but not yet validated by Craft)
- Craft license `mismatched`/`astray` statuses now flag the check as unhealthy (previously only `invalid` did)
- Licensed edition is now read from the `licenseInfo` cache (a string) rather than `getLicensedEdition()`, avoiding `int` vs `CmsEdition` type errors; a mismatch between the licensed and running edition still flags the check unhealthy
- Per-plugin output now includes version, licensed edition, active edition, trial flag, license issues, and last-checked timestamp; the raw license key is intentionally excluded (a `hasLicenseKey` boolean is reported instead)
- Environment check now treats a referenced var as defined when its `CRAFT_`-prefixed equivalent is set (or vice versa), preventing false positives for sites using `CRAFT_DB_*` style env vars
- Environment check returns `degraded` instead of `unhealthy` when variables are referenced but not defined
- Added an `ignoredEnvVars` setting to suppress specific environment variables from the check

## 5.0.1
- Each health check now runs inside its own try/catch; a failing check returns a "Check unavailable" result instead of bringing down the endpoint
- Added a service-level try/catch backstop in `HealthService` so unexpected exceptions can never 500 the response
- Exception messages no longer appear in the response body (they could leak DSN fragments and other sensitive detail); details are written to `Craft::error()` instead
- `/health` now returns `503` when the overall status is `unhealthy` (previously always `200`); `healthy` and `degraded` still return `200`
- Cleaned up the 401 response in `HealthController::beforeAction` to use idiomatic Yii response data flow
- Fix queue check accessing `$queue->channel` as a property instead of calling it as a method
- Normalize Craft edition values for both `int` and `CmsEdition` (enum) returns, so the version and license checks work across Craft 5 point releases

## 5.0.0
- Renamed plugin from Pulse to Ledge
- Versioning now tracks the Craft major version (this is the Craft 5 line)
- Requires Craft CMS 5 and PHP 8.2+
- Package renamed to `ledgehq/ledge-craft` (previously `borders-dev/craft-pulse`)
- Namespace changed to `ledgehq\craftledge`; main class is now `Ledge`
- Plugin handle changed to `ledge` (config file is now `config/ledge.php`)
- Auth env var is now `LEDGE_SECRET_KEY`, header is `X-Ledge-Key`, default endpoint is `/_ledge/health`

## 0.5.3
- Fix queue check: detect stale jobs via direct DB query (previously `timePushed` was not exposed by `getJobInfo()`)
- Replace stuck threshold with configurable `queueAgeThreshold` (default 5 minutes)
- Switch from CP settings page to `config/pulse.php` config file

## 0.5.2
- Run `./craft pulse/generate-key` to generate a key and save it to your `.env` file automatically
- Always return 200 HTTP code on the endpoint

## 0.5.1
- Omit formie/freeform checks from response when plugin not installed
- Include full release notes for all versions between current and latest
- Non-critical Craft/plugin updates now report healthy instead of degraded

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
