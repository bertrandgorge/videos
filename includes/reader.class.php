<?php
include_once 'film.class.php';

class Reader
{
    protected $films;
    protected $dossier;

    public function __construct($dossier) {
        $this->films = array();
        $this->dossier = $dossier;

        $this->analyse($this->dossier);
    }

    // Recursively reads a folder, and for each film, construct a list of films
    public function echoFilms()
    {
        $filename = dirname(__DIR__) . '/out/all_videos.txt';

        $fp = fopen($filename, 'a');

        if (empty($fp)) die();

        fwrite($fp, "\n\n\n");

        foreach ($this->films as $film)
            $film->echoAsCSV($this->dossier, $fp);

        $this->films = [];

        fclose($fp);
    }

    static public function truncate()
    {
        $filename = dirname(__DIR__) . '/out/all_videos.txt';

        $fp = fopen($filename, 'w');

        if (empty($fp)) die();

        fclose($fp);
    }

    // Recursively reads a folder, and for each film, construct a list of films
    protected function analyse($dossier)
    {

    }

    public function getSubtitlesExtensions(bool $capitalise = false)
    {
        $exts = array('srt', 'ass', 'smi');

        if ($capitalise)
            $exts = array_merge($exts, array_map('strtoupper', $exts));

        return $exts;
    }


    public function getMoviesExtensions(bool $capitalise = false)
    {
        $exts = array('avi', 'mkv', 'mp4', 'ts', 'm2ts');

        if ($capitalise)
            $exts = array_merge($exts, array_map('strtoupper', $exts));

        return $exts;
    }

    /**
     * returns true if the filename looks like a film
     */
    public function isFilm(String $filename)
    {
        if (preg_match("@^.*\.(".implode('|', $this->getMoviesExtensions()).")@i", $filename))
            return true;

        return false;
    }

    /**
     * returns true if the filename looks like a subtitle
     */
    public function isSubtitles(String $filename)
    {
        if (preg_match("@^.*\.(".implode('|', $this->getSubtitlesExtensions()).")@i", $filename))
            return true;

        return false;
    }

}