<?php
/**
 * Created by PhpStorm.
 * User: henz
 * Date: 21/01/16
 * Time: 11:40
 */

namespace Optimouv\FfbbBundle\Services;

use PDO;

class Poules{

    private $database_name;
    private $database_user;
    private $database_password;
    private $app_id;
    private $app_code;
    private $error_log_path;

    public function __construct($database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
        $this->error_log_path = $error_log_path;
    }

    public function sauvegarderParamsEnDB()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try{

            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # params d'insertion
            $idGroupe = $_POST["idGroupe"];
            $poulesNbr = intval($_POST["poulesNbr"]);
//            error_log("\n Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow
//                ."\n type poulesNbr: ".print_r(gettype($poulesNbr), true), 3, $this->error_log_path);
            $typeAction = $_POST["typeMatch"];
            $nom = "rapport_groupe_".$idGroupe."_action_".$typeAction;
            $statut = 0;
            $params = json_encode(array("nbrPoule" => $poulesNbr, "interdictions"=> array(), "repartitionHomogene"=> array()));

            # insérer dans la base de données
            $sql = "INSERT INTO  rapport (nom, id_groupe, type_action, date_creation, statut, params) VALUES ( :nom, :id_groupe, :type_action, :date_creation, :statut, :params);";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':id_groupe', $idGroupe);
            $stmt->bindParam(':type_action', $typeAction);
            $stmt->bindParam(':date_creation', $dateCreation);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':params', $params);
            $stmt->execute();

            # afficher le statut de la requete executée
            error_log("\n Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow
                ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, $this->error_log_path);

            # obtenir l'id de l"entité créée
            $idParams = $bdd->lastInsertId();

            $retour = array(
                "success" => true,
                "data" => $idParams
            );


            return $retour;

        } catch (PDOException $e) {
            error_log("\n erreur PDO, Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow."\n"
            ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

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
            error_log("\n Service: Listes, Function: getPdo, datetime: ".$dateTimeNow
                ."\n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $bdd;
    }

}