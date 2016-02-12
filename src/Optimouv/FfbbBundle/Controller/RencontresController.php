<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Slik\DompdfBundle\Wrapper\DompdfWrapper;
class RencontresController extends Controller
{

    public function indexAction($idRapport)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        /////////////////////////////////
        /************Optimal********/
        ///////////////////////////////

//        $retour = $this->get('service_rencontres')->meilleurLieuRencontre($idGroupe);

        $participants = [];

//        $typeAction = "meilleurLieu";
//        $idTache = $this->get('service_rencontres')->Producer($idGroupe, $typeAction);
//
//        $this->get('old_sound_rabbit_mq.rencontre_producer')->publish($idTache);
//
//        do {
//            sleep(5);
//            $statutTache = $em->getRepository('FfbbBundle:Rapport')->getStatut($idTache);
//
//        } while ($statutTache == 2);


        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
        $idGroupe = $idGroupe[0]['idGroupe'];

        $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);


        $retour = json_decode($retour[0]["detailsCalcul"], true);

        $retourOp = $retour[0];

        $retourEq = $retour[1];

        //Donn�es du sc�nario optimal
        $villeDepart = $retourOp[0];
        $longPtDep = $retourOp[1];
        $latPtDep = $retourOp[2];
        $distanceMin = $retourOp[3];
        $dureeTrajet = $retourOp[4];
        $coordonneesVille = $retourOp[5];
        $terrainsNeutres = $retourOp[9];
        $nbrParticipantsTotal = $retourOp["nbrParticipantsTotal"];
        $distanceTotale = $retourOp["distanceTotale"];

        foreach($retourOp[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retourOp[7][$key], 'duree' => $retourOp[8][$key], 'nbrParticipants' => $retourOp[10][$key]);
        }

        /////////////////////////////////
        /************Equitable********/
        ///////////////////////////////

//
//
//        //Donn�es du sc�nario �quitable
//        //$retourEq = $this->get('service_rencontres')->scenarioEquitable($idGroupe);
//
//        $typeAction = "meilleurLieuEq";
//        $idTache = $this->get('service_rencontres')->Producer($idGroupe, $typeAction);
//
//        $this->get('old_sound_rabbit_mq.rencontre_producer')->publish($idTache);
//
//        do {
//            sleep(2);
//            $statutTache = $em->getRepository('FfbbBundle:Rapport')->getStatut($idTache);
//
//        } while ($statutTache == 2);
//
//
//        $retourEq = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idTache);
//
//        $retourEq = $retourEq[0]["detailsCalcul"];
//        $retourEq = json_decode($retourEq, true);

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

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

//
//        # créer un rapport exclusion
//        $idRapport = $this->get('service_rencontres')->creerRapport($idGroupe, "meilleurLieu", -1);
//
//        # créer un scénario barycentre
//        if($idRapport != -1){
//            $this->get('service_rencontres')->creerScenario($idRapport, "optimal",  $distanceMin, $dureeTrajet);
//            $this->get('service_rencontres')->creerScenario($idRapport, "equitable",  $distanceMinEq, $dureeTrajetEq);
//        }

        //envoie de mail de notification pour la fin des calculs
//        $this->sendMailAction();

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
            'idRapport' => $idRapport,

        ));
    }

    public function barycentreAction($idRapport)
    {


        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();


        $participants = [];


        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);

        $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);

        $retour = $retour[0]["detailsCalcul"];
        $retour = json_decode($retour, true);


//        $retour = $this->get('service_rencontres')->Barycentre($idGroupe);


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


        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->getIdListe($idGroupe);

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();

        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->getNomGroupe($idGroupe);


        //convert idGroupe to int

        $idGroupe = $idGroupe[0]['idGroupe'];


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
            'idRapport' => $idRapport,

        ));


    }

    public function exclusionAction($idRapport)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");



        //R�cup�ration du r�sultat du calcul avec contrainte
       // $retour = $this->get('service_rencontres')->Exclusion($valeurExclusion, $idGroupe);
        $participants = [];


        $infoExclusion = $em->getRepository('FfbbBundle:Rapport')->getInfosExclusion($idRapport);
        $idGroupe = $infoExclusion[0]['idGroupe'];
        $valeurExclusion = $infoExclusion[0]['params'];

        $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);

        $retour = json_decode($retour[0]["detailsCalcul"], true);

        $retourBarycentre = $retour[0];
        $retourExclusion = $retour[1];



        //Donn�es du sc�nario optimal
        $villeDepart = $retourExclusion[0];
        $longPtDep = $retourExclusion[1];
        $latPtDep = $retourExclusion[2];
        $distanceMin = $retourExclusion[3];
        $dureeTrajet = $retourExclusion[4];
        $coordonneesVille = $retourExclusion[5];
        $nbrParticipantsTotal = $retourExclusion["nbrParticipantsTotal"];
        $distanceTotale = $retourExclusion[10];

        foreach($retourExclusion[6] as $key => $value ){
            $participants[]= array('ville' => $value, 'distance' => $retourExclusion[7][$key], 'duree' => $retourExclusion[8][$key], 'nbrParticipants' => $retourExclusion[9][$key]);
        }

        /////////////////////////////////
        /************Barycentre********/
        ///////////////////////////////



        //R�cup�ration du r�sultat du calcul sans contrainte

        //Donn�es du sc�nario �quitable

        $villeDepartEq = $retourBarycentre[0];
        $longPtDepEq = $retourBarycentre[1];
        $latPtDepEq = $retourBarycentre[2];
        $distanceMinEq = $retourBarycentre[3];
        $dureeTrajetEq = $retourBarycentre[4];
        $coordonneesVilleEq = $retourBarycentre[5];
        $distanceTotaleEq = $retourBarycentre[10];

        foreach($retourBarycentre[6] as $key => $value ){

            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourBarycentre[7][$key], 'dureeEq' => $retourBarycentre[8][$key], 'nbrParticipants' => $retourBarycentre[9][$key] );
        }

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();

        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

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
            'idRapport' => $idRapport,

        ));


    }

    public function terrainNeutreAction($idRapport){


        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();
        /////////////////////////////////
        /************Optimal********/
        ///////////////////////////////


        $participants = [];


        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
        $idGroupe = $idGroupe[0]['idGroupe'];

        $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);


        $retour = json_decode($retour[0]["detailsCalcul"], true);

        $retourOp = $retour[0];

        $retourEq = $retour[1];


        //R�cup�ration du r�sultat du calcul du terrain neutre
        //$retour = $this->get('service_rencontres')->terrainNeutre($idGroupe);

        //Donn�es du sc�nario optimal
        $villeDepart = $retourOp[0];
        $longPtDep = $retourOp[1];
        $latPtDep = $retourOp[2];
        $distanceMin = $retourOp[3];
        $dureeTrajet = $retourOp[4];
        $coordonneesVille = $retourOp[5];
        $listeTerrain = $retourOp[9];
        $nbrParticipantsTotal = $retourOp["nbrParticipantsTotal"];
        $distanceTotale = $retourOp["distanceTotale"];

        foreach($retourOp[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retourOp[7][$key], 'duree' => $retourOp[8][$key], 'nbrParticipants' => $retourOp[10][$key]);
        }


        /////////////////////////////////
        /************Equitable********/
        ///////////////////////////////



        //Donn�es du sc�nario �quitable

//        $typeAction = "terrainNeutreEq";
//        $idTache = $this->get('service_rencontres')->Producer($idGroupe, $typeAction);
//
//        $this->get('old_sound_rabbit_mq.rencontre_producer')->publish($idTache);
//
//        do {
//            sleep(2);
//            $statutTache = $em->getRepository('FfbbBundle:Rapport')->getStatut($idTache);
//
//        } while ($statutTache == 2);
//
//
//        $retourEq = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idTache);
//
//        $retourEq = $retourEq[0]["detailsCalcul"];
//        $retourEq = json_decode($retourEq, true);


        //R�cup�ration du r�sultat du calcul du terrain neutre Equitable
       // $retourEq = $this->get('service_rencontres')->terrainNeutreEquitable($idGroupe);

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
            'idRapport' => $idRapport,


        ));

    }

    public function detailsCalculAction()
    {

        return $this->render('FfbbBundle:Rencontres:detailsCalcul.html.twig');
    }
    public function previsualisationPdfAction($pdf)
    {

        $pdf = json_decode($pdf, true);



        $html = $this->renderView('FfbbBundle:Poules:previsualisationPdf.html');

//        $html = "Hello Ouss";
        $dompdf = $this->get('slik_dompdf');

        // Generate the pdf
        $dompdf->getpdf($html);

        // Either stream the pdf to the browser
        $dompdf->stream("myfile.pdf");

        // Or get the output to handle it yourself
         $dompdf->output();

    }
}