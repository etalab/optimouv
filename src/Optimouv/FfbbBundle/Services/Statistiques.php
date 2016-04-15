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

    public function augmenterNombreTableStatistiques($utilisateurId, $typeStatistiques, $valeur){
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

            # insérer dans la base de données
            $sql = "INSERT INTO  statistiques_date (date_creation, type_statistiques, id_utilisateur, id_discipline, id_federation, valeur)
                    VALUES (now(), :type_statistiques, :id_utilisateur, :id_discipline, :id_federation, :valeur)
                    on duplicate key UPDATE valeur=valeur+VALUES(valeur);";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':type_statistiques', $typeStatistiques);
            $stmt->bindParam(':id_utilisateur', $utilisateurId);
            $stmt->bindParam(':id_discipline', $disciplineId);
            $stmt->bindParam(':id_federation', $federationId);
            $stmt->bindParam(':valeur', $valeur);
            $statutInsert = $stmt->execute();

            if(!$statutInsert){
                error_log("\n  Erreur d'insertion des données dans DB, details: ".print_r($stmt->errorInfo(), true)."\n Service: Statistiques, Function: augmenterNombreTableStatistiques", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
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



    public function getDetailsFederation($federationId){
        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getDetailsFederation ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            if($federationId == "tous"){
                $sql = "SELECT * from federation";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $resultat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            else{
                $sql = "SELECT * from federation where id=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $federationId);
                $stmt->execute();
                $resultat = $stmt->fetchALL(PDO::FETCH_ASSOC);
            }

            if(!$resultat ){
                error_log("\n  Erreur lors de la récupération de la liste de fédérations, Service: Statistiques, Function: getDetailsFederation, errorInfo: "
                    .print_r($pdo->errorInfo(), true) , 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            // obtenir les détails des disciplines
            foreach($resultat as &$federation){
                $federationId = $federation["id"];
                $sql = "SELECT id, nom from discipline where id_federation=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $federationId);
                $stmt->execute();
                $disciplines = $stmt->fetchALL(PDO::FETCH_ASSOC);


                if(!$disciplines ){
                    error_log("\n  Erreur lors de la récupération de la liste de disciplines pour une fédération, Service: Statistiques, Function: getDetailsFederation, errorInfo: "
                        .print_r($pdo->errorInfo(), true) , 3, $this->error_log_path);
                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
                }

                // obtenir les détails des utilisateurs
                foreach($disciplines as &$discipline ){
                    $disciplineId = $discipline["id"];

                    $sql = "SELECT id, nom, prenom from fos_user where id_discipline=:id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $disciplineId);
                    $stmt->execute();
                    $utilisateurs = $stmt->fetchALL(PDO::FETCH_ASSOC);

                    $discipline["utilisateurs"] = $utilisateurs;


                }

                // ajouter une clé dans la liste de fédérations
                $federation["disciplines"] = $disciplines;

            }


            return $resultat;

        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getDetailsFederation, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }



}

