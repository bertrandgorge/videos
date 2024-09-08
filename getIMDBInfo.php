<?php

require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/includes/imdb.class.php');

$filename = __DIR__ . '/out/all_videos.txt';

if (!file_exists($filename))
{
    echo "Please run buildList.php first.\n";
    exit();
}

$columns = [
    'URL',
    'Titre',
    'Titre FR',
    'Poster URL',
    'Durée',
    'Synopsis',
    'Réalisateur',
    'Presse',
    'metacriticRating',
    'Spectateurs',
    'Acteurs',
    'Genre',
    'Année',
    'Pays',
    'Mots clés',
    'Pas trop long',
    'Date d\'ajout',
    'Nom du fichier',
    'Support',
    'Dossier'];

echo implode("\t", $columns) . "\n";

$imdb_ids = array();

$moviesFiles = file($filename);

$matchCache = __DIR__ . '/out/imdb_urls.txt';
if (file_exists($matchCache))
    $moviesFiles = array_merge($moviesFiles, file($matchCache));


$matchCache = __DIR__ . '/out/Vieilles videos.txt';
if (file_exists($matchCache))
    $moviesFiles = array_merge($moviesFiles, file($matchCache));

foreach ($moviesFiles as $k => $aMovieFile)
{
    $aMovieFile = trim($aMovieFile);
    if ($aMovieFile == '')
        continue;

    $movieFileInfo = explode("\t", $aMovieFile);
    $dateAjout = $movieFileInfo[4] ?? '01/01/1970';

    $filename = $movieFileInfo[0];

    $imdbfilm = new imdb($filename);
    $film = $imdbfilm->getFilmInfo();

    if ($film == 'ignored')
        continue;

    if (empty($film))
    {
        echo "$filename\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t$dateAjout\n";
        continue;
    }

    if (isset($imdb_ids[$film['URL']]))
        continue;

    $imdb_ids[$film['URL']] = true;

    $film['Date d\'ajout'] = $dateAjout;
    $film['Nom du fichier'] = $filename;

    $film['Support'] = $movieFileInfo[3] ?? '';
    $film['Dossier'] = $movieFileInfo[1] ?? '';

    $film['Titre'] = str_replace('&apos;', "'", html_entity_decode($film['Titre']));
    $film['Poster URL'] = '=image("'.$film['Poster URL'].'")';
    $film['Presse'] = str_replace('.', ',', $film['Presse']);

    if ($film['Durée'] < 105)
        $film['Pas trop long'] = "Court";
    else if ($film['Durée'] < 120)
        $film['Pas trop long'] = "Moyen";
    else
        $film['Pas trop long'] = "Long";

    $hours = floor($film['Durée'] / 60);
    $minutes = $film['Durée'] - $hours * 60;
    $film['Durée'] = $hours . 'h' . $minutes;

    foreach ($columns as $key)
        echo $film[$key] . "\t";

    echo "\n";

    if ($k % 10 == 0)
        imdb::saveCachedURLs();
}

imdb::saveCachedURLs();

exit();

