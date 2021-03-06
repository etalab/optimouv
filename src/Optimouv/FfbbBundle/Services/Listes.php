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
use Symfony\Component\Config\Definition\Exception\Exception;

class Listes{


    private $database_name;
    private $database_user;
    private $database_password;
    private $error_log_path;
    private $database_host;

    public function __construct($database_host, $database_name, $database_user, $database_password, $error_log_path)
    {
        $this->database_host = $database_host;
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->error_log_path = $error_log_path;
    }

    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    private function getPdo(){
        # récupérer les parametres de connexion
        $host = $this->database_host;
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # créer une objet PDO
            $bdd = new PDO('mysql:host='.$host.';dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        } catch (PDOException $e) {
            error_log("\n Service: Listes, Function: getPdo, datetime: ".$dateTimeNow
                ."\n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $bdd;
    }
    
    public function controlerEntites($typeEntiteAttendu, $idUtilisateur, $rencontre, $isEquipe){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # afficher le statut de la requete executée
//        error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
//            ."\n _SERVER: ".print_r($_SERVER, true), 3, $this->error_log_path);

        # controler si la taille limite du fichier à été atteinte
        $tailleFichier = $_SERVER["CONTENT_LENGTH"];


        // PHP setting pour détecter la fin de ligne correctement pour Windows, Linux et Mac
        ini_set('auto_detect_line_endings', TRUE);


        # obtenir le chemin d'upload du fichier
        $cheminFichierTemp = $_FILES["file-0"]["tmp_name"];

        # obtenir le type du fichier
        $typeFichier = $_FILES["file-0"]["type"];

        # obtenir le nom du fichier
        $nomFichier = $_FILES["file-0"]["name"];

        # obtenir l'extension du fichier
        $extensionFichier = explode(".", $_FILES["file-0"]["name"]);
        $extensionFichier = end($extensionFichier);


        # prendre juste le nom de fichier (supprimer la partie '.csv' dans le nom de fichier)
        $nomFichierSansExt = explode(".", $nomFichier)[0];


        # vérifier la longueur max du nom de fichier
        if(strlen($nomFichierSansExt) > 100){
            $retour = array(
                "success" => false,
                "msg" => "Le nom du fichier importé est trop long. Veuillez corriger le nom du fichier et l'importer de nouveau."
            );
            return $retour;
        }


//        error_log("\n Function: controlerEntites, \n"
//            ."nomFichierSansExt: ".print_r($nomFichierSansExt, true), 3, $this->error_log_path);

        # controler l'existence du nom de fichier dans la table des listes de participants et des listes de lieux
        $retourControlerExistenceNomFichier = $this->verifierExistenceNomFichier($nomFichierSansExt, $idUtilisateur, $rencontre, $isEquipe);

        if(!$retourControlerExistenceNomFichier["success"]){
            $retour = array(
                "success" => false,
                "msg" => "Une liste avec le même nom a déjà été importée. Veuillez renommer votre liste si vous souhaitez tout de même l'importer."
            );

            return $retour;
        }

        // Dès qu'un fichier a été reçu par le serveur
        if (file_exists($cheminFichierTemp) || is_uploaded_file($cheminFichierTemp)) {

            # types détectés comme csv
            $mimes = array('application/vnd.ms-excel','text/plain','text/csv','text/tsv');

            // Si le fichier n'est pas un fichier csv
            if(in_array($typeFichier, $mimes) and (strtolower($extensionFichier)  == "csv") ){
                // lire le contenu du fichier
                $file = new SplFileObject($cheminFichierTemp, 'r');

                // On lui indique que c'est du CSV
                $file->setFlags(SplFileObject::READ_CSV);

                // auto-détecter le délimiteur
                $this->autoSetDelimiter($file);

                // retourner le curseur de l'objet file à la position initiale
                $file->rewind();

                // obtenir les données d'en-têtes
                $donneesEntete = $file->fgetcsv();


                // Controler les colonnes des en-têtes avec les formats fixés
                // Fichier equipes, personnes, lieux
                $resultatBooleanControlEntete = $this->controlerEntete($donneesEntete, $rencontre, $isEquipe);


                if(!$resultatBooleanControlEntete["success"]){
                    $retour = array(
                        "success" => false,
                        "msg" => "Erreur ligne : 1!"
                            .$resultatBooleanControlEntete["msg"]
                    );
                    return $retour;
                }

                # tableau qui contient toutes les lignes erronées
                $lignesErronees = [];
                $maxLignesErronees = 10;

                # obtenir l'objet PDO
                $bdd = $this->getPdo();

                # tableau qui contient toutes les données (utilisé pour gérer les doublons)
                $toutesLignes = [];

                # tableau qui contient le nom des toutes équipes (utilisé pour gérer le controle du fichier plateau pour les champs equipe adverse 1 et 2)
                $tousNomsEquipes = [];

                # tableau qui contient le nom des equipes adverses pour le premier et deuxième jour
                $premierJourEquipesAdverses1 = [];
                $premierJourEquipesAdverses2 = [];
                $deuxiemeJourEquipesAdverses1 = [];
                $deuxiemeJourEquipesAdverses2 = [];
                $premierJourReceptionListe = [];
                $deuxiemeJourReceptionListe = [];

                # tableau des poules
                $poules = [];

                // msg d'erreur générique
                $genericMsg = "Veuillez corriger les champs indiqués et effectuer à nouveau l’import";

                if (!$bdd) {
                    //erreur de connexion
                    error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
                }

                $idsEntite = [];
                // obtenir les données pour chaque ligne
                $nbrLigne = 1;

                while (!$file->eof()) {
                    $donnéesLigne = $file->fgetcsv();
                    $nbrLigne++;

                    # controler si on a atteint le max nombre des erreurs
                    if(count($lignesErronees) == $maxLignesErronees){
                        break;
                    }

                    // controler le fichier vide (sans données)
                    if($donnéesLigne == array(null) and $nbrLigne == 2){
                        $retour = array(
                            "success" => false,
                            "msg" => "Erreur ligne :".$nbrLigne."!"
                                ." Il n'y a pas de données dans le fichier csv!"
                                ." Veuillez uploader un fichier csv qui contient des données"
                        );
                        array_push($lignesErronees, $retour["msg"]);
                        continue;
                    }




                    // tester s'il y a des données
                    if($donnéesLigne != array(null)){


                        // obtenir la valeur pour chaque paramètre
                        $typeEntite = $donnéesLigne[0];


                        // controler si le type d'entité attendu correspond au type donné dans le fichier
                        if($typeEntiteAttendu == "participants"){
                            if(!in_array(strtolower($typeEntite), ["equipe", "personne"])){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le type d'entité donné: $typeEntite ne correspond pas au type attendu"
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;

                            }
                        }
                        elseif($typeEntiteAttendu == "lieux") {
                            if (strtolower($typeEntite) != "lieu") {
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :" . $nbrLigne . "!"
                                        . " Le type d'entité donné: $typeEntite ne correspond pas au type attendu (LIEU)  "
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;

                            }
                        }
                        else{
                            error_log("service: listes, function: controlerEntites", 3, $this->error_log_path);
                            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');

                        }



                        // obtenir les valeurs selon le type d'entité
                        if (strtolower($typeEntite) == "equipe") {

                            # controler le nombre de colonnes
//                                    if(count($donnéesLigne) != 11 && count($donnéesLigne) != 18){
                            if(count($donnéesLigne) != 11 && count($donnéesLigne) != 12 && count($donnéesLigne) != 18 ){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." La ligne doit contenir 11 valeurs (meilleur lieu), 12 ou 18 valeurs (optimisation de poule). Donné: ".count($donnéesLigne)." valeurs"
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

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
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'nom' (colonne 2) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            if(empty($codePostal)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'code postal' (colonne 3) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            };
                            if(empty($ville)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'ville' (colonne 4) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            };
                            if(empty($participants)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'nombre de participants' (colonne 5) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            };
                            # controler le champ 'lieu de rencontre possible'
                            if( empty($donnéesLigne[5]) ){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'lieu de rencontre possible' (colonne 6) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # controler le champ 'participants'
                            # il faut que la valeur soit une valeur numeric
                            if(!is_numeric($participants)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'nombre de participants' (colonne 5) doit avoir une valeur numérique!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # controler le champ 'lieu de rencontre possible'
                            # il faut que la valeur soit 'OUI' ou 'NON'
                            if((strtolower($donnéesLigne[5]) != 'non') and (strtolower($donnéesLigne[5]) != 'oui')){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'lieu de rencontre possible' (colonne 6) doit avoir la valeur 'OUI' ou 'NON'!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }



                            # corriger le code postal si un zéro est manquant dans le premier chiffre
                            $codePostal = $this->corrigerCodePostal($codePostal);

                            # controler le champ 'code postal'
                            # il faut que la valeur contient 5 chiffres
                            if(strlen($codePostal) != 5){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'code postal' (colonne 3) doit contenir 5 chiffres!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # controler le code postal et la ville
                            # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                            $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);

                            if(!$statutControlCodePostalVille["success"]){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Veuillez corriger le code postal (colonne 3) et la ville (colonne 4) et effectuer à nouveau l'import "
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }




                            # les champs optionnels
                            $adresse = $donnéesLigne[6];
                            $latitude = $donnéesLigne[7];
                            $longitude = $donnéesLigne[8];
                            $projection = $donnéesLigne[9];
                            $licencies = $donnéesLigne[10];


                            // controler le champ 'POULE' pour l'ancien et le nouveau format csv (12 et 18 colonnes)
                            if(count($donnéesLigne) == 12 || count($donnéesLigne) == 18 ){
                                $poule = $donnéesLigne[11];

                                // controler la presence de valeur pour le champ 'POULE'
//                                        if(empty($poule)){
//                                            $retour = array(
//                                                "success" => false,
//                                                "msg" => "Erreur ligne :".$nbrLigne."!"
//                                                    ."Le champ poule doit être rempli. Veuillez corriger et importer de nouveau votre fichier"
//                                            );
//                                            array_push($lignesErronees, $retour["msg"]);
//                                            continue;
//                                        }

                                // controler si les valeurs fournies pour le champ 'POULE' sont des alphabets
                                $alphabet = ['','A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N','O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z' ];
                                if (!in_array($poule, $alphabet))
                                {
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur ligne :".$nbrLigne."!"
                                            ."Le champ poule doit contenir une lettre. Veuillez corriger et importer de nouveau votre fichier"
                                    );
                                    array_push($lignesErronees, $retour["msg"]);
                                    continue;
                                }

                                // ajouter la poule dans le tableau des poules
                                array_push($poules, $poule);

                            }

                            // test de l'import pour l'optimisation des poules
                            if(count($donnéesLigne) == 18){

                                # ajouter les noms dans la liste
                                array_push($tousNomsEquipes, $donnéesLigne[1]);

                                $premierJourReception = $donnéesLigne[12];
                                $premierJourEquipe1 = $donnéesLigne[13];
                                $premierJourEquipe2 = $donnéesLigne[14];

                                $deuxiemeJourReception = $donnéesLigne[15];
                                $deuxiemeJourEquipe1 = $donnéesLigne[16];
                                $deuxiemeJourEquipe2 = $donnéesLigne[17];

                                # ajouter les equipes adverses les listes
                                array_push($premierJourEquipesAdverses1, $premierJourEquipe1);
                                array_push($premierJourEquipesAdverses2, $premierJourEquipe2);
                                array_push($deuxiemeJourEquipesAdverses1, $deuxiemeJourEquipe1);
                                array_push($deuxiemeJourEquipesAdverses2, $deuxiemeJourEquipe2);
                                array_push($premierJourReceptionListe, $premierJourReception);
                                array_push($deuxiemeJourReceptionListe, $deuxiemeJourReception);

                                // controle le champ 'PREMIER JOUR DE RECEPTION'
                                if( ($premierJourReception == "" ) or  !(is_numeric($premierJourReception))   ){

                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur ligne :".$nbrLigne."!"
                                            ."Le champ 'PREMIER JOUR DE RECEPTION' (colonne 13) doit être rempli et avoir une valeur de type entier égale ou supérieure à 0!"
                                            ."Veuillez corriger et importer de nouveau votre fichier"
                                    );
                                    array_push($lignesErronees, $retour["msg"]);
                                    continue;
                                }
                                // controle le champ 'DEUXIEME JOUR DE RECEPTION'
                                if( ($deuxiemeJourReception == "" ) or  !(is_numeric($deuxiemeJourReception))   ){

                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur ligne :".$nbrLigne."!"
                                            ."Le champ 'DEUXIEME JOUR DE RECEPTION' (colonne 16) doit être rempli et avoir une valeur de type entier égale ou supérieure à 0!"
                                            ."Veuillez corriger et importer de nouveau votre fichier"
                                    );
                                    array_push($lignesErronees, $retour["msg"]);
                                    continue;
                                }

                                // controler equipe 1 et equipe 2, elles doivent être renseignées
                                if( ($premierJourReception > 0 ) && ( $premierJourEquipe1 == "" || $premierJourEquipe2 == "" )  ){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur ligne :".$nbrLigne."!"
                                            ."Le champ 'EQUIPE ADVERSE 1' (colonne 14) et le champ 'EQUIPE ADVERSE 2' (colonne 15) doivent être remplis!"
                                            ."Veuillez corriger et importer de nouveau votre fichier"
                                    );
                                    array_push($lignesErronees, $retour["msg"]);
                                    continue;
                                }

                                if( ($deuxiemeJourReception > 0 ) && ( $deuxiemeJourEquipe1 == "" || $deuxiemeJourEquipe2 == "" )  ){
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur ligne :".$nbrLigne."!"
                                            ."Le champ 'EQUIPE ADVERSE 1' (colonne 17) et le champ 'EQUIPE ADVERSE 2' (colonne 18) doivent être remplis!"
                                            ."Veuillez corriger et importer de nouveau votre fichier"
                                    );
                                    array_push($lignesErronees, $retour["msg"]);
                                    continue;
                                }

                            }

                        }
                        elseif (strtolower($typeEntite) == "personne") {

                            # controler le nombre de colonnes
                            if(count($donnéesLigne) != 10){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." La ligne doit contenir 10 valeurs. Donné: ".count($donnéesLigne)." valeurs"
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

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
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'nom' (colonne 2) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            if(empty($prenom)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'prenom' (colonne 3) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            if(empty($codePostal)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'code postal' (colonne 4) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            if(empty($ville)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'ville' (colonne 5) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            # controler le champ 'lieu de rencontre possible'
                            if( empty($donnéesLigne[5]) ){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'lieu de rencontre possible' (colonne 6) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # controler le champ '$lieuRencontrePossible'
                            # il faut que la valeur soit 'OUI' ou 'NON'
                            if((strtolower($donnéesLigne[5]) != 'non') and (strtolower($donnéesLigne[5]) != 'oui')){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'lieu de rencontre possible' (colonne 6) doit avoir la valeur 'OUI' ou 'NON'!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # corriger le code postal si un zéro est manquant dans le premier chiffre
                            $codePostal = $this->corrigerCodePostal($codePostal);

                            # controler le champ 'code postal'
                            # il faut que la valeur contient 5 chiffres
                            if(strlen($codePostal) != 5){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'code postal' (colonne 4) doit contenir 5 chiffres!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # controler le code postal et la ville
                            # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                            $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);

                            if(!$statutControlCodePostalVille["success"]){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Les valeurs du couple 'code postal' (colonne 4) et 'ville' (colonne 5) ne sont pas reconnues!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # les champs optionnels
                            $adresse = $donnéesLigne[6];
                            $latitude = $donnéesLigne[7];
                            $longitude = $donnéesLigne[8];
                            $projection = $donnéesLigne[9];

                        }
                        elseif ($typeEntite == "LIEU") {
                            # les champs obligatoires
                            $nom = $donnéesLigne[1];
                            $codePostal = $donnéesLigne[2];
                            $ville = $donnéesLigne[3];

                            # controler le nombre de colonnes
                            if(count($donnéesLigne) != 13){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." La ligne doit contenir 13 valeurs. Donné: ".count($donnéesLigne)." valeurs"
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            $lieuRencontrePossible = $this->getBoolean($donnéesLigne[4]);


                            # controler tous les champs obligatoires
                            if(empty($nom)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'nom' (colonne 2) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            if(empty($codePostal)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'code postal' (colonne 3) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            if(empty($ville)){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'ville' (colonne 4) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }
                            # controler le champ 'lieu de rencontre possible'
                            if( empty($donnéesLigne[4]) ){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'lieu de rencontre possible' (colonne 5) doit être rempli!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # controler le champ 'lieu de rencontre possible'
                            # il faut que la valeur soit 'OUI'
                            if((strtolower($donnéesLigne[4]) != 'oui')){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'lieu de rencontre possible' (colonne 5) doit avoir la valeur 'OUI' pour la liste de lieux !"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # corriger le code postal si un zéro est manquant dans le premier chiffre
                            $codePostal = $this->corrigerCodePostal($codePostal);

                            # controler le champ 'code postal'
                            # il faut que la valeur contient 5 chiffres
                            if(strlen($codePostal) != 5){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Le champ 'code postal' (colonne 3) doit contenir 5 chiffres!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # controler le code postal et la ville
                            # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                            $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);

                            if(!$statutControlCodePostalVille["success"]){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur ligne :".$nbrLigne."!"
                                        ." Les valeurs du couple 'code postal' (colonne 3) et 'ville' (colonne 4) ne sont pas reconnues!"
                                        .$genericMsg
                                );
                                array_push($lignesErronees, $retour["msg"]);
                                continue;
                            }

                            # les champs optionnels
                            $adresse = $donnéesLigne[5];
                            $latitude = $donnéesLigne[6];
                            $longitude = $donnéesLigne[7];
                            $projection = $donnéesLigne[8];
                            $typeEquipement = $donnéesLigne[9];
                            $nombreEquipement = $donnéesLigne[10];
                            $capaciteRencontre = $this->getBoolean($donnéesLigne[11]);
                            $capacitePhaseFinale = $this->getBoolean($donnéesLigne[12]);
                        }
                        else{
                            $retour = array(
                                "success" => false,
                                "msg" => "Erreur ligne :".$nbrLigne."!"
                                    ." Le type d'entité n'est pas reconnu!"
                                    ." Veuillez s'assurer que le type d'entité est parmi 'EQUIPE', 'PERSONNE' ou 'LIEU'!"
                                    .$genericMsg
                            );
                            array_push($lignesErronees, $retour["msg"]);
                            continue;
                        }


                        # controler les doublons pour tous les types
                        # ajouter la ligne courante dans le répértoire des lignes si ce n'est pas un doublon
                        $donneesLigneEquipe = [$nom, $codePostal, $ville];

                        if(!in_array($donneesLigneEquipe, $toutesLignes )){
                            array_push($toutesLignes, $donneesLigneEquipe);
                        }
                        else{
                            $retour = array(
                                "success" => false,
                                "msg" => "Erreur ligne :".$nbrLigne."!"
                                    ." Le fichier comporte des lignes en double.!"
                                    ." Veuillez supprimer cette ligne et effectuer à nouveau l’import"
                            );
                            array_push($lignesErronees, $retour["msg"]);
                            continue;
                        }



                    }

                }



                // controler le champ 'poule'
                $statutErreurPoule = 0;
                // controler s'il y a des valeurs vides pour le champ 'poule'
                if(in_array("", $poules)){
                    // avoir le nombre d'occurence pour chaque clé
                    $elementsCount = array_count_values($poules);

                    // erreur si nombre des chaines vides n'est pas égal au nombre d'élements dans le tableau
                    if(count($poules) != $elementsCount[""] ){
                        $statutErreurPoule = 1;
                    }
                }



//                error_log("service: listes, function: controlerEntites, statutErreurPoule: ".print_r($statutErreurPoule, True), 3, $this->error_log_path);

                if($statutErreurPoule == 1){
                    $retour = array(
                        "success" => false,
                        "msg" => "Erreur ligne :".$nbrLigne."!"
                            ." Le champ \"POULE\" (colonne 12) être rempli pour toutes les lignes ou vide pour toutes les lignes de votre fichier.!"
                            ."Veuillez corriger votre fichier et effectuer à nouveau l'import"
                    );
                    array_push($lignesErronees, $retour["msg"]);

                }


                // controler equipe 1 et equipe 2, elles doivent figurer dans les lignes importées (match plateau)
                if($donneesEntete != [] && count($donneesEntete) == 18){

                    for($k = 0; $k < count($toutesLignes); $k ++){
                        $nbrLigne = $k + 2;

                        // premier jour
                        $premierJourEquipe1 = $premierJourEquipesAdverses1[$k];
                        $premierJourEquipe2 = $premierJourEquipesAdverses2[$k];
                        $premierJourReception = $premierJourReceptionListe[$k];


                        // deuxième jour
                        $deuxiemeJourEquipe1 = $deuxiemeJourEquipesAdverses1[$k];
                        $deuxiemeJourEquipe2 = $deuxiemeJourEquipesAdverses2[$k];
                        $deuxiemeJourReception = $deuxiemeJourReceptionListe[$k];


                        // premier jour equipe adverse 1
                        if( $premierJourReception > 0 && !in_array($premierJourEquipe1, $tousNomsEquipes) ){


                            $retour = array(
                                "success" => false,
                                "msg" => "Erreur ligne :".$nbrLigne."!"
                                    ." Le champ \"EQUIPE ADVERSE 1\" (colonne 14) est erroné et ne correspond pas à un nom d'équipe contenu dans votre fichier d'import .!"
                                    ." Veuillez corriger les champs indiqués et effectuer à nouveau l'import"
                            );
                            array_push($lignesErronees, $retour["msg"]);
                            continue;
                        }
                        if( $premierJourReception > 0 && !in_array($premierJourEquipe2, $tousNomsEquipes) ) {

                            $retour = array(
                                "success" => false,
                                "msg" => "Erreur ligne :" . $nbrLigne . "!"
                                    ." Le champ \"EQUIPE ADVERSE 2\" (colonne 15) est erroné et ne correspond pas à un nom d'équipe contenu dans votre fichier d'import .!"
                                    ." Veuillez corriger les champs indiqués et effectuer à nouveau l'import"
                            );
                            array_push($lignesErronees, $retour["msg"]);
                            continue;
                        }
                        if( $deuxiemeJourReception> 0 && !in_array($deuxiemeJourEquipe1, $tousNomsEquipes) ) {

                            $retour = array(
                                "success" => false,
                                "msg" => "Erreur ligne :" . $nbrLigne . "!"
                                    ." Le champ \"EQUIPE ADVERSE 1\" (colonne 17) est erroné et ne correspond pas à un nom d'équipe contenu dans votre fichier d'import .!"
                                    ." Veuillez corriger les champs indiqués et effectuer à nouveau l'import"
                            );
                            array_push($lignesErronees, $retour["msg"]);
                            continue;
                        }
                        if( $deuxiemeJourReception> 0 && !in_array($deuxiemeJourEquipe2, $tousNomsEquipes) ) {

                            $retour = array(
                                "success" => false,
                                "msg" => "Erreur ligne :" . $nbrLigne . "!"
                                    ." Le champ \"EQUIPE ADVERSE 2\" (colonne 18) est erroné et ne correspond pas à un nom d'équipe contenu dans votre fichier d'import .!"
                                    ." Veuillez corriger les champs indiqués et effectuer à nouveau l'import"
                            );
                            array_push($lignesErronees, $retour["msg"]);
                            continue;
                        }


                    }

                }

                // controler s'il y a des lignes erronées
                if(count($lignesErronees) > 0){
                    $retour = array(
                        "success" => false,
                        "msg" => $lignesErronees
                    );
                }else{
                    $retour = array(
                        "success" => true,
                        "msg" => "Contrôle réussi "
                    );
                }


            }
            else{
                $retour = array(
                    "success" => false,
                    "msg" => "Veuillez convertir votre fichier au format csv et effectuer à nouveau l'import."
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


    public function creerListeParticipants($idsEntite, $nomFichier, $idUtilisateur, $rencontre){

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerListeParticipants, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            } else {

                # récuperer la valeur des autres variables
                $nom = $nomFichier;

                # construire la liste d'équipes
                $equipes = "";
                for ($i=0; $i<count($idsEntite); $i++){
                    $equipes .= $idsEntite[$i].",";
                }
                // supprimer la dernière virgule
                $equipes = rtrim($equipes, ",");

                # insérer dans la base de données
                $sql = "INSERT INTO  liste_participants (nom, id_utilisateur, date_creation, equipes, rencontre) VALUES ( :nom, :idUtilisateur, :dateCreation, :equipes, :rencontre);";
                $stmt = $bdd->prepare($sql);
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':idUtilisateur', $idUtilisateur);
                $stmt->bindParam(':dateCreation', $dateCreation);
                $stmt->bindParam(':equipes', $equipes);
                $stmt->bindParam(':rencontre', $rencontre);
                $stmt->execute();

                # afficher le statut de la requete executée
//                error_log("\n Service: Listes, Function: creerListeParticipants, datetime: ".$dateTimeNow
//                    ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, $this->error_log_path);
            }

            $retour = array(
                "success" => true,
                "data" => array("dateCreation" => $dateCreation),
            );



        } catch (PDOException $e) {
            error_log("\n erreur PDO, Service: Listes, Function: creerListeParticipants, datetime: ".$dateTimeNow."\n"
                ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }




        return $retour;
    }

    public function creerListeLieux($idsEntite, $nomFichier, $idUtilisateur){

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerListeLieux, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            } else {
                # récuperer la valeur des autres variables
                $nom = $nomFichier;

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
//                error_log("\n Service: Listes, Function: creerListeLieux, datetime: ".$dateTimeNow
//                    ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, $this->error_log_path);
            }

            $retour = array(
                "success" => true,
                "data" => array("dateCreation" => $dateCreation),
            );
        } catch (PDOException $e) {
            error_log("\n erreur PDO, Service: Listes, Function: creerListeLieux, datetime: ".$dateTimeNow."\n"
                ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $retour;
    }
    
    public function creerEntites($idUtilisateur)
    {
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        // PHP setting pour détecter la fin de ligne correctement pour Windows, Linux et Mac
        ini_set('auto_detect_line_endings', TRUE);

        try {

            # obtenir le chemin temporaire du fichier uploadé
            $cheminFichierTemp = $_FILES["file-0"]["tmp_name"];

            # obtenir le nom du fichier uploadé
            $nomFichier = $_FILES["file-0"]["name"];

            # enlever l'extension du nom de fichier
            $nomFichier = str_replace(".csv", "" , $nomFichier);


            // Dès qu'un fichier a été reçu par le serveur
            if (file_exists($cheminFichierTemp) || is_uploaded_file($cheminFichierTemp)) {

                // lire le contenu du fichier
                $file = new SplFileObject($cheminFichierTemp, 'r');
                $delimiter = ",";

                // On lui indique que c'est du CSV
                $file->setFlags(SplFileObject::READ_CSV);

                // auto-détecter le délimiteur
                $this->autoSetDelimiter($file);

                // initialiser toutes les vars

                # obtenir la date courante du système
                date_default_timezone_set('Europe/Paris');
                $dateCreation = date('Y-m-d', time());
                $dateModification = date('Y-m-d', time());
                $dateTimeNow = date('Y-m-d_G:i:s', time());


                # obtenir l'objet PDO
                $bdd = $this->getPdo();

                if (!$bdd) {
                    //erreur de connexion
                    error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow, 3, $this->error_log_path);
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


                            // convertir l'encodage pour le nom
                            $nom = $donnéesLigne[1];
                            $nom = $this->detecterEtConvertirEncodage($nom);


                            // obtenir les valeurs selon le type d'entité
                            if (strtolower($typeEntite) == "equipe") {
                                $codePostal = $donnéesLigne[2];

                                // convertir l'encodage pour la ville
                                $ville = $donnéesLigne[3];
                                $ville = $this->detecterEtConvertirEncodage($ville);

                                $participants = $donnéesLigne[4];
                                $lieuRencontrePossible = $this->getBoolean($donnéesLigne[5]);

                                // convertir l'encodage pour l'adresse
                                $adresse = $donnéesLigne[6];
                                $adresse = $this->detecterEtConvertirEncodage($adresse);

                                $latitude = $donnéesLigne[7];
                                $longitude = $donnéesLigne[8];

                                // convertir l'encodage pour la projection
                                $projection = $donnéesLigne[9];
                                $projection = $this->detecterEtConvertirEncodage($projection);

                                $licencies = $donnéesLigne[10];


                                # corriger le code postal si un zéro est manquant dans le premier chiffre
                                $codePostal = $this->corrigerCodePostal($codePostal);


                                # controler le code postal et la ville
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);
                                $idVilleFrance = $statutControlCodePostalVille["idVille"];


                                // vérifier le nombre de valeurs dans le fichier
                                if (count($donnéesLigne) == 12){
                                    $poule = $donnéesLigne[11];
                                    //champs rencontre pour specifier si l equipe appartient à une poule ou bien dediee pour les rencontres


                                    $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                                        ." projection, participants, "
                                        ." licencies, lieu_rencontre_possible, date_creation, date_modification, id_ville_france, poule )"
                                        ."VALUES ( :id_utilisateur, :type_entite, :nom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                        ." :projection, :participants, "
                                        .":licencies, :lieu_rencontre_possible, :date_creation, :date_modification, :id_ville_france, :poule);";


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
                                    $stmt->bindParam(':id_ville_france', $idVilleFrance);
                                    $stmt->bindParam(':poule', $poule);
                                }
                                elseif(count($donnéesLigne) == 18){
                                    $poule = $donnéesLigne[11];
                                    //champs rencontre pour specifier si l equipe appartient à une poule ou bien dediee pour les rencontres

                                    $premierJourReception = $donnéesLigne[12];
                                    $premierJourEquipe1 = $donnéesLigne[13];
                                    $premierJourEquipe2 = $donnéesLigne[14];

                                    $deuxiemeJourReception = $donnéesLigne[15];
                                    $deuxiemeJourEquipe1 = $donnéesLigne[16];
                                    $deuxiemeJourEquipe2 = $donnéesLigne[17];

                                    $refPlateau = [];
                                    $refPlateau["premierJourReception"] = $premierJourReception;
                                    $refPlateau["premierJourEquipe1"] = $premierJourEquipe1;
                                    $refPlateau["premierJourEquipe2"] = $premierJourEquipe2;
                                    $refPlateau["deuxiemeJourReception"] = $deuxiemeJourReception;
                                    $refPlateau["deuxiemeJourEquipe1"] = $deuxiemeJourEquipe1;
                                    $refPlateau["deuxiemeJourEquipe2"] = $deuxiemeJourEquipe2;
                                    $refPlateau = json_encode($refPlateau);


                                    $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                                        ." projection, participants, "
                                        ." licencies, lieu_rencontre_possible, date_creation, date_modification, id_ville_france, poule, ref_plateau )"
                                        ."VALUES ( :id_utilisateur, :type_entite, :nom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                        ." :projection, :participants, "
                                        .":licencies, :lieu_rencontre_possible, :date_creation, :date_modification, :id_ville_france, :poule, :ref_plateau);";

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
                                    $stmt->bindParam(':id_ville_france', $idVilleFrance);
                                    $stmt->bindParam(':poule', $poule);
                                    $stmt->bindParam(':ref_plateau', $refPlateau);



                                }



                                else{
                                    $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                                        ." projection, participants, "
                                        ." licencies, lieu_rencontre_possible, date_creation, date_modification, id_ville_france  )"
                                        ."VALUES ( :id_utilisateur, :type_entite, :nom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                        ." :projection, :participants, "
                                        .":licencies, :lieu_rencontre_possible, :date_creation, :date_modification, :id_ville_france );";


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
                                    $stmt->bindParam(':id_ville_france', $idVilleFrance);
                                }

                            }
                            elseif (strtolower($typeEntite) == "personne") {

                                // convertir l'encodage pour le prénom
                                $prenom = $donnéesLigne[2];
                                $prenom = $this->detecterEtConvertirEncodage($prenom);

                                $codePostal = $donnéesLigne[3];

                                // convertir l'encodage pour la ville
                                $ville = $donnéesLigne[4];
                                $ville = $this->detecterEtConvertirEncodage($ville);

                                $lieuRencontrePossible = $this->getBoolean($donnéesLigne[5]);

                                // convertir l'encodage pour l'adresse
                                $adresse = $donnéesLigne[6];
                                $adresse = $this->detecterEtConvertirEncodage($adresse);


                                $latitude = $donnéesLigne[7];
                                $longitude = $donnéesLigne[8];

                                // convertir l'encodage pour la projection
                                $projection = $donnéesLigne[9];
                                $projection = $this->detecterEtConvertirEncodage($projection);

                                # nombre de participants par défaut pour personne
                                $participants = 1;

                                # corriger le code postal si un zéro est manquant dans le premier chiffre
                                $codePostal = $this->corrigerCodePostal($codePostal);

                                # controler le code postal et la ville
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);
                                $idVilleFrance = $statutControlCodePostalVille["idVille"];

                                $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, prenom, adresse, code_postal, ville, longitude, latitude,"
                                    ." projection, participants, lieu_rencontre_possible, date_creation, date_modification,  id_ville_france )"
                                    ."VALUES ( :id_utilisateur, :type_entite, :nom, :prenom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                    ." :projection, :participants, :lieu_rencontre_possible, :date_creation, :date_modification, :id_ville_france );";

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
                                $stmt->bindParam(':participants', $participants);
                                $stmt->bindParam(':lieu_rencontre_possible', $lieuRencontrePossible);
                                $stmt->bindParam(':date_creation', $dateCreation);
                                $stmt->bindParam(':date_modification', $dateModification);
                                $stmt->bindParam(':id_ville_france', $idVilleFrance);
                            }
                            elseif ($typeEntite == "LIEU") {
                                $codePostal = $donnéesLigne[2];

                                // convertir l'encodage pour la ville
                                $ville = $donnéesLigne[3];
                                $ville = $this->detecterEtConvertirEncodage($ville);

                                $lieuRencontrePossible = $this->getBoolean($donnéesLigne[4]);

                                // convertir l'encodage pour l'adresse
                                $adresse = $donnéesLigne[5];
                                $adresse = $this->detecterEtConvertirEncodage($adresse);

                                $latitude = $donnéesLigne[6];
                                $longitude = $donnéesLigne[7];

                                // convertir l'encodage pour la projection
                                $projection = $donnéesLigne[8];
                                $projection = $this->detecterEtConvertirEncodage($projection);

                                // convertir l'encodage pour le type d'équipement
                                $typeEquipement = $donnéesLigne[9];
                                $typeEquipement = $this->detecterEtConvertirEncodage($typeEquipement);

                                $nombreEquipement = $donnéesLigne[10];
                                $capaciteRencontre = $this->getBoolean($donnéesLigne[11]);
                                $capacitePhaseFinale = $this->getBoolean($donnéesLigne[12]);

                                # corriger le code postal si un zéro est manquant dans le premier chiffre
                                $codePostal = $this->corrigerCodePostal($codePostal);

                                # controler le code postal et la ville
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);
                                $idVilleFrance = $statutControlCodePostalVille["idVille"];




                                # corriger le code postal si un zéro est manquant dans le premier chiffre
                                $codePostal = $this->corrigerCodePostal($codePostal);

                                $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                                    ." projection, type_equipement, nombre_equipement, capacite_rencontre, capacite_phase_finale, "
                                    ." lieu_rencontre_possible, date_creation, date_modification, id_ville_france )"
                                    ."VALUES ( :id_utilisateur, :type_entite, :nom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                    ." :projection, :type_equipement, :nombre_equipement, :capacite_rencontre, :capacite_phase_finale, "
                                    ." :lieu_rencontre_possible, :date_creation, :date_modification, :id_ville_france );";

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
                                $stmt->bindParam(':id_ville_france', $idVilleFrance);

                            }
                            # executer la requete
                            $stmt->execute();

                            # afficher le statut de la requete executée
//                            error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
//                                ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, $this->error_log_path);

                            # obtenir l'id de l"entité créée
                            $idEntite = $bdd->lastInsertId();
                            array_push($idsEntite, $idEntite);

                        }
                    }
                }
            }

            $retour = array(
                "success" => true,
                "idsEntite" => $idsEntite,
                "nomFichier" => $nomFichier
            );


        } catch (PDOException $e) {
            error_log("\n erreur PDO, Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow."\n"
                ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }




        return $retour;
    }
    
    # controller le code postal et le nom de ville
    public function verifierExistenceCodePostalNomVille($codePostal, $nomVille){
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            $char = array("-", "_", "'");
            $nomVille = str_replace($char, " ", $nomVille);

            $bdd = $this->getPdo();

            //chercher l'id de la ville selon la table de reference
            $query = "SELECT 1 as 'prio', ville_id FROM villes_france_free where ville_nom_simple = '$nomVille' AND  ville_code_postal = '$codePostal'
                  UNION
                  SELECT 3 as 'prio', ville_id FROM villes_france_free where ville_nom_simple = '$nomVille'
                  UNION
                  SELECT 2 as 'prio', ville_id FROM villes_france_free where ville_nom_simple LIKE '%$nomVille%' AND  ville_code_postal LIKE '%$codePostal%'
                  UNION
                  SELECT 4 as 'prio', ville_id FROM villes_france_free where ville_code_postal = '$codePostal'
                  ";
            $reqID = $bdd->prepare($query);

            $reqID->execute();
            $result = $reqID->fetchAll(PDO::FETCH_ASSOC);
            $count = count($result);

            $ok = true;
            $ideal = false;

            // test si pas de ville
            if ($count == 0) {
                $ok = false;
            } else {
                foreach ($result as $line) {
                    if ($line['prio'] == 1) {
                        $ideal = $line['ville_id'];
                        break;
                    }
                }

                // si on n'a pas trouve prio 1 alors on cherche prio 2
                if ($ideal === false) {
                    foreach ($result as $line) {
                        if ($line['prio'] == 2) {
                            $ideal = $line['ville_id'];
                            break;
                        }
                    }
                }

                // si on n'a pas trouve prio 2 alors on cherche prio 3
                if ($ideal === false) {
                    foreach ($result as $line) {
                        if ($line['prio'] == 3) {
                            $ideal = $line['ville_id'];
                            break;
                        }
                    }
                }
            }


            // test si pas de ville idéale et plus de 1 ville approximative
            if ($count >= 2 && $ideal === false) {
                $ok = false;
            }

            // en cas d'erreur
            if (!$ok) {
                $msg = "Il y a une erreur avec cette ville [$nomVille] et le code postal [$codePostal]";
                $retour = array(
                    "success" => false,
                    "msg" => $msg,
                );
                return $retour;
            }

            if ($ideal !== false)
                $idVille = $ideal;
            else
                $idVille = $result[0]['ville_id'];

            $retour = array(
                "success" => true,
                "msg" => "",
                "idVille" => $idVille
            );

        } catch (PDOException $e) {
            error_log("\n erreur PDO, Service: Listes, Function: verifierExistenceCodePostalNomVille, datetime: ".$dateTimeNow."\n"
                ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $retour;


    }



    /*
    * Fonction pour détecter l'encodage utilisé et transcoder la chaine de charactère donné en entrée en UTF-8
    */
    private function detecterEtConvertirEncodage($inputStr){
        // détecter l'encodage MacRoman
        if($this->isMacRomanEncoded($inputStr)){
            $outputStr = iconv('mac', 'UTF-8', $inputStr);
        }
        // faire le transcodage en UTF-8 si l'encodage utilisé n'est pas en UTF-8
        else{
            // détecter l'encodage UTF-8 et ISO-8859-1
            $detectedEncoding = mb_detect_encoding($inputStr, 'UTF-8, ISO-8859-1', true);

            if ($detectedEncoding != "UTF-8") {
                $outputStr = mb_convert_encoding($inputStr, "UTF-8", $detectedEncoding);
            }
            else{
                $outputStr = $inputStr;
            }
        }

        return $outputStr;

    }

    /*
     * Fonction pour détecter si la chaine de caractère est encodé en MacRoman
     */
    private function isMacRomanEncoded($str) {

        $testBytes = array(0x88, 0x8D, 0x8E, 0x8F, 0x9A );
        foreach ($testBytes as $testByte) {
            if (mb_strpos($str, chr($testByte)) !== false) {
                return true;
            }
        }
        return false;
    }
    
    # vérifier l'existence du nom fichier dans la table liste de participants et liste de lieux
    private function verifierExistenceNomFichier($nomFichier, $idUtilisateur, $rencontre, $isEquipe){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());


        try{
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Listes, Function: verifierExistenceNomFichier, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # control pour les équipes
            if($isEquipe == 1){
                # controler les listes de participants
                $sql = "SELECT nom from liste_participants where id_utilisateur=:id_utilisateur and rencontre=:rencontre;";
                $stmt = $bdd->prepare($sql);
                $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                $stmt->bindParam(':rencontre', $rencontre);
                $stmt->execute();
                $resultatListeParticipants = $stmt->fetchAll(PDO::FETCH_ASSOC);

                for($i=0; $i<count($resultatListeParticipants); $i++){
                    $nomListeParticipantsTmp = $resultatListeParticipants[$i]["nom"];

                    if($nomListeParticipantsTmp == $nomFichier){
                        return array("success" => false);
                    }
                }

            }
            # control pour les lieux
            elseif($isEquipe == 0){
                # controler les listes de lieux
                $sql = "SELECT nom from liste_lieux where id_utilisateur=:id_utilisateur;";
                $stmt = $bdd->prepare($sql);
                $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                $stmt->execute();
                $resultatListeLieux = $stmt->fetchAll(PDO::FETCH_ASSOC);

                for($i=0; $i<count($resultatListeLieux); $i++){
                    $nomListeLieuxTmp = $resultatListeLieux[$i]["nom"];

                    if($nomListeLieuxTmp == $nomFichier){
                        return array("success" => false);
                    }

                }

            }



//            error_log("\n Function: verifierExistenceCodePostalNomVille, \n"
//                ."resultatListeParticipants: ".print_r($resultatListeParticipants, true), 3, $this->error_log_path);


            return array("success" => true);
        }
        catch (Exception $e) {
            error_log("\n erreur générique, Service: Listes, Function: verifierExistenceNomFichier, \n"
                . "erreur: " . print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }


    # corriger le code postal
    private  function corrigerCodePostal($codePostal){
        if( (strlen($codePostal) == 4) and (is_numeric( $codePostal)) ){
            // corriger le code postal
            $codePostal = substr_replace($codePostal, "0", 0, 0);

        }
        return $codePostal;

    }


    # controler les données d'en-têtes
    private function controlerEntete($entete, $rencontre, $isEquipe ){

        $retour = array(
            "success" => false,
            "msg" => ""
        );

        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $genericMsg = "Veuillez utiliser les formats de fichier mis à disposition dans Télécharger les formats de fichiers d’import ";

        // tester le nombre de colonnes
        // nombreColonnesEntetes (11 pour liste d'équipes, 10 pour liste de personnes, 13 pour liste de lieux ce sont pour le cas de meuilleur lieux)
        // nombreColonnesEntetes (12 (match aller retour et aller simple), 18 (match plateau) pour l'optimisation de poules)
//        $nombreColonnesEntetes = [11, 10, 13, 12, 18];

        if($rencontre == 1 && $isEquipe == 1){
            $nombreColonnesEntetes = [11, 10];
        }
        elseif($rencontre == 1 & $isEquipe == 0){
            $nombreColonnesEntetes = [13];
        }
        elseif($rencontre == 0 & $isEquipe == 1){
            $nombreColonnesEntetes = [12, 18];
        }


        if(!in_array(count($entete), $nombreColonnesEntetes)){
            $retour["msg"] = "Veuillez vérifier que le nombre des colonnes dans votre fichier correspond aux templates données.!"
                .$genericMsg;
            return $retour;
        }
        else{
            if($entete[0] != "TYPE D'ENTITE" ){
                $retour["msg"] = "Veuillez vérifier que le nom de la colonne 1 de l'en-tête correspond au template donné (TYPE D'ENTITE).!"
                    .$genericMsg;
                return $retour;
            }
            if($entete[1] != "NOM" ){
                $retour["msg"] = "Veuillez vérifier que le nom de la colonne 2 de l'en-tête correspond au template donné (NOM).!"
                    .$genericMsg;
                return $retour;
            }

//            error_log("service: listes, function: controlerEntites, count entete: ".print_r(count($entete), True), 3, $this->error_log_path);

            // pour la liste d'équipes
            if(count($entete) == 11 || count($entete) == 12 || count($entete) == 18){
                if($entete[2] != "CODE POSTAL" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 3 de l'en-tête correspond au template donné (CODE POSTAL).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[3] != "VILLE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 4 de l'en-tête correspond au template donné (VILLE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[4] != "NOMBRE DE PARTICIPANTS" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 5 de l'en-tête correspond au template donné (NOMBRE DE PARTICIPANTS).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[5] != "LIEU DE RENCONTRE POSSIBLE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 6 de l'en-tête correspond au template donné (LIEU DE RENCONTRE POSSIBLE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[6] != "ADRESSE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 7 de l'en-tête correspond au template donné (ADRESSE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[7] != "LATITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 8 de l'en-tête correspond au template donné (LATITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[8] != "LONGITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 9 de l'en-tête correspond au template donné (LONGITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[9] != "SYSTEME DE PROJECTION GEOGRAPHIQUE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 10 de l'en-tête correspond au template donné (SYSTEME DE PROJECTION GEOGRAPHIQUE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[10] != "NOMBRE DE LICENCIES" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 11 de l'en-tête correspond au template donné (NOMBRE DE LICENCIES).!"
                        .$genericMsg;
                    return $retour;
                }

                # controle supplementaire pour le fichier de l'optimisation de poule
                if(count($entete) == 12 || count($entete) == 18) {
                    if($entete[11] != "POULE" ){
                        $retour["msg"] = "Veuillez vérifier que le nom de la colonne 12 de l'en-tête correspond au template donné (POULE).!"
                            .$genericMsg;
                        return $retour;
                    }
                }

                # controle supplementaire pour le fichier de l'optimisation de poule du type match plateau
                if(count($entete) == 18){
                    if($entete[12] != "PREMIER JOUR DE RECEPTION" ){
                        $retour["msg"] = "Veuillez vérifier que le nom de la colonne 13 de l'en-tête correspond au template donné (PREMIER JOUR DE RECEPTION).!"
                            .$genericMsg;
                        return $retour;
                    }
                    if($entete[13] != "EQUIPE ADVERSE 1" ){
                        $retour["msg"] = "Veuillez vérifier que le nom de la colonne 14 de l'en-tête correspond au template donné (EQUIPE ADVERSE 1).!"
                            .$genericMsg;
                        return $retour;
                    }
                    if($entete[14] != "EQUIPE ADVERSE 2" ){
                        $retour["msg"] = "Veuillez vérifier que le nom de la colonne 15 de l'en-tête correspond au template donné (EQUIPE ADVERSE 2).!"
                            .$genericMsg;
                        return $retour;
                    }
                    if($entete[15] != "DEUXIEME JOUR DE RECEPTION" ){
                        $retour["msg"] = "Veuillez vérifier que le nom de la colonne 16 de l'en-tête correspond au template donné (DEUXIEME JOUR DE RECEPTION).!"
                            .$genericMsg;
                        return $retour;
                    }
                    if($entete[16] != "EQUIPE ADVERSE 1" ){
                        $retour["msg"] = "Veuillez vérifier que le nom de la colonne 17 de l'en-tête correspond au template donné (EQUIPE ADVERSE 1).!"
                            .$genericMsg;
                        return $retour;
                    }
                    if($entete[17] != "EQUIPE ADVERSE 2" ){
                        $retour["msg"] = "Veuillez vérifier que le nom de la colonne 18 de l'en-tête correspond au template donné (EQUIPE ADVERSE 2).!"
                            .$genericMsg;
                        return $retour;
                    }
                }

                $retour["success"] = true;

            }
            // pour la liste de personnes
            elseif(count($entete) == 10){
                if($entete[2] != "PRENOM" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 3 de l'en-tête correspond au template donné (PRENOM).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[3] != "CODE POSTAL" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 4 de l'en-tête correspond au template donné (CODE POSTAL).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[4] != "VILLE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 5 de l'en-tête correspond au template donné (VILLE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[5] != "LIEU DE RENCONTRE POSSIBLE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 6 de l'en-tête correspond au template donné (LIEU DE RENCONTRE POSSIBLE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[6] != "ADRESSE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 7 de l'en-tête correspond au template donné (ADRESSE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[7] != "LATITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 8 de l'en-tête correspond au template donné (LATITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[8] != "LONGITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 9 de l'en-tête correspond au template donné (LONGITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[9] != "SYSTEME DE PROJECTION GEOGRAPHIQUE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 10 de l'en-tête correspond au template donné (SYSTEME DE PROJECTION GEOGRAPHIQUE).!"
                        .$genericMsg;
                    return $retour;
                }
                $retour["success"] = true;

            }
            // pour la liste de lieux
            elseif(count($entete) == 13){
                if($entete[2] != "CODE POSTAL" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 3 de l'en-tête correspond au template donné (CODE POSTAL).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[3] != "VILLE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 4 de l'en-tête correspond au template donné (VILLE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[4] != "LIEU DE RENCONTRE POSSIBLE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 5 de l'en-tête correspond au template donné (LIEU DE RENCONTRE POSSIBLE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[5] != "ADRESSE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 6 de l'en-tête correspond au template donné (ADRESSE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[6] != "LATITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 7 de l'en-tête correspond au template donné (LATITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[7] != "LONGITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 8 de l'en-tête correspond au template donné (LONGITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[8] != "SYSTEME DE PROJECTION GEOGRAPHIQUE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 9 de l'en-tête correspond au template donné (SYSTEME DE PROJECTION GEOGRAPHIQUE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[9] != "TYPE D'EQUIPEMENT" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 10 de l'en-tête correspond au template donné (TYPE D'EQUIPEMENT).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[10] != "NOMBRE D'EQUIPEMENT" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 11 de l'en-tête correspond au template donné (NOMBRE D'EQUIPEMENT).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[11] != "CAPACITE RENCONTRE STANDARD" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 12 de l'en-tête correspond au template donné (CAPACITE RENCONTRE STANDARD).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[12] != "CAPACITE PHASE FINALE / EQUIPEMENT HOMOLOGUE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 13 de l'en-tête correspond au template donné (CAPACITE PHASE FINALE / EQUIPEMENT HOMOLOGUE).!"
                        .$genericMsg;
                    return $retour;
                }
                $retour["success"] = true;

            }
        }



        return $retour;

    }

    # détecter le délimiter
    private function autoSetDelimiter($file){
        // Obtenir les données des en-tetes
        $donneesEntete = $file->fgetcsv($delimiter = ",");

        // vérifier le délimiteur utilisé
        if(count($donneesEntete) > 1){
            $file->setCsvControl($delimiter=",");
        }
        else{
            $file->setCsvControl($delimiter=";");
        }

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
    


}


