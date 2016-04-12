<?php

namespace Optimouv\FfbbBundle\Services;

use PDO;

class Statistiques {

    private $error_log_path;
    private $database_name;
    private $database_user;
    private $database_password;

    public function __construct($database_name, $database_user, $database_password, $error_log_path)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->error_log_path = $error_log_path;
    }

    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    private function getPdo(){
        # récupérer les parametres de connexion
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            # créer une objet PDO
            $pdo = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        }
        catch (PDOException $e) {
            error_log("\n Service: Poules, Function: getPdo, \n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $pdo;
    }

    public function augmenterNombreTableStatistiques($utilisateurId, $typeStatistiques){
        $disciplineId = $this->getDisciplineId($utilisateurId);
        $federationId = $this->getFederationId($disciplineId);

        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: augmenterNombreTableStatistiques ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            if($typeStatistiques == "nombreConnexions"){

                # insérer dans la base de données
                $sql = "INSERT INTO  statistiques_date (date_creation, type_statistiques, id_utilisateur, id_discipline, id_federation, valeur)
                        VALUES (now(), :type_statistiques, :id_utilisateur, :id_discipline, :id_federation, 1)
                        on duplicate key UPDATE valeur=valeur+1 ;";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':type_statistiques', $typeStatistiques);
                $stmt->bindParam(':id_utilisateur', $utilisateurId);
                $stmt->bindParam(':id_discipline', $disciplineId);
                $stmt->bindParam(':id_federation', $federationId);
                $statutInsert = $stmt->execute();

                if(!$statutInsert){
                    error_log("\n  Erreur d'insertion des données dans DB, details: ".print_r($stmt->errorInfo(), true)."\n Service: Statistiques, Function: augmenterNombreTableStatistiques", 3, $this->error_log_path);
                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
                }


            }



        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getFederationId, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }
    }

    public function getFederationId($disciplineId){
        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getFederationId ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # obtenir l'id de la fédération
            $sql = "SELECT id_federation from discipline where id=:id;";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $disciplineId);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            $federationId = $resultat["id_federation"];

            if($federationId == ""){
                error_log("\n  l'identifiant de la fédération est null, Service: Statistiques, Function: getFederationId", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            return $federationId;

        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getFederationId, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }


    public function getDisciplineId($utilisateurId){
        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getDisciplineId ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # obtenir l'id de la discipline
            $sql = "SELECT id_discipline from fos_user where id=:id;";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $utilisateurId);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            $disciplineId = $resultat["id_discipline"];

            if($disciplineId == ""){
                error_log("\n  l'identifiant de la discipline est null, Service: Statistiques, Function: getDisciplineId", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }
            return $disciplineId;
        }

        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getDisciplineId, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


    }



}

