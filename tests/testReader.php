<?php

include_once '../includes/reader.class.php';
include_once '../includes/folder.class.php';

$reader = new folderReader("/mnt/z/");
$reader->echoFilms();

// echo ($reader->isFilm("toto.Mp4") ? 'ok' : 'error') . "\n";
// echo ($reader->isFilm("toto.Mp3") ? 'not ok' : 'ok') . "\n";

// $a = $reader->getMoviesExtensions(true);
// print_r($a);