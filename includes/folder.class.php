<?php

require_once(__DIR__ . '/../vendor/autoload.php');

use Samba\SambaStreamWrapper;
//use Icewind\SMB\ServerFactory;
//use Icewind\SMB\BasicAuth;
use Symfony\Component\Finder\Finder;

include_once 'reader.class.php';

/**
 * Use the API to read the content of the freebox folder
 * @see https://dev.freebox.fr/sdk/os/fs/
 */
class folderReader extends Reader
{
    private $paths;
    private $rootFolder;

    /**
     * Call with a folder with a trailing slash
     */
    public function __construct($dossier, $rootFolder) {
        $this->films = array();
        $this->rootFolder = rtrim(str_replace('\\', '/', $rootFolder), '/') . '/';

        $exts = implode(',' , $this->getMoviesExtensions(true));

        $this->paths[] = $this->rootFolder."*.{".$exts."}";
        $this->paths[] = $this->rootFolder."*/*.{".$exts."}";
        $this->paths[] = $this->rootFolder."*/*/*.{".$exts."}";
        $this->paths[] = $this->rootFolder."*/*/*/*.{".$exts."}";
        $this->paths[] = $this->rootFolder."*/*/*/*/*.{".$exts."}";
        $this->paths[] = $this->rootFolder."*/*/*/*/*/*.{".$exts."}";

        parent::__construct($dossier);
    }

    protected function analyse($dossier)
    {
        foreach ($this->paths as $globPath) {
            $this->matchMovies($globPath, $dossier);
            $this->echoFilms();
        }
    }

    private function matchMovies($globPath, $dossier)
    {
        echo "Looking for files in $globPath \n";
        $files = glob($globPath, GLOB_BRACE);

        foreach ($files as $filepath)
        {
            $fs = filesize($filepath);
            if ($fs < 100000 && $fs >= 0)
                continue;

            $path = str_replace($this->rootFolder, '', dirname($filepath));
            $maindir = preg_replace('@/.*@', '', $path);
            $subDir =  str_replace($maindir, '', $path);
            $subDir =  trim($subDir, '/');

            if (empty($subDir))
                $subDir = $dossier;

            if ($maindir == '$RECYCLE.BIN')
                continue;

            // find if there are some subtitles
            $glob_pattern = preg_replace('/(\*|\?|\[)/', '[$1]', dirname($filepath)) . "/*.{".implode(',', $this->getSubtitlesExtensions(true))."}";
            $subtitles_files = glob($glob_pattern, GLOB_BRACE);
            $subtitles = !empty($subtitles_files) ? 'srt' : '';

            // get the dir size
            $filesize = round($this->filsize_32b($filepath) / 1024 / 1000);

            $this->films[] = new film(basename($filepath), $maindir, $subDir, filemtime($filepath), $subtitles, $filesize, dirname($filepath));
        }
    }

    private function filsize_32b($file)
    {
        $filez = filesize($file);
        if($filez < 0) {  return (($filez + PHP_INT_MAX) + PHP_INT_MAX + 2); }
        else { return $filez; }
    }
}