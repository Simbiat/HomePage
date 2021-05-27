<?php
declare(strict_types=1);
namespace Simbiat;

use Simbiat\HTTP20\Common;
use Simbiat\HTTP20\Sharing;

class HomeTests
{
    #Function to test Lodestone
    #Incorporate below ones, when required
    #$data = $Lodestone->searchLinkshell()->getResult();
    #$data = $Lodestone->searchDatabase('duty', 2)->getResult();
    #$data = $Lodestone->searchDatabase('achievement', 0, 0, 'hit the floor')->getResult();
    #$data = $Lodestone->getCharacter('6691027')->getResult();
    #$data = $Lodestone->getCharacterAchievements('6691027', false, 39, true, false)->getResult();
    #$data = $Lodestone->getCharacter('11164476')->getResult();
    #$data = $Lodestone->getCharacter('26370203')->getResult();
    #$data = $Lodestone->getLinkshellMembers('22517998136956039')->getResult();
    #$data = $Lodestone->getLinkshellMembers('22517998136982037')->getResult();
    #$data = $Lodestone->getLinkshellMembers('6e125c2e2f7b9e3d4dbfb2ab7190c32c36b930c5')->getResult();
    #$data = $Lodestone->getPvPTeam('86886213d32985bcb85a157ccaa1fa8d6e7c37c0')->getResult();
    #$data = $Lodestone->getPvPTeam('c036a9c564d1818cde15fc40db01be8fc4a88612')->getResult();
    #$data = $Lodestone->getWorldStatus(true)->getResult();
    #$data = $Lodestone->getDeepDungeon(2, '', '', '')->getResult();
    #$data = $Lodestone->getLinkshellMembers('1', 1)->getResult();
    #$data = $Lodestone->getLinkshellMembers('c4fac4f1cd085af406cc0b0bfe05f4d51e4a2f90')->getResult();
    public function ffTest(bool $full, string $type, string $id = '')
    {
        if ($full) {
            #Run full test
            new LodestoneTest;
        } else {
            $Lodestone = (new Lodestone)->setLanguage('eu')->setUseragent('Simbiat Software');
            switch($type) {
                case 'freecompany':
                    $Lodestone->getFreeCompany($id)->getFreeCompanyMembers($id);
                    break;
            }
            echo '<pre>'.var_export($Lodestone->getResult(), true).'</pre>';
        }
        exit;
    }

    #Function to test file upload using PUT
    public function uploadPut(string $filepath): void
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://'.$_SERVER['HTTP_HOST']);
        curl_setopt($curl, CURLOPT_UPLOAD, true);
        curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_PUT, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 50);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: '.mime_content_type($filepath), 'Content-Disposition: attachment; filename="'.basename($filepath).'"']);
        curl_setopt($curl, CURLOPT_INFILE, fopen($filepath, 'rb'));
        curl_setopt($curl, CURLOPT_INFILESIZE, filesize($filepath));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        (new Common)->zEcho('<pre>'.var_export(curl_exec($curl), true).'</pre>');
        exit;
    }

    #Function to test file upload using POST
    public function uploadPost(string $uploadPath, int $MAX_FILE_SIZE = 300000000): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $output = '
            <form enctype="multipart/form-data" action="https://'.$_SERVER['HTTP_HOST'].'" method="POST">
                <!-- MAX_FILE_SIZE must precede the file input field -->
                <input type="hidden" name="MAX_FILE_SIZE" value="'.$MAX_FILE_SIZE.'" />
                <!-- Name of input element determines name in $_FILES array -->
                Send this file: <input multiple name="userfile[]" type="file" />
                Send this file: <input multiple name="userfile2" type="file" />
                <input type="submit" value="Send File" />
            </form>
            ';
        } else {
           $output = '<pre>'.var_export((new Sharing)->upload($uploadPath, false, false, [], false), true).'</pre>';
        }
        (new Common)->zEcho($output);
        exit;
    }

    #Function to test download
    public function downloadTest(string $filepath, string $bytes = ''): void
    {
        if (!empty($bytes)) {
            $_SERVER['HTTP_RANGE'] = 'bytes='.$bytes;
        }
        (new Sharing)->download($filepath, '', '', true);
        exit;
    }
}
