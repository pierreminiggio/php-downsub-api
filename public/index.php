<?php

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\App;
use App\Service\ActionRunner;
use PierreMiniggio\ConfigProvider\ConfigProvider;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderFactory;

$projectFolder = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$configProvider = new ConfigProvider($projectFolder);

$app = new App(
    $projectFolder,
    $configProvider,
    new ActionRunner((new GithubActionRunStarterAndArtifactDownloaderFactory())->make())
);

/** @var string $requestUrl */
$requestUrl = $_SERVER['REQUEST_URI'];

/** @var string|null $queryParameters */
$queryParameters = ! empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : null;

/** @var string $calledEndPoint */
$calledEndPoint = $queryParameters
    ? str_replace($queryParameters, '', $requestUrl)
    : $requestUrl
;

if (strlen($calledEndPoint) > 1 && substr($calledEndPoint, -1) === '/') {
    /** @var string $calledEndPoint */
    $calledEndPoint = substr($calledEndPoint, 0, -1);
}

$app->run($requestUrl, $queryParameters, $_SERVER['HTTP_AUTHORIZATION'] ?? null);

exit;
