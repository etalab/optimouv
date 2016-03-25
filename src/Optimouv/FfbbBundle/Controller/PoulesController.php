<?php

namespace Optimouv\FfbbBundle\Controller;

use Optimouv\FfbbBundle\Entity\Groupe;
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

//        error_log("\n infoChangeAffectation: ".print_r($infoChangeAffectation , true), 3, "error_log_optimouv.txt");


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



        ));
    }

    public function comparaisonScenarioAction($idResultat){
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




        return $this->render('FfbbBundle:Poules:comparaisonScenario.html.twig', array(
            'idResultat' => $idResultat,
            'scenarioOptimalSansContrainte' => $scenarioOptimalSansContrainte,
            'scenarioEquitableSansContrainte' => $scenarioEquitableSansContrainte,
            'scenarioOptimalAvecContrainte' => $scenarioOptimalAvecContrainte,
            'scenarioEquitableAvecContrainte' => $scenarioEquitableAvecContrainte,
            'scenarioRef' => $scenarioRef,
            'contraintsExiste' => $contraintsExiste,
            'refExiste' => $refExiste,
            'donneesComparison' => $donneesComparison

            ));
    }


    //page qui affiche les détails des calculs
    public function detailsCalculAction()
    {

        return $this->render('FfbbBundle:Poules:detailsCalcul.html.twig');
    }

    public function pretraitementExportAction()
    {
        $formatExport = $_POST['formatExport'];
        $idResultat = $_POST['idResultat'];
        $typeScenario = $_POST['typeScenario'];
        $nomScenario = $this->getNomScenario($typeScenario);

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
        $interdictions = $infoPdf["interdictions"];
        $repartitionsHomogenes = $infoPdf["repartitionsHomogenes"];

        error_log("\n repartitionsHomogenes: ".print_r($repartitionsHomogenes , true), 3, "error_log_optimouv.txt");


        $nomFederation = "FFBB"; # FIXME
        $nomDiscipline ="Basket"; # FIXME

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
                'interdictions' => $interdictions,
                'repartitionsHomogenes' => $repartitionsHomogenes,





            );


            $texte = $this->getTexteExportXml($infoXML);

//             error_log("\n text: ".print_r($texte , true), 3, "error_log_optimouv.txt");


            echo $texte;
            exit();


        }
        elseif ($formatExport == "csv"){
            return new JsonResponse("Cette fonctionalité est en cours de développement. Merci de vouloir patienter.");
            exit();
        }


    }


    private function getTexteExportXml($infoXml){
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
        $texte .= "\t\t<contraintes>\n";

        # interdictions
        $texte .= "\t\t\t<interdictions>\n";
        foreach($infoXml["interdictions"] as $interdictionNbr => $interdictionInfo){
            $texte .= "\t\t\t\t<interdiction>\n";
            $texte .= "\t\t\t\t\t<equipe1>" .$interdictionInfo["noms"][0]."</equipe1>\n";
            $texte .= "\t\t\t\t\t<equipe2>" .$interdictionInfo["noms"][1]."</equipe2>\n";
            $texte .= "\t\t\t\t</interdiction>\n";

        }
        $texte .= "\t\t\t</interdictions>\n";



        # repartitions homogènes
        $texte .= "\t\t\t<repartitions_homogenes>\n";
        foreach($infoXml["repartitionsHomogenes"] as $equipeType => $detailsContraintes){
            $texte .= "\t\t\t\t<$equipeType>\n";
            foreach($detailsContraintes["noms"] as $index => $villeNom){
                $texte .= "\t\t\t\t\t<equipe".">" .$villeNom."</equipe".">\n";
            }
            $texte .= "\t\t\t\t</$equipeType>\n";
        }
        $texte .= "\t\t\t</repartitions_homogenes>\n";


        
        $texte .= "\t\t\t<changement_affectation>\n";

        $texte .= "\t\t\t</changement_affectation>\n";
        $texte .= "\t\t</contraintes>\n";
        $texte .= "\t</params>\n";


        # estimation générale

        $texte .= "\t<estimation_generale>\n";
//        $texte .= "\t\t<distance_totale>" .$infoXml["distanceMin"]." Kms</distance_totale>\n";
//        $texte .= "\t\t<cout_voiture>" .round($infoXml["distanceTotale"]*0.8)." €</cout_voiture>\n";
//        $texte .= "\t\t<cout_covoiturage>" .round($infoXml["distanceTotale"]/4*0.8)." €</cout_covoiturage>\n";
//        $texte .= "\t\t<cout_minibus>" .round($infoXml["distanceTotale"]/9*1.31)." €</cout_minibus>\n";
//        $texte .= "\t\t<co2_emission_voiture>" .round($infoXml["distanceTotale"]*0.157)." KG eq CO2</co2_emission_voiture>\n";
//        $texte .= "\t\t<co2_emission_covoiturage>" .round($infoXml["distanceTotale"]/4*0.157)." KG eq CO2</co2_emission_covoiturage>\n";
//        $texte .= "\t\t<co2_emission_minibus>" .round($infoXml["distanceTotale"]/9*0.185)." KG eq CO2</co2_emission_minibus>\n";
        $texte .= "\t</estimation_generale>\n";
//
//
        # estimation détaillée
        $texte .= "\t<estimation_detaille>\n";
//
//        foreach($infoXml["participants"] as $participant){
//
//            $texte .= "\t\t<participant>\n";
//            $texte .= "\t\t\t<nom>".$participant["villeNom"] ."</nom>\n";
//            $texte .= "\t\t\t<distance_parcourue>" .floor($participant["distance"]/1000)." Kms</distance_parcourue>\n";
//            $texte .= "\t\t\t<duree_trajet>" .round($participant["duree"]/3600).":".round($participant["duree"]%3600/60)." (H:M)"." </duree_trajet>\n";
//            $texte .= "\t\t\t<cout_voiture>" .round($participant["distance"]/1000*$participant["nbrParticipants"]*0.8)." €</cout_voiture>\n";
//            $texte .= "\t\t\t<cout_covoiturage>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.8)." €</cout_covoiturage>\n";
//            $texte .= "\t\t\t<cout_minibus>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/9*1.31)." €</cout_minibus>\n";
//            $texte .= "\t\t\t<co2_emission_voiture>" .round($participant["distance"]/1000*$participant["nbrParticipants"]*0.157)." KG eq CO2</co2_emission_voiture>\n";
//            $texte .= "\t\t\t<co2_emission_covoiturage>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.157)." KG eq CO2</co2_emission_covoiturage>\n";
//            $texte .= "\t\t\t<co2_emission_minibus>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/9*0.185)." KG eq CO2</co2_emission_minibus>\n";
//            $texte .= "\t\t</participant>\n";
//
//        }
//
        $texte .= "\t</estimation_detaille>\n";



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
    
    private function getNomScenario($typeScenario){
        $nomScenario = "";


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

        return $nomScenario;
    }



    public function exportScenarioPdfAction()
    {

        $idResultat = $_POST['idResultat'];
        $typeScenario = $_POST['typeScenario'];
        $nomScenario = $this->getNomScenario($typeScenario);

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

        $nomFederation = "FFBB"; # FIXME
        $nomDiscipline ="Basket"; # FIXME

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

    public function testExportPdfAction()
    {

        return $this->render('FfbbBundle:Poules:testExportPdf.html.twig', [
        ]);

        $html = $this->renderView('FfbbBundle:Poules:testExportPdf.html.twig', [
        ]);






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
        $retour["interdictions"] = $interdictions;
        $retour["repartitionsHomogenes"] = $repartitionsHomogenes;

        return $retour;
    }
}