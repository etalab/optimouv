<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class RencontresController extends Controller
{
    public function indexAction()
    {

        $retour = $this->get('service_rencontres')->meilleurLieuRencontre();
        $retourEq = $this->get('service_rencontres')->scenarioEquitable();

        //Données du scénario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $mesVilles = $retour[6];
        $distVille = $retour[7];
        $dureeVille = $retour[8];

        //Données du scénario équitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceTotaleEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        $mesVillesEq = $retourEq[6];
        $distVilleEq = $retourEq[7];
        $dureeVilleEq = $retourEq[8];



        return $this->render('FfbbBundle:Rencontres:index.html.twig', array(

            //Données du scénario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'mesVilles' => $mesVilles,
            'distVille' => $distVille,
            'dureeVille' => $dureeVille,

            //données scénario équitable
            'villeDepartEq' => $villeDepartEq,
            'longPtDepEq' => $longPtDepEq,
            'latPtDepEq' => $latPtDepEq,
            'distanceTotaleEq' => $distanceTotaleEq,
            'dureeTrajetEq' => $dureeTrajetEq,
            'coordonneesVilleEq' => $coordonneesVilleEq,
            'mesVillesEq' => $mesVillesEq,
            'distVilleEq' => $distVilleEq,
            'dureeVilleEq' => $dureeVilleEq,

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