<?php

include_once 'includes/folder.class.php';

if (!is_dir('/mnt/Videos/Vus'))
{
    echo "Please mount the smb share :
    sudo mkdir /mnt/Videos
    sudo mount -t drvfs '//imprimante/Vidéos' /mnt/Videos

    \n\n";
    exit();
}

echo "Finding films on Imprimante\n";
Reader::truncate();

$reader = new folderReader('Imprimante', "/mnt/Videos");
$reader->echoFilms();

echo "Done.\n";
