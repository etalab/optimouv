<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class GroupeController extends Controller
{
    public function indexAction()
    {

        return $this->render('FfbbBundle:Groupe:index.html.twig');
    }
}