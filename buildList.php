<?php

include_once 'includes/folder.class.php';

if (count($argv) >= 2)
{
    echo "Please mount the smb share :
    sudo mkdir /mnt/Videos
    sudo mount -t drvfs '//mezzanine/Videos' /mnt/Videos
    sudo mount -t drvfs '//Freebox_Server/Disque dur' /mnt/freebox
    sudo mount -t drvfs '//Freebox_Server/Gorge' /mnt/freebox_gorge
    sudo mount -t drvfs '//Freebox_Server/Volume 471Mo' /mnt/freebox_volume_471
    sudo mount -t drvfs '//mezzanine/Vidéos 2' /mnt/Videos2

    \n\n";
    exit();
}

echo "Finding films on freebox\n";
$reader = new folderReader("/mnt/freebox/Téléchargements");
$reader->echoFilms('freebox');

echo "Finding films on freebox - dd gorge\n";
$reader = new folderReader("/mnt/freebox_gorge");
$reader->echoFilms('freebox_gorge');

echo "Finding films on freebox_volume_471\n";
$reader = new folderReader("/mnt/freebox_volume_471");
$reader->echoFilms('freebox_volume_471');

echo "Finding films on Mezzanine\n";
$reader = new folderReader("/mnt/Videos");
$reader->echoFilms('Mezzanine');

echo "Finding films on Mezzanine 2\n";
$reader = new folderReader("/mnt/Videos2");
$reader->echoFilms('Mezzanine2');

echo "Done.\n";
