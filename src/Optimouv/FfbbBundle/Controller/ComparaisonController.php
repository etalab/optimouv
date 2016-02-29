<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class ComparaisonController extends Controller
{
    public function indexAction($idRapport)
    {

        $scenario = $_POST["scenario"];

        if($scenario == 'meilleurLieu'){

            $participants = $this->scenarioMeilleurLieu($idRapport);
        }
        else{
            $participants = $this->scenarioTerrainNeutre($idRapport);
        }

         return $this->render('FfbbBundle:Rencontres:comparaisonScenario.html.twig', array(
             'participants' => $participants,


        ));
    }


    public function scenarioMeilleurLieu($idRapport)
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
        $idGroupe = $idGroupe[0]['idGroupe'];

        $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);


        $retour = json_decode($retour[0]["detailsCalcul"], true);

        $retourOp = $retour[0];
        $retourEq = $retour[1];



        //scénario optimal

        //récupération info pt depart
        $villeDepart = $retourOp[0];
        $villeDepart = substr($villeDepart, 8);
//        $nbrParticipantsTotal = $retourOp["nbrParticipantsTotal"];

        $infoPtDepart [] = array('ville' => $villeDepart, 'distance' => 0, 'duree' => 0, 'nbrParticipants' => 0);
        $infoPtDepart = array_shift($infoPtDepart);

        $nbrParticipant = 0;
        //construire tableau de retour
        foreach($retourOp[6] as $key => $value ){

            $value = substr($value, 8);
            $participants[]= array('ville' => $value, 'distance' => $retourOp[7][$key], 'duree' => $retourOp[8][$key], 'nbrParticipants' => $retourOp['nbrParticipants'][$key]);
        }



        /*******************************************************************/


        //scénario équitable

        //récupération info pt depart
        $villeDepartEq = $retourEq[0];
        $villeDepartEq = substr($villeDepartEq, 8);
        $infoPtDepartEq [] = array('villeEq' => $villeDepartEq, 'distanceEq' => 0, 'dureeEq' => 0 , 'nbrParticipantsEq' => 0);
        $infoPtDepartEq = array_shift($infoPtDepartEq);


        foreach($retourEq[6] as $key => $value ){

            $value = substr($value, 8);
            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key], 'nbrParticipantsEq' => $retourEq['nbrParticipants'][$key]);
        }
        array_push($participantsEq, $infoPtDepartEq);
        sort($participantsEq);


        /*******************************************************************/

        //récupérer nombre de pariticipants pour le pt de départ
        for($i = 0; $i < count($participantsEq); $i++){
            if (strstr($infoPtDepart['ville'], $participantsEq[$i]['villeEq'])) {

                $infoPtDepart['nbrParticipants'] = $participantsEq[$i]['nbrParticipantsEq'];
            }

        }
        // rajouter les infos du pt depart aux restes des pts de participants;
        array_push($participants, $infoPtDepart);

        sort($participants);



        $length = count($participants);

        for($i = 0; $i < $length; $i++){
            $distanceEq = $participantsEq[$i]["distanceEq"];
            $dureeEq = $participantsEq[$i]["dureeEq"];
            $participants[$i]["distanceEq"] = $distanceEq;
            $participants[$i]["dureeEq"] = $dureeEq;

        }

        return $participants;

    }
    public function scenarioTerrainNeutre($idRapport)
    {


        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
        $idGroupe = $idGroupe[0]['idGroupe'];

        $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);


        $retour = json_decode($retour[0]["detailsCalcul"], true);

        $retourOp = $retour[0];
        $retourEq = $retour[1];



        //scénario optimal

        //construire tableau de retour
        foreach($retourOp[6] as $key => $value ){

            $value = substr($value, 8);
            $participants[]= array('ville' => $value, 'distance' => $retourOp[7][$key], 'duree' => $retourOp[8][$key], 'nbrParticipants' => $retourOp['nbrParticipants'][$key]);
        }

        /*******************************************************************/


        //scénario équitable


        foreach($retourEq[6] as $key => $value ){

            $value = substr($value, 8);
            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key], 'nbrParticipantsEq' => $retourEq['nbrParticipants'][$key]);
        }


        $length = count($participants);

        for($i = 0; $i < $length; $i++){
            $distanceEq = $participantsEq[$i]["distanceEq"];
            $dureeEq = $participantsEq[$i]["dureeEq"];
            $participants[$i]["distanceEq"] = $distanceEq;
            $participants[$i]["dureeEq"] = $dureeEq;

        }


//        echo '<pre>',print_r($participants,1),'</pre>';exit;


        return $participants;
    }


}
