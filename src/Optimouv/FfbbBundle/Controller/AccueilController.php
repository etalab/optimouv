<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class AccueilController extends Controller
{
    public function indexAction()
    {
         return $this->render('FfbbBundle:Accueil:index.html.twig');
    }

    public function connectAction()
    {
        return $this->render('FfbbBundle:Accueil:connect.html.twig');

    }
}