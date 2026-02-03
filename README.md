# pulse

Keep a pulse on your Craft CMS websites

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “pulse”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require borders-dev/craft-pulse

# tell Craft to install the plugin
./craft plugin/install pulse

# generate a secret key for the health endpoint
./craft pulse/generate-key
```

## Configuration

Pulse requires a secret key to authenticate requests to the `/_pulse/health` endpoint. You can either:

- Run `./craft pulse/generate-key` to generate a key and save it to your `.env` file automatically
- Add your own key to `.env` manually: `PULSE_SECRET_KEY=your-secret-key`

The health endpoint can then be accessed by including the key in the `X-Pulse-Key` header:

```bash
curl -H "X-Pulse-Key: your-secret-key" https://your-site.com/_pulse/health
```
