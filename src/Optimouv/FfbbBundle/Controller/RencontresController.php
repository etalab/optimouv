<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use ZipArchive;

 class RencontresController extends Controller
 {

     public function indexAction($idRapport)
     {

         # obtenir entity manager
         $em = $this->getDoctrine()->getManager();

         /////////////////////////////////
         /************Optimal********/
         ///////////////////////////////


         $participants = [];


         $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
         $idGroupe = $idGroupe[0]['idGroupe'];

         //récupération du nom du rapport

         $nomRapport = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();

         //récupération des détails de calculs
         $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);

         $retour = json_decode($retour[0]["detailsCalcul"], true);

         $retourOp = $retour[0];
         $retourEq = $retour[1];

         //Données du scénario optimal
         $villeDepart = $retourOp[0];
         $longPtDep = $retourOp[1];
         $latPtDep = $retourOp[2];
         $distanceMin = $retourOp[3];
         $dureeTrajet = $retourOp[4];
         $coordonneesVille = $retourOp[5];
         $terrainsNeutres = $retourOp[9];
         $nbrParticipantsTotal = $retourOp["nbrParticipantsTotal"];
         $distanceTotale = $retourOp["distanceTotale"];

         foreach ($retourOp[6] as $key => $value) {

             $participants[] = array('ville' => $value, 'distance' => $retourOp[7][$key], 'duree' => $retourOp[8][$key], 'nbrParticipants' => $retourOp[10][$key]);
         }

         /////////////////////////////////
         /************Equitable********/
         ///////////////////////////////


         $villeDepartEq = $retourEq[0];
         $longPtDepEq = $retourEq[1];
         $latPtDepEq = $retourEq[2];
         $distanceMinEq = $retourEq[3];
         $dureeTrajetEq = $retourEq[4];
         $coordonneesVilleEq = $retourEq[5];
         $distanceTotaleEq = $retourEq["distanceTotale"];

         foreach ($retourEq[6] as $key => $value) {

             $participantsEq[] = array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key], 'nbrParticipants' => $retourEq[9][$key]);
         }

         # récupérer idListe pour le breadcrump
         $idListe = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
         $nomListe = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
         $nomGroupe = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();


         # obtenir l'id du résultat
         $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);


         if($idResultat != []){
             $idResultat = $idResultat[0]["id"];
         }

         $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
         $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
         $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

         $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
         $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
         $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();


         return $this->render('FfbbBundle:Rencontres:index.html.twig', array(

             //Données du scénario optimal
             'villeDepart' => $villeDepart,
             'longPtDep' => $longPtDep,
             'latPtDep' => $latPtDep,
             'distanceMin' => $distanceMin,
             'dureeTrajet' => $dureeTrajet,
             'coordonneesVille' => $coordonneesVille,
             'participants' => $participants,
             'nbrParticipantsTotal' => $nbrParticipantsTotal,
             'distanceTotale' => $distanceTotale,

             //données scénario équitable
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
             'nomRapport' => $nomRapport,
             'idResultat' => $idResultat,

             'coutVoiture' => $coutVoiture,
             'coutCovoiturage' => $coutCovoiturage,
             'coutMinibus' => $coutMinibus,
             'gesVoiture' => $gesVoiture,
             'gesCovoiturage' => $gesCovoiturage,
             'gesMinibus' => $gesMinibus

         ));
     }

     public function pretraitementExportAction()
     {
         $formatExport = $_POST['formatExport'];
         $idResultat = $_POST['idResultat'];
         $typeScenario = $_POST['typeScenario'];
         $nomScenario = $this->getNomScenario($typeScenario, 1);
         $nomScenarioSansAccent = $this->getNomScenario($typeScenario, 0);
         $typeRencontre = $_POST['typeRencontre'];
         $nomRencontre = $this->get('service_rencontres')->getNomRencontre($typeRencontre);

//          error_log("\n nomRencontre: ".print_r($nomRencontre , true), 3, "error_log_optimouv.txt");
//         error_log("\n nomScenario: ".print_r($nomScenario , true), 3, "error_log_optimouv.txt");


         //recuperation des donnees relatives au scenario
         $infoResultat = $this->getInfoResultat($idResultat, $typeRencontre, $typeScenario) ;

         $nomRapport = $infoResultat["nomRapport"];
         $nomUtilisateur = $infoResultat["nomUtilisateur"];
         $nomListe = $infoResultat["nomListe"];
         $nomGroupe = $infoResultat["nomGroupe"];
         $distanceTotale = $infoResultat["distanceTotale"];
         $distanceMin = $infoResultat["distanceMin"];
         $nbrParticipantsTotal = $infoResultat["nbrParticipantsTotal"];
         $participants = $infoResultat["participants"];
         $boolTropVilles = $infoResultat["boolTropVilles"];


         $nomFederation = "FFBB"; # FIXME
         $nomDiscipline ="Basket"; # FIXME


         $villeDepart = $infoResultat["villeDepart"];

         if($formatExport == "pdf"){

             $coordonneesVille = $infoResultat["coordonneesVille"];
             $coordPointDepart = $infoResultat["coordPointDepart"];


             return $this->render('FfbbBundle:Rencontres:previsualisationPdf.html.twig', array(
                 'idResultat' => $idResultat,
                 'typeScenario' => $typeScenario,
                 'nomScenario' => $nomScenario,
                 'nomUtilisateur' => $nomUtilisateur,
                 'nomListe' => $nomListe,
                 'nomGroupe' => $nomGroupe,
                 'nomRapport' => $nomRapport,
                 'typeRencontre' => $typeRencontre,
                 'nomRencontre' => $nomRencontre,
                 'distanceTotale' => $distanceTotale,
                 'distanceMin' => $distanceMin,
                 'nbrParticipantsTotal' => $nbrParticipantsTotal,
                 'villeDepart' => $villeDepart,
                 'participants' => $participants,
                 'coordonneesVille' => $coordonneesVille,
                 'coordPointDepart' => $coordPointDepart,
                 'boolTropVilles' => $boolTropVilles,
                 'nomFederation' => $nomFederation,
                 'nomDiscipline' => $nomDiscipline,

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
                 "nomRencontre" => $nomRencontre,
                 'villeDepart' => $villeDepart,
                 'distanceMin' => $distanceMin,
                 'distanceTotale' => $distanceTotale,
                 'participants' => $participants,
             );

             $texte = $this->getTexteExportXml($infoXML);

             echo $texte;

//             error_log("\n text: ".print_r($texte , true), 3, "error_log_optimouv.txt");
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
                 "nomRencontre" => $nomRencontre,
                 'villeDepart' => $villeDepart,
                 'distanceMin' => $distanceMin,
                 'distanceTotale' => $distanceTotale,
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
     }

     private function remplirCsvEnZip($infoCsv, $zip){
         // estimation générale
         $headerEstimationGenerale = array("KILOMETRES A PARCOURIR POUR LE SCENARIO",
             "COUT POUR LE SCENARIO EN VOITURE",
             "COUT POUR LE SCENARIO EN COVOITURAGE",
             "COUT POUR LE SCENARIO EN MINIBUS",
             "EMISSIONS TOTALES DE GES EN VOITURE",
             "EMISSIONS TOTALES DE GES EN COVOITURAGE",
             "EMISSIONS TOTALES DE GES EN MINIBUS"
         );
         $coutVoiture = round($infoCsv["distanceTotale"] * 0.8);
         $coutCovoiturage = round($infoCsv["distanceTotale"]/4 * 0.8);
         $coutMinibus = round($infoCsv["distanceTotale"]/9 * 1.31);
         $emissionVoiture = round($infoCsv["distanceTotale"] * 0.157);
         $emissionCovoiturage = round($infoCsv["distanceTotale"]/4 * 0.157);
         $emissionMinibus = round($infoCsv["distanceTotale"]/9 * 0.185);
         $contenuEstimationGenerale = array($infoCsv["distanceMin"],
             $coutVoiture, $coutCovoiturage, $coutMinibus,
             $emissionVoiture, $emissionCovoiturage, $emissionMinibus
         );


         // estimation détaillé
         $headerEstimationDetaille = array( "PARTICIPANTS",
             "KILOMETRES A PARCOURIR",
             "TEMPS DE PARCOURS",
             "COUT DU PARCOURS EN VOITURE",
             "COUT DU PARCOURS EN COVOITURAGE",
             "COUT DU PARCOURS EN MINIBUS",
             "EMISSIONS GES EN VOITURE",
             "EMISSIONS GES EN COVOITURAGE",
             "EMISSIONS GES EN MINIBUS"
         );


         // index=0 pour estimation générale
         // index=1 pour estimation détaillée
         for ($i = 0; $i < 2; $i++) {

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

                 foreach($infoCsv["participants"] as $participant){

                     $contenuEstimationDetaille = array($participant["villeNom"],
                         floor($participant["distance"]/1000),
                         round($participant["duree"]/3600).":".round($participant["duree"]%3600/60),
                         round($participant["distance"]/1000*$participant["nbrParticipants"]*0.8),
                         round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.8),
                         round($participant["distance"]/1000*$participant["nbrParticipants"]/9*1.31),
                         round($participant["distance"]/1000*$participant["nbrParticipants"]*0.157),
                         round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.157),
                         round($participant["distance"]/1000*$participant["nbrParticipants"]/9*0.185)
                     );

                     fputcsv($fd, $contenuEstimationDetaille);
                 }



                 // retourner au début du stream
                 rewind($fd);
                 // ajouter le fichier qui est en mémoire à l'archive, donner un nom
                 $nomFichierEncoder = $infoCsv["nomRapport"]."-".$infoCsv["nomScenarioSansAccent"]."-details.csv";
             }

            // ajouter les fichiers csv en fichier zip
             $zip->addFromString($nomFichierEncoder , stream_get_contents($fd) );


             // fermer le fichier
             fclose($fd);
         }

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
            elseif($typeScenario == "equitable"){
                $nomScenario = "scénario équitable";
            }
            elseif($typeScenario == "optimal"){
                $nomScenario = "scénario optimal";
            }

        }
        elseif($boolAccent == 0){
             if($typeScenario == "optimalSansContrainte"){
                 $nomScenario = "scenario optimal sans contrainte";
             }
             elseif($typeScenario == "optimalAvecContrainte"){
                 $nomScenario = "scenario optimal avec contrainte";
             }
             elseif($typeScenario == "equitable"){
                 $nomScenario = "scenario equitable";
             }
             elseif($typeScenario == "optimal"){
                 $nomScenario = "scenario optimal";
             }

         }

         return $nomScenario;
     }

    private function getTexteExportXml($infoXml){
        $texte = '<?xml version="1.0" encoding="utf-8"?>';

        $texte .= "\n";
        $texte .= "<resultat>\n";

        # parametres
        $texte .= "\t<params>\n";
        $texte .= "\t\t<nom_rapport>" .$infoXml["nomRapport"]."</nom_rapport>\n";
        $texte .= "\t\t<nom_rencontre>" .$infoXml["nomRencontre"]."</nom_rencontre>\n";
        $texte .= "\t\t<nom_scenario>" .$infoXml["nomScenario"]."</nom_scenario>\n";
        $texte .= "\t\t<nom_federation>" .$infoXml["nomFederation"]."</nom_federation>\n";
        $texte .= "\t\t<nom_discipline>" .$infoXml["nomDiscipline"]."</nom_discipline>\n";
        $texte .= "\t\t<nom_utilisateur>" .$infoXml["nomUtilisateur"]."</nom_utilisateur>\n";
        $texte .= "\t\t<nom_groupe>" .$infoXml["nomGroupe"]."</nom_groupe>\n";
        $texte .= "\t</params>\n";


        # estimation générale
        $villeDepart = explode("|", $infoXml["villeDepart"]) ;
        $nomVilleDepart = trim($villeDepart[1]);
        $codePostalVilleDepart = trim($villeDepart[0]);

        $texte .= "\t<estimation_generale>\n";
        $texte .= "\t\t<meilleu_lieu_rencontre_nom>" .$nomVilleDepart."</meilleu_lieu_rencontre_nom>\n";
        $texte .= "\t\t<meilleu_lieu_rencontre_code_postal>" .$codePostalVilleDepart."</meilleu_lieu_rencontre_code_postal>\n";
        $texte .= "\t\t<distance_totale>" .$infoXml["distanceMin"]." Kms</distance_totale>\n";
        $texte .= "\t\t<cout_voiture>" .round($infoXml["distanceTotale"]*0.8)." €</cout_voiture>\n";
        $texte .= "\t\t<cout_covoiturage>" .round($infoXml["distanceTotale"]/4*0.8)." €</cout_covoiturage>\n";
        $texte .= "\t\t<cout_minibus>" .round($infoXml["distanceTotale"]/9*1.31)." €</cout_minibus>\n";
        $texte .= "\t\t<co2_emission_voiture>" .round($infoXml["distanceTotale"]*0.157)." KG eq CO2</co2_emission_voiture>\n";
        $texte .= "\t\t<co2_emission_covoiturage>" .round($infoXml["distanceTotale"]/4*0.157)." KG eq CO2</co2_emission_covoiturage>\n";
        $texte .= "\t\t<co2_emission_minibus>" .round($infoXml["distanceTotale"]/9*0.185)." KG eq CO2</co2_emission_minibus>\n";
        $texte .= "\t</estimation_generale>\n";


        # estimation détaillée
        $texte .= "\t<details>\n";

        foreach($infoXml["participants"] as $participant){

//            error_log("\n participant: ".print_r($participant , true), 3, "error_log_optimouv.txt");

            $texte .= "\t\t<participant>\n";
            $texte .= "\t\t\t<nom>".$participant["villeNom"] ."</nom>\n";
            $texte .= "\t\t\t<distance_parcourue>" .floor($participant["distance"]/1000)." Kms</distance_parcourue>\n";
            $texte .= "\t\t\t<duree_trajet>" .round($participant["duree"]/3600).":".round($participant["duree"]%3600/60)." (H:M)"." </duree_trajet>\n";
            $texte .= "\t\t\t<cout_voiture>" .round($participant["distance"]/1000*$participant["nbrParticipants"]*0.8)." €</cout_voiture>\n";
            $texte .= "\t\t\t<cout_covoiturage>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.8)." €</cout_covoiturage>\n";
            $texte .= "\t\t\t<cout_minibus>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/9*1.31)." €</cout_minibus>\n";
            $texte .= "\t\t\t<co2_emission_voiture>" .round($participant["distance"]/1000*$participant["nbrParticipants"]*0.157)." KG eq CO2</co2_emission_voiture>\n";
            $texte .= "\t\t\t<co2_emission_covoiturage>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/4*0.157)." KG eq CO2</co2_emission_covoiturage>\n";
            $texte .= "\t\t\t<co2_emission_minibus>" .round($participant["distance"]/1000*$participant["nbrParticipants"]/9*0.185)." KG eq CO2</co2_emission_minibus>\n";
            $texte .= "\t\t</participant>\n";

        }

        $texte .= "\t</details>\n";



        $texte .= "</resultat>";

        return $texte;
    }

     public function exportScenarioPdfAction()
     {

         $idResultat = $_POST['idResultat'];
         $typeScenario = $_POST['typeScenario'];
         $nomScenario = $this->getNomScenario($typeScenario, 1);
         $typeRencontre = $_POST['typeRencontre'];
         $nomRencontre = $this->get('service_rencontres')->getNomRencontre($typeRencontre);

         //recuperation des donnees relatives au scenario
         $infoResultat = $this->getInfoResultat($idResultat, $typeRencontre, $typeScenario);

         $nomRapport = $infoResultat["nomRapport"];
         $nomUtilisateur = $infoResultat["nomUtilisateur"];
         $nomListe = $infoResultat["nomListe"];
         $nomGroupe = $infoResultat["nomGroupe"];
         $distanceTotale = $infoResultat["distanceTotale"];
         $distanceMin = $infoResultat["distanceMin"];
         $nbrParticipantsTotal = $infoResultat["nbrParticipantsTotal"];
         $villeDepart = $infoResultat["villeDepart"];
         $participants = $infoResultat["participants"];
         $coordonneesVille = $infoResultat["coordonneesVille"];
         $coordPointDepart = $infoResultat["coordPointDepart"];

         $nomFederation = "FFBB"; # FIXME
         $nomDiscipline ="Basket"; # FIXME
         
         $html = $this->renderView('FfbbBundle:Rencontres:exportPdf.html.twig', array(
             'idResultat' => $idResultat,
             'typeScenario' => $typeScenario,
             'nomScenario' => $nomScenario,
             'nomUtilisateur' => $nomUtilisateur,
             'nomListe' => $nomListe,
             'nomGroupe' => $nomGroupe,
             'nomRapport' => $nomRapport,
             'typeRencontre' => $typeRencontre,
             'nomRencontre' => $nomRencontre,
             'distanceTotale' => $distanceTotale,
             'distanceMin' => $distanceMin,
             'nbrParticipantsTotal' => $nbrParticipantsTotal,
             'villeDepart' => $villeDepart,
             'participants' => $participants,
             'coordonneesVille' => $coordonneesVille,
             'coordPointDepart' => $coordPointDepart,
             'nomFederation' => $nomFederation,
             'nomDiscipline' => $nomDiscipline,

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

     //    function qui ramene toutes les infos necessaires à la view
     private function getInfoResultat($idResultat , $typeRencontre, $typeScenario)
     {

         $em = $this->getDoctrine()->getManager();

         $idRapport = $em->getRepository('FfbbBundle:Scenario')->getIdRapportByIdScenario($idResultat);
         if($idRapport != []){
             $idRapport  = $idRapport[0]["idRapport"];
         }
         # obtenir le nom du rapport
         $nomRapport = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();


         $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);
         if($idGroupe != []){
             $idGroupe = $idGroupe[0]['idGroupe'];
         }
         # obtenir le nom du groupe
         $nomGroupe = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();


         # obtenir l'id de la liste
         $idListe = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
         # obtenir le nom de la liste
         $nomListe = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();



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

         if($typeRencontre == "barycentre"){
             $villeDepart = $detailsCalcul[0];
             $distanceMin = $detailsCalcul[3];
             $coordonneesVille = $detailsCalcul[5];
             $distanceTotale = $detailsCalcul[10];
             $nbrParticipantsTotal = $detailsCalcul["nbrParticipantsTotal"];

             $coordPointDepart = $detailsCalcul[2]."%2C".$detailsCalcul[1];

             foreach($detailsCalcul[6] as $key => $value ){
                 $arrayTmp = array('ville' => $value, 'distance' => $detailsCalcul[7][$key], 'duree' => $detailsCalcul[8][$key], 'nbrParticipants' => $detailsCalcul[9][$key]);
                 $arrayTmp["villeNom"] = substr($value, 8);
                 $participants[] = $arrayTmp;
             }


         }
         elseif ($typeRencontre == "barycentreAvecExlcusion" && $typeScenario == "optimalSansContrainte"){

             $retourOpSc = $detailsCalcul[0];

             $villeDepart = $retourOpSc[0];
             $distanceMin = $retourOpSc[3];
             $coordonneesVille = $retourOpSc[5];
             $distanceTotale = $retourOpSc[10];
             $nbrParticipantsTotal = $retourOpSc["nbrParticipantsTotal"];

             $coordPointDepart = $retourOpSc[2]."%2C".$retourOpSc[1];

             foreach($retourOpSc[6] as $key => $value ){
                 $arrayTmp = array('ville' => $value, 'distance' => $retourOpSc[7][$key], 'duree' => $retourOpSc[8][$key], 'nbrParticipants' => $retourOpSc[9][$key] );
                 $arrayTmp["villeNom"] = substr($value, 8);
                 $participants[] = $arrayTmp;
             }


         }
         elseif ($typeRencontre == "barycentreAvecExlcusion" && $typeScenario == "optimalAvecContrainte"){
             $retourOpAc = $detailsCalcul[1];

             $villeDepart = $retourOpAc[0];
             $distanceMin = $retourOpAc[3];
             $coordonneesVille = $retourOpAc[5];
             $distanceTotale = $retourOpAc[10];
             $nbrParticipantsTotal = $retourOpAc["nbrParticipantsTotal"];

             $coordPointDepart = $retourOpAc[2]."%2C".$retourOpAc[1];

             foreach($retourOpAc[6] as $key => $value ){
                 $arrayTmp =  array('ville' => $value, 'distance' => $retourOpAc[7][$key], 'duree' => $retourOpAc[8][$key], 'nbrParticipants' => $retourOpAc[9][$key]);
                 $arrayTmp["villeNom"] = substr($value, 8);
                 $participants[] = $arrayTmp;

             }

         }
         elseif ($typeRencontre == "meilleurLieu" && $typeScenario == "optimal"){
             $retourOp = $detailsCalcul[0];


             $villeDepart = $retourOp[0];
             $distanceMin = $retourOp[3];
             $coordonneesVille = $retourOp[5];
             $distanceTotale = $retourOp["distanceTotale"];
             $nbrParticipantsTotal = $retourOp["nbrParticipantsTotal"];

             $coordPointDepart = $retourOp[1]."%2C".$retourOp[2];


             foreach($retourOp[6] as $key => $value ){
                 $arrayTmp =  array('ville' => $value, 'distance' => $retourOp[7][$key], 'duree' => $retourOp[8][$key], 'nbrParticipants' => $retourOp[10][$key]);
                 $arrayTmp["villeNom"] = substr($value, 8);
                 $participants[] = $arrayTmp;

             }


         }
         elseif ($typeRencontre == "meilleurLieu" && $typeScenario == "equitable"){
             $retourEq = $detailsCalcul[1];



             $villeDepart = $retourEq[0];
             $distanceMin = $retourEq[3];
             $coordonneesVille = $retourEq[5];
             $distanceTotale = $retourEq["distanceTotale"];



             $nbrParticipantsTotal =  0;
             foreach($retourEq["nbrParticipants"] as $nbrParticipantEquipe ){
                 $nbrParticipantsTotal += $nbrParticipantEquipe;
             }

             $coordPointDepart = $retourEq[1]."%2C".$retourEq[2];



             foreach($retourEq[6] as $key => $value ){
                 $arrayTmp =  array('ville' => $value, 'distance' => $retourEq[7][$key], 'duree' => $retourEq[8][$key], 'nbrParticipants' => $retourEq[9][$key]);
                 $arrayTmp["villeNom"] = substr($value, 8);
                 $participants[] = $arrayTmp;

             }
             
         }

         elseif ($typeRencontre == "terrainNeutre" && $typeScenario == "optimal"){
             $retourOp = $detailsCalcul[0];


             $villeDepart = $retourOp[0];
             $distanceMin = $retourOp[3];
             $coordonneesVille = $retourOp[5];
             $distanceTotale = $retourOp["distanceTotale"];
             $nbrParticipantsTotal = $retourOp["nbrParticipantsTotal"];

             $coordPointDepart = $retourOp[1]."%2C".$retourOp[2];


             foreach($retourOp[6] as $key => $value ){
                 $arrayTmp =  array('ville' => $value, 'distance' => $retourOp[7][$key], 'duree' => $retourOp[8][$key], 'nbrParticipants' => $retourOp[10][$key]);
                 $arrayTmp["villeNom"] = substr($value, 8);
                 $participants[] = $arrayTmp;

             }


         }
         elseif ($typeRencontre == "terrainNeutre" && $typeScenario == "equitable"){
             $retourEq = $detailsCalcul[1];


             $villeDepart = $retourEq[0];
             $distanceMin = $retourEq[3];
             $coordonneesVille = $retourEq[5];
             $distanceTotale = $retourEq["distanceTotale"];



             $nbrParticipantsTotal =  0;
             foreach($retourEq["nbrParticipants"] as $nbrParticipantEquipe ){
                 $nbrParticipantsTotal += $nbrParticipantEquipe;
             }

             $coordPointDepart = $retourEq[1]."%2C".$retourEq[2];



             foreach($retourEq[6] as $key => $value ){
                 $arrayTmp =  array('ville' => $value, 'distance' => $retourEq[7][$key], 'duree' => $retourEq[8][$key], 'nbrParticipants' => $retourEq[9][$key]);
                 $arrayTmp["villeNom"] = substr($value, 8);
                 $participants[] = $arrayTmp;

             }



         }


         # controler le nombre de villes
         # s'il y a plus de 100 villes (la limite HERE ), on prend juste 99 villes
         $boolTropVilles = 0; # indiquer si on dépasse 100 villes
        if(count($coordonneesVille) >= 100){
            $coordonneesVille = array_slice($coordonneesVille, 0, 99);
            $boolTropVilles = 1;
        }

//           error_log("\n coordonneesVille: ".print_r($coordonneesVille , true), 3, "error_log_optimouv.txt");
//             exit();


         # trier le tableau basé sur le nom de ville
         $this->get('service_rencontres')->sksort($participants, "villeNom", true);



         //construire le tableau de retour
         $retour = [];
         $retour["nomRapport"] = $nomRapport;
         $retour["nomGroupe"] = $nomGroupe;
         $retour["nomListe"] = $nomListe;
         $retour["detailsVilles"] = $detailsVilles;
         $retour["idGroupe"] = $idGroupe;
         $retour["idRapport"] = $idRapport;
         $retour["nomUtilisateur"] = $nomUtilisateur;

         $retour["distanceTotale"] = $distanceTotale;
         $retour["distanceMin"] = $distanceMin;
         $retour["nbrParticipantsTotal"] = $nbrParticipantsTotal;
         $retour["villeDepart"] = $villeDepart;
         $retour["participants"] = $participants;
         $retour["coordonneesVille"] = $coordonneesVille;
         $retour["coordPointDepart"] = $coordPointDepart;
         $retour["boolTropVilles"] = $boolTropVilles;

         return $retour;
     }


    public function barycentreAction($idRapport)
    {


        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        //récupération du nom du rapport

        $nomRapport = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();

        $participants = [];


        $idGroupe = $em->getRepository('FfbbBundle:Rapport')->getIdGroupe($idRapport);

        $retour = $em->getRepository('FfbbBundle:Scenario')->getDetailsCalcul($idRapport);

        $retour = $retour[0]["detailsCalcul"];
        $retour = json_decode($retour, true);




        //Données du scénario optimal
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



        # obtenir l'id du résultat
        $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);
//        error_log("\n idResultat: ".print_r($idResultat , true), 3, "error_log_optimouv.txt");


        if($idResultat != []){
            $idResultat = $idResultat[0]["id"];
        }

        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

        return $this->render('FfbbBundle:Rencontres:barycentre.html.twig', array(

            //Données du scénario optimal
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
            'nomRapport' => $nomRapport,
            'idResultat' => $idResultat,
            'coutVoiture' => $coutVoiture,
            'coutCovoiturage' => $coutCovoiturage,
            'coutMinibus' => $coutMinibus,
            'gesVoiture' => $gesVoiture,
            'gesCovoiturage' => $gesCovoiturage,
            'gesMinibus' => $gesMinibus


        ));


    }

    public function exclusionAction($idRapport)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        //récupération du nom du rapport

        $nomRapport = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();

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


        # obtenir l'id du résultat
        $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);
        if($idResultat != []){
            $idResultat = $idResultat[0]["id"];
        }


        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();


        return $this->render('FfbbBundle:Rencontres:exclusion.html.twig', array(
            //Données du scénario avec contrainte
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,
            'nbrParticipantsTotal' => $nbrParticipantsTotal,
            'distanceTotale' => $distanceTotale,

            //données scénario sans contrainte
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
            'nomRapport' => $nomRapport,
            'idResultat' => $idResultat,

            'coutVoiture' => $coutVoiture,
            'coutCovoiturage' => $coutCovoiturage,
            'coutMinibus' => $coutMinibus,
            'gesVoiture' => $gesVoiture,
            'gesCovoiturage' => $gesCovoiturage,
            'gesMinibus' => $gesMinibus

        ));


    }

    public function terrainNeutreAction($idRapport){


        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        //récupération du nom du rapport

        $nomRapport = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();

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

        # obtenir l'id du résultat
        $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);
        if($idResultat != []){
            $idResultat = $idResultat[0]["id"];
        }

        $coutVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(1)->getValeur();
        $coutCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(2)->getValeur();
        $coutMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(3)->getValeur();

        $gesVoiture = $em->getRepository('FfbbBundle:Reference')->findOneById(4)->getValeur();
        $gesCovoiturage = $em->getRepository('FfbbBundle:Reference')->findOneById(5)->getValeur();
        $gesMinibus = $em->getRepository('FfbbBundle:Reference')->findOneById(6)->getValeur();

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
            'nomRapport' => $nomRapport,
            'idResultat' => $idResultat,

            'coutVoiture' => $coutVoiture,
            'coutCovoiturage' => $coutCovoiturage,
            'coutMinibus' => $coutMinibus,
            'gesVoiture' => $gesVoiture,
            'gesCovoiturage' => $gesCovoiturage,
            'gesMinibus' => $gesMinibus
        ));

    }

    public function detailsCalculAction()
    {

        return $this->render('FfbbBundle:Rencontres:detailsCalcul.html.twig');
    }

}