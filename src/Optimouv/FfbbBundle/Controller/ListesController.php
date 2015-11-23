<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ListesController extends Controller
{
    public function indexAction()
    {
        $myfile = fopen("/tmp/ListeController_indexAction.log", "w") or die("Unable to open file!");


        # obtenir entity manager
        $em = $this->getDoctrine()->getEntityManager();

        # obtenir listes des lieux de rencontres
        $listesLieux = $em->getRepository('FfbbBundle:ListeLieux')->getListes();

        fwrite($myfile, "listesLieux: ".print_r($listesLieux, true));

        $listesParticipants = array(
            0 => "liste_equipes1",
            1 => "liste_equipes2"
        );

        $outputArray =  array(
            "listesParticipants" => $listesParticipants,
            "listesLieux" => $listesLieux
        );
        fwrite($myfile, "outputArray: ".print_r($outputArray, true));


        return $this->render('FfbbBundle:Listes:index.html.twig', $outputArray);
        fclose($myfile);
    }

}
