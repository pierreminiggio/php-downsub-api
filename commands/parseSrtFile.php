<?php

use Benlipp\SrtParser\Parser;

$currentDir = __DIR__ . DIRECTORY_SEPARATOR;

require $currentDir . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$subtitleFileName = $currentDir . 'subtitles.srt';

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

file_put_contents($currentDir . 'subtitles.json', json_encode($subtitles));
