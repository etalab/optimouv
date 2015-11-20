<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ListesController extends Controller
{
    public function indexAction()
    {
        return $this->render('FfbbBundle:Listes:index.html.twig', array(
                // ...
            ));    }

}
