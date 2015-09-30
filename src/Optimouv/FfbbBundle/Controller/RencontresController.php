<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class RencontresController extends Controller
{
    public function indexAction()
    {

        $retour = $this->get('service_rencontres')->meilleurLieuRencontre();

        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $mesVilles = $retour[6];
        $distVille = $retour[7];
        $dureeVille = $retour[8];


        return $this->render('FfbbBundle:Rencontres:index.html.twig', array(
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'mesVilles' => $mesVilles,
            'distVille' => $distVille,
            'dureeVille' => $dureeVille,

        ));
    }

    public function barycentreAction()
    {


        $retour = $this->get('service_rencontres')->Barycentre();
        print_r($retour);
        exit;

        $villes = $retour [0];
        $barycentreVille = $retour [1];
        $lanX = $retour [2];
        $latY = $retour [3];

        return $this->render('FfbbBundle:Rencontres:index.html.twig', array(
            'villes' => $villes,
            'barycentreVille' => $barycentreVille,
            'lanX' => $lanX,
            'latY' => $latY,


        ));


    }

    public function exclusionAction()
    {

//        $dbname = $this->container->getParameter('database_name');


        $retour = $this->get('service_rencontres')->Exclusion();
        print_r($retour);
        exit;

        $city = stripcslashes($retour [0]);

        $population = $retour [1];
        $longBarycentre = $retour [2];
        $latBarycentre = $retour [3];

        return $this->render('FfbbBundle:Rencontres:index.html.twig', array(
            'city' => $city,
            'population' => $population,
            'longBarycentre' => $longBarycentre,
            'latBarycentre' => $latBarycentre,


        ));


    }
    public function contactAction()
    {
        return new Response('<h1>Contactez nous!</h1>');
    }
}