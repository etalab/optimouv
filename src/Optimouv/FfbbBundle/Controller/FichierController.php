<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class FichierController extends Controller
{
    public function uploadAction()
    {
        $myfile = fopen("/tmp/FichierController_uploadAction.log", "w") or die("Unable to open file!");
//        fwrite($myfile, "outputArray: ".print_r($outputArray, true));
        fclose($myfile);



        return $this->render('FfbbBundle:Fichier:upload.html.twig', array(
                // ...
            ));    }

}
