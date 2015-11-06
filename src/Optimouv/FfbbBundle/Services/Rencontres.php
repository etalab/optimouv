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

            $retourRoutingMatrixUnStart = $this->routingMatrixUnStart($start, $T2);

            $distanceTotal = $retourRoutingMatrixUnStart[0];
            $dureeTotale = $retourRoutingMatrixUnStart[1];

            array_push($distanceDest, $distanceTotal);
            array_push($dureeDest, $dureeTotale);
            array_push($coordonneesDest, $T2);
            array_push($lesPtsDeparts, $start);

            array_push($T2, $start);
            $villes = $T2;

        }

        $tousLesCalculs[0] = $distanceDest;
        $tousLesCalculs[1] = $dureeDest;
        $tousLesCalculs[2] = $coordonneesDest;

        $sommesDistances = [];
        for($j=0; $j<count($tousLesCalculs[0]); $j++){
            $sommeDistance = array_sum($tousLesCalculs[0][$j]);
            array_push($sommesDistances, $sommeDistance);
        }
        //Min Somme des distances
        $distanceMin = min($sommesDistances);
        $key = array_search($distanceMin, $sommesDistances);

        $coord = $lesPtsDeparts[$key];

        $distanceTotal = $tousLesCalculs[0][$key];
        $dureeTotale = $tousLesCalculs[1][$key];


        //somme des distances
        $distance = array_sum($distanceTotal) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        $mesVillesXY = $coordonneesDest[$key];
        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($mesVillesXY);



        //Nom de la ville de d�part

        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' .$lanX.'%2C'.$latY. '&mode=retrieveAddresses&maxresults=1&gen=8&app_id='.$app_id.'&app_code='.$app_code;

        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $villeDepart = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];



        //distance ville
        $distVille = $distanceDest[$key];
        $dureeVille = $dureeDest[$key];


        $infosVilles = [];
        $infosVilles[0] = $mesVilles;
        $infosVilles[1] = $distVille;
        $infosVilles[2] = $dureeVille;


        $retour = [];

        $retour[0] = $villeDepart;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $mesVillesXY;
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
        for ($i = 0; $i < $length; $i++) {

            $Coordonnes = explode("%2C", $villes[$i]);
            $lan += $Coordonnes[0];
            $lat += $Coordonnes[1];

        }

        $lanX = $lan / $length;
        $latY = $lat / $length;
        $coord = $lanX . '%2C' . $latY;

        $retour = $this->routingMatrix($coord, $villes);

        return $retour;
    }

    //Calcul exclusion géographique
    public function Exclusion($valeurExclusion)
    {
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



        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

            $stmt1 = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg,ville_code_postal, ville_population_2012,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
from villes_france_free
where ville_population_2012 < $valeurExclusion
order by Proximite limit 1;");
            $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

                    $lanX = $result['ville_latitude_deg'];
                    $latY = $result['ville_longitude_deg'];
                    $coord = $lanX . '%2C' . $latY;
                    $retour = $this->routingMatrix($coord, $villes);

                    return $retour;



//            while ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
//                if ($row['ville_population_2012'] > $valeurExclusion) {
//                    continue;
//                } else {
//                    $lanX = $row['ville_latitude_deg'];
//                    $latY = $row['ville_longitude_deg'];
//                    $coord = $lanX . '%2C' . $latY;
//                    $retour = $this->routingMatrix($coord, $villes);
//
//                    return $retour;
//                }
//            }

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
       /////////////********************************/////////////////
        for ($i = 0; $i < $longueurTab; ++$i) {

            $start = $villes[0];

            unset($villes[0]);
            $T2 = array_values($villes);

            $retourRoutingMatrixUnStart = $this->routingMatrixUnStart($start, $T2);

            $distanceTotal = $retourRoutingMatrixUnStart[0];
            $dureeTotale = $retourRoutingMatrixUnStart[1];

            array_push($distanceDest, $distanceTotal);
            array_push($dureeDest, $dureeTotale);
            array_push($coordonneesDest, $T2);
            array_push($lesPtsDeparts, $start);

            array_push($T2, $start);
            $villes = $T2;

        }

       /////////////********************************/////////////////

        $tousLesCalculs[0] = $distanceDest;
        $tousLesCalculs[1] = $dureeDest;
        $tousLesCalculs[2] = $coordonneesDest;

        $distancesMax = [];
        for($j=0; $j<count($tousLesCalculs[0]); $j++){
            $distanceMax = max($tousLesCalculs[0][$j]);
            array_push($distancesMax, $distanceMax);
        }

        //position de la ville equitable
        $distanceEquitable = min($distancesMax);
        $key = array_search($distanceEquitable, $distancesMax);

        $coord = $lesPtsDeparts[$key];
        $distanceTotal = $tousLesCalculs[0][$key];
        $dureeTotale = $tousLesCalculs[1][$key];

       /////////////********************************/////////////////

        //somme des distances
        $distance = array_sum($distanceTotal) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        $mesVillesXY = $coordonneesDest[$key];
        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($mesVillesXY);



        //Nom de la ville de d�part

        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' .$lanX.'%2C'.$latY. '&mode=retrieveAddresses&maxresults=1&gen=8&app_id='.$app_id.'&app_code='.$app_code;

        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $villeDepart = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];



        //distance ville
        $distVille = $distanceDest[$key];
        $dureeVille = $dureeDest[$key];


        $infosVilles = [];
        $infosVilles[0] = $mesVilles;
        $infosVilles[1] = $distVille;
        $infosVilles[2] = $dureeVille;


        $retour = [];

        $retour[0] = $villeDepart;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $mesVillesXY;
        $retour[6] = $mesVilles;
        $retour[7] = $distVille;
        $retour[8] = $dureeVille;




        return $retour;
    }

    public function routingMatrix($coord, $villes)
    {
        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

//
//        $coor_url = 'http://places.api.here.com/places/v1/discover/explore?at=' . $coord . '&app_id=' . $app_id . '&app_code=' . $app_code;
//        $coor_json = file_get_contents($coor_url);
//
//        $coor_array = json_decode($coor_json, true);
//
//        $barycentreVille = $coor_array['search']['context']['location']['address']['city'];

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        $stmt1 = $bdd->prepare("SELECT ville_nom, ville_longitude_deg, ville_latitude_deg,ville_code_postal, ville_population_2012,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
from villes_france_free
order by Proximite limit 1;");
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

        $lanX = $result['ville_latitude_deg'];
        $latY = $result['ville_longitude_deg'];
        $coord = $lanX . '%2C' . $latY;

        $barycentreVille = $result['ville_nom'];

        $start = $coord;

        $retourRoutingMatrixUnStart = $this->routingMatrixUnStart($start,$villes);

        $distanceTotal = $retourRoutingMatrixUnStart[0];
        $dureeTotale   = $retourRoutingMatrixUnStart[1];

        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($villes);

         //somme des distances
        $distance = array_sum($distanceTotal) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);

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
        $retour[7] = $distanceTotal;
        $retour[8] = $dureeTotale;



        return $retour;
    }

    public function terrainNeutre()
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;



        $equipe = $this->index();

        $terrainNeutre = ['48.74305%2C2.4014', '47.48569%2C-3.11922', '43.5732938%2C6.8188967', '47.724709%2C-0.5227929', '49.12878%2C6.22851'];


        $toutesLesDistances = [];
        $toutesLesDurees = [];
        $tousLesCalculs = [];
        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];


            $retourRoutingMatrixUnStart = $this->routingMatrixUnStart($start, $equipe);

            $distanceTotal = $retourRoutingMatrixUnStart[0];
            $dureeTotale = $retourRoutingMatrixUnStart[1];

            array_push($toutesLesDistances, $distanceTotal);
            array_push($toutesLesDurees, $dureeTotale);

        }

        $tousLesCalculs[0] = $toutesLesDistances;
        $tousLesCalculs[1] = $toutesLesDurees;


        $sommesDistances = [];
        for($j=0; $j<count($tousLesCalculs[0]); $j++){
            $sommeDistance = array_sum($tousLesCalculs[0][$j]);
            array_push($sommesDistances, $sommeDistance);
        }

        //Somme des distances
        $distanceMin = min($sommesDistances);
        $key = array_search($distanceMin, $sommesDistances);


        $coord = $terrainNeutre[$key];
        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];



        $distanceTotal = $tousLesCalculs[0][$key];
        $dureeTotale = $tousLesCalculs[1][$key];


        //somme des distances
        $distance = array_sum($distanceTotal) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);


        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($equipe);
//        $villeDepart = $this->mesVilles($coord);
        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox='.$lanX.'%2C'.$latY.'&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

        $retour = [];

        $retour[0] = $maVille;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $equipe;
        $retour[6] = $mesVilles;
        $retour[7] = $distanceTotal;
        $retour[8] = $dureeTotale;


        return $retour;
    }

    public function terrainNeutreEquitable()
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;

//        $equipe = ['43.88953%2C-0.49893', '47.19126%2C-1.5698', '47.46317%2C-0.59261', '47.086%2C2.39315', '49.76019%2C4.71909', '43.70821%2C7.29597'];
        $equipe = $this->index();


        $terrainNeutre = ['48.7402617%2C2.367652', '47.48569%2C-3.11922', '43.5732938%2C6.8188967', '47.724709%2C-0.5227929', '49.12878%2C6.22851'];

        $toutesLesDistances = [];
        $toutesLesDurees = [];
        $tousLesCalculs = [];
        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];


            $retourRoutingMatrixUnStart = $this->routingMatrixUnStart($start, $equipe);

            $distanceTotal = $retourRoutingMatrixUnStart[0];
            $dureeTotale = $retourRoutingMatrixUnStart[1];

            array_push($toutesLesDistances, $distanceTotal);
            array_push($toutesLesDurees, $dureeTotale);

        }

        $tousLesCalculs[0] = $toutesLesDistances;
        $tousLesCalculs[1] = $toutesLesDurees;


        $distancesMax = [];
        for($j=0; $j<count($tousLesCalculs[0]); $j++){
            $distanceMax = max($tousLesCalculs[0][$j]);
            array_push($distancesMax, $distanceMax);
        }

        //position de la ville equitable
        $distanceEquitable = min($distancesMax);
        $key = array_search($distanceEquitable, $distancesMax);

        $coord = $terrainNeutre[$key];
        $distanceTotal = $tousLesCalculs[0][$key];
        $dureeTotale = $tousLesCalculs[1][$key];

        //somme des distances
        $distance = array_sum($distanceTotal) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($equipe);
//        $villeDepart = $this->mesVilles($coord);
        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox='.$lanX.'%2C'.$latY.'&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

        $coor_json = file_get_contents($coor_url);

        $coor_array = json_decode($coor_json, true);

        $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];
        $retour = [];

        $retour[0] = $maVille;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $equipe;
        $retour[6] = $mesVilles;
        $retour[7] = $distanceTotal;
        $retour[8] = $dureeTotale;





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


        $nbrVilles = count($villes);

        for ($i = 0; $i < $nbrVilles; $i++) {
            $req = addslashes($villes[$i]);
            $codePostal = substr($req, 0, 5);
            $nomVille = substr($req, 6);

            //iconv — Convertit une chaîne dans un jeu de caractères
            $nomVille = iconv('utf-8', 'ASCII//IGNORE//TRANSLIT', $nomVille);

            //remplace les espaces vides dans les noms des villes par '%20' selon la syntaxe de la req Here
            $nomVille = urlencode($nomVille);
            $reqGeocode = 'http://geocoder.api.here.com/6.2/geocode.json?country=France&city=' . $nomVille . '&postalCode=' . $codePostal . '&app_id=' . $app_id . '&app_code=' . $app_code . '&gen=8';

            $reqGeocodeJson = file_get_contents($reqGeocode);

            $reqGeocodeArray = json_decode($reqGeocodeJson, true);

            $Longitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Latitude'];
            $Latitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Longitude'];
            if (isset($Longitude, $Latitude)){
                $city = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['Address']['City'];
                $city = addslashes($city);
                $PostalCode = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['Address']['PostalCode'];

                $insert = $bdd->prepare("INSERT INTO  villes (code_postale, nom, longitude, latitude) VALUES ( '$PostalCode', '$city', '$Longitude', '$Latitude');");
                $insert->execute();
            }
            else{
                continue;
            }



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

    public function routingMatrixUnStart($start,$villes){

        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $sousTabVilles = array_chunk($villes, 50, true);


        $distanceTotal=[];
        $dureeTotale=[];
        for($i=0; $i<count($sousTabVilles); $i++){

            $sousTab = $sousTabVilles[$i];
            //on parcourt tous les éléments du deuxième tableau: long + lat


            //on fait appel à la première partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Bcar%3Btraffic%3Aenabled%3B&start0=' . $start;
            $xy = '';
            $j=0;
            foreach($sousTab as $key => $value ){


//                echo "Clé : $key; Valeur : $value<br />\n";
                $elt = $value;
                $xy .='&destination' . $j. '=' ;
                $xy .=  $elt;
                $j=$j+1;
            }


            $maps_url .=$xy;


            //on ramène le dernier element de l'url
            $maps_url .= '&app_id=' . $app_id . '&app_code=' . $app_code;

            $maps_json = file_get_contents($maps_url);

            $maps_array = json_decode($maps_json, true);

            //On r?cup?re le nombre des distances
            $nbrDistances = count($maps_array['Response']['MatrixEntry']);

            $distance = null;
            $duree = null;
            $tabDistance = [];
            $tabDuree = [];

            //On réécupère les distances et les durées pour chaque ville
            for ($k = 0; $k < $nbrDistances; ++$k) {

                //calcul des distances pour chaque ville + duree
                $uneDistance = $maps_array['Response']['MatrixEntry'][$k]['Route']['Summary']['Distance'];
                $uneDuree = $maps_array['Response']['MatrixEntry'][$k]['Route']['Summary']['BaseTime'];

                //Tab des distances des villes
                array_push($tabDistance, $uneDistance);
                //Tab des durées des trajets des villes
                array_push($tabDuree, $uneDuree);
            }


            //Récupérer toutes les distances et toutes les durées dans un seul tableau
            $distanceTotal=array_merge($distanceTotal,$tabDistance);
            $dureeTotale=array_merge($dureeTotale,$tabDuree);

        }//fin boucle tous les blocs de villes

        $retour=[];
        $retour[0] = $distanceTotal;
        $retour[1] = $dureeTotale;

        return $retour;


    }//fin fn routingMatrix

    public function mesVilles($villes){

        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($villes); ++$l) {
            $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' . $villes[$l] . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

            $coor_json = file_get_contents($coor_url);

            $coor_array = json_decode($coor_json, true);

            $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);
        }
        return $mesVilles;
    }





}


