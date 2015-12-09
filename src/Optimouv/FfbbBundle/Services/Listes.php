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

        # afficher le statut de la requete executée
        error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
            ."\n _FILES: ".print_r($_FILES, true), 3, "/tmp/optimouv.log");

//        error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
//            ."\n _SERVER: ".print_r($_SERVER, true), 3, "/tmp/optimouv.log");

        # controler si la taille limite du fichier à été atteinte
        $tailleFichier = $_SERVER["CONTENT_LENGTH"];

        # la taille dépasse 2M (limite du fichier par défaut de PHP) // TODO
        # une erreur bizarre du parseur json ajax
//        if($tailleFichier > 2097152){
//            $retour = array(
//                "success" => false,
//                "msg" => "Le fichier uploadé a dépassé la limite autorisée.!"
//                    ."Veuillez réduire la taille du fichier"
//            );
//            return $retour;
//        }


        # obtenir le chemin d'upload du fichier
        $cheminFichierTemp = $_FILES["file-0"]["tmp_name"];

        # obtenir le type du fichier
        $typeFichier = $_FILES["file-0"]["type"];

        # obtenir le nom du fichier
        $nomFichier = $_FILES["file-0"]["name"];

        # obtenir l'extension du fichier
        $extensionFichier = explode(".", $_FILES["file-0"]["name"]);
        $extensionFichier = end($extensionFichier);

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

                // Controler les colonnes des en-têtes avec les formats fixés  TODO
                // Fichier equipes, personnes, lieux
                $resultatBooleanControlEntete = $this->controlerEntete($donneesEntete);
                error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
                    ."\n resultatBooleanControlEntete : ".print_r($resultatBooleanControlEntete , true), 3, "/tmp/optimouv.log");
                if(!$resultatBooleanControlEntete["success"]){
                    $retour = array(
                        "success" => false,
                        "msg" => "Erreur csv ligne : 1!"
                            .$resultatBooleanControlEntete["msg"]."!"
                            .implode(",", $donneesEntete)
                    );
                    return $retour;
                }
                else{
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

                        # tableau qui contient toutes les données (utilisé pour gérer les doublons)
                        $toutesLignes = [];

                        while (!$file->eof()) {
                            $donnéesLigne = $file->fgetcsv();
                            $nbrLigne++;

                            // controler le fichier vide (sans données)
                            if($donnéesLigne == array(null) and $nbrLigne == 2){
                                $retour = array(
                                    "success" => false,
                                    "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                        ." Il n'y a pas de données dans le fichier csv!"
                                        ." Veuillez uploader un fichier csv qui contient des données!"
                                        .implode(",", $donnéesLigne)
                                );
                                return $retour;
                            }

                            // tester s'il y a des données
                            if($donnéesLigne != array(null)){

                                # controler des doublons
                                # ajouter la ligne courante dans le répértoire des lignes si ce n'est pas un doublon
                                if(!in_array($donnéesLigne, $toutesLignes )){
                                    array_push($toutesLignes, $donnéesLigne);
                                }
                                else{
                                    $retour = array(
                                        "success" => false,
                                        "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                            ." Le fichier comporte des lignes en double.!"
                                            ." Veuillez supprimer cette ligne et reuploader le fichier!"
                                            .implode(",", $donnéesLigne)
                                    );
                                    return $retour;
                                }


                                // obtenir la valeur pour chaque paramètre
                                $typeEntite = $donnéesLigne[0];
                                error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
                                    ."\n typeEntite : ".print_r($typeEntite , true), 3, "/tmp/optimouv.log");

                                // obtenir les valeurs selon le type d'entité
                                if (strtolower($typeEntite) == "equipe") {

                                    # controler le nombre de colonnes
                                    if(count($donnéesLigne) != 11){
                                        $retour = array(
                                            "success" => false,
                                            "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                                ." La ligne doit contenir 11 valeurs. Donné: ".count($donnéesLigne)." valeurs !"
                                                .implode(",", $donnéesLigne)
                                        );
                                        return $retour;
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


                                    # corriger le code postal si un zéro est manquant dans le premier chiffre
                                    $codePostal = $this->corrigerCodePostal($codePostal);

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


                                    # controler le code postal et la ville
                                    # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                    $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);

                                    error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
                                        ."\n statutControlCodePostalVille : ".print_r($statutControlCodePostalVille , true), 3, "/tmp/optimouv.log");

                                    if(!$statutControlCodePostalVille["success"]){
                                        $retour = array(
                                            "success" => false,
                                            "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                                ." Les valeurs du couple 'code postal' (colonne 3) et 'ville' (colonne 4) ne sont pas reconnues!"
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

                                    # controler le nombre de colonnes
                                    if(count($donnéesLigne) != 10){
                                        $retour = array(
                                            "success" => false,
                                            "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                                ." La ligne doit contenir 10 valeurs. Donné: ".count($donnéesLigne)." valeurs !"
                                                .implode(",", $donnéesLigne)
                                        );
                                        return $retour;
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

                                    # corriger le code postal si un zéro est manquant dans le premier chiffre
                                    $codePostal = $this->corrigerCodePostal($codePostal);

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

                                    # controler le code postal et la ville
                                    # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                    $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);

                                    error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
                                        ."\n statutControlCodePostalVille : ".print_r($statutControlCodePostalVille , true), 3, "/tmp/optimouv.log");

                                    if(!$statutControlCodePostalVille["success"]){
                                        $retour = array(
                                            "success" => false,
                                            "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                                ." Les valeurs du couple 'code postal' (colonne 4) et 'ville' (colonne 5) ne sont pas reconnues!"
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


                                    # controler le nombre de colonnes
                                    if(count($donnéesLigne) != 13){
                                        $retour = array(
                                            "success" => false,
                                            "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                                ." La ligne doit contenir 13 valeurs. Donné: ".count($donnéesLigne)." valeurs !"
                                                .implode(",", $donnéesLigne)
                                        );
                                        return $retour;
                                    }

                                    $lieuRencontrePossible = $this->getBoolean($donnéesLigne[4]);

                                    error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                                        ."\n ville: ".print_r($ville, true), 3, "/tmp/optimouv.log");


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

                                    # corriger le code postal si un zéro est manquant dans le premier chiffre
                                    $codePostal = $this->corrigerCodePostal($codePostal);

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

                                    # controler le code postal et la ville
                                    # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                    $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);

                                    error_log("\n Service: Listes, Function: controlerEntites, datetime: ".$dateTimeNow
                                        ."\n statutControlCodePostalVille : ".print_r($statutControlCodePostalVille , true), 3, "/tmp/optimouv.log");

                                    if(!$statutControlCodePostalVille["success"]){
                                        $retour = array(
                                            "success" => false,
                                            "msg" => "Erreur csv ligne :".$nbrLigne."!"
                                                ." Les valeurs du couple 'code postal' (colonne 3) et 'ville' (colonne 4) ne sont pas reconnues!"
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
            }
            else{
                $retour = array(
                    "success" => false,
                    "msg" => "Veuillez convertir votre fichier au format csv et effectuer à nouveau l'import.!"
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

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

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



        } catch (PDOException $e) {
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }




        return $retour;
    }

    public function creerListeLieux($idsEntite){

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

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


        } catch (PDOException $e) {
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }




        return $retour;
    }


    public function creerEntites()
    {

        try {

            # obtenir le nom du fichier uploadé
            $nomFichierTemp = $_FILES["file-0"]["tmp_name"];

            // Dès qu'un fichier a été reçu par le serveur
            if (file_exists($nomFichierTemp) || is_uploaded_file($nomFichierTemp)) {

                // lire le contenu du fichier
                $file = new SplFileObject($nomFichierTemp, 'r');
                $delimiter = ",";

                // On lui indique que c'est du CSV
                $file->setFlags(SplFileObject::READ_CSV);

                // auto-détecter le délimiteur
                $this->autoSetDelimiter($file);

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

                                # corriger le code postal si un zéro est manquant dans le premier chiffre
                                $codePostal = $this->corrigerCodePostal($codePostal);


                                # controler le code postal et la ville
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);
                                $idVilleFrance = $statutControlCodePostalVille["idVille"];

                                error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                                    ."\n idVilleFrance : ".print_r($idVilleFrance , true), 3, "/tmp/optimouv.log");


                                $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, adresse, code_postal, ville, longitude, latitude,"
                                    ." projection, participants, id_ville_france "
                                    ." licencies, lieu_rencontre_possible, date_creation, date_modification )"
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
                                $stmt->bindParam(':id_ville_france', $idVille);

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

                                # corriger le code postal si un zéro est manquant dans le premier chiffre
                                $codePostal = $this->corrigerCodePostal($codePostal);

                                # controler le code postal et la ville
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);
                                $idVilleFrance = $statutControlCodePostalVille["idVille"];

                                error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                                    ."\n idVilleFrance : ".print_r($idVilleFrance , true), 3, "/tmp/optimouv.log");

                                $sql = "INSERT INTO  entite (id_utilisateur, type_entite, nom, prenom, adresse, code_postal, ville, longitude, latitude,"
                                    ." projection, lieu_rencontre_possible, date_creation, date_modification,  id_ville_france)"
                                    ."VALUES ( :id_utilisateur, :type_entite, :nom, :prenom, :adresse, :code_postal, :ville, :longitude, :latitude, "
                                    ." :projection, :lieu_rencontre_possible, :date_creation, :date_modification, :id_ville_france );";

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
                                $stmt->bindParam(':id_ville_france', $idVilleFrance);
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

                                # corriger le code postal si un zéro est manquant dans le premier chiffre
                                $codePostal = $this->corrigerCodePostal($codePostal);

                                # controler le code postal et la ville
                                # il faut que la valeur est incluse dans la liste des codes postaux de la table villes_france_free
                                $statutControlCodePostalVille = $this->verifierExistenceCodePostalNomVille($codePostal, $ville);
                                $idVilleFrance = $statutControlCodePostalVille["idVille"];

                                error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                                    ."\n idVilleFrance : ".print_r($idVilleFrance , true), 3, "/tmp/optimouv.log");



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
                            error_log("\n Service: Listes, Function: creerEntites, datetime: ".$dateTimeNow
                                ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, "/tmp/optimouv.log");

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


        } catch (PDOException $e) {
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }




        return $retour;
    }


    # controller le code postal et le nom de ville
    private function verifierExistenceCodePostalNomVille($codePostal, $nomVille){
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

//        error_log("\n Service: Listes, Function: verifierExistenceCodePostalNomVille, datetime: ".$dateTimeNow
//            ."\n $codePostal Info: ".print_r($codePostal, true), 3, "/tmp/optimouv.log");
//        error_log("\n Service: Listes, Function: verifierExistenceCodePostalNomVille, datetime: ".$dateTimeNow
//            ."\n $nomVille Info: ".print_r($nomVille, true), 3, "/tmp/optimouv.log");

        try {
            $char = array("-", "_", "'");
            $nomVille = str_replace($char, " ", $nomVille);

            $bdd = $this->getPdo();

            //chercher l'id de la ville selon la table de reference
            $query = "SELECT 1 as 'prio', ville_id FROM villes_france_free where ville_nom_simple = '$nomVille' AND  ville_code_postal = '$codePostal'
                  UNION
                  SELECT 3 as 'prio', ville_id FROM villes_france_free where ville_nom_simple = '$nomVille'
                  UNION
                  SELECT 2 as 'prio', ville_id FROM villes_france_free where ville_nom_simple LIKE '%$nomVille%' AND  ville_code_postal LIKE '%$codePostal%'";
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
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $retour;


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
    private function controlerEntete($entete){
        $retour = array(
            "success" => false,
            "msg" => ""
        );

        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $genericMsg = "Veuillez utiliser les formats de fichier mis à disposition dans « Télécharger les formats de fichiers d’import ";

        // tester le nombre de colonnes
        // nombreColonnesEntetes (11 pour liste d'équipes, 10 pour liste de personnes, 13 pour liste de lieux)
        $nombreColonnesEntetes = [11,10,13];
        if(!in_array(count($entete), $nombreColonnesEntetes)){
            $retour["msg"] = "Veuillez vérifier le nombre des en-têtes.!"
                ."Une liste d'équipes contient 11 colonnes, une liste de personnes contient 10 colonnes et une liste de lieux contient 13 colonnes.!"
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

            // pour la liste d'équipes
            if(count($entete) == 11){
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
                if($entete[4] != "PARTICIPANTS" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 5 de l'en-tête correspond au template donné (PARTICIPANTS).!"
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
                if($entete[7] != "LONGITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 8 de l'en-tête correspond au template donné (LONGITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[8] != "LATITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 9 de l'en-tête correspond au template donné (LATITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[9] != "SYSTEME DE PROJECTION GEOGRAPHIQUE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 10 de l'en-tête correspond au template donné (SYSTEME DE PROJECTION GEOGRAPHIQUE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[10] != "LICENCIES" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 11 de l'en-tête correspond au template donné (LICENCIES).!"
                        .$genericMsg;
                    return $retour;
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
                if($entete[7] != "LONGITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 8 de l'en-tête correspond au template donné (LONGITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[8] != "LATITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 9 de l'en-tête correspond au template donné (LATITUDE).!"
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
                if($entete[6] != "LONGITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 7 de l'en-tête correspond au template donné (LONGITUDE).!"
                        .$genericMsg;
                    return $retour;
                }
                if($entete[7] != "LATITUDE" ){
                    $retour["msg"] = "Veuillez vérifier que le nom de la colonne 8 de l'en-tête correspond au template donné (LATITUDE).!"
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



        error_log("\n Service: Listes, Function: controlerEntete, datetime: ".$dateTimeNow
            ."\n entete: ".print_r($entete, true), 3, "/tmp/optimouv.log");


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


