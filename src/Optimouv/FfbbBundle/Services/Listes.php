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

    public function controlerEntites(){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateModification = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir le chemin d'upload du fichier
        $cheminFichierTemp = $_FILES["file-0"]["tmp_name"];

        # obtenir le type du fichier
        $typeFichier = $_FILES["file-0"]["type"];

        # obtenir le nom du fichier
        $nomFichier = $_FILES["file-0"]["name"];


        // Dès qu'un fichier a été reçu par le serveur
        if (file_exists($cheminFichierTemp) || is_uploaded_file($cheminFichierTemp)) {

            // Si le fichier n'est pas un fichier csv
            if($typeFichier == "text/csv"){
                // lire le contenu du fichier
                $file = new SplFileObject($cheminFichierTemp, 'r');
                $delimiter = ",";

                // On lui indique que c'est du CSV
                $file->setFlags(SplFileObject::READ_CSV);

                # afficher le statut de la requete executée
                error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                    ."\n _FILES: ".print_r($_FILES, true), 3, "/tmp/optimouv.log");

                // préciser le délimiteur et le caractère enclosure
                $file->setCsvControl($delimiter);

                // Obtient données des en-tetes
                $donneesEntete = $file->fgetcsv();

                # obtenir l'objet PDO
                $bdd = $this->getPdo();

                if (!$bdd) {
                    //erreur de connexion
//                error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow, 3, "/var/log/apache2/optimouv.log");
                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
                } else {
                    $idsEntite = [];
                    // obtenir les données pour chaque ligne
                    $nbrLigne = 1;

                    // récupérer tous les codes postaux depuis la table villes france_free
                    $sql = "SELECT distinct ville_code_postal  FROM villes_france_free;";
                    $stmt = $bdd->prepare($sql);
                    $stmt->execute();
                    $codesPostaux = $stmt->fetchall(PDO::FETCH_COLUMN, 0);
                    error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                        ."\n code postal: ".print_r($codesPostaux, true), 3, "/tmp/optimouv.log");

                    // récupérer tous les noms de ville depuis la table villes france_free
                    $sql = "SELECT distinct ville_nom  FROM villes_france_free;";
                    $stmt = $bdd->prepare($sql);
                    $stmt->execute();
                    $nomsVilles = $stmt->fetchall(PDO::FETCH_COLUMN, 0);

                    // récupérer tous les noms de ville depuis la table villes france_free
                    $sql = "SELECT distinct ville_nom  FROM villes_france_free;";
                    $stmt = $bdd->prepare($sql);
                    $stmt->execute();
                    $nomsVilles = $stmt->fetchall(PDO::FETCH_COLUMN, 0);



                    error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                        ."\n nomsVilles: ".print_r($nomsVilles, true), 3, "/tmp/optimouv.log");


                    while (!$file->eof()) {
                        $donnéesLigne = $file->fgetcsv();
                        $nbrLigne++;

                        // tester s'il y a des données
                        if($donnéesLigne != array(null)){
                            // obtenir la valeur pour chaque paramètre
                            $typeEntite = $donnéesLigne[0];

                            // obtenir les valeurs selon le type d'entité
                            if (strtolower($typeEntite) == "equipe") {
                                # les champs obligatoires
                                $nom = $donnéesLigne[1];
                                $codePostal = $donnéesLigne[2];
                                $ville = $donnéesLigne[3];
                                $participants = $donnéesLigne[4];
                                $lieuRencontrePossible = $this->getBoolean($donnéesLigne[5]);

                                # controler tous les champs obligatoires
                                if(empty($nom)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'nom' (colonne 2) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                if(empty($codePostal)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'code postal' (colonne 3) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                };
                                if(empty($ville)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'ville' (colonne 4) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                };
                                if(empty($participants)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'participants' (colonne 5) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                };
                                # controler le champ 'lieu de rencontre possible'
                                if( empty($donnéesLigne[5]) ){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'lieu de rencontre possible' (colonne 6) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'participants'
                                # il faut que la valeur soit une valeur numeric
                                if(!is_numeric($participants)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'participants' (colonne 5) doit avoir une valeur numérique!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'lieu de rencontre possible'
                                # il faut que la valeur soit 'OUI' ou 'NON'
                                if((strtolower($donnéesLigne[5]) != 'non') and (strtolower($donnéesLigne[5]) != 'oui')){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'lieu de rencontre possible' (colonne 6) doit avoir la valeur 'OUI' ou 'NON'!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }


                                # controler le champ 'code postal'
                                # il faut que la valeur contient 5 chiffres
                                if(strlen($codePostal) != 5){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'code postal' (colonne 3) doit contenir 5 chiffres!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'code postal'
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                if(!in_array($codePostal,  $codesPostaux)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." La valeur du champ 'code postal' (colonne 3) n'est pas reconnue!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'ville'
                                # il faut que la valeur est incluse dans la liste des noms de ville de la table villes_france_free
                                if(!in_array($ville,  $nomsVilles)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." La valeur du champ 'ville' (colonne 4) n'est pas reconnue!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }



                                # les champs optionnels
                                $adresse = $donnéesLigne[6];
                                $longitude = $donnéesLigne[7];
                                $latitude = $donnéesLigne[8];
                                $projection = $donnéesLigne[9];
                                $licencies = $donnéesLigne[10];

                            }
                            elseif (strtolower($typeEntite) == "personne") {
                                # les champs obligatoires
                                $nom = $donnéesLigne[1];
                                $prenom = $donnéesLigne[2];
                                $codePostal = $donnéesLigne[3];
                                $ville = $donnéesLigne[4];
                                $lieuRencontrePossible = $this->getBoolean($donnéesLigne[5]);

                                # controler tous les champs obligatoires
                                if(empty($nom)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'nom' (colonne 2) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                if(empty($prenom)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'prenom' (colonne 3) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                if(empty($codePostal)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'code postal' (colonne 4) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                if(empty($ville)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'ville' (colonne 5) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                # controler le champ 'lieu de rencontre possible'
                                if( empty($donnéesLigne[5]) ){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'lieu de rencontre possible' (colonne 6) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ '$lieuRencontrePossible'
                                # il faut que la valeur soit 'OUI' ou 'NON'
                                if((strtolower($donnéesLigne[5]) != 'non') and (strtolower($donnéesLigne[5]) != 'oui')){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'lieu de rencontre possible' (colonne 6) doit avoir la valeur 'OUI' ou 'NON'!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'code postal'
                                # il faut que la valeur contient 5 chiffres
                                if(strlen($codePostal) != 5){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'code postal' (colonne 4) doit contenir 5 chiffres!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'code postal'
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                if(!in_array($codePostal,  $codesPostaux)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." La valeur du champ 'code postal' (colonne 4) n'est pas reconnue!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'ville'
                                # il faut que la valeur est incluse dans la liste des noms de ville de la table villes_france_free
                                if(!in_array($ville,  $nomsVilles)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." La valeur du champ 'ville' (colonne 5) n'est pas reconnue!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }



                                # les champs optionnels
                                $adresse = $donnéesLigne[6];
                                $longitude = $donnéesLigne[7];
                                $latitude = $donnéesLigne[8];
                                $projection = $donnéesLigne[9];

                            }
                            elseif ($typeEntite == "LIEU") {
                                # les champs obligatoires
                                $nom = $donnéesLigne[1];
                                $codePostal = $donnéesLigne[2];
                                $ville = $donnéesLigne[3];
                                $lieuRencontrePossible = $this->getBoolean($donnéesLigne[4]);

                                # controler tous les champs obligatoires
                                if(empty($nom)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'nom' (colonne 2) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                if(empty($codePostal)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'code postal' (colonne 3) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                if(empty($ville)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'ville' (colonne 4) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }
                                # controler le champ 'lieu de rencontre possible'
                                if( empty($donnéesLigne[4]) ){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'lieu de rencontre possible' (colonne 5) doit être rempli!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'lieu de rencontre possible'
                                # il faut que la valeur soit 'OUI' ou 'NON'
                                if((strtolower($donnéesLigne[4]) != 'non') and (strtolower($donnéesLigne[4]) != 'oui')){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'lieu de rencontre possible' (colonne 5) doit avoir la valeur 'OUI' ou 'NON'!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'code postal'
                                # il faut que la valeur contient 5 chiffres
                                if(strlen($codePostal) != 5){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le champ 'code postal' (colonne 3) doit contenir 5 chiffres!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # controler le champ 'code postal'
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                if(!in_array($codePostal,  $codesPostaux)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." La valeur du champ 'code postal' (colonne 3) n'est pas reconnue!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # il faut que la valeur est incluse dans la liste des noms de ville de la table villes_france_free
                                if(!in_array($ville,  $nomsVilles)){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." La valeur du champ 'ville' (colonne 4) n'est pas reconnue!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }

                                # les champs optionnels
                                $adresse = $donnéesLigne[5];
                                $longitude = $donnéesLigne[6];
                                $latitude = $donnéesLigne[7];
                                $projection = $donnéesLigne[8];
                                $typeEquipement = $donnéesLigne[9];
                                $nombreEquipement = $donnéesLigne[10];
                                $capaciteRencontre = $this->getBoolean($donnéesLigne[11]);
                                $capacitePhaseFinale = $this->getBoolean($donnéesLigne[12]);
                            }
                            else{
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                        ." Le type d'entité n'est pas reconnu!"
                                        ." Veuillez s'assurer que le type d'entité est parmi 'EQUIPE', 'PERSONNE' ou 'LIEU'!"
                                        .implode(",", $donnéesLigne)
                                );
                                return $retour;
                            }

                        }

                    }
                }
                $retour = array(
                    "success" => true,
                    "msg" => "Contrôle réussi "
                );

            }
            else{
                $retour = array(
                    "success" => false,
                    "msg" => "Veuillez vérifier que le type de fichier uploadé est bien csv.!"
                            ."Nom de fichier: ".$nomFichier."!"
                            ."Type de fichier: ".$typeFichier
                );

            }


        }
        else{
            $retour = array(
                "success" => false,
                "msg" => "Erreur d'upload de fichier. Veuillez réessayer"
            );
        }

        return $retour;

    }


    public function creerListeParticipants($idsEntite){
        # obtenir l'objet PDO
        $bdd = $this->getPdo();

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        if (!$bdd) {
            //erreur de connexion
//            error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerListeParticipants, datetime: ".$dateTimeNow, 3, "/var/log/apache2/optimouv.log");
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        } else {
            
            # récuperer la valeur des autres variables
            $nomUtilisateur = "henz";
//            $nom = "liste_participants_".$nomUtilisateur."_".$dateTimeNow;
            $nom = "liste_participants_".$dateTimeNow;
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

            # afficher le statut de la requete executée
//            error_log("\n Service: Listes, Function: creerListeParticipants, datetime: ".$dateTimeNow
//                ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, "/var/log/apache2/optimouv.log");
        }

        $retour = array(
                "success" => true,
                "data" => "",
        );

        return $retour;
    }

    public function creerListeLieux($idsEntite){
        # obtenir l'objet PDO
        $bdd = $this->getPdo();

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        if (!$bdd) {
            //erreur de connexion
//            error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerListeLieux, datetime: ".$dateTimeNow, 3, "/var/log/apache2/optimouv.log");
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        } else {
            # récuperer la valeur des autres variables
            $nomUtilisateur = "henz";
//            $nom = "liste_terrains_neutres_".$nomUtilisateur."_".$dateTimeNow;
            $nom = "liste_terrains_neutres_".$dateTimeNow;
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

            # afficher le statut de la requete executée
//            error_log("\n Service: Listes, Function: creerListeLieux, datetime: ".$dateTimeNow
//                ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, "/var/log/apache2/optimouv.log");
        }

        $retour = array(
            "success" => true,
            "data" => "",
        );

        return $retour;
    }


    public function creerEntites()
    {
        # obtenir le nom du fichier uploadé
        $nomFichierTemp = $_FILES["file-0"]["tmp_name"];

        // Dès qu'un fichier a été reçu par le serveur
        if (file_exists($nomFichierTemp) || is_uploaded_file($nomFichierTemp)) {

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

            # obtenir la date courante du système
            date_default_timezone_set('Europe/Paris');
            $dateCreation = date('Y-m-d', time());
            $dateModification = date('Y-m-d', time());
            $dateTimeNow = date('Y-m-d_G:i:s', time());

            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
//                error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow, 3, "/var/log/apache2/optimouv.log");
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            } else {
                $idsEntite = [];
                // obtenir les données pour chaque ligne
                while (!$file->eof()) {
                    $donnéesLigne = $file->fgetcsv();

                    // tester s'il y a des données
                    if($donnéesLigne != array(null)){
                        // obtenir la valeur pour chaque paramètre
                        $typeEntite = $donnéesLigne[0];

                        // obtenir les valeurs selon le type d'entité
                        if (strtolower($typeEntite) == "equipe") {
                            $nom = $donnéesLigne[1];
                            $codePostal = $donnéesLigne[2];
                            $ville = $donnéesLigne[3];
                            $participants = $donnéesLigne[4];
                            $lieuRencontrePossible = $this->getBoolean($donnéesLigne[5]);
                            $adresse = $donnéesLigne[6];
                            $longitude = $donnéesLigne[7];
                            $latitude = $donnéesLigne[8];
                            $projection = $donnéesLigne[9];
                            $licencies = $donnéesLigne[10];

                            $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                                ." projection, participants, "
                                ." licencies, lieu_rencontre_possible, date_creation, date_modification )"
                                ."VALUES ( :id_utilisateur, :type_entite, :nom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                ." :projection, :participants, "
                                .":licencies, :lieu_rencontre_possible, :date_creation, :date_modification );";

                            $stmt = $bdd->prepare($sql);
                            $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                            $stmt->bindParam(':type_entite', $typeEntite);
                            $stmt->bindParam(':nom', $nom);
                            $stmt->bindParam(':adresse', $adresse);
                            $stmt->bindParam(':code_postal', $codePostal);
                            $stmt->bindParam(':ville', $ville);
                            $stmt->bindParam(':longitude', $longitude);
                            $stmt->bindParam(':latitude', $latitude);
                            $stmt->bindParam(':projection', $projection);
                            $stmt->bindParam(':participants', $participants);
                            $stmt->bindParam(':licencies', $licencies);
                            $stmt->bindParam(':lieu_rencontre_possible', $lieuRencontrePossible);
                            $stmt->bindParam(':date_creation', $dateCreation);
                            $stmt->bindParam(':date_modification', $dateModification);

                        }
                        elseif (strtolower($typeEntite) == "personne") {
                            $nom = $donnéesLigne[1];
                            $prenom = $donnéesLigne[2];
                            $codePostal = $donnéesLigne[3];
                            $ville = $donnéesLigne[4];
                            $lieuRencontrePossible = $this->getBoolean($donnéesLigne[5]);
                            $adresse = $donnéesLigne[6];
                            $longitude = $donnéesLigne[7];
                            $latitude = $donnéesLigne[8];
                            $projection = $donnéesLigne[9];

                            $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, prenom, adresse, code_postal, ville, longitude, latitude,"
                                ." projection, lieu_rencontre_possible, date_creation, date_modification )"
                                ."VALUES ( :id_utilisateur, :type_entite, :nom, :prenom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                ." :projection, :lieu_rencontre_possible, :date_creation, :date_modification );";

                            $stmt = $bdd->prepare($sql);
                            $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                            $stmt->bindParam(':type_entite', $typeEntite);
                            $stmt->bindParam(':nom', $nom);
                            $stmt->bindParam(':prenom', $prenom);
                            $stmt->bindParam(':adresse', $adresse);
                            $stmt->bindParam(':code_postal', $codePostal);
                            $stmt->bindParam(':ville', $ville);
                            $stmt->bindParam(':longitude', $longitude);
                            $stmt->bindParam(':latitude', $latitude);
                            $stmt->bindParam(':projection', $projection);
                            $stmt->bindParam(':lieu_rencontre_possible', $lieuRencontrePossible);
                            $stmt->bindParam(':date_creation', $dateCreation);
                            $stmt->bindParam(':date_modification', $dateModification);
                        }
                        elseif ($typeEntite == "LIEU") {
                            $nom = $donnéesLigne[1];
                            $codePostal = $donnéesLigne[2];
                            $ville = $donnéesLigne[3];
                            $lieuRencontrePossible = $this->getBoolean($donnéesLigne[4]);
                            $adresse = $donnéesLigne[5];
                            $longitude = $donnéesLigne[6];
                            $latitude = $donnéesLigne[7];
                            $projection = $donnéesLigne[8];
                            $typeEquipement = $donnéesLigne[9];
                            $nombreEquipement = $donnéesLigne[10];
                            $capaciteRencontre = $this->getBoolean($donnéesLigne[11]);
                            $capacitePhaseFinale = $this->getBoolean($donnéesLigne[12]);

                            $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                                ." projection, type_equipement, nombre_equipement, capacite_rencontre, capacite_phase_finale, "
                                ." lieu_rencontre_possible, date_creation, date_modification )"
                                ."VALUES ( :id_utilisateur, :type_entite, :nom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                ." :projection, :type_equipement, :nombre_equipement, :capacite_rencontre, :capacite_phase_finale, "
                                ." :lieu_rencontre_possible, :date_creation, :date_modification );";

                            $stmt = $bdd->prepare($sql);
                            $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                            $stmt->bindParam(':type_entite', $typeEntite);
                            $stmt->bindParam(':nom', $nom);
                            $stmt->bindParam(':adresse', $adresse);
                            $stmt->bindParam(':code_postal', $codePostal);
                            $stmt->bindParam(':ville', $ville);
                            $stmt->bindParam(':longitude', $longitude);
                            $stmt->bindParam(':latitude', $latitude);
                            $stmt->bindParam(':projection', $projection);
                            $stmt->bindParam(':type_equipement', $typeEquipement);
                            $stmt->bindParam(':nombre_equipement', $nombreEquipement);
                            $stmt->bindParam(':capacite_rencontre', $capaciteRencontre);
                            $stmt->bindParam(':capacite_phase_finale', $capacitePhaseFinale);
                            $stmt->bindParam(':lieu_rencontre_possible', $lieuRencontrePossible);
                            $stmt->bindParam(':date_creation', $dateCreation);
                            $stmt->bindParam(':date_modification', $dateModification);

                        }
                        # executer la requete
                        $stmt->execute();

                        # afficher le statut de la requete executée
//                    error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
//                        ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, "/var/log/apache2/optimouv.log");

                        # obtenir l'id de l"entité créée
                        $idEntite = $bdd->lastInsertId();
                        array_push($idsEntite, $idEntite);

                    }
                }
            }
        }

        $retour = array(
                "success" => true,
                "idsEntite" => $idsEntite
        );

        return $retour;
    }

    # convertir la valeur du champ lieuRencontrePossible
    private function getBoolean($input){
        // convertir la valeur en boolean
        if (strtolower($input)  == "oui"){
            $input = 1;
        }
        elseif(strtolower($input) == "non"){
            $input = 0;
        }
        return $input;
    }

    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    private function getPdo(){
        # récupérer les parametres de connexion
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # créer une objet PDO
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        } catch (PDOException $e) {
//            error_log("\n Service: Listes, Function: getPdo, datetime: ".$dateTimeNow
//                ."\n PDOException: ".print_r($e, true), 3, "/var/log/apache2/optimouv.log");
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $bdd;
    }



}


