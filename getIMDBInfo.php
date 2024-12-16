<?php

require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/includes/imdb.class.php');

$filename = __DIR__ . '/out/all_videos.txt';

if (!file_exists($filename))
{
    echo "Please run buildList.php first.\n";
    exit();
}

imdb::printColumns();

$imdb_ids = array();

$moviesFiles = file($filename);


$matchCache = __DIR__ . '/out/Vieilles videos.txt';
if (file_exists($matchCache))
    $moviesFiles = array_merge($moviesFiles, file($matchCache));

foreach ($moviesFiles as $k => $aMovieFile)
{
    $film = imdb::findInfoForFilename($aMovieFile);
    if (empty($film))
        continue;

    if (!empty($film['URL']) && isset($imdb_ids[$film['URL']]))
        continue;

    if (!empty($film['URL']))
        $imdb_ids[$film['URL']] = true;

    imdb::printInfo($film);

    if ($k % 10 == 0)
        imdb::saveCachedURLs();
}

imdb::saveCachedURLs();

$matchCache = __DIR__ . '/out/imdb_urls.txt';
if (file_exists($matchCache)) {
    $cachedFilms = file($matchCache);
    foreach ($cachedFilms as $row) {
        $parts = explode("\t", $row);
        if (!isset($parts[1]))
            continue;

        $imdbfilm = new imdb($parts[0]);
        $film = $imdbfilm->getFilmInfo($parts[1]);

        if (isset($imdb_ids[$film['URL']]))
            continue;

        $imdb_ids[$film['URL']] = true;

        imdb::printInfo($film);
    }
}

imdb::closeFile();

exit();

