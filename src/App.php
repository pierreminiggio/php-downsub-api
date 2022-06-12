<?php

namespace App;

use App\Service\ActionRunner;
use Benlipp\SrtParser\Parser;
use PierreMiniggio\ConfigProvider\ConfigProvider;

class App
{

    public function __construct(
        private string $projectFolder,
        private ConfigProvider $configProvider,
        private ActionRunner $runner
    )
    {
    }

    public function run(
        string $path,
        ?string $queryParameters,
        ?string $authHeader
    ): void
    {
        $config = $this->configProvider->get();
        $apiToken = $config['apiToken'];

        if (! $authHeader || $authHeader !== 'Bearer ' . $config['apiToken']) {
            http_response_code(401);
            
            return;
        }

        if ($path === '/') {
            http_response_code(404);
            
            return;
        }

        $maybeAYoutubeUrl = substr($path, 1);
        $youtubeUrlStart = 'https://www.youtube.com/watch?v=';

        if (! str_starts_with($maybeAYoutubeUrl, $youtubeUrlStart)) {
            http_response_code(400);
            
            return;
        }

        $youtubeUrl = $maybeAYoutubeUrl;

        $videoId = substr($youtubeUrl, strlen($youtubeUrlStart));

        $videoCurl = curl_init('https://youtube-video-infos-api.ggio.fr/' . $videoId);
        curl_setopt_array($videoCurl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json' , 'Authorization: Bearer ' . $apiToken]
        ]);
        curl_exec($videoCurl);
        $httpCode = curl_getinfo($videoCurl)['http_code'];
        curl_close($videoCurl);

        if ($httpCode === 404) {
            http_response_code(400);

            return;
        }

        if ($httpCode !== 200) {
            http_response_code(500);
            
            return;
        }

        // check for video

        $projects = $config['captchaProjects'];
        $project = $projects[array_rand($projects)];

        set_time_limit(1200);
        $response = trim($this->runner->run(
            $project['token'],
            $project['account'],
            $project['project'],
            $youtubeUrl
        ));

        if (! $response) {
            http_response_code(500);

            return;
        }

        $jsonResponse = json_decode($response, true);

        if (! $jsonResponse) {
            http_response_code(500);

            return;
        }

        $languagesAndSubtitles = [];

        $cacheFolder = $this->projectFolder . 'cache' . DIRECTORY_SEPARATOR;

        foreach ($jsonResponse as $entry) {
            $language = $entry['language'] ?? null;

            if (! $language) {
                continue;
            }

            $languageName = $language['name'] ?? null;

            if (! $languageName) {
                continue;
            }

            $subtitleFileUrl = $entry['file'] ?? null;

            if (! $subtitleFileUrl) {
                continue;
            }

            $subtitleFileName = $cacheFolder . $videoId . '-' . base64_encode($languageName);

            $fp = fopen($subtitleFileName, 'w+');
            $subtitleCurlResponse = curl_init($subtitleFileUrl);
            curl_setopt($subtitleCurlResponse, CURLOPT_TIMEOUT, 600);
            curl_setopt($subtitleCurlResponse, CURLOPT_FILE, $fp);
            curl_setopt($subtitleCurlResponse, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($subtitleCurlResponse);
            $httpCode = curl_getinfo($subtitleCurlResponse, CURLINFO_HTTP_CODE);
            curl_close($subtitleCurlResponse);
            fclose($fp);

            if ($httpCode !== 200) {
                if (file_exists($subtitleFileName)) {
                    unlink($subtitleFileName);
                }
                continue;
            }

            $parser = new Parser();
            $parser->loadFile($subtitleFileName);
            $captions = $parser->parse();

            $subtitles = [];

            foreach ($captions as $caption) {
                $subtitles[] = [
                    'startTime' => $caption->startTime,
                    'endTime' => $caption->endTime,
                    'text' => $caption->text
                ];
            }

            $languagesAndSubtitles[] = [
                'language' => $languageName,
                'subtitles' => $subtitles
            ];

            unlink($subtitleFileName);
        }

        http_response_code(200);
        echo json_encode($languagesAndSubtitles);
    }
}
