<?php

include_once '../includes/folder.class.php';

$reader = new folderReader("/mnt/f/");
$reader->echoFilms();

$reader = new folderReader("/mnt/z/");
$reader->echoFilms();
