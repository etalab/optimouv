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

        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $mesVilles = $retour[6];
        $distVille = $retour[7];
        $dureeVille = $retour[8];

        //Donn�es du sc�nario �quitable

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

            //Donn�es du sc�nario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'mesVilles' => $mesVilles,
            'distVille' => $distVille,
            'dureeVille' => $dureeVille,

            //donn�es sc�nario �quitable
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

        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $mesVilles = $retour[6];
        $distVille = $retour[7];
        $dureeVille = $retour[8];

        return $this->render('FfbbBundle:Rencontres:barycentre.html.twig', array(

            //Donn�es du sc�nario optimal
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

    public function exclusionAction()
    {

        //Params de connexion
        $dbname = $this->container->getParameter('database_name');
        $dbuser = $this->container->getParameter('database_user');
        $dbpwd = $this->container->getParameter('database_password');

        //stcoker les params de connexion dans un tableau -> envoyer comme param � la fn exclusion
        $dbcon = [];
        $dbcon[0]=$dbname;
        $dbcon[1]=$dbuser;
        $dbcon[2]=$dbpwd;



        //R�cup�ration du r�sultat du calcul avec contrainte
        $retour = $this->get('service_rencontres')->Exclusion($dbcon);


        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $mesVilles = $retour[6];
        $distVille = $retour[7];
        $dureeVille = $retour[8];

        //R�cup�ration du r�sultat du calcul sans contrainte
        $retourEq = $this->get('service_rencontres')->Barycentre();

        //Donn�es du sc�nario �quitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceTotaleEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        $mesVillesEq = $retourEq[6];
        $distVilleEq = $retourEq[7];
        $dureeVilleEq = $retourEq[8];

//        $city = stripcslashes($retour [0]);

        return $this->render('FfbbBundle:Rencontres:exclusion.html.twig', array(
            //Donn�es du sc�nario avec contrainte
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'mesVilles' => $mesVilles,
            'distVille' => $distVille,
            'dureeVille' => $dureeVille,

            //donn�es sc�nario sans contrainte
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

    public function terrainNeutreAction(){

        //R�cup�ration du r�sultat du calcul du terrain neutre
        $retour = $this->get('service_rencontres')->terrainNeutre();
        //Donn�es du sc�nario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        $mesVilles = $retour[6];
        $distVille = $retour[7];
        $dureeVille = $retour[8];

        //R�cup�ration du r�sultat du calcul du terrain neutre Equitable
        $retourEq = $this->get('service_rencontres')->terrainNeutreEquitable();

        //Donn�es du sc�nario �quitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceTotaleEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        $mesVillesEq = $retourEq[6];
        $distVilleEq = $retourEq[7];
        $dureeVilleEq = $retourEq[8];

        return $this->render('FfbbBundle:Rencontres:terrainNeutre.html.twig', array(

            //Donn�es du sc�nario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'mesVilles' => $mesVilles,
            'distVille' => $distVille,
            'dureeVille' => $dureeVille,

            //donn�es sc�nario sans contrainte
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
    public function contactAction()
    {

        return new Response('<h1>Contactez nous!</h1>');
    }
}