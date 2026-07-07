# Ledge

Health, uptime, and compliance monitoring for your Craft CMS sites.

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Ledge”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require ledgehq/ledge-craft

# tell Craft to install the plugin
./craft plugin/install ledge

# generate a secret key for the health endpoint
./craft ledge/generate-key
```

## Configuration

Ledge requires a secret key to authenticate requests to the `/_ledge/health` endpoint. You can either:

- Run `./craft ledge/generate-key` to generate a key and save it to your `.env` file automatically
- Add your own key to `.env` manually: `LEDGE_SECRET_KEY=your-secret-key`

The health endpoint can then be accessed by including the key in the `X-Ledge-Key` header:

```bash
curl -H "X-Ledge-Key: your-secret-key" https://your-site.com/_ledge/health
```

## Acquire (automated update testing & backups)

Beyond `/health`, Ledge can act as an **acquisition agent**: on a signed command from the Ledge service, the plugin produces an encrypted bundle of the site (a database dump — plus an environment + crawlable-URL manifest for update-test runs) and uploads it to object storage. This powers automated "does this update break the site?" testing and scheduled database backups.

This capability is **off by default** and higher-privilege than `/health`: every command must carry an Ed25519 signature (verified against Ledge's published keyset) on top of the shared key, and nothing is registered until an operator opts in. To enable it:

```php
// config/ledge.php
return [
    'secretKey' => '$LEDGE_SECRET_KEY',
    'acquireEnabled' => true,                                    // or LEDGE_ACQUIRE_ENABLED=true
    'acquireAllowedHosts' => ['app.ledgehq.app', '*.ledgehq.app'], // where bundles/callbacks may be sent
];
```

While disabled, the acquire routes return `404` and the plugin behaves exactly as a health-only install. A companion opt-in endpoint, `GET /_ledge/uris` (enabled with `urisEnabled` / `LEDGE_URIS_ENABLED`), returns the site's crawlable public URL map on demand.

See [docs/acquire-protocol.md](docs/acquire-protocol.md) for the full wire protocol, bundle format, callback events, and command-signing snippets.
