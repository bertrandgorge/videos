<?php

class imdb
{
    static private $cachedURLs = array();
    static private $fp = null;
    static private $imdbApi = null;
    static private $columns = [];

    private $filename = '';

    public function __construct($filename)
    {
        $this->filename = trim($filename);

        if (empty(self::$imdbApi))
        {
            self::$imdbApi = new \hmerritt\Imdb();
        }

        imdb::getCachedURLs();
    }

    static public function printColumns()
    {
        self::$columns = [
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

        imdb::writeRow( implode("\t", self::$columns) . "\n");
    }

    public function getFilmInfo($url = false)
    {
        $imdbId = '';
        $match = array();
        if (!empty($url)) {
            if (preg_match('@tt([0-9]+)@', $url, $match)) {
                $imdbId = 'tt' . $match[1];
            }
            else
                return ['ignored' => true, 'URL' => $this->filename];
        }

        if (empty($imdbId))
            $imdbId = $this->findFilmForFilename();

        if ($imdbId == 'ignored')
            return ['ignored' => true, 'URL' => $this->filename];

        if (empty($imdbId))
            return ['Not found' => true, 'URL' => $this->filename];

        $film = array();

        if (is_scalar($imdbId))
        {
            $cacheFilename = __DIR__ . '/../cache/imdb' . str_replace('tt', '', $imdbId) . '.json';
            if (file_exists($cacheFilename))
            {
                $cache = file_get_contents($cacheFilename);
                if (!empty($cache))
                {
                    $film = json_decode($cache, true);

                    if (empty($film['Acteurs'])) {
                        echo "No actors for ".$imdbId."\n";
                        $film = array(); // force re-fetch
                    }                    
                }
            }
        }

        if (empty($film['URL'])) {
            // Fetch from IMDB using new API
            $filmData = self::$imdbApi->film($imdbId, [
                'curlHeaders' => ['Accept-Language: fr-FR,fr,en;q=0.5'],
                'cache' => false // We manage our own cache
            ]);

            if (empty($filmData) || empty($filmData['title'])) {
                return ['Not found' => true, 'URL' => $this->filename];
            }

            echo "Getting IMDB info for ".$filmData['title']."\n";

            $film['URL'] = 'https://www.imdb.com/title/' . $imdbId . '/';
            $film['Titre'] = $filmData['title'] ?? '';
            $film['Titre FR'] = $filmData['title'] ?? '';
            $film['Presse'] = str_replace('.', ',', $filmData['rating'] ?? '');
            $film['Spectateurs'] = $filmData['rating_votes'] ?? '';
            $film['metacriticRating'] = '';
            $film['Mots clés'] = '';
            $film['Pays'] = '';
            $film['Genre'] = implode(', ', $filmData['genres'] ?? []);
            $film['Poster URL'] = $filmData['poster'] ?? '';
            $film['Durée'] = $filmData['length'] ?? '';
            $film['Poster'] = '';
            $film['Synopsis'] = $filmData['plot'] ?? '';
            $film['ignored'] = false;
            $film['Not found'] = false;

            $directors = array();
            if (!empty($filmData['cast'])) {
                foreach ($filmData['cast'] as $castMember) {
                    if (stripos($castMember['character'] ?? '', 'director') !== false || 
                        stripos($castMember['character'] ?? '', 'réalisateur') !== false) {
                        $directors[] = $castMember['actor'];
                    }
                }
            }
            $film['Réalisateur'] = implode(', ', $directors);

            $actors = array();
            if (!empty($filmData['cast'])) {
                $count = 0;
                foreach ($filmData['cast'] as $castMember) {
                    if ($count >= 5) break; // Limit to 5 actors
                    if (stripos($castMember['character'] ?? '', 'director') === false) {
                        $actors[] = $castMember['actor'];
                        $count++;
                    }
                }
            }
            $film['Acteurs'] = implode(', ', $actors);
            $film['Année'] = $filmData['year'] ?? '';

            $cacheFilename = __DIR__ . '/../cache/imdb' . str_replace('tt', '', $imdbId) . '.json';

            try {
                file_put_contents($cacheFilename, json_encode($film, JSON_THROW_ON_ERROR));
            } catch (Exception $e) {
                echo 'Problème d\'encodage JSON: ',  $e->getMessage(), "\n";
                print_r($film);
                exit();
            }
        }

        $film['Titre'] = str_replace('&apos;', "'", html_entity_decode($film['Titre'] ?? ''));
        if (!empty($film['Poster URL']))
            $film['Poster URL'] = '=image("'.$film['Poster URL'].'")';

        $film['Presse'] = str_replace('.', ',', $film['Presse'] ?? '');

        if (isset($film['Durée'])) {
//            $film['Pas trop long'] = ($film['Durée'] ?? 0) < 120 ? 'Oui' : 'Non';

            if (!is_numeric($film['Durée']))
                echo $cacheFilename . "\n";
            else {
                if ($film['Durée'] < 105)
                    $film['Pas trop long'] = "Court";
                else if ($film['Durée'] < 120)
                    $film['Pas trop long'] = "Moyen";
                else
                    $film['Pas trop long'] = "Long";

                $hours = floor($film['Durée'] / 60);
                $minutes = $film['Durée'] - $hours * 60;
                $film['Durée'] = $hours . 'h' . $minutes;
            }
        }

        return $film;
    }

    private function findFilmForFilename()
    {
        if (isset(self::$cachedURLs[$this->filename]))
        {
            $url = self::$cachedURLs[$this->filename];

            $movieId = false;
            $matches = array();
            if (preg_match('@tt([0-9]+)[/]*@', $url, $matches))
                $movieId = 'tt' . $matches[1];
            else
                return 'ignored';

            return $movieId;
        }

        $titleToSearch = trim($this->filename);
        $titleToSearch = preg_replace('@\.(avi|mkv|mp4|ts|m2ts)@', '', $titleToSearch); // remove the extension
        $titleToSearch = preg_replace('@[0-9]+p.*$@', '', $titleToSearch); // remove the resolution
        $titleToSearch = preg_replace('@\(?([0-9]{4})\)?.*@', ' $1', $titleToSearch); // remove the year
        $titleToSearch = preg_replace('@\[[^]]+\]@', '', $titleToSearch); // remove anything in []
        $titleToSearch = str_replace('.', ' ', trim($titleToSearch));

        try {
            $results = self::$imdbApi->search($titleToSearch, [
                'category' => 'tt', // Films only
                'curlHeaders' => ['Accept-Language: fr-FR,fr,en;q=0.5']
            ]);

            if (empty($results) || empty($results['results'])) {
                self::$cachedURLs[$this->filename] = '';
                return false;
            }

            foreach ($results['results'] as $result)
            {
                // Skip non-film results
                if (empty($result['imdb'])) {
                    continue;
                }

                // Get film details to check genre
                $filmDetails = self::$imdbApi->film($result['imdb'], [
                    'curlHeaders' => ['Accept-Language: fr-FR,fr,en;q=0.5'],
                    'cache' => false
                ]);

                $genres = $filmDetails['genres'] ?? [];
                
                // Skip certain genres
                $skipGenres = ['Court-métrage', 'Talk-show', 'Short', 'Talk-Show'];
                $skip = false;
                foreach ($genres as $genre) {
                    if (in_array($genre, $skipGenres)) {
                        $skip = true;
                        break;
                    }
                }
                
                if ($skip) {
                    continue;
                }

                // Skip if no votes
                if (empty($filmDetails['rating_votes'])) {
                    continue;
                }

                $url = 'https://www.imdb.com/title/' . $result['imdb'] . '/';
                self::$cachedURLs[$this->filename] = $url;
                return $result['imdb'];
            }
        } catch (Exception $e) {
            echo 'Problème recherche IMDB: ',  $e->getMessage(), "\n";
            print_r($this->filename);
            exit();
        }

        self::$cachedURLs[$this->filename] = '';

        return false;
    }

    static private function getCachedURLs()
    {
        if (!empty(self::$cachedURLs))
            return;

        self::$cachedURLs = array();

        $matchCache = dirname(__DIR__) . '/out/imdb_urls.txt';

        if (file_exists($matchCache))
        {
            $cachedURLS = file($matchCache);
            foreach ($cachedURLS as $aMatch)
            {
                $parts = explode("\t", trim($aMatch));
                if (!empty($parts[1]))
                    self::$cachedURLs[$parts[0]] = $parts[1];
            }
        }
    }

    static public function saveCachedURLs()
    {
        $matchCache = dirname(__DIR__) . '/out/imdb_urls.txt';

        $filecontent = '';
        foreach (self::$cachedURLs as $k => $v)
            $filecontent .= $k . "\t" . $v . "\n";

        file_put_contents($matchCache, $filecontent);
    }

    static public function writeRow($str, $bflush = false)
    {
        $filename = __DIR__ . '/../out/imdbinfo.txt';

        if (empty(self::$fp)) {
            self::$fp = fopen($filename, 'w');

            if (empty(self::$fp)) die();
        }

        fwrite(self::$fp, $str);

        if ($bflush)
            fflush(self::$fp);
    }

    static public function closeFile()
    {
        fclose(self::$fp);
    }

    static public function findInfoForFilename($fileRow)
    {
        $movieFileInfo = explode("\t", $fileRow);
        $dateAjout = $movieFileInfo[4] ?? '01/01/1970';

        $filesize = $movieFileInfo[6] ?? 0;
        if ($filesize > 0 && $filesize < 200)
            return null; // Sample file

        $filename = $movieFileInfo[0];

        $imdbfilm = new imdb($filename);
        $film = $imdbfilm->getFilmInfo();

        $film['Date d\'ajout'] = $dateAjout;
        $film['Nom du fichier'] = trim($filename);

        if (!empty($movieFileInfo[4]))
        {
            $film['Support'] = $movieFileInfo[3] ?? '';
            $film['Dossier'] = $movieFileInfo[1] ?? '';
        }
        else
        {
            $film['Support'] = '';
            $film['Dossier'] = '';
        }

        return $film;
    }

    static public function printInfo($film)
    {
        if (!empty($film['ignored']))
            return;

        if (!empty($film['Not found']))
        {
            imdb::writeRow(($film['Nom du fichier'] ?? '') . "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t" . ($film['Date d\'ajout']  ?? '') . "\n");
            return;
        }

        foreach (self::$columns as $key)
            imdb::writeRow(($film[$key] ?? '') . "\t");

        imdb::writeRow("\n", true);
    }
}