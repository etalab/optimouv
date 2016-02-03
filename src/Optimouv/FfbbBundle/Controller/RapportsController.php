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

        # récupérer l'id du rapport (résultat)
        # BUG sur Symphony quand on récupère le statut directement en utilisant le repository du rapport
        for($i=0; $i<count($infoRapports); $i++){
            $idResultat = $infoRapports[$i]["id"];
            $statut  = $this->get('service_poules')->getStatut($idResultat);
            # ajouter le statut dans les infos retournées sur le front
            $infoRapports[$i]["statut"] = $statut[0]["statut"];

        }


//        error_log("\n Controller: Listes, Function: indexAction, datetime: ".$dateTimeNow
//            ."\n infoRapports: ".print_r($infoRapports, true), 3, $this->error_log_path );

        return $this->render('FfbbBundle:Rapports:index.html.twig', [
            "infoRapports" => $infoRapports
        ]);
    }

    public function consulterRapportAction($idRapport)
    {

        $typeAction = $_POST["typeAction"];

        if($typeAction == "barycentre"){

            return $this->redirect($this->generateUrl('ffbb_barycentre', array('idRapport' => $idRapport)));
        }
        elseif($typeAction == "exclusion"){

            return $this->redirect($this->generateUrl('ffbb_exclusion', array('idRapport' => $idRapport)));

        }
        elseif($typeAction == "meilleurLieu"){

            return $this->redirect($this->generateUrl('ffbb_rencontres', array('idRapport' => $idRapport)));
        }

        elseif($typeAction == "terrainNeutre"){

            return $this->redirect($this->generateUrl('ffbb_terrain', array('idRapport' => $idRapport)));
        }
        else{

            die('type d\'action non reconnu! ~rapport controller');
        }


    }
}