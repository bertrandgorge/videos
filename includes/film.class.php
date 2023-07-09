<?php

class film
{
    private $filename, $maindir, $subdir, $filedate, $subtitles, $filesize;

    public function __construct($filename, $maindir, $subdir, $filedate, $subtitles, $filesize)
    {
        $this->filename = $filename;
        $this->maindir = $maindir;
        $this->subdir = $subdir;
        $this->filedate = $filedate;
        $this->subtitles = !empty($subtitles) ? 'srt' : '';
        $this->filesize = $filesize;
    }

    /**
     * echo a CSV row with the following items:
     *  Film
     *  Dossier principal
     *  Dossier
     *  Date d'ajout
     *  Sous-titres
     *  Size
     */
    public function echoAsCSV($support, $fp = null)
    {
        $row = $this->filename . "\t"
                . $this->maindir . "\t"
                . $this->subdir . "\t"
                . $support . "\t"
                . date("d/m/y", $this->filedate)."\t"
                . $this->subtitles . "\t"
                . $this->filesize . "\n";

        if (!empty($fp))
            fwrite($fp, $row);
        else
            echo  $row;
    }
}