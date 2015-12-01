<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {


        $villes = $_POST["duallistbox_demo1"];
        $nomGroupe = $_POST["nomGroupe"];
        $idListeParticipants = $_POST["idListeParticipants"];



//        $this->get('service_rencontres')->geocoderVilles($villes);
        $idGroupe = $this->get('service_rencontres')->creerGroupe($villes, $nomGroupe, $idListeParticipants);

        $coordonneesVille = $this->get('service_rencontres')->index($idGroupe);


        $nomsVilles = $this->get('service_rencontres')->nomsVilles($idGroupe);


         return $this->render('FfbbBundle:Default:index.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
             'idGroupe' => $idGroupe,
        ));
    }

    public function groupeAction($idGroupe)
    {

        $coordonneesVille = $this->get('service_rencontres')->index($idGroupe);


        $nomsVilles = $this->get('service_rencontres')->nomsVilles($idGroupe);


        return $this->render('FfbbBundle:Default:index.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
            'idGroupe' => $idGroupe,
        ));
    }

}
