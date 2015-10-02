<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 11/09/2015
 * Time: 11:43
 */
namespace Optimouv\FfbbBundle\Services;

use \PDO;

class Rencontres
{

    public function index()
    {

        $villes = ["43.67353%2C7.19013", "43.12022%2C6.13101", "45.76931%2C4.84977", "43.95465%2C4.81606", "48.39044%2C-4.48658"];
        return $villes;
    }

    //Calcul du meilleur lieu de rencontre
    public function meilleurLieuRencontre()
    {


        //urlencode pour supprimer les espaces vides dans l'url
        $villes = ["43.67353%2C7.19013", "43.12022%2C6.13101", "45.76931%2C4.84977", "43.95465%2C4.81606", "48.39044%2C-4.48658"];

        $T2 = []; //tableau interm�diaire qui contient les coordonnees des pts d arrivees

        $lesDistances = []; // la somme des distances
        $lesDurees = []; // la somme des durees
        $lesPtsDeparts = []; // tableau qui contient tous les points de depart

        $distanceDest = []; //tableau qui contient toutes les distances vers les destinations d un point de d�part
        $dureeDest = []; //tableau qui contient toutes les dur�es vers les destinations d un point de d�part
        $coordonneesDest = []; //tableau qui contient toutes les coordonn�es vers les destinations d un point de d�part

        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; $i++) {
            $start = $villes[0];

            unset($villes[0]);
            $T2 = array_values($villes);

            //on fait appel � la premi�re partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Btruck%3Btraffic%3Adisabled%3B&start0=' . $start;

            //on parcourt tous les �l�ments du deuxi�me tableau: long + lat
            for ($j = 0; $j < count($T2); $j++) {

                $elt = $T2[$j];
                $maps_url .= '&destination' . $j . '=' . $elt;
            }

            //on ram�ne le dernier element de l'url
            $maps_url .= '&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';

            $maps_json = file_get_contents($maps_url);

            $maps_array = json_decode($maps_json, true);

            //On r�cup�re le nombre des distances � stocker dans un tableau
            $nbrDistances = count($maps_array['Response']['MatrixEntry']);

            $distance = null;
            $duree = null;
            $tabDistance = [];
            $tabDuree = [];

            for ($j = 0; $j < $nbrDistances; $j++) {

                //calcul des distances pour chaque ville + duree
                $uneDistance = $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['Distance'];
                $uneDuree = $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['BaseTime'];
                array_push($tabDistance, $uneDistance);
                array_push($tabDuree, $uneDuree);
                //calcul somme des distances
                $distance += $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['Distance'];
                $duree += $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['BaseTime'];
            }

            //distances pour chaque ville + duree
            array_push($distanceDest, $tabDistance);
            array_push($dureeDest, $tabDuree);
            array_push($coordonneesDest, $T2);

            //somme des distances
            array_push($lesDistances, $distance);
            array_push($lesPtsDeparts, $start);

            //somme des durees
            array_push($lesDurees, $duree);

            array_push($T2, $start);
            $villes = $T2;
        }

        //Somme des distances
        $distanceMin = min($lesDistances);
        $key = array_search($distanceMin, $lesDistances);
        $positionPtDepart = $lesPtsDeparts[$key];

        //Nom de la ville de d�part

        $coor_url = 'https://reverse.geocoder.cit.api.here.com/6.2/reversegeocode.json?prox=' . $positionPtDepart . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';

        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $villeDepart = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];


        //somme des durees
        $dureeTrajet = $lesDurees[$key];

        //distance ville
        $distVille = $distanceDest[$key];
        $dureeVille = $dureeDest[$key];
        $coordonneesVille = $coordonneesDest[$key];

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($coordonneesVille); $l++) {


            $coor_url = 'https://reverse.geocoder.cit.api.here.com/6.2/reversegeocode.json?prox=' . $coordonneesVille[$l] . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';

            $coor_json = file_get_contents($coor_url);

            $coor_array = json_decode($coor_json, true);


            $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);

        }


        $infosVilles = [];
        $infosVilles[0] = $mesVilles;
        $infosVilles[1] = $distVille;
        $infosVilles[2] = $dureeVille;

        //Coordonnées point depart
        $positionPtDepart = explode("%2C", $positionPtDepart);
        $longPtDep = $positionPtDepart[0]; // piece1
        $latPtDep = $positionPtDepart[1]; // piece2

        //calcul arrondie de la distance min:
        $distanceMin = $distanceMin / 1000;
        $distanceMin = round($distanceMin, 0);
        $retour = [];

        $retour[0] = $villeDepart;
        $retour[1] = $longPtDep;
        $retour[2] = $latPtDep;
        $retour[3] = $distanceMin;
        $retour[4] = $dureeTrajet;
        $retour[5] = $coordonneesVille;
        $retour[6] = $mesVilles;
        $retour[7] = $distVille;
        $retour[8] = $dureeVille;


        return $retour;
    }

    //Calcul du barycentre
    public function Barycentre()
    {
        $villes = ["43.67353%2C7.19013", "43.12022%2C6.13101", "45.76931%2C4.84977", "43.95465%2C4.81606", "48.39044%2C-4.48658"];

        //Décalaration du tableau de retour
        $retour = [];

        $length = count($villes);
        $lan = $lat = null;
        for ($i = 0; $i < $length; $i++) {

            $Coordonnes = explode("%2C", $villes[$i]);
            $lan += $Coordonnes[0];
            $lat += $Coordonnes[1];


        }

        $lanX = $lan / $length;
        $latY = $lat / $length;
        $coord = $lanX . '%2C' . $latY;

        $coor_url = 'https://places.demo.api.here.com/places/v1/discover/explore?at=' . $coord . '&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';
        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $barycentreVille = $coor_array['search']['context']['location']['address']['city'];

        $retour[0] = $villes;
        $retour[1] = $barycentreVille;
        $retour[2] = $lanX;
        $retour[3] = $latY;

        return $retour;


    }

    //Calcul exclusion géographique
    public function Exclusion()
    {


        $villes = ["43.67353%2C7.19013", "43.12022%2C6.13101", "45.76931%2C4.84977", "43.95465%2C4.81606", "48.39044%2C-4.48658"];

        $length = count($villes);
        $lan = $lat = null;
        for ($i = 0; $i < $length; $i++) {

            $Coordonnes = explode("%2C", $villes[$i]);
            $lan += $Coordonnes[0];
            $lat += $Coordonnes[1];
        }

        // Somme des X & Somme des Y
        $lanX = $lan / $length;
        $latY = $lat / $length;
        $coord = $lanX . '%2C' . $latY;


        //Mentionner les X,Y du point (Barycentre) et chercher l'emplacement du point sur la carte

        $coor_url = 'https://places.demo.api.here.com/places/v1/discover/explore?at=' . $coord . '&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';
        $coor_json = file_get_contents($coor_url);
        $coor_array = json_decode($coor_json, true);

        //trouver le code postal + nom de la ville

        $postalCode = $coor_array['search']['context']['location']['address']['postalCode'];
        $city = $coor_array['search']['context']['location']['address']['city'];
        //addslashes — Ajoute des antislashs dans une chaîne
        $city = addslashes($city);


        /*
                $dbname = 'optimouv';
                $dbuser = 'root';
                $dbpass = '';
        */

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=optimouv;charset=utf8', 'root', '');
        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }


        $stmt = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg, ville_population_2012 FROM  villes_france_free where ville_code_postal = '$postalCode' AND ville_nom = '$city' ;");
        $stmt->execute();
        $resultReq = $stmt->fetch(PDO::FETCH_ASSOC);

        $population = $resultReq['ville_population_2012'];

        $retourBarycentre = [];
        if ($population < 45000) {

            $longBarycentre = $resultReq['ville_latitude_deg'];
            $latBarycentre = $resultReq['ville_longitude_deg'];
            $retourBarycentre [0] = $city;
            $retourBarycentre [1] = $population;
            $retourBarycentre [2] = $longBarycentre;
            $retourBarycentre [3] = $latBarycentre;

            return $retourBarycentre;

        } else {

            $stmt1 = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg,ville_code_postal, ville_nom, ville_population_2012,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
from villes_france_free
order by Proximite limit 1,5 ;");
            $stmt1->execute();
            while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {

                if ($row['ville_population_2012'] > 45000) {

                    continue;
                } else {


                    $longBarycentre = $row['ville_latitude_deg'];
                    $latBarycentre = $row['ville_longitude_deg'];
                    $city = $row['ville_nom'];
                    $population = $row['ville_population_2012'];


                    $retourBarycentre [0] = $city;
                    $retourBarycentre [1] = $population;
                    $retourBarycentre [2] = $longBarycentre;
                    $retourBarycentre [3] = $latBarycentre;

                    return $retourBarycentre;


                }


            }

        }
    }

    //Calcul du scénario équitable
    public function scenarioEquitable()
    {
        $villes = ["43.67353%2C7.19013", "43.12022%2C6.13101", "45.76931%2C4.84977", "43.95465%2C4.81606", "48.39044%2C-4.48658"];

        $T2 = []; //tableau interm?diaire qui contient les coordonnees des pts d arrivees

        $lesDistances = []; // la somme des distances
        $lesDurees = []; // la somme des durees
        $lesPtsDeparts = []; // tableau qui contient tous les points de depart

        $distanceDest = []; //tableau qui contient toutes les distances vers les destinations d un point de d?part
        $dureeDest = []; //tableau qui contient toutes les dur?es vers les destinations d un point de d?part
        $coordonneesDest = []; //tableau qui contient toutes les coordonn?es vers les destinations d un point de d?part

        $distancesMax = [];//tableau qui contient toutes les distances maxi des différents scénarios

        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; $i++) {
            $start = $villes[0];

            unset($villes[0]);
            $T2 = array_values($villes);

            //on fait appel ? la premi?re partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Btruck%3Btraffic%3Adisabled%3B&start0=' . $start;

            //on parcourt tous les ?l?ments du deuxi?me tableau: long + lat
            for ($j = 0; $j < count($T2); $j++) {

                $elt = $T2[$j];
                $maps_url .= '&destination' . $j . '=' . $elt;
            }

            //on ram?ne le dernier element de l'url
            $maps_url .= '&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';

            $maps_json = file_get_contents($maps_url);

            $maps_array = json_decode($maps_json, true);


            //On r?cup?re le nombre des distances ? stocker dans un tableau
            $nbrDistances = count($maps_array['Response']['MatrixEntry']);

            $distance = null;
            $duree = null;
            $tabDistance = [];
            $tabDuree = [];
            for ($j = 0; $j < $nbrDistances; $j++) {

                //calcul des distances pour chaque ville + duree
                $uneDistance = $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['Distance'];
                $uneDuree = $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['BaseTime'];
                array_push($tabDistance, $uneDistance);
                array_push($tabDuree, $uneDuree);
                //calcul somme des distances
                $distance += $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['Distance'];
                $duree += $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['BaseTime'];
            }

            //Calcul le min des distances Max
            $distanceMax = max($tabDistance);
            array_push($distancesMax, $distanceMax);

            //distances pour chaque ville + duree
            array_push($distanceDest, $tabDistance);
            array_push($dureeDest, $tabDuree);
            array_push($coordonneesDest, $T2);

            //somme des distances
            array_push($lesDistances, $distance);
            array_push($lesPtsDeparts, $start);

            //somme des durees
            array_push($lesDurees, $duree);


            array_push($T2, $start);
            $villes = $T2;

        }
        //position de la ville equitable
        $distanceEquitable = min($distancesMax);
        $key = array_search($distanceEquitable, $distancesMax);
        $positionPtDepart = $lesPtsDeparts[$key];

        //echo '<pre>', print_r($distancesMax), '</pre>';

        //Nom de la ville de d?part

        $coor_url = 'https://reverse.geocoder.cit.api.here.com/6.2/reversegeocode.json?prox=' . $positionPtDepart . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';

        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $villeDepart = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

        //somme des durees
        $dureeTrajet = $lesDurees[$key];

        //distance ville
        $distVille = $distanceDest[$key];
        $dureeVille = $dureeDest[$key];
        $coordonneesVille = $coordonneesDest[$key];

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($coordonneesVille); $l++) {


            $coor_url = 'https://reverse.geocoder.cit.api.here.com/6.2/reversegeocode.json?prox=' . $coordonneesVille[$l] . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';

            $coor_json = file_get_contents($coor_url);

            $coor_array = json_decode($coor_json, true);


            $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);

        }
        //Distance totale à parcourir
        $distanceTotale = (array_sum($distVille))/ 1000;
        $distanceTotale = round($distanceTotale, 0);

        $infosVilles = [];
        $infosVilles[0] = $mesVilles;
        $infosVilles[1] = $distVille;
        $infosVilles[2] = $dureeVille;

        //Coordonnées point depart
        $positionPtDepart = explode("%2C", $positionPtDepart);
        $longPtDep = $positionPtDepart[0]; // piece1
        $latPtDep = $positionPtDepart[1]; // piece2

        $retour = [];

        $retour[0] = $villeDepart;
        $retour[1] = $longPtDep;
        $retour[2] = $latPtDep;
        $retour[3] = $distanceTotale;
        $retour[4] = $dureeTrajet;
        $retour[5] = $coordonneesVille;
        $retour[6] = $mesVilles;
        $retour[7] = $distVille;
        $retour[8] = $dureeVille;

        return $retour;

    }

}

