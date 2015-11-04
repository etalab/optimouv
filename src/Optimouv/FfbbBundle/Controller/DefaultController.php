<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {


        $villes = $_POST["duallistbox_demo1"];

//        echo '<pre>',print_r($villes,1),'</pre>';
//         exit;


        $this->get('service_rencontres')->geocoderVilles($villes);
        $nomsVilles = $this->get('service_rencontres')->nomsVilles($villes);

        $coordonneesVille = $this->get('service_rencontres')->index();

        return $this->render('FfbbBundle:Default:index.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
        ));
    }


}
