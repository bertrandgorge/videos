<?php

define('RootFolder', 'L0Rpc3F1ZSBkdXI=');

/**
 * Use the API to read the content of the freebox folder
 * @see https://dev.freebox.fr/sdk/os/fs/
 */
class freeboxReader extends Reader
{
    // http://mafreebox.freebox.fr/api/v4/fs/ls/L0Rpc3F1ZSBkdXI=
    public function analyse()
    {
        $this->getFilesInFolder(RootFolder, "Root");
    }

    private function getFilesInFolder(String $key, String $currentFolder)
    {
        if ($currentFolder == '.' || $currentFolder == '..')
            return;

        $APIUrl = 'http://mafreebox.freebox.fr/api/v4/fs/ls/' . RootFolder;
        $jsonString = file_get_contents($APIUrl);
        if (empty($jsonString))
            throw new Exception("Could not read URL $APIUrl", 1);

        $jsonData = json_decode($jsonString, true);

        /*
        {"success":true,
         "result":[
            {"type":"dir","index":0,"link":false,"modification":1589376267,"hidden":true,"mimetype":"inode\/directory","name":".","path":"L0Rpc3F1ZSBkdXI=","size":4096},
            {"type":"dir","index":1,"link":false,"modification":1604672492,"hidden":true,"mimetype":"inode\/directory","name":"..","path":"Lw==","size":60},
            {"type":"dir","index":2,"link":false,"modification":1606295947,"hidden":false,"mimetype":"inode\/directory","name":"Enregistrements","path":"L0Rpc3F1ZSBkdXIvRW5yZWdpc3RyZW1lbnRz","size":4096},
            {"type":"dir","index":3,"link":false,"modification":1578480811,"hidden":false,"mimetype":"inode\/directory","name":"Musiques","path":"L0Rpc3F1ZSBkdXIvTXVzaXF1ZXM=","size":4096},
            {"type":"dir","index":4,"link":false,"modification":1578480811,"hidden":false,"mimetype":"inode\/directory","name":"Photos","path":"L0Rpc3F1ZSBkdXIvUGhvdG9z","size":4096},
            {"type":"dir","index":5,"link":false,"modification":1606589176,"hidden":false,"mimetype":"inode\/directory","name":"Téléchargements","path":"L0Rpc3F1ZSBkdXIvVMOpbMOpY2hhcmdlbWVudHM=","size":4096},
            {"type":"dir","index":6,"link":false,"modification":1592215611,"hidden":false,"mimetype":"inode\/directory","name":"Vidéos","path":"L0Rpc3F1ZSBkdXIvVmlkw6lvcw==","size":4096}
            ]}        

            {"type":"file","index":2,"link":false,"modification":1605131699,"hidden":false,"mimetype":"video\/mp2t","name":"Arte - La prière - 11-11-2020 20h54 02h01 (3).m2ts","path":"L0Rpc3F1ZSBkdXIvRW5yZWdpc3RyZW1lbnRzL0FydGUgLSBMYSBwcmnDqHJlIC0gMTEtMTEtMjAyMCAyMGg1NCAwMmgwMSAoMykubTJ0cw==","size":4263916608},
        */


        if (empty($jsonData['success']) || $jsonData['success'] != true)
            throw new Exception("APi Error: " . $jsonString, 1);
        
        $films = array();
        $hasSubtitles = false;

        foreach ($jsonData['result'] as $entry)
        {
            if ($entry['type'] == 'dir')
                $this->getFilesInFolder($entry['path'], $entry['name']);

            if ($this->isFilm($entry['name']))
                $films[] = $entry['name'];

            if ($this->isSubtitles($entry['name']))
                $hasSubtitles = true;

        }
    }
}