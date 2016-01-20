<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 11/01/2016
 * Time: 17:50
 */

namespace Optimouv\FfbbBundle\Services;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PDO;


class CalculRencontreConsumer implements ConsumerInterface
{

    public $database_name;
    public $database_user;
    public $database_password;
    public $app_id;
    public $app_code;
    public $error_log_path;

    public function __construct($database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
        $this->error_log_path = $error_log_path;
    }

    public function connexion()
    {
        //params de connexion

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        return $bdd;
    }


    public	function execute(AMQPMessage $msg)
    {
        //récupérer l'id de la tâche
        $msg =  $msg->body;

      //  echo "Hello $msg->body!".PHP_EOL;


         //on recupere les parametres de connexion
        $bdd= $this->connexion();

        //on change le statut de la tâche pour dire qu elle est passee a en cours de traitement
        $statut = 1;

        //TODO: changer le nom de la table rapport en parametres
        $update = $bdd->prepare("UPDATE rapport SET statut = :statut WHERE id = :id");
        $update->bindParam(':id', $msg);
        $update->bindParam(':statut', $statut);
        $update->execute();


        //recupere les details de l operation
        $req = $bdd->prepare("SELECT * from rapport where id = :id ");
        $req->bindParam(':id', $msg);
        $req->execute();
        $res = $req->fetchAll(PDO::FETCH_ASSOC);

        $typeAction = $res[0]['type_action'];
        $idGroupe = $res[0]['id_groupe'];
        $params = $res[0]['params'];

        //on recupere le service de rencontre
        include_once 'Rencontres.php';

        //passer les params du constructeur
        $app_id = $this->app_id;
        $app_code = $this->app_code;
        $database_name = $this->database_name;
        $database_user = $this->database_user;
        $database_password = $this->database_password;
        $error_log_path = $this->error_log_path;

        //Appel de la classe
        $serviceRencontre = new Rencontres($database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path);


        if($typeAction == "barycentre"){



            $retour = $serviceRencontre->Barycentre($idGroupe);

//            $retour = Rencontres::Barycentre($idGroupe);

            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);


            }

         }
        elseif($typeAction == "exclusion"){

            $retour = $serviceRencontre->Exclusion($params, $idGroupe);
//            $retour = Rencontres::Exclusion($params, $idGroupe);

            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);


            }
        }
        elseif($typeAction == "meilleurLieu"){

            $retour = $serviceRencontre->meilleurLieuRencontre($idGroupe);

            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);


            }

        }
        elseif($typeAction == "meilleurLieuEq"){

            $retour = $serviceRencontre->scenarioEquitable($idGroupe);
            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);

            }

        }
        elseif($typeAction == "terrainNeutre"){
            $retour = $serviceRencontre->terrainNeutre($idGroupe);
            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);

            }
        }
        elseif($typeAction == "terrainNeutreEq"){
            $retour = $serviceRencontre->terrainNeutreEquitable($idGroupe);
            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);


            }
        }



//    $myfile = fopen("newfile.txt", "w") or die("Unable to open file!");
//    fwrite($myfile, print_r($typeAction, true));
//        return $retour;
        echo "la tache $msg a ete bien executee!".PHP_EOL;

     }

    public function stockerResultats($idTache, $resultats)
    {

        $resultats = json_encode($resultats);



        //on recupere les parametres de connexion
        $bdd= $this->connexion();

        //recuperation la date du jour
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');

        //TODO: changer le nom de la table à resultats

        $insert = $bdd->prepare("INSERT INTO  scenario (id_rapport, details_calcul, date_creation) VALUES ( :idTache, :detailsCalcul, :dateCreation);");
        $insert->bindParam(':idTache', $idTache);
        $insert->bindParam(':detailsCalcul', $resultats);
        $insert->bindParam(':dateCreation', $dateCreation);
        $insert->execute();
        $idCalcul = $bdd->lastInsertId();

        return $idCalcul;

        
    }

    public function updateSatut($id, $statut)
    {
        //on recupere les parametres de connexion
        $bdd= $this->connexion();

        //TODO: changer le nom de la table rapport en parametres
        $update = $bdd->prepare("UPDATE rapport SET statut = :statut WHERE id = :id");
        $update->bindParam(':id', $id);
        $update->bindParam(':statut', $statut);
        $update->execute();

    }



}