<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 11/09/2015
 * Time: 11:43.
 */
namespace Optimouv\FfbbBundle\Services;

use SplFileObject;
use PDO;

class Listes{


    private $database_name;
    private $database_user;
    private $database_password;
    private $app_id;
    private $app_code;

    public function __construct($database_name, $database_user, $database_password, $app_id, $app_code)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
    }

    public function creerListe(){
        $myfile = fopen("/tmp/ListesService_creerListe.log", "w") or die("Unable to open file!");

        # insérer dans la base de données

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;




        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
            fwrite($myfile, "pdo: ".print_r($bdd, true)."\n");

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        if (!$bdd) {
            //erreur de connexion!
            die("\nPDO::errorInfo():\n");
        } else {


            date_default_timezone_set('Europe/Paris');
            $dateCreation = date('Y-m-d', time());
            $dateCreationNom = date('Ymd', time());

            $sql = "SELECT count(*) as cnt FROM  liste_participants where date_creation = '$dateCreation' ;";
            fwrite($myfile, "sql : ".print_r($sql, true)."\n");

            $stmt = $bdd->prepare($sql);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);

            $nbrResultat = $resultat["cnt"];

            fwrite($myfile, "resultat  : ".print_r($resultat , true)."\n");


            $nomUtilisateur = "henz";

            fwrite($myfile, "dateCreation: ".print_r($dateCreation, true)."\n");

            $nom = "liste_equipe".$nbrResultat."_".$nomUtilisateur."_".$dateCreationNom;
            $idUtilisateur = 1;
            $equipes = "[1,2,3]";


            $sql = "INSERT INTO  liste_participants (nom, id_utilisateur, date_creation, equipes) VALUES ( '$nom', '$idUtilisateur', '$dateCreation', '$equipes');\"";
            fwrite($myfile, "sql : ".print_r($sql , true)."\n");
            $insert = $bdd->prepare($sql);
            $insert->execute();
        }



        $retour = json_encode(
            array(
                "success" => true,
                "data" => "",
            )
        );

        fwrite($myfile, "retour : ".print_r($retour , true)."\n");


        return $retour;


    }





    public function creerEntites()
    {


        $myfile = fopen("/tmp/ListesService_creerEntites.log", "w") or die("Unable to open file!");


        $tempFilename = $_FILES["file-0"]["tmp_name"];

        // Dès qu'un fichier a été reçu par le serveur
        if (file_exists($tempFilename) || is_uploaded_file($tempFilename)) {

            // détecte le type de fichier



            // lire le contenu du fichier
            $file = new SplFileObject($tempFilename, 'r');
            $delimiter = ",";

            // On lui indique que c'est du CSV
            $file->setFlags(SplFileObject::READ_CSV);

            // préciser le délimiteur et le caractère enclosure
            $file->setCsvControl($delimiter);

            // Obtient données des en-tetes
            $headerData = $file->fgetcsv();

            fwrite($myfile, "headerData : ".print_r($headerData , true));




        }

        $retour = json_encode(
            array(
                "success" => true,
                "data" => $headerData,
                )
        );



        return $retour;
    }





}


