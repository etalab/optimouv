<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class StatistiqueController extends Controller
{
    public function indexAction()
    {


        return $this->render('FfbbBundle:Statistique:index.html.twig', array(
            ));
    }

}
