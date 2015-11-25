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

    public function creerListeParticipants($idsEntite){
        $myfile = fopen("/tmp/ListesService_creerListe.log", "w") or die("Unable to open file!");

        # obtenir l'objet PDO
        $bdd = $this->getPdo();

        if (!$bdd) {
            //erreur de connexion!
            die("\nPDO::errorInfo():\n");
        } else {

            # obtenir la date courante du système
            date_default_timezone_set('Europe/Paris');
            $dateCreation = date('Y-m-d', time());
            $dateCreationNom = date('Y-m-d_G:i:s', time());

            # récuperer la valeur des autres variables
            $nomUtilisateur = "henz";
            $nom = "liste_equipe_".$nomUtilisateur."_".$dateCreationNom;
            $idUtilisateur = 1;

            # construire la liste d'équipes
            $equipes = "";
            for ($i=0; $i<count($idsEntite); $i++){
                $equipes .= $idsEntite[$i].",";
            }
            // supprimer la dernière virgule
            $equipes = rtrim($equipes, ",");
//            fwrite($myfile, "equipes  : " . print_r($equipes , true) . "\n"); # FIXME

            # insérer dans la base de données
            $sql = "INSERT INTO  liste_participants (nom, id_utilisateur, date_creation, equipes) VALUES ( :nom, :idUtilisateur, :dateCreation, :equipes);\"";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':idUtilisateur', $idUtilisateur);
            $stmt->bindParam(':dateCreation', $dateCreation);
            $stmt->bindParam(':equipes', $equipes);
            $stmt->execute();

        }

        $retour = array(
                "success" => true,
                "data" => "",
        );

        return $retour;
    }



    public function creerEntites()
    {
        $myfile = fopen("/tmp/ListesService_creerEntites.log", "w") or die("Unable to open file!");

        # obtenir le nom du fichier uploadé
        $nomFichierTemp = $_FILES["file-0"]["tmp_name"];

        // Dès qu'un fichier a été reçu par le serveur
        if (file_exists($nomFichierTemp) || is_uploaded_file($nomFichierTemp)) {

            // détecte le type de fichier
            # TODO

            // lire le contenu du fichier
            $file = new SplFileObject($nomFichierTemp, 'r');
            $delimiter = ",";

            // On lui indique que c'est du CSV
            $file->setFlags(SplFileObject::READ_CSV);

            // préciser le délimiteur et le caractère enclosure
            $file->setCsvControl($delimiter);

            // Obtient données des en-tetes
            $donneesEntete = $file->fgetcsv();
//            fwrite($myfile, "donneesEntete : ".print_r($donneesEntete , true)."\n"); # FIXME

            // obtenir les données pour chaque ligne
            $idsEntite = [];
            while(!$file->eof()) {
                $donnéesLigne = $file->fgetcsv();

                // obtenir la valeur pour chaque paramètre
                $idFederation = $donnéesLigne[1];
                $typeEntite = $donnéesLigne[2];
                $nom = $donnéesLigne[3];
                $adresse = $donnéesLigne[4];
                $codePostal = $donnéesLigne[5];
                $ville = $donnéesLigne[6];
                $lon = $donnéesLigne[7];
                $lat = $donnéesLigne[8];
                $projection = $donnéesLigne[9];
                $nbrParticipants = $donnéesLigne[10];
                $nbrLicencies = $donnéesLigne[11];
                $lieuRencontrePossible = $donnéesLigne[12];

                # obtenir la date courante du système
                date_default_timezone_set('Europe/Paris');
                $dateCreation = date('Y-m-d', time());
                $dateModification = date('Y-m-d', time());

                # obtenir l'id d'utilisateur
                $idUtilisateur = 1;

                # obtenir l'objet PDO
                $bdd = $this->getPdo();

                if (!$bdd) {
                    //erreur de connexion!
                    die("\nPDO::errorInfo():\n");
                } else {
                    # insérer dans la base de données
                    $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                            ." projection, participants, licencies, lieu_rencontre_possible, date_creation, date_modification )"
                        ."VALUES ( :id_utilisateur, :type_entite, :nom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                            ." :projection, :participants, :licencies, :lieu_rencontre_possible, :date_creation, :date_modification );\"";
                    $stmt = $bdd->prepare($sql);
                    $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                    $stmt->bindParam(':type_entite', $typeEntite);
                    $stmt->bindParam(':nom', $nom);
                    $stmt->bindParam(':adresse', $adresse);
                    $stmt->bindParam(':code_postal', $codePostal);
                    $stmt->bindParam(':ville', $ville);
                    $stmt->bindParam(':longitude', $lon);
                    $stmt->bindParam(':latitude', $lat);
                    $stmt->bindParam(':projection', $projection);
                    $stmt->bindParam(':participants', $nbrParticipants);
                    $stmt->bindParam(':licencies', $nbrLicencies);
                    $stmt->bindParam(':lieu_rencontre_possible', $lieuRencontrePossible);
                    $stmt->bindParam(':date_creation', $dateCreation);
                    $stmt->bindParam(':date_modification', $dateModification);

                    $stmt->execute();

                    $idEntite = $bdd->lastInsertId();
                    array_push($idsEntite, $idEntite);
                }

            }

        }

        $retour = array(
                "success" => true,
                "idsEntite" => $idsEntite
        );



        return $retour;
    }

    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    public function getPdo(){
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

        return $bdd;
    }



}


