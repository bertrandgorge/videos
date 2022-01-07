<?php

include_once 'includes/folder.class.php';
include_once 'includes/myAllocine.class.php';

if (!is_dir('/mnt/Videos'))
{
    echo "Please mount the smb share :
    sudo mkdir /mnt/Videos
    sudo mount -t drvfs '//mezzanine/Videos' /mnt/Videos\n\n";
    exit();
}

echo "Finding films on Mezzanine\n";
$reader = new folderReader("/mnt/Videos");
$reader->echoFilms();

// echo "Finding films in F: (external hard drive)\n";
// $reader = new folderReader("f:/");
// $reader->echoFilms();

// echo "Finding films in Z: (freebox)\n";
// $reader = new folderReader("z:/");
// $reader->echoFilms();

echo "Getting Allocine info\n";
$ac = new myAlloCine();
$ac->GetAllocine();

echo "Done.\n";
