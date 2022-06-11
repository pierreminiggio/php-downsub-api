<?php

namespace App\Service;

use Exception;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloader;
use PierreMiniggio\GithubActionRunStarterAndArtifactDownloader\GithubActionRunStarterAndArtifactDownloaderException;

class ActionRunner
{

    public function __construct(private GithubActionRunStarterAndArtifactDownloader $runner)
    {
    }

    public function run(
        string $token,
        string $account,
        string $project,
        string $link
    ): string
    {
        try {
            $artifacts = $this->runner->runActionAndGetArtifacts(
                token: $token,
                owner: $account,
                repo: $project,
                workflowIdOrWorkflowFileName: 'get-subs.yml',
                refreshTime: 70,
                inputs: ['link' => $link]
            );
        } catch (GithubActionRunStarterAndArtifactDownloaderException $e) {
            throw new Exception('Action failed, message : ' . $e->getMessage() . ', trace : ' . json_encode(
                $e->getTrace()
            ));
        }

        $artifactsCount = count($artifacts);

        if ($artifactsCount !== 1) {
            throw new Exception('Bad artifacts count ' . $artifactsCount . (
                $artifactsCount ? ' (' . json_encode($artifacts) . ')' : ''
            ));
        }

        $responseFileName = $artifacts[0];
        $response = file_get_contents($responseFileName);
        unlink($responseFileName);

        return $response;
    }
}
