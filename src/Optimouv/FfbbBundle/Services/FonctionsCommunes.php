<?php
namespace Optimouv\FfbbBundle\Services;
use PDO;

class FonctionsCommunes{
    private $error_log_path;
    private $database_name;
    private $database_user;
    private $database_password;
    private $database_host;

    public function __construct($database_host, $database_name, $database_user, $database_password, $error_log_path)
    {
        $this->database_host = $database_host;
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->error_log_path = $error_log_path;
    }


    public function getNextIdParametres(){
        $bdd = $this->getPdo();

        $sql = "select id from parametres order by id  desc limit 1";
        $stmt = $bdd->prepare($sql);
        $stmt->execute();
        $dernierId = intval($stmt->fetchColumn());
        $courantId = $dernierId + 1;

        return $courantId;
    }

    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    private function getPdo(){
        # récupérer les parametres de connexion
        $host = $this->database_host;
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            # créer une objet PDO
            $pdo = new PDO('mysql:host='.$host.';dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        }
        catch (PDOException $e) {
            error_log("\n Service: Rapport, Function: getPdo, \n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $pdo;
    }



}