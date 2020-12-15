<?php

include_once 'includes/folder.class.php';
include_once 'includes/myAllocine.class.php';

echo "Finding films in F: (external hard drive)\n";
$reader = new folderReader("f:/");
$reader->echoFilms();

echo "Finding films in Z: (freebox)\n";
$reader = new folderReader("z:/");
$reader->echoFilms();

echo "Getting Allocine info\n";
$ac = new myAlloCine();
$ac->GetAllocine();

echo "Done.\n";
