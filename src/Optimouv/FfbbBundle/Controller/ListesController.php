<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class ListesController extends Controller
{
    public function indexAction()
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir listes des participants
        $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes();

        # obtenir listes des lieux de rencontres
        $listesLieux = $em->getRepository('FfbbBundle:ListeLieux')->getListes();

        return $this->render('FfbbBundle:Listes:index.html.twig', array(
            "listesParticipants" => $listesParticipants,
            "listesLieux" => $listesLieux
        ));

    }

    public function creerListeParticipantsAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites();

        error_log("\n Controller: Listes, Function: indexAction, datetime: ".$dateTimeNow
            ."\n statutUpload: ".print_r($statutUpload, true), 3, "/tmp/optimouv.log");

        if($statutUpload["success"]){
            # créer des entités dans la table entite
            $retourEntites = $this->get('service_listes')->creerEntites();
            $idsEntite = $retourEntites["idsEntite"];

            # créer une liste dans la table liste_participants
            $retourListe = $this->get('service_listes')->creerListeParticipants($idsEntite);

            # obtenir entity manager
            $em = $this->getDoctrine()->getManager();

            # obtenir listes des participants
            $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes();

//            return new JsonResponse($listesParticipants);
            return new JsonResponse(array(
                "success" => true,
                "msg" => "Upload réussi",
                "data" => $listesParticipants
            ));

        }
        else{

        }


    }

    public function creerListeLieuxAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites();

        error_log("\n Controller: Listes, Function: indexAction, datetime: ".$dateTimeNow
            ."\n statutUpload: ".print_r($statutUpload, true), 3, "/var/log/apache2/optimouv.log");

        # créer des entités dans la table entite
        $retourEntites = $this->get('service_listes')->creerEntites();
        $idsEntite = $retourEntites["idsEntite"];

        # créer une liste dans la table liste_participants
        $retourListe = $this->get('service_listes')->creerListeLieux($idsEntite);

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir listes des participants
        $listesLieux = $em->getRepository('FfbbBundle:ListeLieux')->getListes();

        return new JsonResponse($listesLieux);
    }

}
