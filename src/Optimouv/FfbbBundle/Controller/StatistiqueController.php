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
            $listeFederations = $this->get("service_statistiques")->getDetailsFederation("tous");
        }
        # pour l'admin fédérale
        else{
            # obtenir la fédération
            $listeFederations = $this->get("service_statistiques")->getDetailsFederation($federationId);


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

            $output = fopen("php://output",'w') or die("Can't open php://output");
            header("Content-Type:application/csv");
            header("Content-Disposition:attachment;filename=$nomRapport.csv");


            // créer l'en_tête pour le fichier csv (suivant l'ordre selon le type de rapport)
            $headerArray = array();
            array_push($headerArray, "DATES CHOISIES");
            array_push($headerArray, "NOMBRE DE CONNEXIONS A OPTIMOUV");
            array_push($headerArray, "NOMBRE DE LANCEMENTS DE LA FONCTION OPTIMISATION DES POULES");
            array_push($headerArray, "NOMBRE DE LANCEMENTS DE LA FONCTION MEILLEUR LIEU DE RENCONTRE");

            if($typeRapport == "utilisateur" || "federation"){
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
            error_log("\n donneesTableau: ".print_r($donneesTableau, true), 3, $this->error_log_path);


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

                if($typeRapport == "utilisateur" || "federation"){

                }


                fputcsv($output, $tempArray);
            }

            fclose($output) or die("Can't close php://output");
            exit;
        }
        elseif($formatExport == "xml"){

        }

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
