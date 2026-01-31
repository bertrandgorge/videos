<?php

include_once 'includes/folder.class.php';

// if (!is_dir('/mnt/videos'))
// {
//     echo "Please mount the smb share :
//     sudo mkdir /mnt/Videos
//     sudo mount -t drvfs '//imprimante/Vidéos' /mnt/Videos

//     \n\n";
//     exit();
// }

echo "Finding films on Imprimante\n";
Reader::truncate();

$reader = new folderReader('Imprimante', "/media/USBDrive1To/Vidéos");
$reader->echoFilms();

// $reader = new folderReader('Imprimante', "/mnt/videos_archive");
// $reader->echoFilms();

echo "Done.\n";
