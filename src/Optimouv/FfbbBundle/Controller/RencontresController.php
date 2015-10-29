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
        foreach($retour[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key]);
        }

        //Données du scénario équitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceTotaleEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        foreach($retourEq[6] as $key => $value ){

            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key]);
        }


        return $this->render('FfbbBundle:Rencontres:index.html.twig', array(

            //Données du scénario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,

            //données scénario équitable
            'villeDepartEq' => $villeDepartEq,
            'longPtDepEq' => $longPtDepEq,
            'latPtDepEq' => $latPtDepEq,
            'distanceTotaleEq' => $distanceTotaleEq,
            'dureeTrajetEq' => $dureeTrajetEq,
            'coordonneesVilleEq' => $coordonneesVilleEq,
            'participantsEq' => $participantsEq,

        ));
    }

    public function barycentreAction()
    {


        $participants = [];
        $retour = $this->get('service_rencontres')->Barycentre();

        //Données du scénario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];

        foreach($retour[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key]);
        }



//        echo '<pre>',print_r($participants,1),'</pre>';exit;
        return $this->render('FfbbBundle:Rencontres:barycentre.html.twig', array(

            //Données du scénario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,


        ));


    }

    public function exclusionAction()
    {

        //Récupération de la valeur saisie par l'utilisateur
        $valeurExclusion = $_POST["valeurExclusion"];

        //Récupération du résultat du calcul avec contrainte
        $retour = $this->get('service_rencontres')->Exclusion($valeurExclusion);


        //Données du scénario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        foreach($retour[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key]);
        }


        //Récupération du résultat du calcul sans contrainte
        $retourEq = $this->get('service_rencontres')->Barycentre();

        //Données du scénario équitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceTotaleEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        foreach($retourEq[6] as $key => $value ){

            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key]);
        }

//        $city = stripcslashes($retour [0]);

        return $this->render('FfbbBundle:Rencontres:exclusion.html.twig', array(
            //Données du scénario avec contrainte
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,

            //données scénario sans contrainte
            'villeDepartEq' => $villeDepartEq,
            'longPtDepEq' => $longPtDepEq,
            'latPtDepEq' => $latPtDepEq,
            'distanceTotaleEq' => $distanceTotaleEq,
            'dureeTrajetEq' => $dureeTrajetEq,
            'coordonneesVilleEq' => $coordonneesVilleEq,
            'participantsEq' => $participantsEq,




        ));


    }

    public function terrainNeutreAction(){

        //Récupération du résultat du calcul du terrain neutre
        $retour = $this->get('service_rencontres')->terrainNeutre();
        //Données du scénario optimal
        $villeDepart = $retour[0];
        $longPtDep = $retour[1];
        $latPtDep = $retour[2];
        $distanceMin = $retour[3];
        $dureeTrajet = $retour[4];
        $coordonneesVille = $retour[5];
        foreach($retour[6] as $key => $value ){

            $participants[]= array('ville' => $value, 'distance' => $retour[7][$key], 'duree' => $retour[8][$key]);
        }

        //Récupération du résultat du calcul du terrain neutre Equitable
        $retourEq = $this->get('service_rencontres')->terrainNeutreEquitable();

        //Données du scénario équitable

        $villeDepartEq = $retourEq[0];
        $longPtDepEq = $retourEq[1];
        $latPtDepEq = $retourEq[2];
        $distanceTotaleEq = $retourEq[3];
        $dureeTrajetEq = $retourEq[4];
        $coordonneesVilleEq = $retourEq[5];
        foreach($retourEq[6] as $key => $value ){

            $participantsEq[]= array('villeEq' => $value, 'distanceEq' => $retourEq[7][$key], 'dureeEq' => $retourEq[8][$key]);
        }

        return $this->render('FfbbBundle:Rencontres:terrainNeutre.html.twig', array(

            //Données du scénario optimal
            'villeDepart' => $villeDepart,
            'longPtDep' => $longPtDep,
            'latPtDep' => $latPtDep,
            'distanceMin' => $distanceMin,
            'dureeTrajet' => $dureeTrajet,
            'coordonneesVille' => $coordonneesVille,
            'participants' => $participants,


            //données scénario sans contrainte
            'villeDepartEq' => $villeDepartEq,
            'longPtDepEq' => $longPtDepEq,
            'latPtDepEq' => $latPtDepEq,
            'distanceTotaleEq' => $distanceTotaleEq,
            'dureeTrajetEq' => $dureeTrajetEq,
            'coordonneesVilleEq' => $coordonneesVilleEq,
            'participantsEq' => $participantsEq,


        ));

    }
    public function contactAction()
    {

        return new Response('<h1>Contactez nous!</h1>');
    }
}