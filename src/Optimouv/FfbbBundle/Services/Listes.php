<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 11/09/2015
 * Time: 11:43.
 */
namespace Optimouv\FfbbBundle\Services;

use SplFileObject;

class Listes{


    public function uploadFichier()
    {
        $myfile = fopen("/tmp/ListesService_uploadFichier.log", "w") or die("Unable to open file!");


        $tempFilename = $_FILES["file-0"]["tmp_name"];

        // Dès qu'un fichier a été reçu par le serveur
        if (file_exists($tempFilename) || is_uploaded_file($tempFilename)) {

            // détecte le type de fichier

            // lire le contenu du fichier


            $file = new \SplFileObject($tempFilename, 'r');
            $delimiter = ",";

            // On lui indique que c'est du CSV
            $file->setFlags(SplFileObject::READ_CSV);

            // préciser le délimiteur et le caractère enclosure
            $file->setCsvControl($delimiter);

            // Obtient données des en-tetes
            $headerData = $file->fgetcsv();

            fwrite($myfile, "headerData : ".print_r($headerData , true));



//            fwrite($myfile, "headerData: ".print_r($headerData, true));

//        // Gestion des headers : présence ou non
//        if ($request["header_opt"] == 1) {
//
//            // Obtient données des en-tetes
//            $headerData = $file->fgetcsv();
//
//            // Données de l'aperçu
//            $preview = $this->_preview($file);
//
//            // supprimer le fichier temporaire
//            unlink($tempFile);
//
//            $str = json_encode(
//                array(
//                    "success" => true,
//                    "data" => $headerData,
//                    "lignes" => $preview
//                )
//            );
//            echo $str;
//            exit;

        }




        $retour = "hello";

        return $retour;
    }






}


