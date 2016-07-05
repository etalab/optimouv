<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class StatistiqueController extends Controller
{
    public function indexAction()
    {
        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");

        $autorisationChecker = $this->get('security.authorization_checker');
        $em = $this->getDoctrine()->getManager();

        # obtenir l'id de l'utilisateur
        $utilisateur = $this->get("security.token_storage")->getToken()->getUser();
        $utilisateurId = $utilisateur->getId();

        # obtenir l'id de la discipline
        $disciplineId = $this->get("service_statistiques")->getDisciplineId($utilisateurId);

        # obtenir l'id de la fédération
        $federationId = $this->get("service_statistiques")->getFederationId($disciplineId);

        # controler le role de l'utilisateur
        # pour l'admin générale
        if($autorisationChecker->isGranted("ROLE_SUPER_ADMIN")){
            # obtenir la liste de toutes fédérations
            $listeFederations = $this->get("service_statistiques")->getDetailsFederation("tous"); ///
        }
        # pour l'admin fédérale
        else{
            # obtenir la fédération
            $listeFederations = $this->get("service_statistiques")->getDetailsFederation($federationId); ////
        }


        return $this->render('FfbbBundle:Statistique:index.html.twig', array(
            "listeFederations" => $listeFederations
            ));
    }

    public function filtreAction(){

        $donneesStatistiques = $this->get('service_statistiques')->getDonneesStatistiques();

        return new JsonResponse($donneesStatistiques
        );
    }

    public function pretraitementExportAction(){
        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('dmY', time());

        $formatExport = $_POST['formatExport'];

        # obtenir les params envoyé par l'utilisateur
        if(array_key_exists("typeRapport", $_POST)){
            $typeRapport = $_POST["typeRapport"];
        }
        else{
            $typeRapport  = "";
        }
        if(array_key_exists("idFederation", $_POST)){
            $idFederation = $_POST["idFederation"];
        }
        else{
            $idFederation  = -1;
        }
        if(array_key_exists("idDiscipline", $_POST)){
            $idDiscipline = $_POST["idDiscipline"];
        }
        else{
            $idDiscipline  = -1;
        }
        if(array_key_exists("idUtilisateur", $_POST)){
            $idUtilisateur = $_POST["idUtilisateur"];
        }
        else{
            $idUtilisateur  = -1;
        }

        $em = $this->getDoctrine()->getManager();

        # obtenir le nom et prénom de l'utilisateur et le nom de la fédération
        $resultat = $this->get('service_statistiques')->getNomUtilisateurNomFederation($idUtilisateur, $idFederation);
        $nomUtilisateur = $resultat["nomUtilisateur"];
        $prenomUtilisateur = $resultat["prenomUtilisateur"];
        $nomFederation = $resultat["nomFederation"];

        if($idUtilisateur == "tous"){
            $nomUtilisateur = "tous";
            $prenomUtilisateur = "tous";
        }
        # obtenir le nom de discipline et le nom d'utilisateur
        if($idDiscipline == "tous"){
            $nomDiscipline = "tous";
        }
        else{
            $discipline = $em->getRepository('FfbbBundle:Discipline')->findOneBy(array('id'=>$idDiscipline));
            $nomDiscipline = $discipline->getNom();

        }

        // construire le nom de rapport
        $nomRapport = "Rapport_".$typeRapport."_";
        if($typeRapport == "utilisateur"){
            $nomRapport .= $prenomUtilisateur."_".$nomUtilisateur;
        }
        else{
            $nomRapport .= $nomFederation;
        }
        $nomRapport .= "_".$dateTimeNow;


        if($formatExport == "pdf"){
            return $this->exportPdf();
        }
        elseif($formatExport == "csv"){
            $this->exportCsv($typeRapport, $nomRapport);
        }
        elseif($formatExport == "xml"){

            $infoXML = array(
                "typeRapport" => $typeRapport,
                "nomRapport" => $nomRapport,
                "nomFederation" => $nomFederation,
                "nomDiscipline" => $nomDiscipline,
                "nomUtilisateur" => $nomUtilisateur,
            );

            $this->exportXml($infoXML);

        }

    }

    private function exportXml($infoXML){
        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");

        $typeRapport = $infoXML["typeRapport"];
        $nomRapport = $infoXML["nomRapport"];
        $nomFederation = $infoXML["nomFederation"];
        $nomDiscipline = $infoXML["nomDiscipline"];
        $nomUtilisateur = $infoXML["nomUtilisateur"];

        # remplacement pour toutes disciplines et tous utilisateurs
        if($nomDiscipline == "tous"){
            $nomDiscipline = "Toutes disciplines";
        }
        if($nomUtilisateur == "tous"){
            $nomUtilisateur = "Tous utilisateurs";
        }


        if(array_key_exists("dateDebutStr", $_POST)){
            $dateDebutStr = $_POST["dateDebutStr"];
        }
        else{
            $dateDebutStr  = "";
        }
        if(array_key_exists("dateFinStr", $_POST)){
            $dateFinStr = $_POST["dateFinStr"];
        }
        else{
            $dateFinStr  = "";
        }

        // obtenir les données du tableau
        $donneesStatistiques = $this->get('service_statistiques')->getDonneesStatistiques();
        $donneesTableau = $donneesStatistiques["lignesTableau"];
//        error_log("\n donneesTableau: ".print_r($donneesTableau, true), 3, $this->error_log_path);


        $texte = '<?xml version="1.0" encoding="utf-8"?>';
        $texte .= "\n";

        $texte .= "<resultat>\n";
        $texte .= "\t<params>\n";
        $texte .= "\t\t<nom_rapport>$nomRapport</nom_rapport>\n";
        $texte .= "\t\t<nom_federation>$nomFederation</nom_federation>\n";
        $texte .= "\t\t<nom_discipline>$nomDiscipline</nom_discipline>\n";
        $texte .= "\t\t<nom_utilisateur>$nomUtilisateur</nom_utilisateur>\n";
        $texte .= "\t\t<date_debut>$dateDebutStr</date_debut>\n";
        $texte .= "\t\t<date_fin>$dateFinStr</date_fin>\n";
        $texte .= "\t</params>\n";

        $texte .= "\t<donnees_tableau>\n";


        foreach($donneesTableau as $dateCourante => $donneesDateCourante){
            $texte .= "\t\t<ligne_tableau>\n";
            $texte .= "\t\t\t<date_choisie>$dateCourante</date_choisie>\n";

            # nombre de connexions à Optimouv
            if(array_key_exists("nombreConnexions", $donneesDateCourante)){
                $nombreConnexions = $donneesDateCourante["nombreConnexions"];
            }
            else{
                $nombreConnexions  = 0;
            }
            $texte .= "\t\t\t<nombre_connexions_optimouv>".$nombreConnexions."</nombre_connexions_optimouv>\n";

            # nombre de lancements Optimisation de poule
            if(array_key_exists("nombreLancementsOptiPoule", $donneesDateCourante)){
                $nombreLancementsOptiPoule = $donneesDateCourante["nombreLancementsOptiPoule"];
            }
            else{
                $nombreLancementsOptiPoule  = 0;
            }
            $texte .= "\t\t\t<nombre_lancements_optimisation_poules>".$nombreLancementsOptiPoule."</nombre_lancements_optimisation_poules>\n";


            # nombre de lancements meilleur lieu
            if(array_key_exists("nombreLancementsMeilleurLieu", $donneesDateCourante)){
                $nombreLancementsMeilleurLieu = $donneesDateCourante["nombreLancementsMeilleurLieu"];
            }
            else{
                $nombreLancementsMeilleurLieu  = 0;
            }
            $texte .= "\t\t\t<nombre_lancements_meilleur_lieu_rencontre>".$nombreLancementsMeilleurLieu."</nombre_lancements_meilleur_lieu_rencontre>\n";

            if($typeRapport == "utilisateur" || $typeRapport ==  "federation"){
                # nombre d'interdictions
                if(array_key_exists("nombreInterdictions", $donneesDateCourante)){
                    $nombreInterdictions = $donneesDateCourante["nombreInterdictions"];
                }
                else{
                    $nombreInterdictions = 0;
                }
                $texte .= "\t\t\t<nombre_interdictions>".$nombreInterdictions."</nombre_interdictions>\n";

                # nombre de répartitions homogenes
                if(array_key_exists("nombreRepartitionsHomogenes", $donneesDateCourante)){
                    $nombreRepartitionsHomogenes = $donneesDateCourante["nombreRepartitionsHomogenes"];
                }
                else{
                    $nombreRepartitionsHomogenes = 0;
                }
                $texte .= "\t\t\t<nombre_repartitions_homogenes>".$nombreRepartitionsHomogenes."</nombre_repartitions_homogenes>\n";

                # nombre d'exclusion géographiques
                if(array_key_exists("nombreExclusions", $donneesDateCourante)){
                    $nombreExclusions = $donneesDateCourante["nombreExclusions"];
                }
                else{
                    $nombreExclusions = 0;
                }
                $texte .= "\t\t\t<nombre_exclusions_geographiques>".$nombreExclusions."</nombre_exclusions_geographiques>\n";

            }

            # nombre de requetes here
            if(array_key_exists("nombreRequetesHere", $donneesDateCourante)){
                $nombreRequetesHere = $donneesDateCourante["nombreRequetesHere"];
            }
            else{
                $nombreRequetesHere = 0;
            }
            $texte .= "\t\t\t<nombre_requetes_here>".$nombreRequetesHere."</nombre_requetes_here>\n";


            if($typeRapport == "systeme"){
                # temps de réponse pour calcul opti poule
                if(array_key_exists("tempsCalculOptiPoule", $donneesDateCourante)){
                    $tempsCalculOptiPoule = $donneesDateCourante["tempsCalculOptiPoule"];
                }
                else{
                    $tempsCalculOptiPoule = 0;
                }
                $texte .= "\t\t\t<temps_calcul_optimisation_poules>".$tempsCalculOptiPoule."</temps_calcul_optimisation_poules>\n";

                # temps de réponse pour calcul meilleur lieu
                if(array_key_exists("tempsCalculMeilleurLieu", $donneesDateCourante)){
                    $tempsCalculMeilleurLieu = $donneesDateCourante["tempsCalculMeilleurLieu"];
                }
                else{
                    $tempsCalculMeilleurLieu = 0;
                }
                $texte .= "\t\t\t<temps_calcul_meilleur_lieu_rencontre>".$tempsCalculMeilleurLieu."</temps_calcul_meilleur_lieu_rencontre>\n";
            }



            $texte .= "\t\t</ligne_tableau>\n";
        }

        $texte .= "\t</donnees_tableau>\n";
        $texte .= "</resultat>";


        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename="'.$nomRapport.'.xml"');

        echo $texte;
        exit();


    }


    private function exportCsv($typeRapport, $nomRapport){

        $output = fopen("php://output",'w') or die("Can't open php://output");
        header("Content-Type:application/csv");
        header("Content-Disposition:attachment;filename=$nomRapport.csv");

        // créer l'en_tête pour le fichier csv (suivant l'ordre selon le type de rapport)
        $headerArray = array();
        array_push($headerArray, "DATES CHOISIES");
        array_push($headerArray, "NOMBRE DE CONNEXIONS A OPTIMOUV");
        array_push($headerArray, "NOMBRE DE LANCEMENTS DE LA FONCTION OPTIMISATION DES POULES");
        array_push($headerArray, "NOMBRE DE LANCEMENTS DE LA FONCTION MEILLEUR LIEU DE RENCONTRE");

        if($typeRapport == "utilisateur" || $typeRapport == "federation"){
            array_push($headerArray, "NOMBRE D'INTERDICTIONS UTILISEES");
            array_push($headerArray, "NOMBRE DE REPARTITIONS HOMOGENES UTILISEES");
            array_push($headerArray, "NOMBRE D'EXCLUSIONS GEOGRAPHIQUES UTILISEES");
        }
        array_push($headerArray, "NOMBRE DE REQUETES HERE EFFECTUEES (TOUTES FONCTIONS CONFONDUES)");
        if($typeRapport == "systeme"){
            array_push($headerArray, "TEMPS DE REPONSE MOYEN DE LA FONCTION OPTIMISATION DE POULES POUR L'OBTENTION DES RESULTATS (H:M:S)");
            array_push($headerArray, "TEMPS DE REPONSE MOYEN DE LA FONCTION MEILLEUR LIEU DE RENCONTRE POUR L'OBTENTION DES RESULTATS (H:M:S)");
        }
        fputcsv($output, $headerArray);

        // obtenir les données du tableau
        $donneesStatistiques = $this->get('service_statistiques')->getDonneesStatistiques();
        $donneesTableau = $donneesStatistiques["lignesTableau"];
//            error_log("\n donneesTableau: ".print_r($donneesTableau, true), 3, $this->error_log_path);


        // créer le contenu pour le fichier csv'
        foreach($donneesTableau as $dateCourante => $donneesDateCourante){
            $tempArray = array();
            array_push($tempArray, $dateCourante);

            // nombre de connexions (pour tous rapports)
            if(array_key_exists("nombreConnexions", $donneesDateCourante)){
                $nombreConnexions = $donneesDateCourante["nombreConnexions"];
            }
            else{
                $nombreConnexions  = 0;
            }
            array_push($tempArray, $nombreConnexions);


            // nombre de lancements de la fonction optimisation des poules (pour tous rapports)
            if(array_key_exists("nombreLancementsOptiPoule", $donneesDateCourante)){
                $nombreLancementsOptiPoule = $donneesDateCourante["nombreLancementsOptiPoule"];
            }
            else{
                $nombreLancementsOptiPoule  = 0;
            }
            array_push($tempArray, $nombreLancementsOptiPoule);

            // nombre de lancements de la fonction meilleur de lieu (pour tous rapports)
            if(array_key_exists("nombreLancementsMeilleurLieu", $donneesDateCourante)){
                $nombreLancementsMeilleurLieu = $donneesDateCourante["nombreLancementsMeilleurLieu"];
            }
            else{
                $nombreLancementsMeilleurLieu  = 0;
            }
            array_push($tempArray, $nombreLancementsMeilleurLieu);

            if($typeRapport == "utilisateur" || $typeRapport ==  "federation"){
                // nombre d'interdictions (pour rapports utilisateurs et fédérations)
                if(array_key_exists("nombreInterdictions", $donneesDateCourante)){
                    $nombreInterdictions = $donneesDateCourante["nombreInterdictions"];
                }
                else{
                    $nombreInterdictions  = 0;
                }
                array_push($tempArray, $nombreInterdictions);

                // nombre de repartitions homogènes (pour rapports utilisateurs et fédérations)
                if(array_key_exists("nombreRepartitionsHomogenes", $donneesDateCourante)){
                    $nombreRepartitionsHomogenes = $donneesDateCourante["nombreRepartitionsHomogenes"];
                }
                else{
                    $nombreRepartitionsHomogenes  = 0;
                }
                array_push($tempArray, $nombreRepartitionsHomogenes);

                // nombre d'exclusion (pour rapports utilisateurs et fédérations)
                if(array_key_exists("nombreExclusions", $donneesDateCourante)){
                    $nombreExclusions = $donneesDateCourante["nombreExclusions"];
                }
                else{
                    $nombreExclusions  = 0;
                }
                array_push($tempArray, $nombreExclusions);

            }
            // nombre de requetes HERE (pour tous rapports)
            if(array_key_exists("nombreRequetesHere", $donneesDateCourante)){
                $nombreRequetesHere = $donneesDateCourante["nombreRequetesHere"];
            }
            else{
                $nombreRequetesHere = 0;
            }
            array_push($tempArray, $nombreRequetesHere);

            if($typeRapport == "systeme"){
                // nombre d'exclusion (pour rapports utilisateurs et fédérations)
                if(array_key_exists("tempsCalculOptiPoule", $donneesDateCourante)){
                    $tempsCalculOptiPoule = $donneesDateCourante["tempsCalculOptiPoule"];
                }
                else{
                    $tempsCalculOptiPoule = 0;
                }
                array_push($tempArray, $tempsCalculOptiPoule);

                // nombre d'exclusion (pour rapports utilisateurs et fédérations)
                if(array_key_exists("tempsCalculMeilleurLieu", $donneesDateCourante)){
                    $tempsCalculMeilleurLieu = $donneesDateCourante["tempsCalculMeilleurLieu"];
                }
                else{
                    $tempsCalculMeilleurLieu = 0;
                }
                array_push($tempArray, $tempsCalculMeilleurLieu);
            }

            fputcsv($output, $tempArray);
        }

        fclose($output) or die("Can't close php://output");
        exit;


    }


    private function exportPdf()
    {
        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('dmY', time());



        # obtenir les params envoyé par l'utilisateur
        if(array_key_exists("typeRapport", $_POST)){
            $typeRapport = $_POST["typeRapport"];
        }
        else{
            $typeRapport  = "";
        }
        if(array_key_exists("idFederation", $_POST)){
            $idFederation = $_POST["idFederation"];
        }
        else{
            $idFederation  = -1;
        }
        if(array_key_exists("idDiscipline", $_POST)){
            $idDiscipline = $_POST["idDiscipline"];
        }
        else{
            $idDiscipline  = -1;
        }
        if(array_key_exists("idUtilisateur", $_POST)){
            $idUtilisateur = $_POST["idUtilisateur"];
        }
        else{
            $idUtilisateur  = -1;
        }
        if(array_key_exists("dateDebutStr", $_POST)){
            $dateDebutStr = $_POST["dateDebutStr"];
        }
        else{
            $dateDebutStr  = "";
        }
        if(array_key_exists("dateFinStr", $_POST)){
            $dateFinStr = $_POST["dateFinStr"];
        }
        else{
            $dateFinStr  = "";
        }

        $em = $this->getDoctrine()->getManager();

        # obtenir le nom et prénom de l'utilisateur et le nom de la fédération
        $resultat = $this->get('service_statistiques')->getNomUtilisateurNomFederation($idUtilisateur, $idFederation);
        $nomUtilisateur = $resultat["nomUtilisateur"];
        $prenomUtilisateur = $resultat["prenomUtilisateur"];
        $nomFederation = $resultat["nomFederation"];

        # obtenir le nom de discipline et le nom d'utilisateur
        if($idDiscipline == "tous"){
            $nomDiscipline = "tous";
        }
        else{
            $discipline = $em->getRepository('FfbbBundle:Discipline')->findOneBy(array('id'=>$idDiscipline));
            $nomDiscipline = $discipline->getNom();

        }

        if($idUtilisateur == "tous"){
            $nomUtilisateur = "tous";
            $prenomUtilisateur = "tous";
        }


        $donneesStatistiques = $this->get('service_statistiques')->getDonneesStatistiques();


        # construire le nom de la graphique
        $nomGraph = "Rapport_".$typeRapport."_";
        if($typeRapport == "utilisateur"){
            $nomGraph .= $prenomUtilisateur."_".$nomUtilisateur;
        }
        else{
            $nomGraph .= $nomFederation;
        }
        $nomGraph .= "_".$dateTimeNow;


        $tableauOutput = array(
            "donneesStatistiques" => $donneesStatistiques,
            "typeRapport" => $typeRapport,
            "dateDebutStr" => $dateDebutStr,
            "dateFinStr" => $dateFinStr,
            "nomFederation" => $nomFederation,
            "nomDiscipline" => $nomDiscipline,
            "nomUtilisateur" => $nomUtilisateur,
            "prenomUtilisateur" => $prenomUtilisateur,
            "nomGraph" => $nomGraph,

        );

        # Pour Debugging # FIXME
//        return $this->render('FfbbBundle:Statistique:exportPdf.html.twig',
//            $tableauOutput
//        );



        # render la page d'export pdf
        $html = $this->renderView('FfbbBundle:Statistique:exportPdf.html.twig', $tableauOutput
        );
        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="'.$nomGraph.'.pdf"',
                'print-media-type'      => false,
                'outline'               => true,

            )
        );



    }

}
