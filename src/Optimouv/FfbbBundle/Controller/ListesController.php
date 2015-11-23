<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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


        $outputArray =  array(
            "listesParticipants" => $listesParticipants,
            "listesLieux" => $listesLieux
        );

        return $this->render('FfbbBundle:Listes:index.html.twig', $outputArray);

    }

    public function creerListeParticipantsAction()
    {
        $myfile = fopen("/tmp/ListesController_creerListeParticipantsAction.log", "w") or die("Unable to open file!");
//        fwrite($myfile, "outputArray: ".print_r($outputArray, true));



//        return $this->render('FfbbBundle:Fichier:upload.html.twig', array(
//                // ...
//            ));

        fclose($myfile);
    }

    public function creerListeLieuxAction()
    {
        $myfile = fopen("/tmp/ListesController_creerListeLieuxAction.log", "w") or die("Unable to open file!");
//        fwrite($myfile, "outputArray: ".print_r($outputArray, true));



//        return $this->render('FfbbBundle:Fichier:upload.html.twig', array(
//                // ...
//            ));

        fclose($myfile);

    }



}
