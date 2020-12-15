<?php

if (!file_exists(__DIR__ . "/../api-allocine-helper/api-allocine-helper.php"))
{
	echo "git clone --branch fix/new-algo https://github.com/gromez/api-allocine-helper.git api-allocine-helper\n";
	die();
}

require_once __DIR__ . "/../api-allocine-helper/api-allocine-helper.php";

$helper = new AlloHelper;
$helper->setUtf8Decode(false);

if (!file_exists(__DIR__.'/../films.txt'))
{
	echo "List all Allocine URLs in films.txt\n";
	die();
}
$films = file(__DIR__.'/../films.txt');

foreach ($films as $filmURL)
{
	$filmURL = trim($filmURL);
	if (empty($filmURL))
		continue;

	$matches = array();
	$filmID = null;
	$method = 'movie';

	if (preg_match('@cfilm=([0-9]+)\.htm@', $filmURL, $matches, PREG_OFFSET_CAPTURE, 3))
		$filmID = $matches[1][0];
	elseif (preg_match('@fichefilm-([0-9]+)/@', $filmURL, $matches, PREG_OFFSET_CAPTURE, 3))
		$filmID = $matches[1][0];
	elseif (preg_match('@ficheserie_gen_cserie=([0-9]+)\.htm@', $filmURL, $matches, PREG_OFFSET_CAPTURE, 3))
	{
		$filmID = $matches[1][0];
		$method = 'tvseries';
	}
	
	if (empty($filmID))
	{
		// echo "Failed to match $filmURL\n";
		continue;
	}

	// Avoid duplicates
	if (isset($filmsDone[$method . $filmID]))
		continue;
	
	$filmsDone[$method . $filmID] = true;

	$filmInfo = array();

	$cacheFilename = __DIR__ . '/../cache/' . $method . $filmID . '.json';
	if (file_exists($cacheFilename))
	{
		$cache = @file_get_contents($cacheFilename);
		if (!empty($cache))
		{
			$filmInfo = json_decode($cache, true);
			if (empty($filmInfo['url']))
				$filmInfo = array();
		}	
	}

	if (empty($filmInfo['url']))
	{
		switch ($method) {
			case 'movie':
				$movie = $helper->movie( $filmID );
				$duration = $movie->runtime;

				$hours = floor($duration / 3600);
				$minutes = floor(($duration - $hours * 3600) / 60);
				break;

			case 'tvseries':
				$movie = $helper->tvserie( $filmID, 'small' );
				$duration = $movie->formatTime;

				$hours = floor($duration / 60);
				$minutes = floor($duration - $hours * 60);			
				break;

			default:
				# code...
				break;
			}
		
		$filmInfo['url'] = trim($filmURL);

		// print_r($movie);

		if (!empty($movie->title))
			$filmInfo['originalTitle'] = $movie->title;
		else
			$filmInfo['originalTitle'] = $movie->originalTitle;

		$posterURL = '';
		if (get_class($movie->poster) == 'AlloData' && !empty($movie->poster['href']))
			$posterURL = $movie->poster['href'];
		else if (get_class($movie->poster) == 'AlloImage')
			$posterURL = $movie->poster->url();

		if (!empty($posterURL))
		{
			$filmInfo['poster'] = '=IMAGE("'. $posterURL .'"; 1)';
			$filmInfo['poster_url'] = $posterURL;
		}	
		else
		{
			$filmInfo['poster'] = '';
			$filmInfo['poster_url'] = '';
		}

		$filmInfo['duration']  = $hours . 'h' . $minutes . "min";

		if (isset($movie->synopsisShort))
			$filmInfo['synopsisShort'] = preg_replace('@<[^>]+>@', '', str_replace("\r\n", " ", $movie->synopsisShort));
		else
			$filmInfo['synopsisShort'] = '';

		if (isset($movie->castingShort['directors']))
			$filmInfo['directors'] = $movie->castingShort['directors'];
		else if (isset($movie->castingShort['creators']))
			$filmInfo['directors'] = $movie->castingShort['creators'];
		else
			$filmInfo['directors'] = '';

		$filmInfo['pressRating'] = isset($movie->statistics['pressRating']) ? str_replace('.', ',', $movie->statistics['pressRating']) : '';
		$filmInfo['userRating'] = isset($movie->statistics['userRating']) ? str_replace('.', ',', $movie->statistics['userRating']) : '';

		if (isset($movie->castingShort['actors']))
			$filmInfo['actors'] = str_replace("\t", " ", $movie->castingShort['actors']);
		else
			$filmInfo['actors'] = '';

		$filmInfo['genres'] = getArrayStrings($movie->genre);

		if (isset($movie->yearStart))
			$filmInfo['productionYear'] = $movie->yearStart;
		else
			$filmInfo['productionYear'] = $movie->productionYear;

		$filmInfo['nationality'] = getArrayStrings($movie->nationality);

		if (isset($movie->tag))
			$filmInfo['tags'] = getArrayStrings($movie->tag);
		else
			$filmInfo['tags'] = '';

		// save in cache
		file_put_contents($cacheFilename, json_encode($filmInfo, JSON_THROW_ON_ERROR));
	}

	echo implode("\t", $filmInfo) . "\n";
}

function getArrayStrings($in)
{
	$genres = array();
	if (empty($in))
		return '';

	foreach($in as $genre)
		$genres[] = $genre['$'];

	return implode(', ', $genres);
}