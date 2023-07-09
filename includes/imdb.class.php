<?php

class imdb
{
    static private $cachedURLs = array();
    static private $config = null;
    private $filename = '';

    public function __construct($filename)
    {
        $this->filename = $filename;

        if (empty(self::$config))
        {
            self::$config = new \Imdb\Config();
            self::$config->language = 'fr-FR,fr,en';
        }

        imdb::getCachedURLs();
    }

    public function getFilmInfo()
    {
        $title = $this->findFilmForFilename();

        if (empty($title))
            return false;

        if (is_scalar($title))
        {
            $cacheFilename = __DIR__ . '/../cache/imdb' . $title . '.json';
            if (file_exists($cacheFilename))
            {
                $cache = file_get_contents($cacheFilename);
                if (!empty($cache))
                {
                    $filmInfo = json_decode($cache, true);
                    if (!empty($filmInfo['URL']))
                        return $filmInfo;
                }
            }

            $title = new \Imdb\Title($title, self::$config);
        }

        $film = array();

        $film['URL'] = $title->main_url();
        $film['Titre'] = $title->orig_title();
        $film['Titre FR'] = $title->title();
        $film['Presse'] = str_replace('.', ',', $title->rating());
        $film['Spectateurs'] = $title->votes();
        $film['metacriticRating'] = $title->metacriticRating();
        $film['Mots clés'] = implode(', ', $title->keywords());
        $film['Pays'] = implode(', ', $title->country());
        $film['Genre'] = $title->genre();
        $film['Poster URL'] = $title->photo();
        $film['Durée'] = $title->runtime();
        $film['Poster'] = '';
        $film['Synopsis'] = implode(" ", $title->plot());

        $directors = array();
        foreach ($title->director() as $director)
            $directors[] = $director['name'];
        $film['Réalisateur'] = implode(', ', $directors);

        $actors = array();
        foreach ($title->actor_stars() as $actor)
            $actors[] = $actor['name'];

        $film['Acteurs'] = implode(', ', $actors);
        $film['Année'] = $title->year();

        $film['Pas trop long'] = $film['Durée'] < 120 ? 'Oui' : 'Non';

        $cacheFilename = __DIR__ . '/../cache/imdb' . $title->imdbid() . '.json';
        file_put_contents($cacheFilename, json_encode($film, JSON_THROW_ON_ERROR));

        return $film;
    }

    private function findFilmForFilename()
    {
        if (isset(self::$cachedURLs[$this->filename]))
        {
            $url = self::$cachedURLs[$this->filename];

            $movieId = false;
            $matches = array();
            if (preg_match('@tt([0-9]+)/@', $url, $matches))
                $movieId = $matches[1];

            return $movieId;
        }

        $titleToSearch = trim($this->filename);
        $titleToSearch = preg_replace('@\.(avi|mkv|mp4|ts|m2ts)@', '', $titleToSearch); // remove the extension
        $titleToSearch = preg_replace('@[0-9]+p.*$@', '', $titleToSearch); // remove the resolution
        $titleToSearch = preg_replace('@\(?([0-9]{4})\)?.*@', ' $1', $titleToSearch); // remove the year
        $titleToSearch = preg_replace('@\[[^]]+\]@', '', $titleToSearch); // remove anything in []
        $titleToSearch = str_replace('.', ' ', trim($titleToSearch));

        $search = new \Imdb\TitleSearch(self::$config);

        $results = $search->search($titleToSearch, array(\Imdb\TitleSearch::MOVIE)); // Optional second parameter restricts types returned

        foreach ($results as $title)
        {
            $genre = $title->genre();

            switch ($genre) {
                case '':
                case 'Court-métrage':
                case 'Talk-show':
                    continue 2;

                default:
                    break;
            }

            if ($title->votes() == 0)
                continue;

            self::$cachedURLs[$this->filename] = $title->main_url();
            return $title;
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
            $filecontent .= "$k\t$v\n";

        file_put_contents($matchCache, $filecontent);
    }
}