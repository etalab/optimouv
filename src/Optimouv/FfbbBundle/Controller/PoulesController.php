<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Optimouv\FfbbBundle\Entity\Entite;
use Optimouv\FfbbBundle\Form\EntiteType;
use Symfony\Component\HttpFoundation\Response;
use PDO;
class PoulesController extends Controller
{
    public function indexAction()
    {
//       $x = $this->get('service_rencontres')->myFunction();
        return $this->render('FfbbBundle:Poules:index.html.twig');
    }


    public function criteresAction()
    {

        return $this->render('FfbbBundle:Poules:criteres.html.twig');

    }

    public function choisirListeEquipesAction()
    {


        $em = $this->getDoctrine()->getManager();
        //récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();
        $rencontre = 0;
        $listeParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListesEquipes($idUtilisateur, $rencontre);

        return $this->render('FfbbBundle:Poules:choisirListeEquipes.html.twig', array(
            'listeParticipants'=>$listeParticipants,
        ));


//        return $this->render('FfbbBundle:Poules:choisirListeEquipes.html.twig');


    }

    public function gererListeEquipesAction()
    {

        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        $em = $this->getDoctrine()->getManager();

        //récupérer la liste de groupes

        $rencontre = 0;
        $listesParticipants =  $em->getRepository('FfbbBundle:ListeParticipants')->getListesEquipes($idUtilisateur, $rencontre);

        return $this->render('FfbbBundle:Poules:gererListeEquipes.html.twig', [
            'listesParticipants' => $listesParticipants,
        ]);



//        return $this->render('FfbbBundle:Poules:gererListeEquipes.html.twig');

    }

    public function creerListeEquipesAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());


        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites("participants");

        # récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        if($statutUpload["success"]){

            # créer des entités dans la table entite
            $retourEntites = $this->get('service_listes')->creerEntites($idUtilisateur);
            $idsEntite = $retourEntites["idsEntite"];
            $nomFichier = $retourEntites["nomFichier"];
            $rencontre = 0;


            # créer une liste dans la table liste_participants
            $retourListe = $this->get('service_listes')->creerListeParticipants($idsEntite, $nomFichier, $idUtilisateur, $rencontre);

            # obtenir entity manager
            $em = $this->getDoctrine()->getManager();

            # obtenir listes des participants
            $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes($rencontre);

            return new JsonResponse(array(
                "success" => true,
                "msg" => "Votre liste a été correctement importée",
                "data" => $listesParticipants,
                "dateCreation" =>  $retourListe["data"]["dateCreation"]
            ));

        }
        else{

            # convertir str au tableau
            if(!is_array($statutUpload["msg"])  ){
                return new JsonResponse(array(
                    "success" => false,
                    "msg" => array($statutUpload["msg"])
                ));

            }
            else{
                return new JsonResponse(array(
                    "success" => false,
                    "msg" => $statutUpload["msg"]
                ));

            }


        }
    }

    public function renommerListeAction($idListeParticipants)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getNom();

        return $this->render('FfbbBundle:Poules:renommerListeEquipes.html.twig', array(

            'idListeParticipants' => $idListeParticipants,
            'nomListe' => $nomListe,
        ));

    }

    public function renommerListeEquipesAction($idListeParticipants)
    {



        $em = $this->getDoctrine()->getManager();


        $entity = $em->getRepository('FfbbBundle:ListeParticipants')->find($idListeParticipants);

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe non trouvée!');
        }

        $nomGroupe = $_POST["nomListeParticipants"];

        $entity->setNom($nomGroupe);
        $em->persist($entity);
        $em->flush();

        return $this->redirect($this->generateUrl('ffbb_poules_gerer_liste' ));

    }

    public function visualiserListeEquipesAction($idListeParticipants){

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir les participants pour cette liste de participants
        $participants = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getEquipes();

        //$participants de string a array
        $participants = explode(",", $participants);

        # obtenir tout info pour chaque participant
        $detailsEntites = $em->getRepository('FfbbBundle:Entite')->getEntities($participants);

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getNom();

        return $this->render('FfbbBundle:Poules:visualiserListeEquipes.html.twig', array(
            'idListeParticipants' => $idListeParticipants,
            'detailsEntites' => $detailsEntites,
            'nomListe' =>$nomListe,

        ));

    }

    public function nouveauGroupeAction()
    {

        $idListeParticipants = $_POST['listeParticipants'];


        $em = $this->getDoctrine()->getManager();

        $participants = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getEquipes();


        //$participants de string a array

        $participants = explode(",", $participants);

        $detailsEntite = $em->getRepository('FfbbBundle:Entite')->getEntities($participants);

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getNom();


            return $this->render('FfbbBundle:Poules:nouveauGroupe.html.twig', [
                'idListeParticipants' => $idListeParticipants,
                'entites' => $detailsEntite,
                'nomListe' => $nomListe,
            ]);

    }

    public function creerGroupeAction()
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        $villes = $_POST["duallistbox_demo1"];

        $nomGroupe = $_POST["nomGroupe"];
        $idListeParticipants = $_POST["idListeParticipants"];
        $idListeLieux = null;

        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        $idGroupe = $this->get('service_rencontres')->creerGroupe($villes, $nomGroupe, $idListeParticipants, $idListeLieux, $idUtilisateur);

        $coordonneesVille = $this->get('service_rencontres')->index($idGroupe);

        $coordonneesVille = array_merge($coordonneesVille[0], $coordonneesVille[1]);

        $nomsVilles = $this->get('service_rencontres')->nomsVilles($idGroupe);

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        # récupérer la liste des noms et des ids de villes
        $detailsVilles = $em->getRepository('FfbbBundle:Entite')->getEntities($villes);

        return $this->render('FfbbBundle:Poules:criteres.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
            'idGroupe' => $idGroupe,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
            'detailsVilles' => $detailsVilles,


        ));
    }

    public function lancerCalculAction()
    {

        # sauvegarder les params dans la DB
        $retour = $this->get('service_poules')->sauvegarderParamsEnDB();

        $idParams = $retour["data"];


        # envoyer l'id du rapport (params) à  RabbitMQ
        $this->get('old_sound_rabbit_mq.poule_producer')->publish($idParams);

//        error_log("\n id params: ".print_r($idParams , true), 3, "error_log_optimouv.txt");

        return new JsonResponse(array(
            "success" => true,
            "msg" => ""
        ));

    }

    public function lancerGroupeAction($idGroupe)
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        $coordonneesVille = $this->get('service_rencontres')->index($idGroupe);
        $coordonneesVille = array_merge($coordonneesVille[0], $coordonneesVille[1]);

        $nomsVilles = $this->get('service_rencontres')->nomsVilles($idGroupe);

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();

        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        # récupérer les villes qui correspondent à un groupe
        $villes =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();
        $villes = explode(",", $villes);

        # récupérer la liste des noms et des ids de villes
        $detailsVilles = $em->getRepository('FfbbBundle:Entite')->getEntities($villes);

        return $this->render('FfbbBundle:Poules:criteres.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
            'idGroupe' => $idGroupe,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
            'detailsVilles' => $detailsVilles,

        ));
    }

    public function choisirGroupeAction($idListe)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        if(!isset($idListe)){
            die('Une erreur interne est survenue. Veuillez sélectionner une liste de participants. ');
        }


        $tousLesGroupes = $em->getRepository('FfbbBundle:Groupe')->getGroupList($idListe);

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();


        return $this->render('FfbbBundle:Poules:choisirGroupe.html.twig', [
            'tousLesGroupes' => $tousLesGroupes,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
        ]);

    }

    public function gererGroupeAction($idListe)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $tousLesGroupes = $em->getRepository('FfbbBundle:Groupe')->getGroupList($idListe);
        return $this->render('FfbbBundle:Poules:gererGroupe.html.twig', [
            'tousLesGroupes' => $tousLesGroupes,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
        ]);

    }

    public function renommerAction($idGroupe)
    {

        $em = $this->getDoctrine()->getManager();
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        return $this->render('FfbbBundle:Poules:renommer.html.twig', array(

            'idGroupe' => $idGroupe,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
        ));

    }

    public function renommerGroupeAction($idGroupe)
    {

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('FfbbBundle:Groupe')->find($idGroupe);

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe non trouvée!');
        }

        $nomGroupe = $_POST["nomGroupe"];
        $idListe = $_POST["idListe"];

        $entity->setNom($nomGroupe);
        $em->persist($entity);
        $em->flush();

        return $this->redirect($this->generateUrl('ffbb_poules_gerer_groupe', array('idListe' => $idListe)));

    }

    public function visualiserGroupeAction($idGroupe)
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir les participants pour cette liste de participants
        $participants = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();

        //$participants de string a array
        $participants = explode(",", $participants);

        # obtenir tout info pour chaque participant
        $detailsEntites = $em->getRepository('FfbbBundle:Entite')->getEntities($participants);


        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();


        return $this->render('FfbbBundle:Poules:visualiserGroupe.html.twig', array(

            'idGroupe' => $idGroupe,
            'detailsEntites' => $detailsEntites,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
        ));
    }

    public function entiteAction($idGroupe)
    {


        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        return $this->render('FfbbBundle:Poules:entite.html.twig', [
            'idGroupe' => $idGroupe,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
        ]);
    }

    public function creerEntiteAction()
    {


        $idGroupe = $_POST['idGroupe'];

        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        $typeEntite = $_POST['typeLieu'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $adresse = $_POST['adresse'];
        $codePostal = $_POST['codePostal'];
        $ville = $_POST['ville'];
        $longitude = $_POST['longitude'];
        $latitude = $_POST['latitude'];
        $projection = $_POST['projection'];
        $typeEquipement = $_POST['typeEquipement'];
        $nbrEquipement = $_POST['nbrEquipement'];
        $capaciteRencontreStandard = $_POST['capaciteRencontreStandard'];
        $capacitePhasefinale = $_POST['capacitePhasefinale'];
        $nbrParticipants = $_POST['nbrParticipants'];
        $nbrLicencies = $_POST['nbrLicencies'];
        $lieuRencontrePossible = $_POST['lieuRencontrePossible'];
        $poule = $_POST['poule'];

        $dateCreation = new \DateTime('11/22/2015'); //this returns the current date time

        //récupérer ville_id de la table villefrance

        $villeId = $this->get('service_listes')->verifierExistenceCodePostalNomVille($codePostal, $ville);

        $villeId = $villeId['idVille'];


        $em = $this->getDoctrine()->getManager();


        $entity = $entity = new Entite();

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe non trouvée!');
        }


        $entity->setTypeEntite($typeEntite)
            ->setNom($nom)
            ->setIdUtilisateur($idUtilisateur)
            ->setPrenom($prenom)
            ->setAdresse($adresse)
            ->setCodePostal($codePostal)
            ->setVille($ville)
            ->setLongitude($longitude)
            ->setLatitude($latitude)
            ->setProjection($projection)
            ->setTypeEquipement($typeEquipement)
            ->setNombreEquipement($nbrEquipement)
            ->setCapaciteRencontre($capaciteRencontreStandard)
            ->setCapacitePhaseFinale($capacitePhasefinale)
            ->setParticipants($nbrParticipants)
            ->setLicencies($nbrLicencies)
            ->setLieuRencontrePossible($lieuRencontrePossible)
            ->setDateCreation($dateCreation)
            ->setIdVilleFrance($villeId)
            ->setPoule($poule)
        ;

        $em->persist($entity);
        $em->flush();
        $idEntite = $entity->getId();

        //rajouter l'équipe au groupe
        $groupe = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();

        $ajoutEntite = $groupe.",".$idEntite;

        $ajout = $em->getRepository('FfbbBundle:Groupe')->ajoutEntiteGroupe($idGroupe, $ajoutEntite);


        //rajouter l'équipe au groupe
        $idListeParticipant = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $listeParticipant = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipant)->getEquipes();

        $ajoutEntiteListe = $listeParticipant.",".$idEntite;


        $ajoutListe = $em->getRepository('FfbbBundle:ListeParticipants')->ajoutEntiteListe($idListeParticipant, $ajoutEntiteListe);

        if($ajout && $ajoutListe){
            return $this->redirect($this->generateUrl('ffbb_poules_gerer_groupe', array('idListe' => $idListeParticipant)));

        }
        else{
            return new Response("Problème de mise à jour du groupe");
        }

    }

    public function resultatCalculAction($idResultat)
    {


//        error_log("\n idResultat: ".print_r($idResultat , true), 3, "error_log_optimouv.txt");

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        $idRapport = $em->getRepository('FfbbBundle:Scenario')->getIdRapportByIdScenario($idResultat);

        if($idRapport != []){
            $idRapport  = $idRapport[0]["idRapport"];
        }


//        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idResultat);
        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);

        if($idGroupe != []){
            $idGroupe = $idGroupe[0]['idGroupe'];
        }

        $equipes = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();
        //$equipes de string a array
        $equipes = explode(",", $equipes);

        # récupérer la liste des noms et des ids de villes
        $detailsVilles = $em->getRepository('FfbbBundle:Entite')->getEntities($equipes);



        $detailsCalcul = $em->getRepository('FfbbBundle:Scenario')->findOneById($idResultat)->getDetailsCalcul();

        $detailsCalcul = json_decode($detailsCalcul, true);

        $nombrePoule = $detailsCalcul["nombrePoule"];
        $taillePoule = $detailsCalcul["taillePoule"];
        $contraintsExiste = $detailsCalcul["contraintsExiste"];
        $typeMatch = $detailsCalcul["typeMatch"];
        $scenarioOptimalAvecContrainte = $detailsCalcul["scenarioOptimalAvecContrainte"];
        $scenarioEquitableAvecContrainte = $detailsCalcul["scenarioEquitableAvecContrainte"];
        $scenarioEquitableSansContrainte = $detailsCalcul["scenarioEquitableSansContrainte"];
        $scenarioOptimalSansContrainte = $detailsCalcul["scenarioOptimalSansContrainte"];
        $scenarioRef = $detailsCalcul["scenarioRef"];
        $refExiste = $detailsCalcul["refExiste"];
        $varEquipeParPouleProposition = $detailsCalcul["params"]["varEquipeParPouleProposition"];

        # récupérer la nombre de la variation d'équipes choisi
        if(array_key_exists("varEquipeParPouleChoisi", $detailsCalcul["params"])){
            $varEquipeParPouleChoisi = $detailsCalcul["params"]["varEquipeParPouleChoisi"];
        }
        else {
            $varEquipeParPouleChoisi  = 0;
        }

        # récupérer les contraintes d'interdictions
        if(array_key_exists("interdictions", $detailsCalcul["params"])){
            $interdictions = $detailsCalcul["params"]["interdictions"];
        }
        else {
            $interdictions = [];
        }

        # récupérer les contraintes de répartitions homogènes
        if(array_key_exists("repartitionsHomogenes", $detailsCalcul["params"])){
            $repartitionsHomogenes = $detailsCalcul["params"]["repartitionsHomogenes"];
        }
        else{
            $repartitionsHomogenes = [];
        }

        # récupérer le statut final pour le rapport
        if(array_key_exists("final", $detailsCalcul["params"])){
            $finalStatut = $detailsCalcul["params"]["final"];
        }
        else{
            $finalStatut= "non";
        }

        # récupérer le boolean des equipes phantom
        if(array_key_exists("phantomExiste", $detailsCalcul["params"])){
            $phantomExiste = $detailsCalcul["params"]["phantomExiste"];
        }
        else{
            $phantomExiste = 0;
        }
        # récupérer l'info des poules
        if(array_key_exists("infoPoule", $detailsCalcul["params"])){
            $infoPoule = $detailsCalcul["params"]["infoPoule"];
        }
        else{
            $infoPoule = array($taillePoule => $nombrePoule);
        }


//        error_log("\n infoPoule : ".print_r($infoPoule , true), 3, "error_log_optimouv.txt");


        //récupération du nom du rapport
        $connection = $em->getConnection();

        $statement = $connection->prepare("SELECT  b.id as idRapport, b.nom as nomRapport, b.id_groupe as idGroupe FROM scenario as a, rapport as b where a.id_rapport = b.id and a.id = :id");
        $statement->bindParam(':id', $idResultat);
        $statement->execute();
        $statement = $statement->fetchAll(PDO::FETCH_ASSOC);
        $idRapport = $statement[0]['idRapport'];
        $nomRapport = $statement[0]['nomRapport'];
        $idGroupe = $statement[0]['idGroupe'];

        //récupération du nom du groupe

        $statement = $connection->prepare("SELECT  a.nom as nomGroupe, a.id_liste_participant as idListe from groupe as a where a.id = :id");
        $statement->bindParam(':id', $idGroupe);
        $statement->execute();
        $statement = $statement->fetchAll(PDO::FETCH_ASSOC);
        $idListe = $statement[0]['idListe'];
        $nomGroupe = $statement[0]['nomGroupe'];


        //récupération du nom du=e la liste

        $statement = $connection->prepare("SELECT  a.nom as nomListe from liste_participants as a where a.id = :id");
        $statement->bindParam(':id', $idListe);
        $statement->execute();
        $statement = $statement->fetchAll(PDO::FETCH_ASSOC);
        $nomListe = $statement[0]['nomListe'];

        return $this->render('FfbbBundle:Poules:resultatCalcul.html.twig' , array(

            'nombrePoule' => $nombrePoule,
            'taillePoule' => $taillePoule,
            'contraintsExiste' => $contraintsExiste,
            'typeMatch'=> $typeMatch,
            'scenarioOptimalAvecContrainte' => $scenarioOptimalAvecContrainte,
            'scenarioEquitableAvecContrainte' => $scenarioEquitableAvecContrainte,
            'scenarioEquitableSansContrainte' => $scenarioEquitableSansContrainte,
            'scenarioOptimalSansContrainte' => $scenarioOptimalSansContrainte,
            'scenarioRef' => $scenarioRef,
            'refExiste' => $refExiste,
            'nomRapport' => $nomRapport,
            'nomGroupe' => $nomGroupe,
            'nomListe' => $nomListe,
            'detailsVilles' => $detailsVilles,
            'idGroupe' => $idGroupe,
            'varEquipeParPouleProposition' => $varEquipeParPouleProposition,
            'varEquipeParPouleChoisi' => $varEquipeParPouleChoisi,
            'idResultat' => $idResultat,
            'interdictions' => $interdictions,
            'repartitionsHomogenes' => $repartitionsHomogenes,
            'finalStatut' => $finalStatut,
            'phantomExiste' => $phantomExiste,
            'infoPoule' => $infoPoule,


        ));
    }

    public function comparaisonScenarioAction($idResultat){
        error_log("\n idResultat : ".print_r($idResultat , true), 3, "error_log_optimouv.txt");

        return $this->render('FfbbBundle:Poules:comparaisonScenario.html.twig', array(
            ));
    }


    //page qui affiche les détails des calculs

    public function detailsCalculAction()
    {

        return $this->render('FfbbBundle:Poules:detailsCalcul.html.twig');
    }

    public function previsualisationPdfAction()
    {

        $params = $_POST['params'];


        //recuperation des donnees relatives au scenario
        $infoPdf = $this->getInfoPdfAction($params);

//        echo '<pre>',print_r($infoPdf,1),'</pre>';exit;


        $nombrePoule = $infoPdf[0];
        $taillePoule = $infoPdf[1];
        $contraintsExiste = $infoPdf[2];
        $typeMatch = $infoPdf[3];
        $scenarioOptimalSansContrainte = $infoPdf[4];
        $nomRapport = $infoPdf[5];
        $nomGroupe = $infoPdf[6];
        $nomListe = $infoPdf[7];
        $detailsVilles = $infoPdf[8];
        $idGroupe = $infoPdf[9];
        $idRapport = $infoPdf[10];


        return $this->render('FfbbBundle:Poules:previsualisationPdf.html.twig', array(
            'nomRapport' => $nomRapport,
            'typeMatch' => $typeMatch,
            'nombrePoule' => $nombrePoule,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
            'taillePoule' => $taillePoule,
            'contraintsExiste' => $contraintsExiste,
            'scenarioOptimalSansContrainte' => $scenarioOptimalSansContrainte,
            'idRapport' => $idRapport,
            'detailsVilles' => $detailsVilles,
            'idGroupe' => $idGroupe,
            'idResultat' => $params,
        ));

    }

    public function exportScenarioAction()
    {

        $params = $_POST['params'];

        //recuperation des donnees relatives au scenario
        $infoPdf = $this->getInfoPdfAction($params);

        $nombrePoule = $infoPdf[0];
        $taillePoule = $infoPdf[1];
        $contraintsExiste = $infoPdf[2];
        $typeMatch = $infoPdf[3];
        $scenarioOptimalSansContrainte = $infoPdf[4];
        $nomRapport = $infoPdf[5];
        $nomGroupe = $infoPdf[6];
        $nomListe = $infoPdf[7];
        $detailsVilles = $infoPdf[8];
        $idGroupe = $infoPdf[9];
        $idRapport = $infoPdf[10];


        $html = $this->renderView('FfbbBundle:Poules:previsualisationPdf.html.twig', array(
            'nomRapport' => $nomRapport,
            'typeMatch' => $typeMatch,
            'nombrePoule' => $nombrePoule,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
            'taillePoule' => $taillePoule,
            'contraintsExiste' => $contraintsExiste,
            'scenarioOptimalSansContrainte' => $scenarioOptimalSansContrainte,
            'idRapport' => $idRapport,
            'detailsVilles' => $detailsVilles,
            'idGroupe' => $idGroupe,
            'idResultat' => $params,

        ));


        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="mon_rapport.pdf"',
                'print-media-type'      => false,
                'outline'               => true,

            )
        );
    }

//    function qui ramene toutes les infos necessaires à la view

    public function getInfoPdfAction($idResultat)
    {


        $em = $this->getDoctrine()->getManager();

        $idRapport = $em->getRepository('FfbbBundle:Scenario')->getIdRapportByIdScenario($idResultat);

        if($idRapport != []){
            $idRapport  = $idRapport[0]["idRapport"];
        }

         $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);


        if($idGroupe != []){
            $idGroupe = $idGroupe[0]['idGroupe'];
        }


        $equipes = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();
        //$equipes de string a array
        $equipes = explode(",", $equipes);

        # récupérer la liste des noms et des ids de villes
        $detailsVilles = $em->getRepository('FfbbBundle:Entite')->getEntities($equipes);
        $detailsCalcul = $em->getRepository('FfbbBundle:Scenario')->findOneById($idResultat)->getDetailsCalcul();
        $detailsCalcul = json_decode($detailsCalcul, true);
        $nombrePoule = $detailsCalcul["nombrePoule"];
        $taillePoule = $detailsCalcul["taillePoule"];
        $contraintsExiste = $detailsCalcul["contraintsExiste"];
        $typeMatch = $detailsCalcul["typeMatch"];
        $scenarioOptimalSansContrainte = $detailsCalcul["scenarioOptimalSansContrainte"];

        //récupération du nom du rapport
        $connection = $em->getConnection();

        $statement = $connection->prepare("SELECT  b.id as idRapport, b.nom as nomRapport, b.id_groupe as idGroupe FROM scenario as a, rapport as b where a.id_rapport = b.id and a.id = :id");
        $statement->bindParam(':id', $idResultat);
        $statement->execute();
        $statement = $statement->fetchAll(PDO::FETCH_ASSOC);
        $idRapport = $statement[0]['idRapport'];
        $nomRapport = $statement[0]['nomRapport'];
        $idGroupe = $statement[0]['idGroupe'];

        //récupération du nom du groupe

        $statement = $connection->prepare("SELECT  a.nom as nomGroupe, a.id_liste_participant as idListe from groupe as a where a.id = :id");
        $statement->bindParam(':id', $idGroupe);
        $statement->execute();
        $statement = $statement->fetchAll(PDO::FETCH_ASSOC);
        $idListe = $statement[0]['idListe'];
        $nomGroupe = $statement[0]['nomGroupe'];


        //récupération du nom du=e la liste

        $statement = $connection->prepare("SELECT  a.nom as nomListe from liste_participants as a where a.id = :id");
        $statement->bindParam(':id', $idListe);
        $statement->execute();
        $statement = $statement->fetchAll(PDO::FETCH_ASSOC);
        $nomListe = $statement[0]['nomListe'];

        //construire le tableau de retour

        $retour = [];
        $retour[0] = $nombrePoule;
        $retour[1] = $taillePoule;
        $retour[2] = $contraintsExiste;
        $retour[3] = $typeMatch;
        $retour[4] = $scenarioOptimalSansContrainte;
        $retour[5] = $nomRapport;
        $retour[6] = $nomGroupe;
        $retour[7] = $nomListe;
        $retour[8] = $detailsVilles;
        $retour[9] = $idGroupe;
        $retour[10] = $idRapport;

        return $retour;
    }
}