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
//        $myfile = fopen("/tmp/ListesService_creerListeParticipants.log", "w") or die("Unable to open file!"); # FIXME

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
            $nom = "liste_participants_".$nomUtilisateur."_".$dateCreationNom;
            $idUtilisateur = 1;

            # construire la liste d'équipes
            $equipes = "";
            for ($i=0; $i<count($idsEntite); $i++){
                $equipes .= $idsEntite[$i].",";
            }
            // supprimer la dernière virgule
            $equipes = rtrim($equipes, ",");

            # insérer dans la base de données
            $sql = "INSERT INTO  liste_participants (nom, id_utilisateur, date_creation, equipes) VALUES ( :nom, :idUtilisateur, :dateCreation, :equipes);";
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

    public function creerListeLieux($idsEntite){
//        $myfile = fopen("/tmp/ListesService_creerListeLieux.log", "w") or die("Unable to open file!");

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
            $nom = "liste_terrains_neutres_".$nomUtilisateur."_".$dateCreationNom;
            $idUtilisateur = 1;

            # construire la liste d'équipes
            $lieux = "";
            for ($i=0; $i<count($idsEntite); $i++){
                $lieux .= $idsEntite[$i].",";
            }
            // supprimer la dernière virgule
            $lieux = rtrim($lieux, ",");

            # insérer dans la base de données
            $sql = "INSERT INTO  liste_lieux (nom, id_utilisateur, date_creation, lieux) VALUES ( :nom, :idUtilisateur, :dateCreation, :lieux);";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':idUtilisateur', $idUtilisateur);
            $stmt->bindParam(':dateCreation', $dateCreation);
            $stmt->bindParam(':lieux', $lieux);
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

            // initialiser toutes les vars
            $idUtilisateur = 1;
            $nom = "";
            $prenom = "";
            $adresse = "";
            $codePostal = "";
            $ville = "";
            $lon = -1;
            $lat = -1;
            $projection = "";
            $typeEquipement = "";
            $nombreEquipement = -1;
            $capaciteRencontre = -1;
            $capacitePhaseFinale = -1;
            $participants = -1;
            $licencies = -1;
            $lieuRencontrePossible = -1;

            # obtenir la date courante du système
            date_default_timezone_set('Europe/Paris');
            $dateCreation = date('Y-m-d', time());
            $dateModification = date('Y-m-d', time());

            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion!
                die("\nPDO::errorInfo():\n");
            } else {
                $idsEntite = [];
                // obtenir les données pour chaque ligne
                while (!$file->eof()) {
                    $donnéesLigne = $file->fgetcsv();

                    // obtenir la valeur pour chaque paramètre
                    $typeEntite = $donnéesLigne[0];

                    // obtenir les valeurs selon le type d'entité
                    if ($typeEntite == "EQUPE") {
                        $nom = $donnéesLigne[1];
                        $adresse = $donnéesLigne[2];
                        $codePostal = $donnéesLigne[3];
                        $ville = $donnéesLigne[4];
                        $lon = $donnéesLigne[5];
                        $lat = $donnéesLigne[6];
                        $projection = $donnéesLigne[7];
                        $participants = $donnéesLigne[8];
                        $licencies = $donnéesLigne[9];
                        $lieuRencontrePossible = $donnéesLigne[10];
                    } elseif ($typeEntite == "LIEU") {
                        $nom = $donnéesLigne[1];
                        $adresse = $donnéesLigne[2];
                        $codePostal = $donnéesLigne[3];
                        $ville = $donnéesLigne[4];
                        $lon = $donnéesLigne[5];
                        $lat = $donnéesLigne[6];
                        $projection = $donnéesLigne[7];
                        $typeEquipement = $donnéesLigne[8];
                        $nombreEquipement = $donnéesLigne[9];
                        $capaciteRencontre = $donnéesLigne[10];
                        $capacitePhaseFinale = $donnéesLigne[11];
                        $lieuRencontrePossible = $donnéesLigne[12];
                    } elseif ($typeEntite == "PERSONNE") {
                        $nom = $donnéesLigne[1];
                        $prenom = $donnéesLigne[2];
                        $adresse = $donnéesLigne[3];
                        $codePostal = $donnéesLigne[4];
                        $ville = $donnéesLigne[5];
                        $lon = $donnéesLigne[6];
                        $lat = $donnéesLigne[7];
                        $projection = $donnéesLigne[8];
                        $lieuRencontrePossible = $donnéesLigne[9];
                    }


                    # insérer dans la base de données
                    $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, prenom, adresse, code_postal, ville, longitude, latitude,"
                        ." projection, type_equipement, nombre_equipement, capacite_rencontre, capacite_phase_finale, participants, "
                        ." licencies, lieu_rencontre_possible, date_creation, date_modification )"
                        ."VALUES ( :id_utilisateur, :type_entite, :nom, :prenom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                        ." :projection, :type_equipement, :nombre_equipement, :capacite_rencontre, :capacite_phase_finale,  :participants, "
                        .":licencies, :lieu_rencontre_possible, :date_creation, :date_modification );";

                    fwrite($myfile, "sql  : " . print_r($sql , true) . "\n"); # FIXME
                    $stmt = $bdd->prepare($sql);
                    $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                    $stmt->bindParam(':type_entite', $typeEntite);
                    $stmt->bindParam(':nom', $nom);
                    $stmt->bindParam(':prenom', $prenom);
                    $stmt->bindParam(':adresse', $adresse);
                    $stmt->bindParam(':code_postal', $codePostal);
                    $stmt->bindParam(':ville', $ville);
                    $stmt->bindParam(':longitude', $lon);
                    $stmt->bindParam(':latitude', $lat);
                    $stmt->bindParam(':projection', $projection);
                    $stmt->bindParam(':type_equipement', $typeEquipement);
                    $stmt->bindParam(':nombre_equipement', $nombreEquipement);
                    $stmt->bindParam(':capacite_rencontre', $capaciteRencontre);
                    $stmt->bindParam(':capacite_phase_finale', $capacitePhaseFinale);
                    $stmt->bindParam(':participants', $participants);
                    $stmt->bindParam(':licencies', $licencies);
                    $stmt->bindParam(':lieu_rencontre_possible', $lieuRencontrePossible);
                    $stmt->bindParam(':date_creation', $dateCreation);
                    $stmt->bindParam(':date_modification', $dateModification);
                    $stmt->execute();

                    # obtenir l'id de l"entité créée
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


