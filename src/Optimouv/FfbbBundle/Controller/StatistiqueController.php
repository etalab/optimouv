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

//        error_log("\n listeFederations: ".print_r($listeFederations, true), 3, $this->error_log_path);


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

//        return $this->render('FfbbBundle:Statistique:exportPdf.html.twig', [
//        ]);


        $html = $this->renderView('FfbbBundle:Statistique:exportPdf.html.twig', [
        ]);


        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="graph.pdf"',
                'print-media-type'      => false,
                'outline'               => true,

            )
        );



    }

}
