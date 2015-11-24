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

        # récupérer les parametres de connexion
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            # créer une objet PDO
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        if (!$bdd) {
            //erreur de connexion!
            die("\nPDO::errorInfo():\n");
        } else {

            # obtenir la date courante du système
            date_default_timezone_set('Europe/Paris');
            $dateCreation = date('Y-m-d', time());
            $dateCreationNom = date('Ymd', time());

            # obtenir le nombre d'enregistrements dans la table liste_participants
            $sql = "SELECT count(*) as cnt FROM  liste_participants where date_creation = :dateCreation ;";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':dateCreation', $dateCreation);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            $nbrResultat = $resultat["cnt"];

            # récuperer la valeur des autres variables
            $nomUtilisateur = "henz";
            $nom = "liste_equipe".$nbrResultat."_".$nomUtilisateur."_".$dateCreationNom;
            $idUtilisateur = 1;
            $equipes = "[1,2,3]";


            # insérer dans la base de données
            $sql = "INSERT INTO  liste_participants (nom, id_utilisateur, date_creation, equipes) VALUES ( :nom, :idUtilisateur, :dateCreation, :equipes);\"";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':idUtilisateur', $idUtilisateur);
            $stmt->bindParam(':dateCreation', $dateCreation);
            $stmt->bindParam(':equipes', $equipes);


            fwrite($myfile, "sql : ".print_r($sql , true)."\n"); # FIXME
            $stmt->execute();
        }


        $retour = json_encode(
            array(
                "success" => true,
                "data" => "",
            )
        );

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


