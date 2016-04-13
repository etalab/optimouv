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
use Symfony\Component\DependencyInjection\ContainerInterface ;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

class CalculRencontreConsumer implements ConsumerInterface
{

    public $database_name;
    public $database_user;
    public $database_password;
    public $app_id;
    public $app_code;
    public $error_log_path;
    private $container;
    private $mailer;
    private $templating;

    /**
     * @var Statistiques $serviceStatistiques
     */
    protected $serviceStatistiques;

    public function __construct($database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path, ContainerInterface $container, $mailer, EngineInterface $templating, $serviceStatistiques)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
        $this->error_log_path = $error_log_path;
        $this->container = $container;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->serviceStatistiques = $serviceStatistiques;
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
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        # noter le temps de début de traitement
        $tempsDebut = new \DateTime();

        //récupérer l'id de la tâche
        $msg =  $msg->body;

         //on recupere les parametres de connexion
        $pdo= $this->connexion();

        if (!$pdo) {
            //erreur de connexion
            error_log("\n erreur récupération de l'objet PDO, Service: CalculRencontreConsumer, Function: execute ", 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d G:i:s', time());

        //on change le statut de la tâche pour dire qu elle est passee a en cours de traitement
        $statut = 1;

        //TODO: changer le nom de la table rapport en parametres
        $update = $pdo->prepare("UPDATE parametres SET statut = :statut WHERE id = :id");
        $update->bindParam(':id', $msg);
        $update->bindParam(':statut', $statut);
        $update->execute();


        //recupere les details de l operation
        $req = $pdo->prepare("SELECT * from parametres where id = :id ");
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
        $serviceRencontre = new Rencontres($database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path, $this->serviceStatistiques);




        if($typeAction == "barycentre"){


            $retour = $serviceRencontre->Barycentre($idGroupe);
            
            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);
                $this->sendMail($msg,$typeAction );


            }

         }
        elseif($typeAction == "exclusion"){

            $retour = [];
            $retourBarycentre = $serviceRencontre->Barycentre($idGroupe);
            $retourExclusion = $serviceRencontre->Exclusion($params, $idGroupe);

            $retour[0] = $retourBarycentre;
            $retour[1] = $retourExclusion;

            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);
                $this->sendMail($msg,$typeAction );
            }
        }
        elseif($typeAction == "meilleurLieu"){

            $retour = [];
            $retourOp = $serviceRencontre->meilleurLieuRencontre($idGroupe);
            $retourEq = $serviceRencontre->scenarioEquitable($idGroupe);

            $retour[0] = $retourOp;
            $retour[1] = $retourEq;

            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);
                $this->sendMail($msg,$typeAction );

            }

        }
        elseif($typeAction == "terrainNeutre"){

            $retour = [];

            $retour[0] = $serviceRencontre->terrainNeutre($idGroupe);
            $retour[1] = $serviceRencontre->terrainNeutreEquitable($idGroupe);

            $idCalcul = $this->stockerResultats($msg,$retour);

            if ($idCalcul) {

                $statut = 2;

                $this->updateSatut($msg, $statut);
                $this->sendMail($msg,$typeAction );
            }
        }
        else{

            die("type de job non reconnu!");
        }




        # noter le temps de fin de traitement
        $tempsFin = new \DateTime();

        # calculer le temps de calcul
        $tempsCalcul = $tempsFin->getTimestamp()-$tempsDebut->getTimestamp();

        // insérer les données en DB
        $utilisateurId = $serviceRencontre->getUtilisateurIdParGroupeId($idGroupe);
        $this->insererTempsCalculEnDB($tempsDebut, $tempsFin, $tempsCalcul, $utilisateurId);

        echo "la tache $msg a ete bien executee!".PHP_EOL;

    }


    private function insererTempsCalculEnDB($tempsDebut, $tempsFin, $tempsCalcul, $utilisateurId){
        try{
            //on recupere les parametres de connexion
            $pdo= $this->connexion();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: CalculRencontreConsumer, Function: insererTempsCalculEnDB ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            $typeStatistiques = "tempsCalculMeilleurLieu";
            $disciplineId = $this->serviceStatistiques->getDisciplineId($utilisateurId);
            $federationId = $this->serviceStatistiques->getFederationId($disciplineId);
            $tempsDebut = $tempsDebut->format('Y-m-d H:i:s');
            $tempsFin = $tempsFin->format('Y-m-d H:i:s');

//            error_log("\n type tempsFin: ".gettype($tempsFin), 3, $this->error_log_path);

            # insérer dans la base de données
            $sql = "INSERT INTO  statistiques_date_temps (temps_debut, temps_fin , type_statistiques, id_utilisateur, id_discipline, id_federation, valeur)
                    VALUES (:temps_debut, :temps_fin,  :type_statistiques, :id_utilisateur, :id_discipline, :id_federation, :valeur);";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':temps_debut', $tempsDebut);
            $stmt->bindParam(':temps_fin', $tempsFin);
            $stmt->bindParam(':type_statistiques', $typeStatistiques);
            $stmt->bindParam(':id_utilisateur', $utilisateurId);
            $stmt->bindParam(':id_discipline', $disciplineId);
            $stmt->bindParam(':id_federation', $federationId);
            $stmt->bindParam(':valeur', $tempsCalcul);
            $statutInsert = $stmt->execute();

            if(!$statutInsert){
                error_log("\n  Erreur d'insertion des données dans DB, details: ".print_r($stmt->errorInfo(), true)."\n Service: CalculRencontreConsumer, Function: insererTempsCalculEnDB", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }



        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: CalculRencontreConsumer, Function: insererTempsCalculEnDB, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

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

        $insert = $bdd->prepare("INSERT INTO  resultats (id_rapport, details_calcul, date_creation) VALUES ( :idTache, :detailsCalcul, :dateCreation);");
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
        $update = $bdd->prepare("UPDATE parametres SET statut = :statut WHERE id = :id");
        $update->bindParam(':id', $id);
        $update->bindParam(':statut', $statut);
        $update->execute();

    }

//    public function sendMail()
//    {
//
//        // the message
//        $msg = "First line of text\nSecond line of text";
//
//        // use wordwrap() if lines are longer than 70 characters
//        $msg = wordwrap($msg,70);
//
//     // send email
//
//        try {
//            mail("oussema.ghodbane@it4pme.fr","My subject",$msg);
//        } catch (Exception $e) {
//            echo 'Exception reçue : ',  $e->getMessage(), "\n";
//        }
//
//    }

    public function sendMail($idRapport,$typeAction)
    {


        $userEmail = $this->getUserEmail($idRapport);

        $body = $this->templating->render('FfbbBundle:Mails:confirmationCalcul.html.twig', array('idRapport' => $idRapport, 'typeAction' => $typeAction));


        $message = \Swift_Message::newInstance()
            ->setSubject('Mise à disposition de vos résultats de calculs')
            ->setFrom('servicetechnique@it4pme.fr')
            ->setTo($userEmail)
            ->setBody($body, 'text/html')
        ;
        $this->container->get('mailer')->send($message);


    }

    public function getUserEmail($idRapport)
    {

        //on recupere les parametres de connexion
        $bdd= $this->connexion();

        $stmt1 = $bdd->prepare("select email from fos_user where id = (select id_utilisateur from groupe where id = (SELECT id_groupe FROM parametres where id = :id));");

        $stmt1->bindParam(':id', $idRapport);
        $stmt1->execute();
        $userEmail = $stmt1->fetchColumn();

        return $userEmail;

    }


}