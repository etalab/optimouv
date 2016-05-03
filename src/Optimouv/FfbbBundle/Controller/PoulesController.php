<?php

namespace Optimouv\FfbbBundle\Controller;

use Optimouv\FfbbBundle\Entity\Groupe;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Optimouv\FfbbBundle\Entity\Entite;
use Optimouv\FfbbBundle\Form\EntiteType;
use Symfony\Component\HttpFoundation\Response;
use PDO;
use ZipArchive;
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

        # récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        # flag rencontre
        $rencontre = 0;

        # flag type équipe
        $isEquipe = 1;



        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites("participants", $idUtilisateur, $rencontre, $isEquipe);


        if($statutUpload["success"]){

            # créer des entités dans la table entite
            $retourEntites = $this->get('service_listes')->creerEntites($idUtilisateur);
            $idsEntite = $retourEntites["idsEntite"];
            $nomFichier = $retourEntites["nomFichier"];


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

        # decoder les refs pour le match plateau
        for($i=0; $i<count($detailsEntites); $i++){
            $detailsEntites[$i]["refPlateauDecoder"] =  json_decode($detailsEntites[$i]["refPlateau"], true) ;
        }

        # boolean pour indiquer si le csv est pour match aller retour ou plateau
        if($detailsEntites != [] && $detailsEntites[0] != [] && $detailsEntites[0]["refPlateauDecoder"] != []  ){
            $typeMatchPlateau = 1;
        }
        else{
            $typeMatchPlateau = 0;

        }



        return $this->render('FfbbBundle:Poules:visualiserListeEquipes.html.twig', array(
            'idListeParticipants' => $idListeParticipants,
            'detailsEntites' => $detailsEntites,
            'nomListe' => $nomListe,
            'typeMatchPlateau' => $typeMatchPlateau,
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
        $categories = $em->getRepository('FfbbBundle:RepartitionHomogene')->findAll();

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
            'categories' =>$categories,


        ));
    }

    public function lancerCalculAction()
    {
        # obtenir l'id utilisateur
        $utilisateur = $this->get('security.token_storage')->getToken()->getUser();
        $utilisateurId = $utilisateur->getId();

        # sauvegarder les params dans la DB
        $retour = $this->get('service_poules')->sauvegarderParamsEnDB($utilisateurId);
        $idParams = $retour["data"];

//        error_log("\n envoyer id à rabbitmq: ".print_r($idParams , true), 3, "error_log_optimouv.txt");

        $queuePoule = $this->getParameter('mq_poule_queue');


        # envoyer l'id du rapport (params) à  RabbitMQ
//        $this->get('old_sound_rabbit_mq.poule_producer')->publish($idParams);
        $this->get('old_sound_rabbit_mq.poule_producer')->publish($idParams, $queuePoule);



        # incrémenter le nombre de lancements de calcul pour opti poule
        $this->get('service_statistiques')->augmenterNombreTableStatistiques($utilisateurId, "nombreLancementsOptiPoule", 1);


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

        $categories = $em->getRepository('FfbbBundle:RepartitionHomogene')->findAll();

        return $this->render('FfbbBundle:Poules:criteres.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
            'idGroupe' => $idGroupe,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
            'detailsVilles' => $detailsVilles,
            'categories' => $categories,

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
        
        # obtenir entity manager
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
        $nomMatch = $this->getNomMatch($typeMatch);
        
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
        $infoPouleStr = $this->getStrInfoPoule($infoPoule);
        
        
        # récupérer la contrainte d'accueil pour le match plateau
        if(array_key_exists("contrainteAccueilPlateauExiste", $detailsCalcul["params"])){
            $contrainteAccueilPlateauExiste = $detailsCalcul["params"]["contrainteAccueilPlateauExiste"];
        }
        else{
            $contrainteAccueilPlateauExiste = 0;;
        }

        # récupérer les changements d'affectation d'équipes entre les poules
        if(array_key_exists("changeAffectEquipes", $detailsCalcul["params"])){
            $changeAffectEquipes = $detailsCalcul["params"]["changeAffectEquipes"];
        }
        else{
            $changeAffectEquipes = [];;
        }


        $connection = $em->getConnection();
        //récupération du nom du rapport
        $statement = $connection->prepare("SELECT  b.id as idRapport, b.nom as nomRapport, b.id_groupe as idGroupe FROM resultats as a, parametres as b where a.id_rapport = b.id and a.id = :id");
        $statement->bindParam(':id', $idResultat);
        $statement->execute();
        $statement = $statement->fetchAll(PDO::FETCH_ASSOC);
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



        // info pour changement d'affectation d'équipe par poule
        $infoChangeAffectation = $this->get('service_poules')->getInfoChangeAffectation($scenarioOptimalSansContrainte, $scenarioEquitableSansContrainte,  $scenarioOptimalAvecContrainte, $scenarioEquitableAvecContrainte);

//        error_log("\n changeAffectEquipes: ".print_r($changeAffectEquipes , true), 3, "error_log_optimouv.txt");

        $categories = $em->getRepository('FfbbBundle:RepartitionHomogene')->findAll();


        //recuperer les coeff
        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

        return $this->render('FfbbBundle:Poules:resultatCalcul.html.twig' , array(

            'nombrePoule' => $nombrePoule,
            'taillePoule' => $taillePoule,
            'contraintsExiste' => $contraintsExiste,
            'typeMatch'=> $typeMatch,
            'nomMatch'=> $nomMatch,
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
            'infoPouleStr' => $infoPouleStr,
            'contrainteAccueilPlateauExiste' => $contrainteAccueilPlateauExiste,
            'infoChangeAffectation' => $infoChangeAffectation,
            'changeAffectEquipes' => $changeAffectEquipes,
            'categories' => $categories,
            'coutVoiture' => $coutVoiture,
            'coutCovoiturage' => $coutCovoiturage,
            'coutMinibus' => $coutMinibus,
            'gesVoiture' => $gesVoiture,
            'gesCovoiturage' => $gesCovoiturage,
            'gesMinibus' => $gesMinibus,
            'idListe' =>$idListe,


        ));
    }

    public function comparaisonScenarioAction($idResultat){

        //récupérer les coefff
        $em = $this->getDoctrine()->getManager();
        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

        $idRapport = $em->getRepository('FfbbBundle:Scenario')->findOneById($idResultat)->getIdRapport();
        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
        $idGroupe = $idGroupe[0]['idGroupe'];
        $idListe = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();






        $infoComparaison = $this->getInfoComparaison($idResultat);


        return $this->render('FfbbBundle:Poules:comparaisonScenario.html.twig', array(
            'idResultat' => $idResultat,
            'typeMatch' => $infoComparaison["typeMatch"],
            'scenarioOptimalSansContrainte' => $infoComparaison["scenarioOptimalSansContrainte"],
            'scenarioEquitableSansContrainte' => $infoComparaison["scenarioEquitableSansContrainte"],
            'scenarioOptimalAvecContrainte' => $infoComparaison["scenarioOptimalAvecContrainte"],
            'scenarioEquitableAvecContrainte' => $infoComparaison["scenarioEquitableAvecContrainte"],
            'scenarioRef' => $infoComparaison["scenarioRef"],
            'contraintsExiste' => $infoComparaison["contraintsExiste"],
            'refExiste' => $infoComparaison["refExiste"],
            'donneesComparison' => $infoComparaison["donneesComparison"],
            'coutVoiture' => $coutVoiture,
            'coutCovoiturage' => $coutCovoiturage,
            'coutMinibus' => $coutMinibus,
            'gesVoiture' => $gesVoiture,
            'gesCovoiturage' => $gesCovoiturage,
            'gesMinibus' => $gesMinibus,
            'idListe' =>$idListe,
            'idGroupe' =>$idGroupe,
            ));
    }

    private function getInfoComparaison($idResultat){

        $em = $this->getDoctrine()->getManager();
        $detailsCalcul = $em->getRepository('FfbbBundle:Scenario')->findOneById($idResultat)->getDetailsCalcul();
        $detailsCalcul = json_decode($detailsCalcul, true);

        $contraintsExiste = $detailsCalcul["contraintsExiste"];
        $refExiste = $detailsCalcul["refExiste"];
        $scenarioOptimalSansContrainte = $detailsCalcul["scenarioOptimalSansContrainte"];
        $scenarioEquitableSansContrainte = $detailsCalcul["scenarioEquitableSansContrainte"];

        # récupérer le scénario optimal avec contrainte
        if(array_key_exists("scenarioOptimalAvecContrainte", $detailsCalcul)){
            $scenarioOptimalAvecContrainte = $detailsCalcul["scenarioOptimalAvecContrainte"];
        }
        else {
            $scenarioOptimalAvecContrainte = [];
        }
        # récupérer le scénario équitable avec contrainte
        if(array_key_exists("scenarioEquitableAvecContrainte", $detailsCalcul)){
            $scenarioEquitableAvecContrainte = $detailsCalcul["scenarioEquitableAvecContrainte"];
        }
        else {
            $scenarioEquitableAvecContrainte = [];
        }
        # récupérer le scénario de réference
        if(array_key_exists("scenarioRef", $detailsCalcul)){
            $scenarioRef = $detailsCalcul["scenarioRef"];
        }
        else {
            $scenarioRef = [];
        }


        # obtenir l'id du rapport
        $idRapport = $em->getRepository('FfbbBundle:Scenario')->getIdRapportByIdScenario($idResultat);
        if($idRapport != []){
            $idRapport  = $idRapport[0]["idRapport"];
        }

        # obtenir l'id du groupe
        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
        if($idGroupe != []){
            $idGroupe = $idGroupe[0]['idGroupe'];
        }


        # obtenir l'id des équipes
        $equipes = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();
        //$equipes de string a array
        $equipes = explode(",", $equipes);

        # obtenir le détail des équipes
        $detailsVilles = $em->getRepository('FfbbBundle:Entite')->getEntities($equipes);

        # obtenir le type de match
        $typeMatch = $detailsCalcul["typeMatch"];


        # parser les données pour l'affichage
        $donneesComparison = $this->get('service_poules')->parserComparaisonScenario($detailsVilles, $scenarioOptimalAvecContrainte, $scenarioOptimalSansContrainte, $scenarioEquitableAvecContrainte, $scenarioEquitableSansContrainte, $scenarioRef, $refExiste, $contraintsExiste, $typeMatch );

        # obtenir le nom du rapport
        $nomRapport = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();


        $infoComparaison = array("typeMatch"=>$typeMatch,
            "nomRapport" => $nomRapport,
            "refExiste" => $refExiste,
            "contraintsExiste" => $contraintsExiste,
            "donneesComparison" => $donneesComparison,
            "scenarioRef" => $scenarioRef,
            'scenarioOptimalSansContrainte' => $scenarioOptimalSansContrainte,
            'scenarioEquitableSansContrainte' => $scenarioEquitableSansContrainte,
            'scenarioOptimalAvecContrainte' => $scenarioOptimalAvecContrainte,
            'scenarioEquitableAvecContrainte' => $scenarioEquitableAvecContrainte,
        ) ;


        return $infoComparaison;


    }

    public function exportComparaisonAction(){

        $idResultat = $_POST['idResultat'];

        $infoCsv = $this->getInfoComparaison($idResultat);

        $nomRapport = $infoCsv["nomRapport"];


        // créer le fichier zip
        $zipNom = "$nomRapport-comparaison_scenario.zip";
        $zip = new ZipArchive;
        $zip->open($zipNom, ZipArchive::CREATE);


        $this->remplirCsvEnZipComparaison($infoCsv, $zip);

        // fermer le fichier d'archive
        $zip->close();

        header('Content-Type: application/zip; charset=utf-8');
        header('Content-disposition: attachment; filename='.$zipNom);
        header('Content-Length: ' . filesize($zipNom));
        readfile($zipNom);

        // supprimer le fichier zip
        unlink($zipNom);

        exit;

    }


    public function remplirCsvEnZipComparaison($infoCsv, $zip)
    {

        $em = $this->getDoctrine()->getManager();
        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

        $refExiste = $infoCsv["refExiste"];
        $contraintsExiste = $infoCsv["contraintsExiste"];


        // avec contraintes et ref
        if($contraintsExiste == 1 && $refExiste == 1){
            // distance et temps du parcours
            $headerDistanceParcours = array("EQUIPES",
                "KMS A PARCOURIR - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "KMS A PARCOURIR - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "KMS A PARCOURIR - SCENARIO DE REFERENCE",
                "KMS A PARCOURIR - SCENARIO OPTIMAL SANS CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO DE REFERENCE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL SANS CONTRAINTE",
            );

            // cout du parcours
            $headerCoutParcours = array( "EQUIPES",
                "COUT EN VOITURE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN VOITURE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "COUT EN VOITURE - SCENARIO DE REFERENCE",
                "COUT EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO DE REFERENCE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO DE REFERENCE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
            );


            // émission de GES
            $headerCoutEmission= array( "EQUIPES",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO DE REFERENCE",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO DE REFERENCE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO DE REFERENCE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
            );
        }
        // avec contraintes sans ref
        elseif ($contraintsExiste == 1 && $refExiste == 0){
            // distance et temps du parcours
            $headerDistanceParcours = array("EQUIPES",
                "KMS A PARCOURIR - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "KMS A PARCOURIR - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "KMS A PARCOURIR - SCENARIO OPTIMAL SANS CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL SANS CONTRAINTE",
            );


            // cout du parcours
            $headerCoutParcours = array( "EQUIPES",
                "COUT EN VOITURE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN VOITURE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "COUT EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
            );


            // émission de GES
            $headerCoutEmission= array( "EQUIPES",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO EQUITABLE AVEC CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
            );

        }
        // sans contraintes avec ref
        elseif ($contraintsExiste == 0 && $refExiste == 1){

            // distance et temps du parcours
            $headerDistanceParcours = array("EQUIPES",
                "KMS A PARCOURIR - SCENARIO OPTIMAL SANS CONTRAINTE",
                "KMS A PARCOURIR - SCENARIO EQUITABLE SANS CONTRAINTE",
                "KMS A PARCOURIR - SCENARIO DE REFERENCE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL SANS CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO EQUITABLE SANS CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO DE REFERENCE",
            );


            // cout du parcours
            $headerCoutParcours = array( "EQUIPES",
                "COUT EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN VOITURE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "COUT EN VOITURE - SCENARIO DE REFERENCE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO DE REFERENCE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO EQUITABLE SANS CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO DE REFERENCE",
            );


            // émission de GES
            $headerCoutEmission= array( "EQUIPES",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO DE REFERENCE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO DE REFERENCE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO EQUITABLE SANS CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO DE REFERENCE",
            );

        }
        // sans contraintes sans ref
        elseif ($contraintsExiste == 0 && $refExiste == 0){

            // distance et temps du parcours
            $headerDistanceParcours = array("EQUIPES",
                "KMS A PARCOURIR - SCENARIO OPTIMAL SANS CONTRAINTE",
                "KMS A PARCOURIR - SCENARIO EQUITABLE SANS CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL SANS CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO EQUITABLE SANS CONTRAINTE",
            );


            // cout du parcours
            $headerCoutParcours = array( "EQUIPES",
                "COUT EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN VOITURE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO EQUITABLE SANS CONTRAINTE",
            );


            // émission de GES
            $headerCoutEmission= array( "EQUIPES",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO EQUITABLE SANS CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO EQUITABLE SANS CONTRAINTE",
            );

        }


//        error_log("\n donneesComparison: ".print_r($donneesComparison , true), 3, "error_log_optimouv.txt");

        // trier le tableau basé sur le nom de ville
        $this->get('service_rencontres')->sksort($infoCsv["donneesComparison"], "nom", true);

        // index=0 pour distance et temps du parcours
        // index=1 pour cout du parcours
        // index=2 pour émission de GES
        for ($i = 0; $i < 3; $i++) {

            // créer le fichier temporaire
            $fd = fopen('php://temp/maxmemory:1048576', 'w');
            if (false === $fd) {
                die('Erreur interne lors de la création du fichier temporaire');
            }

            // index=0 pour distance et temps
            if($i == 0){
                // écrire les données en csv
                fputcsv($fd, $headerDistanceParcours);

                foreach($infoCsv["donneesComparison"] as $equipe){

                    if($contraintsExiste == 1 && $refExiste == 1){
                        $dureeFormaterOpAc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioOptimalAvecContrainte"]);
                        $dureeFormaterEqAc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioEquitableAvecContrainte"]);
                        $dureeFormaterRef = $this->formaterJourHeureMinute($equipe["duree"]["scenarioRef"]);
                        $dureeFormaterOpSc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioOptimalSansContrainte"]);

                        $contenuDistanceParcours = array($equipe["nom"],
                            floor($equipe["distance"]["scenarioOptimalAvecContrainte"]/1000),
                            floor($equipe["distance"]["scenarioEquitableAvecContrainte"]/1000),
                            floor($equipe["distance"]["scenarioRef"]/1000),
                            floor($equipe["distance"]["scenarioOptimalSansContrainte"]/1000),
                            ($dureeFormaterOpAc["nbrJour"]." ".$dureeFormaterOpAc["nbrHeure"].":".$dureeFormaterOpAc["nbrMin"]),
                            ($dureeFormaterEqAc["nbrJour"]." ".$dureeFormaterEqAc["nbrHeure"].":".$dureeFormaterEqAc["nbrMin"]),
                            ($dureeFormaterRef["nbrJour"]." ".$dureeFormaterRef["nbrHeure"].":".$dureeFormaterRef["nbrMin"]),
                            ($dureeFormaterOpSc["nbrJour"]." ".$dureeFormaterOpSc["nbrHeure"].":".$dureeFormaterOpSc["nbrMin"]),
                        );

                    }
                    elseif($contraintsExiste == 1 && $refExiste == 0){
                        $dureeFormaterOpAc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioOptimalAvecContrainte"]);
                        $dureeFormaterEqAc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioEquitableAvecContrainte"]);
                        $dureeFormaterOpSc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioOptimalSansContrainte"]);

                        $contenuDistanceParcours = array($equipe["nom"],
                            floor($equipe["distance"]["scenarioOptimalAvecContrainte"]/1000),
                            floor($equipe["distance"]["scenarioEquitableAvecContrainte"]/1000),
                            floor($equipe["distance"]["scenarioOptimalSansContrainte"]/1000),
                            ($dureeFormaterOpAc["nbrJour"]." ".$dureeFormaterOpAc["nbrHeure"].":".$dureeFormaterOpAc["nbrMin"]),
                            ($dureeFormaterEqAc["nbrJour"]." ".$dureeFormaterEqAc["nbrHeure"].":".$dureeFormaterEqAc["nbrMin"]),
                            ($dureeFormaterOpSc["nbrJour"]." ".$dureeFormaterOpSc["nbrHeure"].":".$dureeFormaterOpSc["nbrMin"]),
                        );

                    }
                    elseif($contraintsExiste == 0 && $refExiste == 1){
                        $dureeFormaterOpSc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioOptimalSansContrainte"]);
                        $dureeFormaterEqSc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioEquitableSansContrainte"]);
                        $dureeFormaterRef = $this->formaterJourHeureMinute($equipe["duree"]["scenarioRef"]);

                        $contenuDistanceParcours = array($equipe["nom"],
                            floor($equipe["distance"]["scenarioOptimalSansContrainte"]/1000),
                            floor($equipe["distance"]["scenarioEquitableSansContrainte"]/1000),
                            floor($equipe["distance"]["scenarioRef"]/1000),
                            ($dureeFormaterOpSc["nbrJour"]." ".$dureeFormaterOpSc["nbrHeure"].":".$dureeFormaterOpSc["nbrMin"]),
                            ($dureeFormaterEqSc["nbrJour"]." ".$dureeFormaterEqSc["nbrHeure"].":".$dureeFormaterEqSc["nbrMin"]),
                            ($dureeFormaterRef["nbrJour"]." ".$dureeFormaterRef["nbrHeure"].":".$dureeFormaterRef["nbrMin"]),
                        );

                    }
                    elseif($contraintsExiste == 0 && $refExiste == 0){
                        $dureeFormaterOpSc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioOptimalSansContrainte"]);
                        $dureeFormaterEqSc = $this->formaterJourHeureMinute($equipe["duree"]["scenarioEquitableSansContrainte"]);

                        $contenuDistanceParcours = array($equipe["nom"],
                            floor($equipe["distance"]["scenarioOptimalSansContrainte"]/1000),
                            floor($equipe["distance"]["scenarioEquitableSansContrainte"]/1000),
                            ($dureeFormaterOpSc["nbrJour"]." ".$dureeFormaterOpSc["nbrHeure"].":".$dureeFormaterOpSc["nbrMin"]),
                            ($dureeFormaterEqSc["nbrJour"]." ".$dureeFormaterEqSc["nbrHeure"].":".$dureeFormaterEqSc["nbrMin"]),
                        );

                    }
                    
                    fputcsv($fd, $contenuDistanceParcours);
                }



                // retourner au début du stream
                rewind($fd);
                // ajouter le fichier qui est en mémoire à l'archive, donner un nom
                $nomFichierEncoder = $infoCsv["nomRapport"]."-comparaison kilometres et temps.csv";
            }
            // index=1 pour cout
            elseif ($i == 1){
                // écrire les données en csv
                fputcsv($fd, $headerCoutParcours);

                foreach($infoCsv["donneesComparison"] as $equipe){

                    if($contraintsExiste == 1 && $refExiste == 1){

                        $contenuCoutParcours = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $coutVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $coutCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $coutMinibus/1000),

                        );

                    }
                    elseif($contraintsExiste == 1 && $refExiste == 0){
                        $contenuCoutParcours = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $coutVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $coutCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $coutMinibus/1000),

                        );

                    }
                    elseif($contraintsExiste == 0 && $refExiste == 1){

                        $contenuCoutParcours = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]* $coutVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/4* $coutCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/9 * $coutMinibus/1000),

                        );

                    }
                    elseif($contraintsExiste == 0 && $refExiste == 0){
                        $contenuCoutParcours = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $coutVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]* $coutVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $coutCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/4* $coutCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $coutMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/9 * $coutMinibus/1000),

                        );


                    }

                    fputcsv($fd, $contenuCoutParcours);
                }


                // retourner au début du stream
                rewind($fd);
                // ajouter le fichier qui est en mémoire à l'archive, donner un nom
                $nomFichierEncoder = $infoCsv["nomRapport"]."-comparaison cout.csv";
            }
            // index=2 pour emission GES
            elseif ($i == 2){
                // écrire les données en csv
                fputcsv($fd, $headerCoutEmission);

                foreach($infoCsv["donneesComparison"] as $equipe){

                    if($contraintsExiste == 1 && $refExiste == 1){

                        $contenuCoutEmission = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $gesVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $gesCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $gesMinibus/1000),

                        );

                    }
                    elseif($contraintsExiste == 1 && $refExiste == 0){
                        $contenuCoutEmission = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $gesVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $gesCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalAvecContrainte"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableAvecContrainte"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $gesMinibus/1000),

                        );

                    }
                    elseif($contraintsExiste == 0 && $refExiste == 1){
                        $contenuCoutEmission = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]* $gesVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/4* $gesCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioRef"]/9 *$gesMinibus/1000),

                        );

                    }
                    elseif($contraintsExiste == 0 && $refExiste == 0){
                        $contenuCoutEmission = array($equipe["nom"],
                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]* $gesVoiture/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]* $gesVoiture/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/4* $gesCovoiturage/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/4* $gesCovoiturage/1000),

                            floor($equipe["distanceTotale"]["scenarioOptimalSansContrainte"]/9 * $gesMinibus/1000),
                            floor($equipe["distanceTotale"]["scenarioEquitableSansContrainte"]/9 * $gesMinibus/1000),

                        );


                    }

                    fputcsv($fd, $contenuCoutEmission);
                }




                // retourner au début du stream
                rewind($fd);
                // ajouter le fichier qui est en mémoire à l'archive, donner un nom
                $nomFichierEncoder = $infoCsv["nomRapport"]."-comparaison emission GES.csv";

            }



            // ajouter les fichiers csv en fichier zip
            $zip->addFromString($nomFichierEncoder , stream_get_contents($fd) );

            // fermer le fichier
            fclose($fd);
        }


    }

    private function formaterJourHeureMinute($duree){

        $nbrJour = round($duree /86400);
        if ($nbrJour <10) $nbrJour = "0$nbrJour";

        $nbrHeure = round($duree%86400/3600);
        if($nbrHeure <10) $nbrHeure = "0$nbrHeure";

        $nbrMin = round(($duree%86400%3600)/60);
        if($nbrMin <10 ) $nbrMin = "0$nbrMin";

        return array(
            "nbrJour"=> $nbrJour,
            "nbrHeure"=> $nbrHeure,
            "nbrMin"=>$nbrMin
        );

    }



    //page qui affiche les détails des calculs
    public function detailsCalculAction()
    {

        return $this->render('FfbbBundle:Poules:detailsCalcul.html.twig');
    }

    public function pretraitementExportAction()
    {

        $em = $this->getDoctrine()->getManager();
        $formatExport = $_POST['formatExport'];
        $idResultat = $_POST['idResultat'];
        $typeScenario = $_POST['typeScenario'];
        $nomScenario = $this->getNomScenario($typeScenario, 1);
        $nomScenarioSansAccent = $this->getNomScenario($typeScenario, 0);

        //recuperation des donnees relatives au scenario
        $infoPdf = $this->getInfoPdf($idResultat, $typeScenario);


        $nombrePoule = $infoPdf[0];
        $taillePoule = $infoPdf[1];
        $contraintsExiste = $infoPdf[2];
        $typeMatch = $infoPdf[3];
        $nomMatch = $this->getNomMatch($typeMatch);
        $scenarioResultats = $infoPdf[4];
        $nomRapport = $infoPdf[5];
        $nomGroupe = $infoPdf[6];
        $nomListe = $infoPdf[7];
        $detailsVilles = $infoPdf[8];
        $nomUtilisateur = $infoPdf[11];
        $infoPoule = $infoPdf["infoPoule"];
        $infoPouleStr = $this->getStrInfoPoule($infoPoule);

        
        # obtenir l'id de l'utilisateur
        $utilisateur = $this->get("security.token_storage")->getToken()->getUser();
        $utilisateurId = $utilisateur->getId();

        # obtenir l'id de la discipline
        $disciplineId = $this->get("service_statistiques")->getDisciplineId($utilisateurId);
        $discipline = $em->getRepository('FfbbBundle:Discipline')->findOneBy(array('id'=>$disciplineId));
        $nomDiscipline = $discipline->getNom();

        # obtenir l'id de la fédération
        $federationId = $this->get("service_statistiques")->getFederationId($disciplineId);
        $federation = $em->getRepository('FfbbBundle:Federation')->findOneBy(array('id'=>$federationId));
        $nomFederation = $federation->getNom();

        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

        if($formatExport == "pdf"){
            
            return $this->render('FfbbBundle:Poules:previsualisationPdf.html.twig', array(
                'nomRapport' => $nomRapport,
                'typeMatch' => $typeMatch,
                'nomMatch' => $nomMatch,
                'nombrePoule' => $nombrePoule,
                'nomListe' => $nomListe,
                'nomGroupe' => $nomGroupe,
                'taillePoule' => $taillePoule,
                'contraintsExiste' => $contraintsExiste,
                'scenarioResultats' => $scenarioResultats,
                'detailsVilles' => $detailsVilles,
                'idResultat' => $idResultat,
                'nomUtilisateur' => $nomUtilisateur,
                'typeScenario' => $typeScenario,
                'nomScenario' => $nomScenario,
                'nomFederation' => $nomFederation,
                'nomDiscipline' => $nomDiscipline,
                'infoPouleStr' => $infoPouleStr,
                'coutVoiture' => $coutVoiture,
                'coutCovoiturage' => $coutCovoiturage,
                'coutMinibus' => $coutMinibus,
                'gesVoiture' => $gesVoiture,
                'gesCovoiturage' => $gesCovoiturage,
                'gesMinibus' => $gesMinibus
            ));


        }
        elseif ($formatExport == "xml"){

            header('Content-type: text/xml');
            header('Content-Disposition: attachment; filename="'.$nomRapport.'.xml"');


            $infoXML = array(
                "nomRapport" => $nomRapport,
                "nomScenario" => $nomScenario,
                "nomFederation" => $nomFederation,
                "nomDiscipline" => $nomDiscipline,
                "nomUtilisateur" => $nomUtilisateur,
                "nomGroupe" => $nomGroupe,
                "nomMatch" => $nomMatch,
                'nomListe' => $nomListe,
                'nombrePoule' => $nombrePoule,
                'taillePoule' => $taillePoule,
                'infoPouleStr' => $infoPouleStr,
                'scenarioResultats' => $scenarioResultats,
                'typeMatch' => $typeMatch,

            );


            $texte = $this->getTexteExportXml($infoXML);

            echo $texte;
            exit();


        }
        elseif ($formatExport == "csv"){

            // créer le fichier zip
            $zipNom = "$nomRapport-$nomScenario-csv.zip";
            $zip = new ZipArchive;
            $zip->open($zipNom, ZipArchive::CREATE);


            $infoCsv = array(
                "nomRapport" => $nomRapport,
                "nomScenario" => $nomScenario,
                "nomScenarioSansAccent" => $nomScenarioSansAccent,
                "nomFederation" => $nomFederation,
                "nomDiscipline" => $nomDiscipline,
                "nomUtilisateur" => $nomUtilisateur,
                "nomGroupe" => $nomGroupe,
                "nomMatch" => $nomMatch,
                'nomListe' => $nomListe,
                'nombrePoule' => $nombrePoule,
                'taillePoule' => $taillePoule,
                'infoPouleStr' => $infoPouleStr,
                'scenarioResultats' => $scenarioResultats,
                'typeMatch' => $typeMatch,
            );

//            error_log("\n typeMatch: ".print_r($typeMatch , true), 3, "error_log_optimouv.txt");

            
            $this->remplirCsvEnZip($infoCsv, $zip);

            // fermer le fichier d'archive
            $zip->close();

            header('Content-Type: application/zip; charset=utf-8');
            header('Content-disposition: attachment; filename='.$zipNom);
            header('Content-Length: ' . filesize($zipNom));
            readfile($zipNom);

             // supprimer le fichier zip
            unlink($zipNom);

            exit;



        }


    }

    private function remplirCsvEnZip($infoCsv, $zip){

        $em = $this->getDoctrine()->getManager();
        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();
        
        // entete pour l'estimation générale
        $headerEstimationGenerale = array("KILOMETRES A PARCOURIR POUR LE SCENARIO",
            "COUT POUR LE SCENARIO EN VOITURE",
            "COUT POUR LE SCENARIO EN COVOITURAGE",
            "COUT POUR LE SCENARIO EN MINIBUS",
            "EMISSIONS TOTALES DE GES EN VOITURE",
            "EMISSIONS TOTALES DE GES EN COVOITURAGE",
            "EMISSIONS TOTALES DE GES EN MINIBUS"
        );

        // contenu de l'estimation générale
        $distanceTotale = round($infoCsv["scenarioResultats"]["estimationGenerale"]["distanceTotale"]/1000);
        $distanceTotaleTousParticipants = round($infoCsv["scenarioResultats"]["estimationGenerale"]["distanceTotaleTousParticipants"]/1000);

        $coutVoiture = round($distanceTotaleTousParticipants * $coutVoiture);
        $coutCovoiturage = round($distanceTotaleTousParticipants/4 * $coutCovoiturage);
        $coutMinibus = round($distanceTotaleTousParticipants/9 * $coutMinibus);
        $emissionVoiture = round($distanceTotaleTousParticipants * $gesVoiture);
        $emissionCovoiturage = round($distanceTotaleTousParticipants/4 * $gesCovoiturage);
        $emissionMinibus = round($distanceTotaleTousParticipants/9 * $gesMinibus);
        $contenuEstimationGenerale = array($distanceTotale,
            $coutVoiture, $coutCovoiturage, $coutMinibus,
            $emissionVoiture, $emissionCovoiturage, $emissionMinibus
        );


        // entete pour l'estimation détaillé
        $headerEstimationDetaille = array( "PARTICIPANTS",
            "KILOMETRES A PARCOURIR",
            "TEMPS DE PARCOURS (J H:M)",
            "COUT DU PARCOURS EN VOITURE",
            "COUT DU PARCOURS EN COVOITURAGE",
            "COUT DU PARCOURS EN MINIBUS",
            "EMISSIONS GES EN VOITURE",
            "EMISSIONS GES EN COVOITURAGE",
            "EMISSIONS GES EN MINIBUS"
        );

        // entete pour l' liste de rencontres
        if($infoCsv["typeMatch"] == "allerRetour" ||  $infoCsv["typeMatch"] == "allerSimple"){
            $headerRencontres = array("POULE", "PARTICIPANT 1", "PARTICIPANT 2"
            );
        }
        elseif($infoCsv["typeMatch"] == "plateau" ){
            $headerRencontres = array("POULE", "JOURNEE", "EQUIPE HOTE", "EQUIPE ADVERSE 1", "EQUIPE ADVERSE 2"
            );

        }

        // alphabet pour le nom de poule
        $alphabet = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N','O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z' ];


        // index=0 pour estimation générale
        // index=1 pour estimation détaillée
        // index=2 pour rencontre
        for ($i = 0; $i < 3; $i++) {

            // créer le fichier temporaire
            $fd = fopen('php://temp/maxmemory:1048576', 'w');
            if (false === $fd) {
                die('Erreur interne lors de la création du fichier temporaire');
            }

            // index=0 pour estimation générale
            if($i == 0){
                // écrire les données en csv
                fputcsv($fd, $headerEstimationGenerale);
                fputcsv($fd, $contenuEstimationGenerale);
                // retourner au début du stream
                rewind($fd);
                // ajouter le fichier qui est en mémoire à l'archive, donner un nom
                $nomFichierEncoder = $infoCsv["nomRapport"]."-".$infoCsv["nomScenarioSansAccent"]."-estimations.csv";
            }
            // index=1 pour estimation détaillée
            elseif ($i == 1){
                // écrire les données en csv
                fputcsv($fd, $headerEstimationDetaille);


                $estimationDetails = $infoCsv["scenarioResultats"]["estimationDetails"];
                ksort($estimationDetails);



                foreach($estimationDetails as $pouleNbr => $estimationDetail) {


                    $jourTrajet = round($estimationDetail["dureeTotale"]/86400);
                    if( $jourTrajet < 10){
                        $jourTrajet = "0$jourTrajet";
                    }
                    $heureTrajet = round($estimationDetail["dureeTotale"]%86400/3600);
                    if( $heureTrajet< 10){
                        $heureTrajet = "0$heureTrajet";
                    }
                    $minuteTrajet = round($estimationDetail["dureeTotale"]%86400/3600);
                    if( $minuteTrajet< 10){
                        $minuteTrajet = "0$minuteTrajet";
                    }

                    $contenuEstimationDetaille = array($alphabet[$pouleNbr-1],
                        floor($estimationDetail["distanceTotale"]/1000),
                        $jourTrajet." ".$heureTrajet.":".$minuteTrajet,
                        floor($estimationDetail["distanceTotaleTousParticipants"]/1000*$coutVoiture),
                        floor($estimationDetail["distanceTotaleTousParticipants"]/1000/4*$coutCovoiturage),
                        floor($estimationDetail["distanceTotaleTousParticipants"]/1000/9*$coutMinibus),
                        floor($estimationDetail["distanceTotaleTousParticipants"]/1000*$gesVoiture),
                        floor($estimationDetail["distanceTotaleTousParticipants"]/1000/4*$gesCovoiturage),
                        floor($estimationDetail["distanceTotaleTousParticipants"]/1000/9*$gesMinibus),

                    );

                    fputcsv($fd, $contenuEstimationDetaille);

                }

                
                // retourner au début du stream
                rewind($fd);
                // ajouter le fichier qui est en mémoire à l'archive, donner un nom
                $nomFichierEncoder = $infoCsv["nomRapport"]."-".$infoCsv["nomScenarioSansAccent"]."-details.csv";
            }
            // index=2 pour liste de rencontre
            // il y a deux formats: aller-retour (aller-simple) et plateau
            elseif ($i == 2){
                // écrire les données en csv
                fputcsv($fd, $headerRencontres);


                $rencontres =  $infoCsv["scenarioResultats"]["rencontreDetails"];
                ksort($rencontres);
                $typeMatch = $infoCsv["typeMatch"];

                if($typeMatch == "allerRetour" || $typeMatch == "allerSimple") {
                    foreach ($rencontres as $pouleNbr => $rencontresParPoule) {
                        foreach ($rencontresParPoule as $rencontre) {
                            $contenuRencontres = array($alphabet[$pouleNbr-1],
                                $rencontre["equipeDepartNom"],
                                $rencontre["equipeDestinationNom"],
                            );

                            fputcsv($fd, $contenuRencontres);
                        }

                    }
                }
                elseif ($typeMatch == "plateau"){
                    foreach ($rencontres as $pouleNbr => $rencontresParPoule) {
                        foreach ($rencontresParPoule as $jourNbr => $rencontresParJour) {
                            foreach ($rencontresParJour as $rencontre) {
                                $contenuRencontres = array($alphabet[$pouleNbr-1],
                                    $jourNbr,
                                    $rencontre["hoteNom"],
                                    $rencontre["premierEquipeNom"],
                                    $rencontre["deuxiemeEquipeNom"]
                                );

                                fputcsv($fd, $contenuRencontres);

                            }
                        }

                    }
                }


                
                // retourner au début du stream
                rewind($fd);
                // ajouter le fichier qui est en mémoire à l'archive, donner un nom
                $nomFichierEncoder = $infoCsv["nomRapport"]."-".$infoCsv["nomScenarioSansAccent"]."-rencontres.csv";
            }

            // ajouter les fichiers csv en fichier zip
            $zip->addFromString($nomFichierEncoder, stream_get_contents($fd) );


            // fermer le fichier
            fclose($fd);
        }
    }

    private function getTexteExportXml($infoXml){

        $em = $this->getDoctrine()->getManager();
        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

        $texte = '<?xml version="1.0" encoding="utf-8"?>';

        $texte .= "\n";
        $texte .= "<resultat>\n";

        # parametres
        $texte .= "\t<params>\n";
        $texte .= "\t\t<nom_rapport>" .$infoXml["nomRapport"]."</nom_rapport>\n";
        $texte .= "\t\t<nom_rencontre>" .$infoXml["nomMatch"]."</nom_rencontre>\n";
        $texte .= "\t\t<nom_scenario>" .$infoXml["nomScenario"]."</nom_scenario>\n";
        $texte .= "\t\t<nom_federation>" .$infoXml["nomFederation"]."</nom_federation>\n";
        $texte .= "\t\t<nom_discipline>" .$infoXml["nomDiscipline"]."</nom_discipline>\n";
        $texte .= "\t\t<nom_utilisateur>" .$infoXml["nomUtilisateur"]."</nom_utilisateur>\n";
        $texte .= "\t\t<nom_liste>" .$infoXml["nomListe"]."</nom_liste>\n";
        $texte .= "\t\t<nom_groupe>" .$infoXml["nomGroupe"]."</nom_groupe>\n";
        $texte .= "\t\t<info_poules>" .$infoXml["infoPouleStr"]."</info_poules>\n";
        $texte .= "\t</params>\n";


        # estimation générale

        $distanceTotale = round($infoXml["scenarioResultats"]["estimationGenerale"]["distanceTotale"]/1000);
        $distanceTotaleTousParticipants = round($infoXml["scenarioResultats"]["estimationGenerale"]["distanceTotaleTousParticipants"]/1000);

        $texte .= "\t<estimation_generale>\n";
        $texte .= "\t\t<distance_totale>" .$distanceTotale." Kms</distance_totale>\n";
        $texte .= "\t\t<cout_voiture>" .round($distanceTotaleTousParticipants*$coutVoiture)." €</cout_voiture>\n";
        $texte .= "\t\t<cout_covoiturage>" .round($distanceTotaleTousParticipants/4*$coutCovoiturage)." €</cout_covoiturage>\n";
        $texte .= "\t\t<cout_minibus>" .round($distanceTotaleTousParticipants/9*$coutMinibus)." €</cout_minibus>\n";
        $texte .= "\t\t<co2_emission_voiture>" .round($distanceTotaleTousParticipants*$gesVoiture)." KG eq CO2</co2_emission_voiture>\n";
        $texte .= "\t\t<co2_emission_covoiturage>" .round($distanceTotaleTousParticipants/4*$gesCovoiturage)." KG eq CO2</co2_emission_covoiturage>\n";
        $texte .= "\t\t<co2_emission_minibus>" .round($distanceTotaleTousParticipants/9*$gesMinibus)." KG eq CO2</co2_emission_minibus>\n";
        $texte .= "\t</estimation_generale>\n";


        # estimation détaillée
        $estimationDetails = $infoXml["scenarioResultats"]["estimationDetails"];
        ksort($estimationDetails);
        $texte .= "\t<details>\n";
        $alphabet = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N','O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z' ];



        foreach($estimationDetails as $pouleNbr => $estimationDetail) {

            $texte .= "\t\t<participant>\n";
            $texte .= "\t\t\t<nom>"."Poule ".$alphabet[$pouleNbr-1] ."</nom>\n";
            $texte .= "\t\t\t<distance_parcourue>" .round($estimationDetail["distanceTotale"]/1000)." Kms</distance_parcourue>\n";

            $jourTrajet = round($estimationDetail["dureeTotale"]/86400);
            if( $jourTrajet < 10){
                $jourTrajet = "0$jourTrajet";
            }
            $heureTrajet = round($estimationDetail["dureeTotale"]%86400/3600);
            if( $heureTrajet< 10){
                $heureTrajet = "0$heureTrajet";
            }
            $minuteTrajet = round($estimationDetail["dureeTotale"]%86400/3600);
            if( $minuteTrajet< 10){
                $minuteTrajet = "0$minuteTrajet";
            }

            $texte .= "\t\t\t<duree_trajet>" .$jourTrajet." ".$heureTrajet.":".$minuteTrajet." (J H:M)"." </duree_trajet>\n";
            $texte .= "\t\t\t<cout_voiture>" .round($estimationDetail["distanceTotaleTousParticipants"]/1000*$coutVoiture)." €</cout_voiture>\n";
            $texte .= "\t\t\t<cout_covoiturage>" .round($estimationDetail["distanceTotaleTousParticipants"]/1000/4*$coutCovoiturage)." €</cout_covoiturage>\n";
            $texte .= "\t\t\t<cout_minibus>" .round($estimationDetail["distanceTotaleTousParticipants"]/1000/9*$coutMinibus)." €</cout_minibus>\n";
            $texte .= "\t\t\t<co2_emission_voiture>" .round($estimationDetail["distanceTotaleTousParticipants"]/1000*$gesVoiture)." KG eq CO2</co2_emission_voiture>\n";
            $texte .= "\t\t\t<co2_emission_covoiturage>" .round($estimationDetail["distanceTotaleTousParticipants"]/1000/4*$gesCovoiturage)." KG eq CO2</co2_emission_covoiturage>\n";
            $texte .= "\t\t\t<co2_emission_minibus>" .round($estimationDetail["distanceTotaleTousParticipants"]/1000/9*$gesMinibus)." KG eq CO2</co2_emission_minibus>\n";
            $texte .= "\t\t</participant>\n";

        }

        $texte .= "\t</details>\n";


        # liste de rencontre
        $rencontres =  $infoXml["scenarioResultats"]["rencontreDetails"];
        ksort($rencontres);
        $typeMatch = $infoXml["typeMatch"];
//        error_log("\n rencontres: ".print_r($rencontres , true), 3, "error_log_optimouv.txt");
        $texte .= "\t<liste_rencontres>\n";

        if($typeMatch == "allerRetour" || $typeMatch == "allerSimple") {
            foreach ($rencontres as $pouleNbr => $rencontresParPoule) {
                foreach ($rencontresParPoule as $rencontre) {
                    $texte .= "\t\t<rencontre>\n";
                    $texte .= "\t\t\t<poule>Poule " .$alphabet[$pouleNbr-1]."</poule>\n";
                    $texte .= "\t\t\t<equipe1>" .$rencontre["equipeDepartNom"]."</equipe1>\n";
                    $texte .= "\t\t\t<equipe2>" .$rencontre["equipeDestinationNom"]."</equipe2>\n";
                    $texte .= "\t\t</rencontre>\n";

                }

            }
        }
        elseif ($typeMatch == "plateau"){
            foreach ($rencontres as $pouleNbr => $rencontresParPoule) {
                foreach ($rencontresParPoule as $jourNbr => $rencontresParJour) {
                    foreach ($rencontresParJour as $rencontre) {
                        $texte .= "\t\t<rencontre>\n";
                        $texte .= "\t\t\t<poule>Poule " .$alphabet[$pouleNbr-1]."</poule>\n";
                        $texte .= "\t\t\t<jour> " .$jourNbr."</jour>\n";
                        $texte .= "\t\t\t<equipe_hote>" .$rencontre["hoteNom"]."</equipe_hote>\n";
                        $texte .= "\t\t\t<equipe_adverse1>" .$rencontre["premierEquipeNom"]."</equipe_adverse1>\n";
                        $texte .= "\t\t\t<equipe_adverse2>" .$rencontre["deuxiemeEquipeNom"]."</equipe_adverse2>\n";
                        $texte .= "\t\t</rencontre>\n";

                    }
                }

            }
        }


        $texte .= "\t</liste_rencontres>\n";
        $texte .= "</resultat>";

        return $texte;
    }

    private function getNomMatch($typeMatch){
        $nomMatch = "";

 
        if($typeMatch == "allerRetour"){
            $nomMatch = "Optimisation de poules - match aller retour";
        }
        elseif($typeMatch == "allerSimple"){
            $nomMatch = "Optimisation de poules - match aller simple";
        }
        elseif($typeMatch == "plateau"){
            $nomMatch = "Optimisation de poules - match plateau";
        }

        return $nomMatch;
    }

    // si boolAccent = 1, le nom est avec accent
    // si boolAccent = 0, le nom sans avec accent
    private function getNomScenario($typeScenario, $boolAccent){
        $nomScenario = "";


        if($boolAccent == 1){
            if($typeScenario == "optimalSansContrainte"){
                $nomScenario = "scénario optimal sans contrainte";
            }
            elseif($typeScenario == "optimalAvecContrainte"){
                $nomScenario = "scénario optimal avec contrainte";
            }
            elseif($typeScenario == "equitableSansContrainte"){
                $nomScenario = "scénario équitable sans contrainte";
            }
            elseif($typeScenario == "equitableAvecContrainte"){
                $nomScenario = "scénario équitable avec contrainte";
            }
            elseif($typeScenario == "ref"){
                $nomScenario = "scénario de référence";
            }

        }
        elseif ($boolAccent == 0){
            if($typeScenario == "optimalSansContrainte"){
                $nomScenario = "scenario optimal sans contrainte";
            }
            elseif($typeScenario == "optimalAvecContrainte"){
                $nomScenario = "scenario optimal avec contrainte";
            }
            elseif($typeScenario == "equitableSansContrainte"){
                $nomScenario = "scenario equitable sans contrainte";
            }
            elseif($typeScenario == "equitableAvecContrainte"){
                $nomScenario = "scenario equitable avec contrainte";
            }
            elseif($typeScenario == "ref"){
                $nomScenario = "scenario de reference";
            }

        }

        return $nomScenario;
    }

    public function exportScenarioPdfAction()
    {
        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");


        $em = $this->getDoctrine()->getManager();
        $idResultat = $_POST['idResultat'];
        $typeScenario = $_POST['typeScenario'];
        $nomScenario = $this->getNomScenario($typeScenario, 1);

        //recuperation des donnees relatives au scenario
        $infoPdf = $this->getInfoPdf($idResultat, $typeScenario);

        $nombrePoule = $infoPdf[0];
        $taillePoule = $infoPdf[1];
        $contraintsExiste = $infoPdf[2];
        $typeMatch = $infoPdf[3];
        $nomMatch = $this->getnomMatch($typeMatch);
        $scenarioResultats = $infoPdf[4];
        $nomRapport = $infoPdf[5];
        $nomGroupe = $infoPdf[6];
        $nomListe = $infoPdf[7];
        $detailsVilles = $infoPdf[8];
        $nomUtilisateur = $infoPdf[11];
        $infoPoule = $infoPdf["infoPoule"];
        $infoPouleStr = $this->getStrInfoPoule($infoPoule);


        # obtenir l'id de l'utilisateur
        $utilisateur = $this->get("security.token_storage")->getToken()->getUser();
        $utilisateurId = $utilisateur->getId();

        # obtenir l'id de la discipline
        $disciplineId = $this->get("service_statistiques")->getDisciplineId($utilisateurId);
        $discipline = $em->getRepository('FfbbBundle:Discipline')->findOneBy(array('id'=>$disciplineId));
        $nomDiscipline = $discipline->getNom();

        # obtenir l'id de la fédération
        $federationId = $this->get("service_statistiques")->getFederationId($disciplineId);
        $federation = $em->getRepository('FfbbBundle:Federation')->findOneBy(array('id'=>$federationId));
        $nomFederation = $federation->getNom();

//        error_log("\n nomDiscipline: ".print_r($nomDiscipline, true), 3, $this->error_log_path);
//        error_log("\n nomFederation: ".print_r($nomFederation, true), 3, $this->error_log_path);


        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

        $html = $this->renderView('FfbbBundle:Poules:exportPdf.html.twig', array(
            'nomRapport' => $nomRapport,
            'typeMatch' => $typeMatch,
            'nomMatch' => $nomMatch,
            'nombrePoule' => $nombrePoule,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,
            'taillePoule' => $taillePoule,
            'contraintsExiste' => $contraintsExiste,
            'scenarioResultats' => $scenarioResultats,
            'detailsVilles' => $detailsVilles,
            'idResultat' => $idResultat,
            'nomUtilisateur' => $nomUtilisateur,
            'typeScenario' => $typeScenario,
            'nomScenario' => $nomScenario,
            'nomFederation' => $nomFederation,
            'nomDiscipline' => $nomDiscipline,
            'infoPouleStr' => $infoPouleStr,
            'coutVoiture' => $coutVoiture,
            'coutCovoiturage' => $coutCovoiturage,
            'coutMinibus' => $coutMinibus,
            'gesVoiture' => $gesVoiture,
            'gesCovoiturage' => $gesCovoiturage,
            'gesMinibus' => $gesMinibus

        ));


        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="'.$nomRapport.'.pdf"',
                'print-media-type'      => false,
                'outline'               => true,

            )
        );
    }

    private function getStrInfoPoule($infoPoule){
        $strInfoPoule = "";

        $i = 0;
        foreach($infoPoule as $taillePoule => $nombrePoule){
            $i ++;
            if($nombrePoule == 1){
                $strInfoPoule .= $nombrePoule. " poule de ". $taillePoule . " équipes";
            }
            else{
                $strInfoPoule .= $nombrePoule. " poules de ". $taillePoule . " équipes";
            }

            if($i != count($infoPoule)){
                $strInfoPoule .= " et ";
            }


        }


        return $strInfoPoule;
    }


//    function qui ramene toutes les infos necessaires à la view
    private function getInfoPdf($idResultat , $typeScenario)
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


        //récupérer le nom d'utilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $nomUtilisateur = $user->getUsername();

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

        # récupérer l'info des poules
        if(array_key_exists("infoPoule", $detailsCalcul["params"])){
            $infoPoule = $detailsCalcul["params"]["infoPoule"];
        }
        else{
            $infoPoule = array($taillePoule => $nombrePoule);
        }

        # obtenir scénario selon leur type
        if($typeScenario == "optimalSansContrainte"){
            $scenarioResultats = $detailsCalcul["scenarioOptimalSansContrainte"];
        }
        elseif ($typeScenario == "equitableSansContrainte"){
            $scenarioResultats = $detailsCalcul["scenarioEquitableSansContrainte"];

        }
        elseif ($typeScenario == "optimalAvecContrainte"){
            $scenarioResultats = $detailsCalcul["scenarioOptimalAvecContrainte"];
        }
        elseif ($typeScenario == "equitableAvecContrainte"){
            $scenarioResultats = $detailsCalcul["scenarioEquitableAvecContrainte"];
        }
        elseif ($typeScenario == "ref"){
            $scenarioResultats = $detailsCalcul["scenarioRef"];

        }


        //récupération du nom du rapport
        $connection = $em->getConnection();

        $statement = $connection->prepare("SELECT  b.id as idRapport, b.nom as nomRapport, b.id_groupe as idGroupe FROM resultats as a, parametres as b where a.id_rapport = b.id and a.id = :id");
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
        $retour[4] = $scenarioResultats;
        $retour[5] = $nomRapport;
        $retour[6] = $nomGroupe;
        $retour[7] = $nomListe;
        $retour[8] = $detailsVilles;
        $retour[9] = $idGroupe;
        $retour[10] = $idRapport;
        $retour[11] = $nomUtilisateur;
        $retour["infoPoule"] = $infoPoule;

        return $retour;
    }
}