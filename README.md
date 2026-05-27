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
