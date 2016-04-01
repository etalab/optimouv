<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ComparaisonController extends Controller
{
    public function indexAction($idRapport)
    {

        $scenario = $_POST["scenario"];

        $em = $this->getDoctrine()->getManager();
        $typeAction = $em->getRepository('FfbbBundle:Rapport')->getTypeAction($idRapport);
        $typeAction = $typeAction[0]['typeAction'];


        if($scenario == 'meilleurLieu'){
            $participants = $this->scenarioMeilleurLieu($idRapport);
        }
        else{
            $participants = $this->scenarioTerrainNeutre($idRapport);
        }


        // obtenir l'id du résultat
        $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);
        if($idResultat != []) $idResultat = $idResultat[0]["id"];

//        error_log("\n typeAction: ".print_r($typeAction , true), 3, "error_log_optimouv.txt");


        return $this->render('FfbbBundle:Rencontres:comparaisonScenario.html.twig', array(
             'participants' => $participants,
             'typeAction' => $typeAction,
             'idResultat' => $idResultat,
            'idRapport' => $idRapport,



        ));
    }

    public function exportAction()
    {
        $idRapport = $_POST['idRapport'];
        $typeRencontre = $_POST['typeRencontre'];


        if($typeRencontre == 'meilleurLieu'){
            $participants = $this->scenarioMeilleurLieu($idRapport);
        }
        else{
            $participants = $this->scenarioTerrainNeutre($idRapport);
        }

        // trier le tableau basé sur le nom de ville
        $this->get('service_rencontres')->sksort($participants, "ville", true);

        $em = $this->getDoctrine()->getManager();

        # obtenir le nom du rapport
        $nomRapport = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();

//        error_log("\n participants: ".print_r($participants , true), 3, "error_log_optimouv.txt");

        
        // créer le fichier zip
        $zipNom = "$nomRapport-comparaison_scenario.zip";
        $zip = new ZipArchive;
        $zip->open($zipNom, ZipArchive::CREATE);


        $infoCsv = array(
            "nomRapport" => $nomRapport,
            "typeRencontre" => $typeRencontre,
            'participants' => $participants,
        );


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

    private function remplirCsvEnZip($infoCsv, $zip){

        // le cas du barycentre avec exclusion
        if($infoCsv["typeRencontre"] == "exclusion"){
            // distance et temps du parcours
            $headerDistanceParcours = array("PARTICIPANTS",
                "KMS A PARCOURIR EN VOITURE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "KMS A PARCOURIR EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL SANS CONTRAINTE"
            );


            // cout du parcours
            $headerCoutParcours = array( "PARTICIPANTS",
                "COUT EN VOITURE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE"
            );


            // émission de GES
            $headerCoutEmission= array( "PARTICIPANTS",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL SANS CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL AVEC CONTRAINTE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL SANS CONTRAINTE"
            );



        }
        // le cas du meilleur lieu et terrain neutre
        elseif (($infoCsv["typeRencontre"] == "meilleurLieu" || $infoCsv["typeRencontre"] == "terrainNeutre")){
            // distance et temps du parcours
            $headerDistanceParcours = array("PARTICIPANTS",
                "KMS A PARCOURIR EN VOITURE - SCENARIO OPTIMAL",
                "KMS A PARCOURIR EN VOITURE - SCENARIO EQUITABLE",
                "TEMPS DE PARCOURS - SCENARIO OPTIMAL",
                "TEMPS DE PARCOURS - SCENARIO EQUITABLE",
            );

            $headerCoutParcours = array( "PARTICIPANTS",
                "COUT EN VOITURE - SCENARIO OPTIMAL",
                "COUT EN VOITURE - SCENARIO EQUITABLE",
                "COUT EN COVOITURAGE - SCENARIO OPTIMAL",
                "COUT EN COVOITURAGE - SCENARIO EQUITABLE",
                "COUT EN MINIBUS - SCENARIO OPTIMAL",
                "COUT EN MINIBUS - SCENARIO EQUITABLE",
            );

            $headerCoutEmission= array( "PARTICIPANTS",
                "EMISSIONS GES EN VOITURE - SCENARIO OPTIMAL",
                "EMISSIONS GES EN VOITURE - SCENARIO EQUITABLE",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO OPTIMAL",
                "EMISSIONS GES EN COVOITURAGE - SCENARIO EQUITABLE",
                "EMISSIONS GES EN MINIBUS - SCENARIO OPTIMAL",
                "EMISSIONS GES EN MINIBUS - SCENARIO EQUITABLE"
            );

        }






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


                foreach($infoCsv["participants"] as $participant){

                    $dureeFormater = $this->formatterHeureMinute($participant["duree"]);
                    $dureeFormaterEq = $this->formatterHeureMinute($participant["duree"]);

                    $contenuDistanceParcours = array($participant["ville"],
                        round($participant["distance"]/1000),
                        round($participant["distanceEq"]/1000),
                        ($dureeFormater["nbrHeure"].":".$dureeFormater["nbrMin"]),
                        ($dureeFormaterEq["nbrHeure"].":".$dureeFormaterEq["nbrMin"]),
                    );

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

                foreach($infoCsv["participants"] as $participant){

                    $contenuCoutParcours = array($participant["ville"],
                        round($participant["distance"]/1000*$participant["nbrParticipants"]*0.8),
                        round($participant["distanceEq"]/1000*$participant["nbrParticipants"]*0.8),
                        round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.8),
                        round($participant["distanceEq"]/1000*$participant["nbrParticipants"]/4*0.8),
                        round($participant["distance"]/1000*$participant["nbrParticipants"]/9*1.31),
                        round($participant["distanceEq"]/1000*$participant["nbrParticipants"]/9*1.31),
                    );

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

                foreach($infoCsv["participants"] as $participant){

                    $contenuCoutEmission = array($participant["ville"],
                        round($participant["distance"]/1000*$participant["nbrParticipants"]*0.157),
                        round($participant["distanceEq"]/1000*$participant["nbrParticipants"]*0.157),
                        round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.157),
                        round($participant["distanceEq"]/1000*$participant["nbrParticipants"]/4*0.157),
                        round($participant["distance"]/1000*$participant["nbrParticipants"]/9*0.185),
                        round($participant["distanceEq"]/1000*$participant["nbrParticipants"]/9*0.185),
                    );

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

    private function formatterHeureMinute($duree){
        $nbrHeure = round($duree/3600);
        if($nbrHeure <10) $nbrHeure = "0$nbrHeure";

        $nbrMin = round(($duree%3600)/60);
        if($nbrMin <10 ) $nbrMin = "0$nbrMin";

        return array("nbrHeure"=> $nbrHeure, "nbrMin"=>$nbrMin);
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
