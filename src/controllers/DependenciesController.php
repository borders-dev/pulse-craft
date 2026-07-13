<?php

declare(strict_types=1);

namespace ledgehq\craftledge\controllers;

use Composer\InstalledVersions;
use Craft;
use craft\web\Controller;
use ledgehq\craftledge\Ledge;
use yii\web\Response;

/**
 * Installed package inventory for Ledge's security-advisory matching.
 *
 * Sourced strictly from Composer's runtime data (InstalledVersions), never a
 * lockfile: it reflects what is actually deployed, and on a production
 * `composer install --no-dev` the dev dependencies simply are not present.
 */
class DependenciesController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Craft::$app->getResponse()->setNoCacheHeaders();

        $error = $this->validateSecretKey();
        if ($error !== null) {
            $response = Craft::$app->getResponse();
            $response->setStatusCode(401);
            $response->format = Response::FORMAT_JSON;
            $response->data = ['error' => $error];
            return false;
        }

        return true;
    }

    public function actionIndex(): Response
    {
        $rootPackage = InstalledVersions::getRootPackage()['name'];
        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $name) {
            if ($name === $rootPackage) {
                continue;
            }

            $version = InstalledVersions::getPrettyVersion($name);

            if ($version === null) {
                continue;
            }

            $packages[] = [
                'name' => strtolower($name),
                'version' => ltrim($version, 'v'),
            ];
        }

        usort($packages, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $this->asJson([
            'composer' => $packages,
            'generatedAt' => date('c'),
            'hash' => hash('sha256', json_encode($packages)),
        ]);
    }

    private function validateSecretKey(): ?string
    {
        $settings = Ledge::getInstance()->getSettings();
        $configuredKey = $settings->getSecretKey();

        if (empty($configuredKey)) {
            return 'LEDGE_SECRET_KEY is not configured';
        }

        $request = Craft::$app->getRequest();
        $providedKey = $request->getHeaders()->get('X-Ledge-Key')
            ?? $request->getQueryParam('key');

        if (empty($providedKey) || !hash_equals($configuredKey, $providedKey)) {
            return 'Unauthorized';
        }

        return null;
    }
}
