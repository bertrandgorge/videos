<?php

if (!file_exists(__DIR__ . "/../api-allocine-helper/api-allocine-helper.php"))
{
	echo "git clone --branch fix/new-algo https://github.com/gromez/api-allocine-helper.git api-allocine-helper\n";
	die();
}

require_once __DIR__ . "/../api-allocine-helper/api-allocine-helper.php";


class myAlloCine
{
	public function GetAllocine()
	{
		if (!file_exists(__DIR__.'/../out/urls.txt'))
		{
			echo "List all Allocine URLs in out/urls.txt\n";
			die();
		}

		$films = file(__DIR__.'/../out/urls.txt');

		if (empty($films))
		{
			echo "No urls found in " . __DIR__.'/../out/urls.txt' . "\n";
		}

		$filename = dirname(__DIR__) . '/out/allocine_info.txt';

		$fp = fopen($filename, 'a');

		if (empty($fp)) die();

		$foundfilms = array();

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
			elseif (preg_match('@ficheserie-([0-9]+)/@', $filmURL, $matches, PREG_OFFSET_CAPTURE, 3))
			{
				// http://www.allocine.fr/series/ficheserie-4528/saison-23176/
				$filmID = $matches[1][0];
				$method = 'tvseries';
			}
			
			if (empty($filmID))
			{
				echo "Failed to match filmID in '$filmURL'\n";
				continue;
			}
			
			$foundfilms[$method . $filmID] = array('method' => $method, 'filmID' => $filmID, 'filmURL' => $filmURL, 'done' => false);
		}

		foreach ($foundfilms as $k => $aFilm)
		{
			if ($aFilm['done'])
				continue;

			$filmInfo = $this->getFilmInfoForId($aFilm['filmID'], $aFilm['method'], $aFilm['filmURL']);

			if (empty($filmInfo))
				continue;
			
			$films[$k]['done'] = true;

			fwrite($fp, implode("\t", $filmInfo) . "\n");
		}

		fclose($fp);
	}
	
	private function getFilmInfoForId($filmID, $method, $filmURL)
	{
		$helper = new AlloHelper;
		$helper->setUtf8Decode(false);

		$filmURL = trim($filmURL);
		if (empty($filmURL))
		{
			throw new Exception("Empty filmURL", 1);
		}

		if (empty($filmID))
		{
			// echo "Failed to match $filmURL\n";
			throw new Exception("Empty filmId", 1);
		}

		$cacheFilename = __DIR__ . '/../cache/' . $method . $filmID . '.json';
		if (file_exists($cacheFilename))
		{
			$cache = @file_get_contents($cacheFilename);
			if (!empty($cache))
			{
				$filmInfo = json_decode($cache, true);
				if (!empty($filmInfo['url']))
					return $filmInfo;
			}	
		}

		$filmInfo = array();

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

		$filmInfo['genres'] = $this->getArrayStrings($movie->genre);

		if (isset($movie->yearStart))
			$filmInfo['productionYear'] = $movie->yearStart;
		else
			$filmInfo['productionYear'] = $movie->productionYear;

		$filmInfo['nationality'] = $this->getArrayStrings($movie->nationality);

		if (isset($movie->tag))
			$filmInfo['tags'] = $this->getArrayStrings($movie->tag);
		else
			$filmInfo['tags'] = '';

		// save in cache
		file_put_contents($cacheFilename, json_encode($filmInfo, JSON_THROW_ON_ERROR));

		return $filmInfo;
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
}