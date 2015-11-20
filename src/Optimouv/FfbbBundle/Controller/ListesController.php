<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ListesController extends Controller
{
    public function indexAction()
    {

        $listeParticipants = array(
            0 => "liste_equipes1",
            1 => "liste_equipes2"
        );

        $listeLieux = array(
            0 => "liste_terrains1",
            1 => "liste_terrains2"

        );

        $outputListe =  array(
            "listeParticipants" => $listeParticipants,
            "listeLieux" => $listeLieux
        );

        return $this->render('FfbbBundle:Listes:index.html.twig', $outputListe);    }

}
