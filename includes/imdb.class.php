<?php

class imdb
{
    static private $cachedURLs = array();
    static private $fp = null;
    static private $config = null;
    static private $columns = [];

    private $filename = '';

    public function __construct($filename)
    {
        $this->filename = trim($filename);

        if (empty(self::$config))
        {
            self::$config = new \Imdb\Config();
            self::$config->language = 'fr-FR,fr,en';
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
        $title = '';
        $match = array();
        if (!empty($url)) {
            if (preg_match('@tt([0-9]+)@', $url, $match)) {
                $title = $match[1];
            }
            else
                return ['ignored' => true, 'URL' => $this->filename];
        }

        if (empty($title))
            $title = $this->findFilmForFilename();

        if ($title == 'ignored')
            return ['ignored' => true, 'URL' => $this->filename];

        if (empty($title))
            return ['Not found' => true, 'URL' => $this->filename];

        $film = array();

        if (is_scalar($title))
        {
            $cacheFilename = __DIR__ . '/../cache/imdb' . $title . '.json';
            if (file_exists($cacheFilename))
            {
                $cache = file_get_contents($cacheFilename);
                if (!empty($cache))
                {
                    $film = json_decode($cache, true);
                }
            }

            if (empty($film['URL']))
                $title = new \Imdb\Title($title, self::$config);
        }

        if (empty($film['URL'])) {

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
            $film['ignored'] = false;
            $film['Not found'] = false;

            $directors = array();
            foreach ($title->director() as $director)
                $directors[] = $director['name'];
            $film['Réalisateur'] = implode(', ', $directors);

            $actors = array();
            foreach ($title->actor_stars() as $actor)
                $actors[] = $actor['name'];

            $film['Acteurs'] = implode(', ', $actors);
            $film['Année'] = $title->year();

            $cacheFilename = __DIR__ . '/../cache/imdb' . $title->imdbid() . '.json';

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
                $movieId = $matches[1];
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

        $search = new \Imdb\TitleSearch(self::$config);

        try {
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
        $aMovieFile = trim($fileRow);
        if ($aMovieFile == '')
            return null;

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