<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class RapportsController extends Controller
{
    public function indexAction()
    {
        return $this->render('FfbbBundle:Rapports:index.html.twig');
    }
}