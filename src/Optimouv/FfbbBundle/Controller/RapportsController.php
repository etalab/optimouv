<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class RapportsController extends Controller
{
    public function indexAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer tous les groupes appartenant à l'utilisateur courant
        $groupesTemp = $em->getRepository('FfbbBundle:Groupe')->getGroupesParIdUtilisateur($idUtilisateur);
        $idGroupes = [];
        for($i=0; $i<count($groupesTemp); $i++){
            array_push($idGroupes, $groupesTemp[$i]["id"]);
        }

        # récupérer tous les rapports de tous les groupes
        $infoRapports = $em->getRepository('FfbbBundle:Rapport')->getRapportsParIdGroupe($idGroupes);

//        error_log("\n Controller: Listes, Function: indexAction, datetime: ".$dateTimeNow
//            ."\n infoRapports: ".print_r($infoRapports, true), 3, $this->error_log_path);

        return $this->render('FfbbBundle:Rapports:index.html.twig', [
            "infoRapports" => $infoRapports
        ]);
    }
}