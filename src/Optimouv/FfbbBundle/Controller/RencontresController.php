<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class RencontresController extends Controller
{
    public function indexAction($idGroupe)
    {

        $retour = $this->get('service_rencontres')->meilleurLieuRencontre($idGroupe);
        $retourEq = $this->get('service_rencontres')->scenarioEquitable($idGroupe);




        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $terrainsNeutres = $retour[9];
        $nbrParticipantsTotal = $retour["nbrParticipantsTotal"];
        $distanceTotale = $retour["distanceTotale"];

        foreach($retour[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key], 'nbrParticipants' => $retour[10][$key]);
        }

        //Donn�es du sc�nario �quitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceMinEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        $distanceTotaleEq = $retourEq["distanceTotale"];

        foreach($retourEq[6] as $key => $value ){

            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key], 'nbrParticipants' => $retourEq[9][$key]);
        }

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        return $this->render('FfbbBundle:Rencontres:index.html.twig', array(

            //Donn�es du sc�nario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,
            'nbrParticipantsTotal' => $nbrParticipantsTotal,
            'distanceTotale' => $distanceTotale,

            //donn�es sc�nario �quitable
            'villeDepartEq' => $villeDepartEq,
            'longPtDepEq' => $longPtDepEq,
            'latPtDepEq' => $latPtDepEq,
            'distanceMinEq' => $distanceMinEq,
            'dureeTrajetEq' => $dureeTrajetEq,
            'coordonneesVilleEq' => $coordonneesVilleEq,
            'participantsEq' => $participantsEq,
            'idGroupe' => $idGroupe,
            'terrainsNeutres' => $terrainsNeutres,
            'distanceTotaleEq' => $distanceTotaleEq,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,

        ));
    }

    public function barycentreAction($idGroupe)
    {

        $participants = [];
        $retour = $this->get('service_rencontres')->Barycentre($idGroupe);

        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $nbrParticipantsTotal = $retour["nbrParticipantsTotal"];
        $distanceTotale = $retour[10];

        foreach($retour[6] as $key => $value ){
            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key], 'nbrParticipants' => $retour[9][$key]);
        }

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        # créer un rapport barycentre
        $idRapport = $this->get('service_rencontres')->creerRapport($idGroupe, "barycentre", -1);

        # créer un scénario barycentre
        if($idRapport != -1){
            $this->get('service_rencontres')->creerScenario($idRapport, "optimal",  $distanceMin, $dureeTrajet);
        }




        return $this->render('FfbbBundle:Rencontres:barycentre.html.twig', array(

            //Donn�es du sc�nario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,
            'idGroupe' => $idGroupe,
            'nbrParticipantsTotal' => $nbrParticipantsTotal,
            'distanceTotale' => $distanceTotale,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,

        ));


    }

    public function exclusionAction($idGroupe)
    {

        //R�cup�ration de la valeur saisie par l'utilisateur
        $valeurExclusion = $_POST["valeurExclusion"];

        //R�cup�ration du r�sultat du calcul avec contrainte
        $retour = $this->get('service_rencontres')->Exclusion($valeurExclusion, $idGroupe);



        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $nbrParticipantsTotal = $retour["nbrParticipantsTotal"];
        $distanceTotale = $retour[10];

        foreach($retour[6] as $key => $value ){
            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key], 'nbrParticipants' => $retour[9][$key]);
        }


        //R�cup�ration du r�sultat du calcul sans contrainte
        $retourEq = $this->get('service_rencontres')->Barycentre($idGroupe);

        //Donn�es du sc�nario �quitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceMinEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        $distanceTotaleEq = $retourEq[10];

        foreach($retourEq[6] as $key => $value ){

            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key], 'nbrParticipants' => $retourEq[9][$key] );
        }


        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        # créer un rapport exclusion
        $idRapport = $this->get('service_rencontres')->creerRapport($idGroupe, "exclusion", $valeurExclusion);

        # créer un scénario barycentre
        if($idRapport != -1){
            $this->get('service_rencontres')->creerScenario($idRapport, "optimal",  $distanceMin, $dureeTrajet);
            $this->get('service_rencontres')->creerScenario($idRapport, "equitable",  $distanceMinEq, $dureeTrajetEq);
        }

        return $this->render('FfbbBundle:Rencontres:exclusion.html.twig', array(
            //Donn�es du sc�nario avec contrainte
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,
            'nbrParticipantsTotal' => $nbrParticipantsTotal,
            'distanceTotale' => $distanceTotale,

            //donn�es sc�nario sans contrainte
            'villeDepartEq' => $villeDepartEq,
            'longPtDepEq' => $longPtDepEq,
            'latPtDepEq' => $latPtDepEq,
            'distanceMinEq' => $distanceMinEq,
            'dureeTrajetEq' => $dureeTrajetEq,
            'coordonneesVilleEq' => $coordonneesVilleEq,
            'participantsEq' => $participantsEq,
            'valeurExclusion' => $valeurExclusion,
            'idGroupe' => $idGroupe,
            'distanceTotaleEq' => $distanceTotaleEq,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,



        ));


    }

    public function terrainNeutreAction($idGroupe){

        //R�cup�ration du r�sultat du calcul du terrain neutre
        $retour = $this->get('service_rencontres')->terrainNeutre($idGroupe);
        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $nbrParticipantsTotal = $retour["nbrParticipantsTotal"];
        $distanceTotale = $retour["distanceTotale"];

        foreach($retour[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key], 'nbrParticipants' => $retour[10][$key]);
        }

        //R�cup�ration du r�sultat du calcul du terrain neutre Equitable
        $retourEq = $this->get('service_rencontres')->terrainNeutreEquitable($idGroupe);

        //Donn�es du sc�nario �quitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceMinEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        $listeTerrain = $retour[9];
        $distanceTotaleEq = $retourEq["distanceTotale"];


        foreach($retourEq[6] as $key => $value ){

            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key], 'nbrParticipants' => $retourEq[9][$key]);
        }

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        return $this->render('FfbbBundle:Rencontres:terrainNeutre.html.twig', array(

            //Donn�es du sc�nario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,
            'listeTerrain' => $listeTerrain,
            'nbrParticipantsTotal' => $nbrParticipantsTotal,
            'distanceTotale' => $distanceTotale,


            //donn�es sc�nario sans contrainte
            'villeDepartEq' => $villeDepartEq,
            'longPtDepEq' => $longPtDepEq,
            'latPtDepEq' => $latPtDepEq,
            'distanceMinEq' => $distanceMinEq,
            'dureeTrajetEq' => $dureeTrajetEq,
            'coordonneesVilleEq' => $coordonneesVilleEq,
            'participantsEq' => $participantsEq,
            'idGroupe' => $idGroupe,
            'distanceTotaleEq' => $distanceTotaleEq,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,


        ));

    }
    public function detailsCalculAction()
    {

        return $this->render('FfbbBundle:Rencontres:detailsCalcul.html.twig');
    }
}