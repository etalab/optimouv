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
        $idListeLieux = null;
        if(!empty($_POST["idListeLieux"])){
            $idListeLieux = $_POST["idListeLieux"];

        }

        $idGroupe = $this->get('service_rencontres')->creerGroupe($villes, $nomGroupe, $idListeParticipants, $idListeLieux);

        $coordonneesVille = $this->get('service_rencontres')->index($idGroupe);

        $coordonneesVille = array_merge($coordonneesVille[0], $coordonneesVille[1]);

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
        $coordonneesVille = array_merge($coordonneesVille[0], $coordonneesVille[1]);


        $nomsVilles = $this->get('service_rencontres')->nomsVilles($idGroupe);


        return $this->render('FfbbBundle:Default:index.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
            'idGroupe' => $idGroupe,
        ));
    }

}
