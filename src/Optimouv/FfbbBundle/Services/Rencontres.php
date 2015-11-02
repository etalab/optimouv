<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 11/09/2015
 * Time: 11:43.
 */
namespace Optimouv\FfbbBundle\Services;

use PDO;

class Rencontres
{

    private $database_name;
    private $database_user;
    private $database_password;
    private $app_id;
    private $app_code;

    public function __construct($database_name, $database_user, $database_password, $app_id, $app_code)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
    }
    public function index()
    {

//        print_r($this->database_host); "%database_name%", "%database_user%", "%database_password%"
//        exit;
        //params de connexion

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }
        $villes = [];
        $stmt = $bdd->prepare("SELECT longitude, latitude FROM  villes ;");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $longitude = $row['longitude'];
            $latitude = $row['latitude'];
            $coordonnee = $longitude . "%2C" . $latitude;
            array_push($villes, $coordonnee);

        }

//        $villes = ['43.88953%2C-0.49893', '47.19126%2C-1.5698', '47.46317%2C-0.59261', '47.086%2C2.39315', '49.76019%2C4.71909', '43.70821%2C7.29597'];
       // $villes = ["44.05513%2C4.6983", "48.8276353%2C2.2602854", "49.4926%2C3.70997", "49.50153%2C3.59576", "49.49291%2C3.30955", "48.7929%2C2.28623", "48.90686%2C2.24473", "43.69319%2C3.80698", "47.16489%2C0.28529", "44.89553%2C-0.71666", "48.14808%2C-1.6567", "45.21823%2C5.86072"];

        return $villes;
    }

    //Calcul du meilleur lieu de rencontre
    public function meilleurLieuRencontre()
    {
        $app_id = $this->app_id;
        $app_code = $this->app_code;

        //urlencode pour supprimer les espaces vides dans l'url
//        $villes = ["43.67353%2C7.19013", "43.12022%2C6.13101", "45.76931%2C4.84977", "43.95465%2C4.81606", "48.39044%2C-4.48658"];
        //on récupère le tableau des villes
        $villes = $this->index();
        $T2 = []; //tableau interm�diaire qui contient les coordonnees des pts d arrivees

        $lesDistances = []; // la somme des distances
        $lesDurees = []; // la somme des durees
        $lesPtsDeparts = []; // tableau qui contient tous les points de depart

        $distanceDest = []; //tableau qui contient toutes les distances vers les destinations d un point de d�part
        $dureeDest = []; //tableau qui contient toutes les dur�es vers les destinations d un point de d�part
        $coordonneesDest = []; //tableau qui contient toutes les coordonn�es vers les destinations d un point de d�part

        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; ++$i) {
            $start = $villes[0];

            unset($villes[0]);
            $T2 = array_values($villes);

            //on fait appel � la premi�re partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Bcar%3Btraffic%3Aenabled%3B&start0=' . $start;

            //on parcourt tous les �l�ments du deuxi�me tableau: long + lat
            for ($j = 0; $j < count($T2); ++$j) {
                $elt = $T2[$j];
                $maps_url .= '&destination' . $j . '=' . $elt;
            }

            //on ram�ne le dernier element de l'url
            $maps_url .= '&app_id='.$app_id.'&app_code='.$app_code;

            $maps_json = file_get_contents($maps_url);

            $maps_array = json_decode($maps_json, true);

            //On r�cup�re le nombre des distances � stocker dans un tableau
            $nbrDistances = count($maps_array['Response']['MatrixEntry']);

            $distance = null;
            $duree = null;
            $tabDistance = [];
            $tabDuree = [];

            for ($j = 0; $j < $nbrDistances; ++$j) {

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
        for ($l = 0; $l < count($coordonneesVille); ++$l) {
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
        $positionPtDepart = explode('%2C', $positionPtDepart);
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

        //on récupère le tableau des villes
        $villes = $this->index();

        $length = count($villes);
        $lan = $lat = null;
        for ($i =0; $i<$length; $i++){

            $Coordonnes = explode("%2C", $villes[$i]);
            $lan += $Coordonnes[0];
            $lat += $Coordonnes[1];

        }

        $lanX = $lan/$length;
        $latY = $lat/$length;
        $coord = $lanX.'%2C'.$latY ;

        $retour = $this->routingMatrix($coord, $villes);

        return $retour;
    }

    //Calcul exclusion géographique
    public function Exclusion($valeurExclusion)
    {
        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        //on récupère le tableau des villes
        $villes = $this->index();
//        $villes = ["43.39498%2C6.3141", "45.76679%2C5.66442", "48.6721%2C5.88819", "48.80155%2C2.43209", "49.16847%2C6.869"];


        $length = count($villes);
        $lan = $lat = null;
        for ($i = 0; $i < $length; ++$i) {
            $Coordonnes = explode('%2C', $villes[$i]);
            $lan += $Coordonnes[0];
            $lat += $Coordonnes[1];
        }

        // Somme des X & Somme des Y
        $lanX = $lan / $length;
        $latY = $lat / $length;
        $coord = $lanX . '%2C' . $latY;

        //Mentionner les X,Y du point (Barycentre) et chercher l'emplacement du point sur la carte

        $coor_url = 'https://places.demo.api.here.com/places/v1/discover/explore?at=' . $coord . '&app_id='.$app_id.'&app_code='.$app_code;
        $coor_json = file_get_contents($coor_url);
        $coor_array = json_decode($coor_json, true);

        //trouver le code postal + nom de la ville

        $postalCode = $coor_array['search']['context']['location']['address']['postalCode'];
        $city = $coor_array['search']['context']['location']['address']['city'];
        //addslashes — Ajoute des antislashs dans une chaîne
        $city = addslashes($city);

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        $stmt = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg, ville_population_2012 FROM  villes_france_free where ville_code_postal = '$postalCode' AND ville_nom = '$city' ;");
        $stmt->execute();
        $resultReq = $stmt->fetch(PDO::FETCH_ASSOC);

        $population = $resultReq['ville_population_2012'];

        $retourBarycentre = [];
        if ($population < $valeurExclusion) {
            $lanX = $resultReq['ville_latitude_deg'];
            $latY = $resultReq['ville_longitude_deg'];
            $coord = $lanX . '%2C' . $latY;
            $retour = $this->Barycentre();

            return $retour;

//            $retourBarycentre [0] = $city;
//            $retourBarycentre [1] = $population;
//            $retourBarycentre [2] = $longBarycentre;
//            $retourBarycentre [3] = $latBarycentre;
        } else {
            $stmt1 = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg,ville_code_postal, ville_nom, ville_population_2012,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
from villes_france_free
order by Proximite limit 1,15 ;");
            $stmt1->execute();
            while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
                if ($row['ville_population_2012'] > $valeurExclusion) {
                    continue;
                } else {
                    $lanX = $row['ville_latitude_deg'];
                    $latY = $row['ville_longitude_deg'];
                    $coord = $lanX . '%2C' . $latY;
                    $retour = $this->routingMatrix($coord, $villes);

                    return $retour;
                }
            }
        }
    }

    //Calcul du scénario équitable
    public function scenarioEquitable()
    {
        $app_id = $this->app_id;
        $app_code = $this->app_code;

        //        $villes = ["43.67353%2C7.19013", "43.12022%2C6.13101", "45.76931%2C4.84977", "43.95465%2C4.81606", "48.39044%2C-4.48658"];
        $villes = $this->index();

        $T2 = []; //tableau interm?diaire qui contient les coordonnees des pts d arrivees

        $lesDistances = []; // la somme des distances
        $lesDurees = []; // la somme des durees
        $lesPtsDeparts = []; // tableau qui contient tous les points de depart

        $distanceDest = []; //tableau qui contient toutes les distances vers les destinations d un point de d?part
        $dureeDest = []; //tableau qui contient toutes les dur?es vers les destinations d un point de d?part
        $coordonneesDest = []; //tableau qui contient toutes les coordonn?es vers les destinations d un point de d?part

        $distancesMax = [];//tableau qui contient toutes les distances maxi des différents scénarios

        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; ++$i) {
            $start = $villes[0];

            unset($villes[0]);
            $T2 = array_values($villes);

            //on fait appel ? la premi?re partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Bcar%3Btraffic%3Aenabled%3B&start0=' . $start;

            //on parcourt tous les ?l?ments du deuxi?me tableau: long + lat
            for ($j = 0; $j < count($T2); ++$j) {
                $elt = $T2[$j];
                $maps_url .= '&destination' . $j . '=' . $elt;
            }

            //on ram?ne le dernier element de l'url
            $maps_url .= '&app_id='.$app_id.'&app_code='.$app_code;

            $maps_json = file_get_contents($maps_url);

            $maps_array = json_decode($maps_json, true);

            //On r?cup?re le nombre des distances ? stocker dans un tableau
            $nbrDistances = count($maps_array['Response']['MatrixEntry']);

            $distance = null;
            $duree = null;
            $tabDistance = [];
            $tabDuree = [];
            for ($j = 0; $j < $nbrDistances; ++$j) {

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
        for ($l = 0; $l < count($coordonneesVille); ++$l) {
            $coor_url = 'https://reverse.geocoder.cit.api.here.com/6.2/reversegeocode.json?prox=' . $coordonneesVille[$l] . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=Zu1dv3uaX2PrzVrLglxr&app_code=hwW5E_XPS9E6A15-PYHBkg';

            $coor_json = file_get_contents($coor_url);

            $coor_array = json_decode($coor_json, true);

            $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);
        }
        //Distance totale à parcourir
        $distanceTotale = (array_sum($distVille)) / 1000;
        $distanceTotale = round($distanceTotale, 0);

        $infosVilles = [];
        $infosVilles[0] = $mesVilles;
        $infosVilles[1] = $distVille;
        $infosVilles[2] = $dureeVille;

        //Coordonnées point depart
        $positionPtDepart = explode('%2C', $positionPtDepart);
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

    public function routingMatrix($coord, $villes)
    {
        $app_id =$this->app_id;
        $app_code = $this->app_code;

        $coor_url = 'https://places.demo.api.here.com/places/v1/discover/explore?at=' . $coord . '&app_id='.$app_id.'&app_code='.$app_code;
        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $barycentreVille = $coor_array['search']['context']['location']['address']['city'];

        $start = $coord;
        $T2 = $villes;

        //on fait appel à la première partie de l'url here
        $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Bcar%3Btraffic%3Aenabled%3B&start0=' . $start;
        //on parcourt tous les éléments du deuxième tableau: long + lat
        for ($j = 0; $j < count($T2); ++$j) {
            $elt = $T2[$j];
            $maps_url .= '&destination' . $j . '=' . $elt;
        }

        //on ram?ne le dernier element de l'url
        $maps_url .= '&app_id='.$app_id.'&app_code='.$app_code;

        $maps_json = file_get_contents($maps_url);

        $maps_array = json_decode($maps_json, true);

        //On r?cup?re le nombre des distances ? stocker dans un tableau
        $nbrDistances = count($maps_array['Response']['MatrixEntry']);

        $distance = null;
        $duree = null;
        $tabDistance = [];
        $tabDuree = [];

        for ($j = 0; $j < $nbrDistances; ++$j) {

            //calcul des distances pour chaque ville + duree
            $uneDistance = $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['Distance'];
            $uneDuree = $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['BaseTime'];

            //Tab des distances des villes
            array_push($tabDistance, $uneDistance);

            //Tab des durées des trajets des villes
            array_push($tabDuree, $uneDuree);

            //calcul somme des distances
            $distance += $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['Distance'];

            //calcul somme des durees
            $duree += $maps_array['Response']['MatrixEntry'][$j]['Route']['Summary']['BaseTime'];
        }

        //Récupérer les noms de villes de destination

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($villes); ++$l) {
            $coor_url = 'https://reverse.geocoder.cit.api.here.com/6.2/reversegeocode.json?prox=' . $villes[$l] . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id='.$app_id.'&app_code='.$app_code;

            $coor_json = file_get_contents($coor_url);

            $coor_array = json_decode($coor_json, true);

            $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);
        }

        $distance = $distance / 1000;
        $distance = round($distance, 0);

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        $retour = [];

        $retour[0] = $barycentreVille;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $villes;
        $retour[6] = $mesVilles;
        $retour[7] = $tabDistance;
        $retour[8] = $tabDuree;

        return $retour;
    }

    public function terrainNeutre()
    {
        $app_id =$this->app_id;
        $app_code = $this->app_code;

        $equipe = $this->index();

        $terrainNeutre = ['48.8357%2C2.2473', '47.48569%2C-3.11922', '43.5732938%2C6.8188967', '47.724709%2C-0.5227929', '49.12878%2C6.22851'];

        $distanceMin = null;
        $lesDistances = [];
        $lesDurees = [];

        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];

            //on fait appel à la première partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Bcar%3Btraffic%3Aenabled%3B&start0=' . $start;
            for ($j = 0; $j < count($equipe); ++$j) {
                $destination = $equipe[$j];

                $maps_url .= '&destination' . $j . '=' . $destination;
            }

            //on ramène le dernier element de l'url
            $maps_url .= '&app_id='.$app_id.'&app_code='.$app_code;

            $maps_json = file_get_contents($maps_url);

            $maps_array = json_decode($maps_json, true);

            //On récupère le nombre des distances à stocker dans un tableau
            $nbrDistances = count($maps_array['Response']['MatrixEntry']);
            $tabDistance = [];
            $tabDuree = [];
            $distance = null;
            $duree = null;
            //On récupère chaque distance
            for ($k = 0; $k < $nbrDistances; ++$k) {
                $distance += $maps_array['Response']['MatrixEntry'][$k]['Route']['Summary']['Distance'];
                $duree += $maps_array['Response']['MatrixEntry'][$k]['Route']['Summary']['BaseTime'];
            }

            //somme des distances
            array_push($lesDistances, $distance);

            //somme des distances
            array_push($lesDurees, $duree);
        }

        //Somme des distances
        $distanceMin = min($lesDistances);
        $key = array_search($distanceMin, $lesDistances);

        $coord = $terrainNeutre[$key];

        $retour = $this->routingMatrix($coord, $equipe);

        return $retour;
    }

    public function terrainNeutreEquitable()
    {

        $app_id =$this->app_id;
        $app_code = $this->app_code;

//        $equipe = ['43.88953%2C-0.49893', '47.19126%2C-1.5698', '47.46317%2C-0.59261', '47.086%2C2.39315', '49.76019%2C4.71909', '43.70821%2C7.29597'];
        $equipe = $this->index();



        $terrainNeutre = ['48.8357%2C2.2473', '47.48569%2C-3.11922', '43.5732938%2C6.8188967', '47.724709%2C-0.5227929', '49.12878%2C6.22851'];

        $coord = null;

        $distancesMax = [];//tableau qui contient toutes les distances maxi des différents scénarios

        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];

            //on fait appel à la première partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Bcar%3Btraffic%3Aenabled%3B&start0=' . $start;
            for ($j = 0; $j < count($equipe); ++$j) {
                $destination = $equipe[$j];

                $maps_url .= '&destination' . $j . '=' . $destination;
            }

            //on ramène le dernier element de l'url
            $maps_url .= '&app_id='.$app_id.'&app_code='.$app_code;

            $maps_json = file_get_contents($maps_url);

            $maps_array = json_decode($maps_json, true);

            //On récupère le nombre des distances à stocker dans un tableau
            $nbrDistances = count($maps_array['Response']['MatrixEntry']);

            $tabDistance = [];

            //On récupère chaque distance
            for ($k = 0; $k < $nbrDistances; ++$k) {

                //calcul des distances pour chaque ville + duree
                $uneDistance = $maps_array['Response']['MatrixEntry'][$k]['Route']['Summary']['Distance'];
                array_push($tabDistance, $uneDistance);
            }

            //Calcul le min des distances Max
            $distanceMax = max($tabDistance);

            array_push($distancesMax, $distanceMax);
        }

        //position de la ville equitable
        $distanceEquitable = min($distancesMax);
        $key = array_search($distanceEquitable, $distancesMax);
        $coord = $terrainNeutre[$key];

        $retour = $this->routingMatrix($coord, $equipe);

        return $retour;
    }

    public function geocoderVilles($villes)
    {

         $app_id = $this->app_id;
         $app_code = $this->app_code;

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }


        //vider la table villes

        $truncate = $bdd->prepare("truncate table villes");
        $truncate->execute();


        $longueur = count($villes);

        for ($i = 0; $i < $longueur; $i++) {
            $req = addslashes($villes[$i]);

            $req = str_replace(' ', '%20', $req);

            $reqGeocode = 'https://geocoder.cit.api.here.com/6.2/geocode.json?searchtext=' . $req . '&app_id='.$app_id.'&app_code='.$app_code.'&gen=8';
            $reqGeocodeJson = file_get_contents($reqGeocode);

            $reqGeocodeArray = json_decode($reqGeocodeJson, true);

            $Longitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Latitude'];
            $Latitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Longitude'];
            $city = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['Address']['City'];
            $city = addslashes($city);
            $PostalCode = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['Address']['PostalCode'];

            $insert = $bdd->prepare("INSERT INTO  villes (code_postale, nom, longitude, latitude) VALUES ( '$PostalCode', '$city', '$Longitude', '$Latitude');");
            $insert->execute();


        }

    }

    public function nomsVilles($villes)
    {

        $longueur = count($villes);
        $mesVilles = [];
        for ($i = 0; $i < $longueur; $i++) {

            $nomVille = substr($villes[$i], 6);
            array_push($mesVilles, $nomVille);

        }

        return $mesVilles;


    }
}


