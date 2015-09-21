<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class RapportsController extends Controller
{
    public function indexAction()
    {
//       $x = $this->get('service_rencontres')->myFunction();
        return $this->render('FfbbBundle:Rapports:index.html.twig');
    }
}