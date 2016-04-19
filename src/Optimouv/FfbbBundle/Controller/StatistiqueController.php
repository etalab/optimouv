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

    public function exportPdfAction()
    {
        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('dmY', time());


        $em = $this->getDoctrine()->getManager();

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

//        error_log("\n typeRapport: ".print_r($typeRapport, true), 3, $this->error_log_path);

        $donneesStatistiques = $this->get('service_statistiques')->getDonneesStatistiques();

        $tableauOutput = array(
            "donneesStatistiques" => $donneesStatistiques,
            "typeRapport" => $typeRapport,
            "dateDebutStr" => $dateDebutStr,
            "dateFinStr" => $dateFinStr,
            "nomFederation" => $nomFederation,
            "nomDiscipline" => $nomDiscipline,
            "nomUtilisateur" => $nomUtilisateur,
            "prenomUtilisateur" => $prenomUtilisateur,


        );

        return $this->render('FfbbBundle:Statistique:exportPdf.html.twig',
            $tableauOutput
        );



        # construire le nom de la graphique
        $nomGraph = "Rapport_".$typeRapport."_";
        if($typeRapport == "utilisateur"){
            $nomGraph .= $prenomUtilisateur."_".$nomUtilisateur;
        }
        else{
            $nomGraph .= $nomFederation;
        }
        $nomGraph .= "_".$dateTimeNow;

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
