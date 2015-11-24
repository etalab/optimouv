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

        # obtenir listes des lieux de rencontres
        $listesLieux = $em->getRepository('FfbbBundle:ListeLieux')->getListes();

        # obtenir listes des participants
        $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes();

        $outputTableau =  array(
            "listesParticipants" => $listesParticipants,
            "listesLieux" => $listesLieux
        );

        return $this->render('FfbbBundle:Listes:index.html.twig', $outputTableau);

    }

    public function creerListeParticipantsAction()
    {
        $myfile = fopen("/tmp/ListesController_creerListeParticipantsAction.log", "w") or die("Unable to open file!");

        # créer des entités dans la table entite
        $retour = $this->get('service_listes')->creerEntites();

        fwrite($myfile, "retour: ".print_r($retour, true));

        # créer une liste dans la table liste_participants
        $retourListe = $this->get('service_listes')->creerListe();

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir listes des participants
        $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes();


        return new JsonResponse($listesParticipants);

    }

    public function creerListeLieuxAction()
    {
        $myfile = fopen("/tmp/ListesController_creerListeLieuxAction.log", "w") or die("Unable to open file!");
//        fwrite($myfile, "outputArray: ".print_r($outputArray, true));



        return new Response();


    }



}
