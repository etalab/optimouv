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
use Exception;

class CalculRencontreConsumer implements ConsumerInterface
{
    public $database_host;
    public $database_name;
    public $database_user;
    public $database_password;
    public $error_log_path;
    private $container;
    private $mailer;
    private $templating;
    private $mailer_user;
    private $base_url;
    private $mailer_sender;
    private $here_request_limit;
    private $sender_name;
    private $here_request_limit_debut;
    private $here_request_limit_fin;

    public $route_app_id;
    public $route_app_code;
    public $geocode_app_id;
    public $geocode_app_code;


    /**
     * @var FonctionsCommunes $fonctionsCommunes
     */
    protected $fonctionsCommunes;


    /**
     * @var Statistiques $serviceStatistiques
     */
    protected $serviceStatistiques;

    public function __construct($database_host, $database_name, $database_user, $database_password, $route_app_id, $route_app_code, $geocode_app_id, $geocode_app_code, $error_log_path, ContainerInterface $container, $mailer, EngineInterface $templating, $serviceStatistiques, $mailer_user, $base_url, $mailer_sender, $here_request_limit, $sender_name, $fonctionsCommunes, $here_request_limit_debut, $here_request_limit_fin )
    {
        $this->database_host = $database_host;
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->error_log_path = $error_log_path;
        $this->container = $container;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->serviceStatistiques = $serviceStatistiques;
        $this->mailer_user = $mailer_user;
        $this->base_url = $base_url;
        $this->mailer_sender = $mailer_sender;
        $this->here_request_limit = $here_request_limit;
        $this->mailer_sender = $mailer_sender;
        $this->fonctionsCommunes = $fonctionsCommunes;
        $this->here_request_limit_debut = $here_request_limit_debut;
        $this->here_request_limit_fin = $here_request_limit_fin;

        $this->route_app_id = $route_app_id;
        $this->route_app_code = $route_app_code;
        $this->geocode_app_id = $geocode_app_id;
        $this->geocode_app_code = $geocode_app_code;


    }

    public function connexion()
    {
        //params de connexion
        $host = $this->database_host;
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host='.$host.';dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

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

        $update = $pdo->prepare("UPDATE parametres SET statut = :statut WHERE id = :id");
        $update->bindParam(':id', $msg);
        $update->bindParam(':statut', $statut);
        $update->execute();

        $pdo= null;

        $pdo= $this->connexion();
        //recupere les details de l operation
        $req = $pdo->prepare("SELECT * from parametres where id = :id ");
        $req->bindParam(':id', $msg);
        $req->execute();

        $res = $req->fetchAll(PDO::FETCH_ASSOC);


        $typeAction = $res[0]['type_action'];
        $idGroupe = $res[0]['id_groupe'];
        $params = $res[0]['params'];
        $pdo= null;

        //on recupere le service de rencontre
        include_once 'Rencontres.php';

        //passer les params du constructeur
        $database_name = $this->database_name;
        $database_user = $this->database_user;
        $database_password = $this->database_password;
        $error_log_path = $this->error_log_path;
        $database_host = $this->database_host;
        $here_request_limit = $this->here_request_limit;
        $fonctionsCommunes = $this->fonctionsCommunes;
        $serviceStatistiques = $this->serviceStatistiques;
        $here_request_limit_debut =  $this->here_request_limit_debut;
        $here_request_limit_fin = $this->here_request_limit_fin ;

        //Appel de la classe
        $serviceRencontre = new Rencontres($database_host, $database_name, $database_user, $database_password, $this->route_app_id, $this->route_app_code, $this->geocode_app_id, $this->geocode_app_code, $error_log_path, $serviceStatistiques, $here_request_limit, $fonctionsCommunes, $here_request_limit_debut, $here_request_limit_fin);
        
        if($typeAction == "barycentre"){


            $retour = $serviceRencontre->Barycentre($idGroupe);

            if( is_array($retour) && array_key_exists("success", $retour) && array_key_exists("donneesRetour", $retour) && $retour["success"] === TRUE)
            {
                $idCalcul = $this->stockerResultats($msg, $retour["donneesRetour"]);

                if ($idCalcul) {
                    $statut = 2;
                    $this->updateSatut($msg, $statut);
                    $this->sendMail($msg, $typeAction, 0 );
                }
            }

        }
        elseif($typeAction == "exclusion"){

            $retourBarycentre = $serviceRencontre->Barycentre($idGroupe);
            $retourExclusion = $serviceRencontre->Exclusion($params, $idGroupe);

            if( is_array($retourBarycentre) && array_key_exists("success", $retourBarycentre) && array_key_exists("donneesRetour", $retourBarycentre) && $retourBarycentre["success"] === TRUE
                && is_array($retourExclusion) && array_key_exists("success", $retourExclusion) && array_key_exists("donneesRetour", $retourExclusion) && $retourExclusion["success"] === TRUE) {
                $retour = [];
                $retour[0] = $retourBarycentre["donneesRetour"];
                $retour[1] = $retourExclusion["donneesRetour"];

                $idCalcul = $this->stockerResultats($msg, $retour);

                if ($idCalcul) {
                    $statut = 2;
                    $this->updateSatut($msg, $statut);
                    $this->sendMail($msg, $typeAction, 0 );
                }
            }

        }
        elseif($typeAction == "meilleurLieu"){
            $retourOp = $serviceRencontre->meilleurLieuRencontre($idGroupe);
            $retourEq = $serviceRencontre->scenarioEquitable($idGroupe);

            if( is_array($retourOp) && array_key_exists("success", $retourOp) && array_key_exists("donneesRetour", $retourOp) && $retourOp["success"] === TRUE
                && is_array($retourEq) && array_key_exists("success", $retourEq) && array_key_exists("donneesRetour", $retourEq) && $retourEq["success"] === TRUE) {

                $retour = [];
                $retour[0] = $retourOp["donneesRetour"];
                $retour[1] = $retourEq["donneesRetour"];

                $idCalcul = $this->stockerResultats($msg, $retour);

                if ($idCalcul) {

                    $statut = 2;
                    $this->updateSatut($msg, $statut);
                    $this->sendMail($msg, $typeAction, 0 );
                }
            }
            else{
                // l'envoi d'un email d'erreur
                $this->updateSatut($msg, -1);
                $this->sendMail($msg, $typeAction, $retourOp["codeErreur"] );

            }
        }
        elseif($typeAction == "terrainNeutre"){

            $retourOp = $serviceRencontre->terrainNeutre($idGroupe);
            $retourEq = $serviceRencontre->terrainNeutreEquitable($idGroupe);

            if( is_array($retourOp) && array_key_exists("success", $retourOp) && array_key_exists("donneesRetour", $retourOp) && $retourOp["success"] === TRUE
                && is_array($retourEq) && array_key_exists("success", $retourEq) && array_key_exists("donneesRetour", $retourEq) && $retourEq["success"] === TRUE) {
                $retour = [];
                $retour[0] = $retourOp["donneesRetour"];
                $retour[1] = $retourEq["donneesRetour"];

                $idCalcul = $this->stockerResultats($msg,$retour);

                if ($idCalcul) {

                    $statut = 2;

                    $this->updateSatut($msg, $statut);
                    $this->sendMail($msg, $typeAction, 0 );
                }

            }
            else{
                // l'envoi d'un email d'erreur
                $this->updateSatut($msg, -1);

                // erreur pour le scénario optimal
                if(is_array($retourOp) && array_key_exists("success", $retourOp) && array_key_exists("codeErreur", $retourOp)  && $retourOp["success"] === FALSE ){
                    $this->sendMail($msg, $typeAction, $retourOp["codeErreur"] );
                }
                // erreur pour le scénario équitable
                elseif(is_array($retourEq) && array_key_exists("success", $retourEq) && array_key_exists("codeErreur", $retourEq)  && $retourEq["success"] === FALSE ){
                    $this->sendMail($msg, $typeAction, $retourEq["codeErreur"] );
                }
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
            $pdo = null;
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
        $insert = $bdd->prepare("INSERT INTO  resultats (id_rapport, details_calcul, date_creation) VALUES ( :idTache, :detailsCalcul, :dateCreation);");
        $insert->bindParam(':idTache', $idTache);
        $insert->bindParam(':detailsCalcul', $resultats);
        $insert->bindParam(':dateCreation', $dateCreation);
        $insert->execute();
        $idCalcul = $bdd->lastInsertId();
        $pdo = null;
        return $idCalcul;
        
    }

    public function updateSatut($id, $statut)
    {
        //on recupere les parametres de connexion
        $bdd= $this->connexion();
        $update = $bdd->prepare("UPDATE parametres SET statut = :statut WHERE id = :id");
        $update->bindParam(':id', $id);
        $update->bindParam(':statut', $statut);
        $update->execute();
        $pdo= null;
    }


    public function sendMail($idRapport, $typeAction, $statut)
    {
        $expediteurEmail = $this->mailer_sender;
        $sender_name = $this->sender_name;
        $userEmail = $this->getUserEmail($idRapport);

        // cas de succès
        if ($statut == 0) {

            $body = $this->templating->render('FfbbBundle:Mails:confirmationCalcul.html.twig', array('idRapport' => $idRapport, 'typeAction' => $typeAction));

        } elseif ($statut == 1) {

            $body = $this->templating->render('FfbbBundle:Mails:depassementSeuilHereRencontre.html.twig');
        }

        $message = \Swift_Message::newInstance()
            ->setSubject('OPTIMOUV - mise à disposition de vos résultats de calculs')
            ->setFrom(array($expediteurEmail => $sender_name))
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
        $pdo= null;
        return $userEmail;

    }


}