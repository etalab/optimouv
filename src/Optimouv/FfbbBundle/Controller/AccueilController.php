<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class AccueilController extends Controller
{
    public function indexAction()
    {
        if (!$this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('FfbbBundle:Accueil:index.html.twig');
        }
        else{
            return $this->redirectToRoute('ffbb_accueil_connect');
        }

    }

    public function connectAction()
    {
        return $this->render('FfbbBundle:Accueil:connect.html.twig');

    }
    public function mentionsLegalesAction(){

        return $this->render('FfbbBundle:Accueil:mentions.html.twig');
    }
}