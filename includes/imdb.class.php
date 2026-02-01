<?php

class imdb
{
    static private $cachedURLs = array();
    static private $fp = null;
    static private $tmdbClient = null;
    static private $columns = [];

    private $filename = '';

    public function __construct($filename)
    {
        $this->filename = trim($filename);

        if (empty(self::$tmdbClient))
        {
            $config = require __DIR__ . '/../config/tmdb.php';
            
            // Déterminer le type de token à utiliser
            if (!empty($config['auth_type']) && $config['auth_type'] === 'bearer' && !empty($config['bearer_token'])) {
                $token = new \Tmdb\Token\Api\BearerToken($config['bearer_token']);
            } elseif (!empty($config['api_key'])) {
                $token = $config['api_key'];
            } else {
                throw new Exception('Aucune clé API ou Bearer Token configuré dans config/tmdb.php');
            }
            
            // Créer l'EventDispatcher
            $eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
            
            // Créer le client
            self::$tmdbClient = new \Tmdb\Client([
                'api_token' => $token,
                'secure' => true,
                'event_dispatcher' => [
                    'adapter' => $eventDispatcher
                ]
            ]);
            
            // Enregistrer les listeners requis
            $requestListener = new \Tmdb\Event\Listener\RequestListener(
                self::$tmdbClient->getHttpClient(), 
                $eventDispatcher
            );
            $eventDispatcher->addListener(\Tmdb\Event\RequestEvent::class, $requestListener);
            
            // Listeners pour les headers
            $apiTokenListener = new \Tmdb\Event\Listener\Request\ApiTokenRequestListener(
                self::$tmdbClient->getToken()
            );
            $eventDispatcher->addListener(\Tmdb\Event\BeforeRequestEvent::class, $apiTokenListener);
            
            $acceptJsonListener = new \Tmdb\Event\Listener\Request\AcceptJsonRequestListener();
            $eventDispatcher->addListener(\Tmdb\Event\BeforeRequestEvent::class, $acceptJsonListener);
            
            $jsonContentTypeListener = new \Tmdb\Event\Listener\Request\ContentTypeJsonRequestListener();
            $eventDispatcher->addListener(\Tmdb\Event\BeforeRequestEvent::class, $jsonContentTypeListener);
            
            $userAgentListener = new \Tmdb\Event\Listener\Request\UserAgentRequestListener();
            $eventDispatcher->addListener(\Tmdb\Event\BeforeRequestEvent::class, $userAgentListener);
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
        $movieId = ''; // Peut être un ID TMDB ou IMDB
        $match = array();
        $cachedFilm = array();

        if (!empty($url)) {
            // Vérifier si c'est une URL TMDB
            if (preg_match('@themoviedb\.org/movie/([0-9]+)@', $url, $match)) {
                $movieId = $match[1]; // ID TMDB
            }
            // Vérifier si c'est un ID IMDB
            elseif (preg_match('@tt([0-9]+)@', $url, $match)) {
                $movieId = 'tt' . $match[1];
            }
            else
                return ['ignored' => true, 'URL' => $this->filename];
        }

        if (empty($movieId))
            $movieId = $this->findFilmForFilename();

        if ($movieId == 'ignored')
            return ['ignored' => true, 'URL' => $this->filename];

        if (empty($movieId))
            return ['Not found' => true, 'URL' => $this->filename];

        $film = array();

        if (is_scalar($movieId))
        {
            // Cache filename basé sur l'ID (TMDB ou IMDB)
            $cacheId = is_string($movieId) && strpos($movieId, 'tt') === 0 ? str_replace('tt', '', $movieId) : $movieId;
            $cacheFilename = __DIR__ . '/../cache/imdb' . $cacheId . '.json';
            if (file_exists($cacheFilename))
            {
                $cache = file_get_contents($cacheFilename);
                if (!empty($cache))
                {
                    $film = json_decode($cache, true);
                    $cachedFilm = $film;

                    if (empty($film['Réalisateur']) || empty($film['Acteurs']) || empty($film['URL'])) {
                        echo "No actors for ".$movieId."\n";
                        $film = array(); // force re-fetch
                    }                    
                }
            }
        }

        if (empty($film['URL'])) {
            // $movieId peut être soit un ID TMDB soit un ID IMDB (tt...)
            try {
                // Si c'est un ID IMDB, on cherche le film correspondant sur TMDB
                if (is_string($movieId) && strpos($movieId, 'tt') === 0) {

                    $titleToSearch = $this->cleanTitleBeforeSearch($this->filename);

                    // Extract the year from filename if possible
                    $year = null;
                    if (preg_match('@(?:19|20)([0-9]{2})@', $titleToSearch, $matches)) {
                        $year = $matches[0];

                        // If year found, remove it from title to search
                        $titleToSearch = trim(preg_replace('@(?:19|20)([0-9]{2})@', '', $titleToSearch));
                    }

                    $results = self::$tmdbClient->getSearchApi()->searchMovies($titleToSearch);
                    $tmdbId = null;
                    
                    if (!empty($results['results'])) {
                        // Chercher le film avec le bon ID IMDB
                        foreach ($results['results'] as $result) {
                            if ($result['adult']) {
                                continue;
                            }

                            // If the year is specified, check it matches
                            if (!empty($year) && isset($result['release_date']) && strpos($result['release_date'], $year) !== 0) {
                                continue;
                            }

                            if (isset($result['id'])) {
                                $movieDetails = self::$tmdbClient->getMoviesApi()->getMovie($result['id']);
                                if (isset($movieDetails['imdb_id']) && $movieDetails['imdb_id'] === $movieId) {
                                    $tmdbId = $result['id'];
                                    break;
                                }
                            }
                        }

                        // Si pas trouvé par IMDB ID, prendre le premier résultat
                        if (empty($tmdbId) && !empty($results['results'][0]['id'])) {
                            $tmdbId = $results['results'][0]['id'];
                        }
                    }
                    
                    if (empty($tmdbId)) {
                        if (!empty($cachedFilm))
                            return $cachedFilm;

                        return ['Not found' => true, 'URL' => $this->filename];
                    }
                } else {
                    // C'est un ID TMDB direct
                    $tmdbId = $movieId;
                }

                // Store the URL in the cache
                self::$cachedURLs[$this->filename] = 'https://www.themoviedb.org/movie/' . $tmdbId;

                // Récupérer les détails du film
                $movie = self::$tmdbClient->getMoviesApi()->getMovie($tmdbId, ['append_to_response' => 'credits']);
                
                if (empty($movie)) {
                    return ['Not found' => true, 'URL' => $this->filename];
                }

                echo "Getting TMDB info for ".$movie['title']."\n";

                // Construire l'URL IMDB
                $imdbIdFromTmdb = $movie['imdb_id'] ?? '';
                $film['URL'] = !empty($imdbIdFromTmdb) ? 'https://www.imdb.com/title/' . $imdbIdFromTmdb . '/' : '';
                if (empty($film['URL'])) {
                    $film['URL'] = 'https://www.themoviedb.org/movie/' . $tmdbId;
                }

                $film['Titre'] = $movie['original_title'] ?? $movie['title'] ?? '';
                $film['Titre FR'] = $movie['title'] ?? '';
                $film['Presse'] = str_replace('.', ',', $movie['vote_average'] ?? '');
                $film['Spectateurs'] = $movie['vote_count'] ?? '';
                $film['metacriticRating'] = '';
                $film['Mots clés'] = '';
                
                // Pays
                $countries = [];
                if (!empty($movie['production_countries'])) {
                    foreach ($movie['production_countries'] as $country) {
                        $countries[] = $country['name'];
                    }
                }
                $film['Pays'] = implode(', ', $countries);
                
                // Genres
                $genres = [];
                if (!empty($movie['genres'])) {
                    foreach ($movie['genres'] as $genre) {
                        $genres[] = $genre['name'];
                    }
                }
                $film['Genre'] = implode(', ', $genres);
                
                // Poster
                $posterPath = $movie['poster_path'] ?? '';
                $film['Poster URL'] = !empty($posterPath) ? '=IMAGE("https://image.tmdb.org/t/p/w500' . $posterPath . '")': '';
                $film['Durée'] = $movie['runtime'] ?? '';
                $film['Poster'] = '';
                $film['Synopsis'] = $movie['overview'] ?? '';
                $film['ignored'] = false;
                $film['Not found'] = false;

                // Réalisateurs
                $directors = [];
                if (!empty($movie['credits']['crew'])) {
                    foreach ($movie['credits']['crew'] as $crew) {
                        if ($crew['job'] === 'Director') {
                            $directors[] = $crew['name'];
                        }
                    }
                }
                $film['Réalisateur'] = implode(', ', $directors);

                // Acteurs (top 5)
                $actors = [];
                if (!empty($movie['credits']['cast'])) {
                    $count = 0;
                    foreach ($movie['credits']['cast'] as $cast) {
                        if ($count >= 5) break;
                        $actors[] = $cast['name'];
                        $count++;
                    }
                }
                $film['Acteurs'] = implode(', ', $actors);
                
                // Année (extraire de release_date)
                $releaseDate = $movie['release_date'] ?? '';
                $film['Année'] = !empty($releaseDate) ? substr($releaseDate, 0, 4) : '';
            } catch (Exception $e) {
                echo 'Erreur TMDB: ' . $e->getMessage() . "\n";
                return ['Not found' => true, 'URL' => $this->filename];
            }

            // Sauvegarder dans le cache
            $cacheId = is_string($movieId) && strpos($movieId, 'tt') === 0 ? str_replace('tt', '', $movieId) : $movieId;
            $cacheFilename = __DIR__ . '/../cache/imdb' . $cacheId . '.json';

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

            if (empty($url))
                return false;

            $movieId = false;
            $matches = array();
            
            // Vérifier si c'est une URL TMDB
            if (preg_match('@themoviedb\.org/movie/([0-9]+)@', $url, $matches)) {
                $movieId = $matches[1]; // ID TMDB
            }
            // Vérifier si c'est un ID IMDB (ancien cache)
            elseif (preg_match('@tt([0-9]+)[/]*@', $url, $matches)) {
                $movieId = 'tt' . $matches[1];
            }
            else {
                return 'ignored';
            }

            return $movieId;
        }

        $titleToSearch = $this->cleanTitleBeforeSearch($this->filename);

        // Extract the year from filename if possible
        $year = null;
        if (preg_match('@(?:19|20)([0-9]{2})@', $titleToSearch, $matches)) {
            $year = $matches[0];

            // If year found, remove it from title to search
            $titleToSearch = trim(preg_replace('@(?:19|20)([0-9]{2})@', '', $titleToSearch));
        }
        
        try {
            $results = self::$tmdbClient->getSearchApi()->searchMovies($titleToSearch);

            if (empty($results['results'])) {
                self::$cachedURLs[$this->filename] = '';
                return false;
            }

            foreach ($results['results'] as $result)
            {
                // Skip if no ID
                if (empty($result['id'])) {
                    continue;
                }

                // Get full movie details including IMDB ID
                try {
                    $movie = self::$tmdbClient->getMoviesApi()->getMovie($result['id']);
                } catch (Exception $e) {
                    continue;
                }

                // Skip adult movies
                if ($movie['adult']) {
                    continue;
                }

                // If the year is specified, check it matches
                if (!empty($year) && isset($result['release_date']) && strpos($result['release_date'], $year) !== 0) {
                    continue;
                }

                // Skip certain genres
                $skipGenres = ['Short', 'Talk Show'];
                $skip = false;
                if (!empty($movie['genres'])) {
                    foreach ($movie['genres'] as $genre) {
                        if (in_array($genre['name'], $skipGenres)) {
                            $skip = true;
                            break;
                        }
                    }
                }
                
                if ($skip) {
                    continue;
                }

                // Skip if no votes
                if (empty($movie['vote_count']) || $movie['vote_count'] < 10) {
                    continue;
                }

                // Stocker l'URL TMDB dans le cache pour éviter les recherches futures
                $tmdbUrl = 'https://www.themoviedb.org/movie/' . $result['id'];
                self::$cachedURLs[$this->filename] = $tmdbUrl;
                
                return $result['id']; // Return TMDB ID
            }
        } catch (Exception $e) {
            echo 'Problème recherche TMDB: ',  $e->getMessage(), "\n";
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
            imdb::writeRow(($film['Nom du fichier'] ?? $film['URL']) . "\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t" . ($film['Date d\'ajout']  ?? '') . "\n");
            return;
        }

        // if the poster url is set, convert it to IMAGE formula
        if (!empty($film['Poster URL']) && strpos(strtoupper($film['Poster URL']), '=IMAGE') === false)
            $film['Poster URL'] = '=IMAGE("' . $film['Poster URL'] . '")';

        foreach (self::$columns as $key)
            imdb::writeRow(($film[$key] ?? '') . "\t");

        if (empty($film['URL']) && empty($film['Nom du fichier']))
            return;

        imdb::writeRow("\n", true);
    }

    function cleanTitleBeforeSearch($titleToSearch)
    {
        $titleToSearch = preg_replace('@\.(avi|mkv|mp4|ts|m2ts)@', '', $titleToSearch); // remove the extension
        $titleToSearch = preg_replace('@[0-9]+p.*$@', '', $titleToSearch); // remove the resolution
        $titleToSearch = preg_replace('@\(?([0-9]{4})\)?.*@', ' $1', $titleToSearch); // remove the year
        $titleToSearch = preg_replace('@\[[^]]+\]@', '', $titleToSearch); // remove anything in []
        $titleToSearch = str_replace('.', ' ', trim($titleToSearch));

        // Remove everything after 1080p, 720p, etc.
        $titleToSearch = preg_replace('@(1080p|720p|480p|2160p|4K).*$@i', '', $titleToSearch);

        return $titleToSearch;
    }
}