<?php

require_once(__DIR__.'/allocine.class.php');

define('ALLOCINE_PARTNER_KEY', '100043982026');
define('ALLOCINE_SECRET_KEY', '29d185d98c984a359e6e6f26a0474269');

$allocine = new Allocine(ALLOCINE_PARTNER_KEY, ALLOCINE_SECRET_KEY);

// $result = $allocine->get(27405);
// echo $result;
// exit();


$films = file(__DIR__.'/films.txt');

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

	$result = $allocine->get($filmID, $method);
	$film = json_decode($result, true);

	$bSuccess = !empty($film[$method]);
	if (!$bSuccess)
	{
		// Retry 2 or 3 times
		for ($i = 0; $i < 3; $i++)
		{
			$result = $allocine->get($filmID, $method);
			$film = json_decode($result, true);
			$bSuccess = !empty($film[$method]);
			if ($bSuccess)
				break;
		}

		if (!$bSuccess)
		{
			echo "Failed to retrieve info for $filmID\n";
			print_r($film);
			continue;
		}
	}

	$filmInfo = array();
	$filmInfo['url'] = trim($filmURL);

	if (isset($film[$method]['title']))
		$filmInfo['originalTitle'] = $film[$method]['title'];
	else
		$filmInfo['originalTitle'] = $film[$method]['originalTitle'];

	if (!empty($film[$method]['poster']['href']))
		$filmInfo['poster'] = '=IMAGE("'.$film[$method]['poster']['href'].'"; 1)';
	else
		$filmInfo['poster'] = '';

	if (isset( $film[$method]['runtime']))
	{
		$duration = $film[$method]['runtime'];

		$hours = floor($duration / 3600);
		$minutes = floor(($duration - $hours * 3600) / 60);
	}
	else if (isset( $film[$method]['formatTime']))
	{
		$duration = $film[$method]['formatTime'];

		$hours = floor($duration / 60);
		$minutes = floor($duration - $hours * 60);
	}

	$filmInfo['duration']  = $hours . 'h' . $minutes . "min";

	if (isset($film[$method]['synopsisShort']))
		$filmInfo['synopsisShort'] = str_replace("\r\n", " ", $film[$method]['synopsisShort']);
	else
		$filmInfo['synopsisShort'] = '';

	if (isset($film[$method]['castingShort']['directors']))
		$filmInfo['directors'] = $film[$method]['castingShort']['directors'];
	else if (isset($film[$method]['castingShort']['creators']))
		$filmInfo['directors'] = $film[$method]['castingShort']['creators'];
	else
		$filmInfo['directors'] = '';

	$filmInfo['pressRating'] = isset($film[$method]['statistics']['pressRating']) ? str_replace('.', ',', $film[$method]['statistics']['pressRating']) : '';
	$filmInfo['userRating'] = isset($film[$method]['statistics']['userRating']) ? str_replace('.', ',', $film[$method]['statistics']['userRating']) : '';

	if (isset($film[$method]['castingShort']['actors']))
		$filmInfo['actors'] = str_replace("\t", " ", $film[$method]['castingShort']['actors']);
	else
		$filmInfo['actors'] = '';

	$filmInfo['genres'] = getArrayStrings($film[$method]['genre']);

	if (isset($film[$method]['yearStart']))
		$filmInfo['productionYear'] = $film[$method]['yearStart'];
	else
		$filmInfo['productionYear'] = $film[$method]['productionYear'];

	$filmInfo['nationality'] = getArrayStrings($film[$method]['nationality']);

	if (isset($film[$method]['tag']))
		$filmInfo['tags'] = getArrayStrings($film[$method]['tag']);
	else
		$filmInfo['tags'] = '';

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