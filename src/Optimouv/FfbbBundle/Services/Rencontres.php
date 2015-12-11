<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 11/09/2015
 * Time: 11:43.
 */
namespace Optimouv\FfbbBundle\Services;

use PDO;
use Symfony\Component\Validator\Constraints\NotNull;

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

    public function index($idGroupe)
    {


        //params de connexion

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        $reqVilles = $bdd->prepare("SELECT equipes FROM  groupe WHERE id = $idGroupe;");
        //$reqVilles->bindParam(':id', $idGroupe);
        $reqVilles->execute();
        $reqVilles = $reqVilles->fetchColumn();
        $reqVilles = explode(",", $reqVilles);


        $villes = [];


        for ($i = 0; $i < count($reqVilles); $i++) {
            $stmt = $bdd->prepare("SELECT id, longitude, latitude, id_ville_france FROM  entite WHERE id = :id;");
            $stmt->bindParam(':id', $reqVilles[$i]);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $idEntite = $row['id'];
            $longitude = $row['longitude'];
            $latitude = $row['latitude'];
            $idVille = $row['id_ville_france'];

            if (empty($longitude) && empty($latitude)) {

               $retour = $this->geocoderUneVille($idVille);

                $longitude = $retour[1];
                $latitude = $retour[0];

                $date = new \DateTime();
                $dateModification = $date->format('Y-m-d');

                $update = $bdd->prepare("UPDATE entite SET longitude = :Longitude, latitude= :Latitude, date_modification = :dateModification WHERE id = :idEntite");

                $update->bindParam(':idEntite', $idEntite);
                $update->bindParam(':Longitude', $longitude);
                $update->bindParam(':Latitude', $latitude);
                $update->bindParam(':dateModification', $dateModification);
                $update->execute();

                $coordonnee = $latitude . "%2C" . $longitude;
                array_push($villes, $coordonnee);

            }
            else{
                $coordonnee = $latitude . "%2C" . $longitude;
                array_push($villes, $coordonnee);

            }



        }

          return $villes;
    }

    //Calcul du meilleur lieu de rencontre
    public function meilleurLieuRencontre($idGroupe)
    {

        //Récupération de détail de la liste de lieux

        $listeLieux = $this->getListeLieux($idGroupe);

        $nomsTerrainsNeutres = $listeLieux[0];


        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        //on récupère le tableau des villes
        $villes = $this->index($idGroupe);
        $T2 = []; //tableau interm�diaire qui contient les coordonnees des pts d arrivees

        $lesDistances = []; // la somme des distances
        $lesDurees = []; // la somme des durees
        $lesPtsDeparts = []; // tableau qui contient tous les points de depart
        $coordonneesVilles = [];

        $distanceDest = []; //tableau qui contient toutes les distances vers les destinations d un point de d�part
        $dureeDest = []; //tableau qui contient toutes les dur�es vers les destinations d un point de d�part
        $coordonneesDest = []; //tableau qui contient toutes les coordonn�es vers les destinations d un point de d�part
        $sommeDistances = [];
        $sommeDurees = [];
        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; ++$i) {
            $start = $villes[0];

            unset($villes[0]);
            $T2 = array_values($villes);


             $Coordonnes = explode("%2C", $start);
            $lanY  = $Coordonnes[0];
            $lanX = $Coordonnes[1];


            $resultat = $this->calculRoute($lanX, $lanY, $T2);

            $distanceDest = $resultat[0];
            $dureeDest = $resultat[1];
            $coordonneesDest = $T2;
            $sommeDistanceDep = array_sum($distanceDest);
            $sommeDureeDep = array_sum($dureeDest);

            //on groupe les résultats de tous les cas possibles!

            array_push($lesDistances, $distanceDest);
            array_push($lesDurees, $dureeDest);
            array_push($coordonneesVilles, $coordonneesDest);
            array_push($lesPtsDeparts, $start);
            array_push($sommeDistances, $sommeDistanceDep);
            array_push($sommeDurees, $sommeDureeDep);


            array_push($T2, $start);
            $villes = $T2;
        }//fin parcourir longuerTab

        //Min Somme des distances
        $distanceMin = min($sommeDistances);
        $key = array_search($distanceMin, $sommeDistances);//on récupère la position de la somme min

        $coord = $lesPtsDeparts[$key]; //on récupère le point de depart

        $distanceTotale = $sommeDistances[$key];//on recupere la somme des tistances pour notre ville de depart
        $distanceTotale = $distanceTotale/1000;
        $distanceTotale = round($distanceTotale,0);//on fait l'arrondie de la distance totale

        $dureeTotale = $sommeDurees[$key];//on recupere la somme des durees trajets pour notre ville de depart

        //Nom de la ville de depart
        $coordVille = explode('%2C', $coord);

        $lanX = $coordVille[0];
        $latY = $coordVille[1];

        $stmt1 = $bdd->prepare("SELECT ville from entite where longitude = $latY AND latitude = $lanX;");
        $stmt1->execute();
        $villeDepart = $stmt1->fetchColumn();


        $mesVillesXY = $coordonneesVilles[$key];
        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($mesVillesXY);

        //distance ville
        $distVille = $lesDistances[$key];
        $dureeVille = $lesDurees[$key];

        $retour = [];

        $retour[0] = $villeDepart;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distanceTotale;
        $retour[4] = $dureeTotale;
        $retour[5] = $mesVillesXY;
        $retour[6] = $mesVilles;
        $retour[7] = $distVille;
        $retour[8] = $dureeVille;
        $retour[9] = $nomsTerrainsNeutres;




        //echo '<pre>',print_r($mesVilles,1),'</pre>';
     //   print_r("$$$$$");
//exit;


        return $retour;
    }

    //Calcul du barycentre
    public function Barycentre($idGroupe)
    {

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        //on récupère le tableau des villes
        $villes = $this->index($idGroupe);


        $length = count($villes);
        $lan = $lat = null;
        for ($i = 0; $i < $length; $i++) {

            $Coordonnes = explode("%2C", $villes[$i]);
            $lan += $Coordonnes[0];
            $lat += $Coordonnes[1];

        }

        $lanX = $lan / $length;
        $latY = $lat / $length;




        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        $stmt1 = $bdd->prepare("SELECT ville_nom, ville_longitude_deg, ville_latitude_deg, ville_code_postal,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                                from villes_france_free
                                order by Proximite limit 1;");
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

        $latY  = $result['ville_latitude_deg'];
        $lanX = $result['ville_longitude_deg'];
        $ville = $result['ville_nom'];
        $codePostal = $result['ville_code_postal'];
        $nom = 'Barycentre_Groupe_'.$idGroupe;
        //recuperer la date du jour
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');

        //vérifier si le barycentre existe deja
        $barycentre = $bdd->prepare("SELECT id from entite where longitude = $lanX AND latitude = $latY");
        $barycentre->execute();
        $res = $barycentre->fetchColumn();

        if(!$res){
            $insert = $bdd->prepare("INSERT INTO  entite (nom, ville, code_postal, longitude, latitude, date_creation) VALUES ( :nom, :ville, :codePostal, :Longitude,:Latitude, :dateCreation );");
            $insert -> bindParam(':nom', $nom);
            $insert -> bindParam(':ville', $ville);
            $insert -> bindParam(':codePostal', $codePostal);
            $insert -> bindParam(':Longitude', $lanX);
            $insert -> bindParam(':Latitude', $latY);
            $insert -> bindParam(':dateCreation', $dateCreation);

            $insert->execute();
        }


        $coord = $lanX . '%2C' . $latY; // pour appel la fn routing matrix

        $retour = $this->routingMatrix($coord, $villes);
//        $retour = $this->calculRoute($lanX,$latY, $villes);
        return $retour;
    }

    //Calcul exclusion géographique
    public function Exclusion($valeurExclusion, $idGroupe)
    {
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        if($valeurExclusion){

            //on récupère le tableau des villes
            $villes = $this->index($idGroupe);


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
            //  $coord = $lanX . '%2C' . $latY;


            try {
                $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

            } catch (PDOException $e) {
                die('Erreur : ' . $e->getMessage());
            }

            $stmt1 = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg,ville_code_postal, ville_population_2012,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
from villes_france_free
where ville_population_2012 < :valeurExclusion
order by Proximite limit 1;");
            $stmt1->bindParam(':valeurExclusion', $valeurExclusion );

            $stmt1->execute();
            $result = $stmt1->fetch(PDO::FETCH_ASSOC);

            $lanX = $result['ville_latitude_deg'];
            $latY = $result['ville_longitude_deg'];
            $coord = $latY. '%2C' .$lanX ;


            //vérifier si le barycentre existe deja
            $barycentre = $bdd->prepare("SELECT id from entite where longitude = $latY AND latitude = $lanX");
            $barycentre->execute();
            $res = $barycentre->fetchColumn();

            $nom = 'Barycentre_Groupe_'.$idGroupe;
            //recuperer la date du jour
            $date = new \DateTime();
            $dateCreation = $date->format('Y-m-d');

            if(!$res){
                $insert = $bdd->prepare("INSERT INTO  entite (nom, ville, code_postal, longitude, latitude, date_creation) VALUES ( :nom, :ville, :codePostal, :Longitude,:Latitude, :dateCreation );");
                $insert -> bindParam(':nom', $nom);
                $insert -> bindParam(':ville', $ville);
                $insert -> bindParam(':codePostal', $codePostal);
                $insert -> bindParam(':Longitude', $latY);
                $insert -> bindParam(':Latitude', $lanX);
                $insert -> bindParam(':dateCreation', $dateCreation);

                $insert->execute();
            }


            $retour = $this->routingMatrix($coord, $villes);
        }
        else{

            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        return $retour;

    }

    //Calcul du scénario équitable
    public function scenarioEquitable($idGroupe)
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

        //on récupère le tableau des villes
        $villes = $this->index($idGroupe);
        $T2 = []; //tableau interm�diaire qui contient les coordonnees des pts d arrivees

        $lesDistances = []; // la somme des distances
        $lesDurees = []; // la somme des durees
        $lesPtsDeparts = []; // tableau qui contient tous les points de depart
        $coordonneesVilles = [];

        $distanceDest = []; //tableau qui contient toutes les distances vers les destinations d un point de d�part
        $dureeDest = []; //tableau qui contient toutes les dur�es vers les destinations d un point de d�part
        $coordonneesDest = []; //tableau qui contient toutes les coordonn�es vers les destinations d un point de d�part
        $sommeDistances = [];
        $sommeDurees = [];
        $distancesMax = [];//tableau qui contient toutes les distances maxi
        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; ++$i) {
            $start = $villes[0];

            unset($villes[0]);
            $T2 = array_values($villes);


            $Coordonnes = explode("%2C", $start);
            $lanY  = $Coordonnes[0];
            $lanX = $Coordonnes[1];

            $resultat = $this->calculRoute($lanX, $lanY, $T2);

            $distanceDest = $resultat[0];
            $dureeDest = $resultat[1];
            $coordonneesDest = $T2;
            $sommeDistanceDep = array_sum($distanceDest);
            $sommeDureeDep = array_sum($dureeDest);
            $distanceMax = max($distanceDest);

            //on groupe les résultats de tous les cas possibles!

              array_push($lesDistances, $distanceDest);
              array_push($lesDurees, $dureeDest);
              array_push($coordonneesVilles, $coordonneesDest);
              array_push($lesPtsDeparts, $start);
              array_push($sommeDistances, $sommeDistanceDep);
              array_push($sommeDurees, $sommeDureeDep);
              array_push($distancesMax, $distanceMax);


            array_push($T2, $start);
            $villes = $T2;
        }//fin parcourir longuerTab

        //Min des distances Max
        $distanceMin = min($distancesMax);
        $key = array_search($distanceMin, $distancesMax);//on récupère la position de la somme min

        $coord = $lesPtsDeparts[$key]; //on récupère le point de depart

        $distanceTotale = $sommeDistances[$key];//on recupere la somme des tistances pour notre ville de depart
        $distanceTotale = $distanceTotale/1000;
        $distanceTotale = round($distanceTotale,0);//on fait l'arrondie de la distance totale

        $dureeTotale = $sommeDurees[$key];//on recupere la somme des durees trajets pour notre ville de depart

        //Nom de la ville de depart
        $coordVille = explode('%2C', $coord);

        $lanX = $coordVille[0];
        $latY = $coordVille[1];

        $stmt1 = $bdd->prepare("SELECT ville_nom,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                                    from villes_france_free
                                    order by Proximite limit 1;");
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);
        $villeDepart = $result['ville_nom'];

        $mesVillesXY = $coordonneesVilles[$key];
        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($mesVillesXY);

        //distance ville
        $distVille = $lesDistances[$key];
        $dureeVille = $lesDurees[$key];

        $retour = [];

        $retour[0] = $villeDepart;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distanceTotale;
        $retour[4] = $dureeTotale;
        $retour[5] = $mesVillesXY;
        $retour[6] = $mesVilles;
        $retour[7] = $distVille;
        $retour[8] = $dureeVille;


        return $retour;
    }

    public function routingMatrix($coord, $villes)
    {

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;


        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        //////////////////////
        $stmt1 = $bdd->prepare("SELECT ville from entite where longitude=:longitude and latitude = :latitude ;");

        $stmt1 -> bindParam(':longitude', $lanX);
        $stmt1 -> bindParam(':latitude', $latY);
        $stmt1->execute();
        $result = $stmt1->fetchColumn();

        $barycentreVille = $result;

     //   $start = $coord;

//        $retourRoutingMatrixUnStart = $this->routingMatrixUnStart($start, $villes);
        $calculRoute = $this->calculRoute($lanX, $latY, $villes);

        $distanceTotale = $calculRoute[0];
        $dureeTotale = $calculRoute[1];

        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($villes);

        //somme des distances
        $distance = array_sum($distanceTotale) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);



        $retour = [];

        $retour[0] = $barycentreVille;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $villes;
        $retour[6] = $mesVilles;
        $retour[7] = $distanceTotale;
        $retour[8] = $dureeTotale;


        return $retour;
    }

    public function terrainNeutre($idGroupe)
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;


        $equipe = $this->index($idGroupe);

        $listeLieux = $this->getListeLieux($idGroupe);
        $terrainNeutre = $listeLieux[1];
        $listeTerrain = $listeLieux[0];

       // $terrainNeutre = ['48.74305%2C2.4014', '47.48569%2C-3.11922', '43.5732938%2C6.8188967', '47.724709%2C-0.5227929', '49.12878%2C6.22851'];


        $toutesLesDistances = [];
        $toutesLesDurees = [];
        $tousLesCalculs = [];
        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];

            $start = explode('%2C', $start);
            $latY  = $start[0];
            $lanX = $start[1];


            $calculRoute = $this->calculRoute($lanX, $latY, $equipe);

            $distanceTotale = $calculRoute[0];
            $dureeTotale = $calculRoute[1];

            array_push($toutesLesDistances, $distanceTotale);
            array_push($toutesLesDurees, $dureeTotale);

        }

        $tousLesCalculs[0] = $toutesLesDistances;
        $tousLesCalculs[1] = $toutesLesDurees;


        $sommesDistances = [];
        for ($j = 0; $j < count($tousLesCalculs[0]); $j++) {
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
        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' . $lanX . '%2C' . $latY . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

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
        $retour[7] = $distanceTotale;
        $retour[8] = $dureeTotale;
        $retour[9] = $listeTerrain;



        return $retour;
    }

    public function terrainNeutreEquitable($idGroupe)
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;

//        $equipe = ['43.88953%2C-0.49893', '47.19126%2C-1.5698', '47.46317%2C-0.59261', '47.086%2C2.39315', '49.76019%2C4.71909', '43.70821%2C7.29597'];
        $equipe = $this->index($idGroupe);

        $listeLieux = $this->getListeLieux($idGroupe);
        $terrainNeutre = $listeLieux[1];

        // $terrainNeutre = ['48.74305%2C2.4014', '47.48569%2C-3.11922', '43.5732938%2C6.8188967', '47.724709%2C-0.5227929', '49.12878%2C6.22851'];

        $toutesLesDistances = [];
        $toutesLesDurees = [];
        $tousLesCalculs = [];
        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];


//            $retourRoutingMatrixUnStart = $this->routingMatrixUnStart($start, $equipe);
//
//            $distanceTotal = $retourRoutingMatrixUnStart[0];
//            $dureeTotale = $retourRoutingMatrixUnStart[1];

            $start = explode('%2C', $start);
            $latY = $start[0];
            $lanX = $start[1];


            $calculRoute = $this->calculRoute($lanX, $latY, $equipe);

            $distanceTotal = $calculRoute[0];
            $dureeTotale = $calculRoute[1];

            array_push($toutesLesDistances, $distanceTotal);
            array_push($toutesLesDurees, $dureeTotale);

        }

        $tousLesCalculs[0] = $toutesLesDistances;
        $tousLesCalculs[1] = $toutesLesDurees;


        $distancesMax = [];
        for ($j = 0; $j < count($tousLesCalculs[0]); $j++) {
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
        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' . $lanX . '%2C' . $latY . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

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

    //TODO: verifier si j'ai les bons xy des villes!!
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

            $char = array("-", "_", "'");
            $nomVille = str_replace($char, " ", $nomVille);

            //iconv — Convertit une chaîne dans un jeu de caractères
//            $nomVille = iconv('utf-8', 'ASCII//IGNORE//TRANSLIT', $nomVille);

            //chercher l'id de la ville selon la table de reference

            $reqID = $bdd->prepare("SELECT ville_id FROM villes_france_free where ville_nom_simple LIKE '$nomVille%' AND  ville_code_postal LIKE '$codePostal%'; ");

            $reqID->execute();
            $idVille = $reqID->fetchColumn();

            //remplace les espaces vides dans les noms des villes par '%20' selon la syntaxe de la req Here
            $nomVille = urlencode($nomVille);

            $reqGeocode = 'http://geocoder.api.here.com/6.2/geocode.json?country=France&city=' . $nomVille . '&postalCode=' . $codePostal . '&app_id=' . $app_id . '&app_code=' . $app_code . '&gen=8';

            $reqGeocodeJson = file_get_contents($reqGeocode);

            $reqGeocodeArray = json_decode($reqGeocodeJson, true);

            $Latitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Latitude'];
            $Longitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Longitude'];
            if (isset($Longitude, $Latitude, $idVille)) {
//                $city = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['Address']['City'];
//                $city = addslashes($city);
//                $PostalCode = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['Address']['PostalCode'];

                $insert = $bdd->prepare("INSERT INTO  villes (id, longitude, latitude) VALUES ( :idVille, :Longitude,:Latitude);");
                $insert -> bindParam(':idVille', $idVille);
                $insert -> bindParam(':Longitude', $Longitude);
                $insert -> bindParam(':Latitude', $Latitude);

                $insert->execute();
            } else {
                continue;
            }

        }
        return true;


    }


    public function nomsVilles($idGroupe)
    {

        //params de connexion

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        $reqVilles = $bdd->prepare("SELECT equipes FROM  groupe where id = :idGroupe ;");
        $reqVilles -> bindParam(':idGroupe', $idGroupe);
        $reqVilles->execute();
        $reqVilles = $reqVilles->fetchColumn();
        $reqVilles = explode(",", $reqVilles);

        $villes = [];

        for ($i = 0; $i < count($reqVilles); $i++) {
            $stmt = $bdd->prepare("SELECT ville FROM  entite WHERE id = :idEntite ;");
            $stmt -> bindParam(':idEntite', $reqVilles[$i]);
            $stmt->execute();
            $nomVille = $stmt->fetchColumn();
            array_push($villes, $nomVille);

        }


        return $villes;


    }

    public function routingMatrixUnStart($start, $villes)
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $sousTabVilles = array_chunk($villes, 50, true);


        $distanceTotal = [];
        $dureeTotale = [];
        for ($i = 0; $i < count($sousTabVilles); $i++) {

            $sousTab = $sousTabVilles[$i];
            //on parcourt tous les éléments du deuxième tableau: long + lat


            //on fait appel à la première partie de l'url here
            $maps_url = 'https://route.st.nlp.nokia.com/routing/6.2/calculatematrix.json?mode=fastest%3Bcar%3Btraffic%3Aenabled%3B&start0=' . $start;
            $xy = '';
            $j = 0;
            foreach ($sousTab as $key => $value) {


//                echo "Clé : $key; Valeur : $value<br />\n";
                $elt = $value;
                $xy .= '&destination' . $j . '=';
                $xy .= $elt;
                $j = $j + 1;
            }


            $maps_url .= $xy;


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
            $distanceTotal = array_merge($distanceTotal, $tabDistance);
            $dureeTotale = array_merge($dureeTotale, $tabDuree);

        }//fin boucle tous les blocs de villes

        $retour = [];
        $retour[0] = $distanceTotal;
        $retour[1] = $dureeTotale;

        return $retour;


    }//fin fn routingMatrix

    public function mesVilles($villes)
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

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($villes); ++$l) {

            $start = explode('%2C', $villes[$l]);
            $lanX = $start[0];
            $latY = $start[1];
         /*   $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' . $villes[$l] . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

            $coor_json = file_get_contents($coor_url);

            $coor_array = json_decode($coor_json, true);

            $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];
*/
            $stmt1 = $bdd->prepare("SELECT ville from entite where longitude = $latY AND latitude = $lanX;");
            $stmt1->execute();
            $maVille = $stmt1->fetchColumn();


            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);
        }
        return $mesVilles;
    }

    public function creerGroupe($villes, $nomGroupe, $idListeParticipants, $idListeLieux)
    {

        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        $nbrVilles = count($villes);
        $villes = implode(",", $villes);


        $dateCreation = date("Y-m-d");
        //TODO: idUtilisateur sera dynamique selon la personne connectée
        $idUtilisateur = 1;


        $reqGroupe = $bdd->prepare("INSERT INTO  groupe (id_utilisateur, nom, equipes, date_creation,nb_participants, id_liste_participant, id_liste_lieux)
                            VALUES ( :idUtilisateur, :nomGroupe, :equipes, :dateCreation, :nbParticipants, :idListeParticipants, :idListeLieux);");
        $reqGroupe->bindParam(':idUtilisateur', $idUtilisateur);
        $reqGroupe->bindParam(':nomGroupe', $nomGroupe);
        $reqGroupe->bindParam(':equipes', $villes);
        $reqGroupe->bindParam(':dateCreation', $dateCreation);
        $reqGroupe->bindParam(':nbParticipants', $nbrVilles);
        $reqGroupe->bindParam(':idListeParticipants', $idListeParticipants);
        $reqGroupe->bindParam(':idListeLieux', $idListeLieux);
        $reqGroupe->execute();
        $idGroupe = $bdd->lastInsertId();
        $this->index($idGroupe);


        return $idGroupe;


    }

    public function geocoderUneVille($idVille)
    {

//        error_log("geocoderUneVille $idVille $nomVille $codePostal");


        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        /* $nomVille = urlencode($nomVille);


         $reqGeocode = 'http://geocoder.api.here.com/6.2/geocode.json?country=France&city=' . $nomVille . '&postalCode=' . $codePostal . '&app_id=' . $app_id . '&app_code=' . $app_code . '&gen=8';


         $curl = curl_init($reqGeocode);
         curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($curl, CURLOPT_FAILONERROR, true);

         $curl_response = curl_exec($curl);



         if ($curl_response === false) {
             $info = curl_getinfo($curl);
             curl_close($curl);
             error_log(print_R($info, TRUE), 3, "error_log_optimouv.txt");

             die('Une erreur interne est survenue. Veuillez recharger l\'application. ');

         }
         curl_close($curl);
         $decoded = json_decode($curl_response, true);
         if (isset($decoded->response->status) && $decoded->response->status == 'ERROR') {
             die('Erreur: ' . $decoded->response->errormessage);
         }


         $Latitude = $decoded['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Latitude'];
         $Longitude = $decoded['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Longitude'];
 */
        $reqVille = $bdd->prepare("SELECT ville_latitude_deg, ville_longitude_deg FROM villes_france_free where ville_id = :idVille;");
        $reqVille->bindParam(':idVille', $idVille);
        $reqVille->execute();
        $row = $reqVille->fetch(PDO::FETCH_ASSOC);
        $Latitude = $row['ville_latitude_deg'];
        $Longitude = $row['ville_longitude_deg'];


        /*
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');


        if (isset($Latitude, $Longitude)) {
            $insert = $bdd->prepare("INSERT INTO  villes (id, longitude, latitude, date_creation) VALUES ( :idVille, :Longitude,:Latitude, :dateCreation);");
            $insert->bindParam(':idVille', $idVille);
            $insert->bindParam(':Longitude', $Longitude);
            $insert->bindParam(':Latitude', $Latitude);
            $insert->bindParam(':dateCreation', $dateCreation);
            $insert->execute();

            return true;
        } else {
            return false;
        }

        */

        $retour = [];
        $retour[0]=$Latitude;
        $retour[1]=$Longitude;

        return $retour;

    }

    public function calculRoute($lanX, $latY, $villes)
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

        $stmt1 = $bdd->prepare("SELECT id from entite where longitude= :longitude and latitude = :latitude ;");
        $stmt1 -> bindParam(':longitude', $lanX);
        $stmt1 -> bindParam(':latitude', $latY);
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

        $idStart = $result['id'];

        $coordStart =  $latY. '%2C' .$lanX ;

        $distanceTotale = [];
        $dureeTotale = [];

        //parcourir tout le tableau des villes
        for ($i = 0; $i < count($villes); $i++) {


            $maVille = $villes[$i];

            $coordVille = explode('%2C', $maVille);


            try {

                $Y = $coordVille[0];
                $X = $coordVille[1];

                //recuperer l id de la ville

                $reqID = $bdd->prepare("SELECT id FROM entite where longitude = :X AND  latitude= :Y;");
                $reqID->bindParam(':X', $X);
                $reqID->bindParam(':Y', $Y);
                $reqID->execute();
                $idVille = $reqID->fetchColumn();


                //tester si on a deja le calcul de trajet entre le point start et notre point actuel

                $req = $bdd->prepare("SELECT distance, duree FROM trajet where depart = :idStart AND destination = :idVille;");
                $req->bindParam(':idStart', $idStart);
                $req->bindParam(':idVille', $idVille);
                $req->execute();
                $res = $req->fetch(PDO::FETCH_ASSOC);

                if ($res) {

                    $distance = $res['distance'];
                    $duree = $res['duree'];
                    array_push($distanceTotale, $distance);
                    array_push($dureeTotale, $duree);

                } else {
                    $reqRoute = 'http://route.api.here.com/routing/7.2/calculateroute.json?waypoint0=' . $coordStart . '&waypoint1=' . $villes[$i] . '&mode=fastest%3Bcar%3Btraffic%3Adisabled&app_id=' . $app_id . '&app_code=' . $app_code;

                    $curl = curl_init($reqRoute);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_FAILONERROR, true);

                    $curl_response = curl_exec($curl);

                    if ($curl_response === false) {
                        $info = curl_getinfo($curl);
                        curl_close($curl);
                        error_log(print_R($info, TRUE), 3, "error_log_optimouv.txt");

                        die('Une erreur interne est survenue. Veuillez recharger l\'application. ');

                    }
                    curl_close($curl);
                    $decoded = json_decode($curl_response, true);
                    if (isset($decoded->response->status) && $decoded->response->status == 'ERROR') {
                        die('Erreur: ' . $decoded->response->errormessage);
                    }

                    $distance = $decoded['response']['route'][0]['summary']['distance'];
                    $duree = $decoded['response']['route'][0]['summary']['baseTime'];

                    //recuperer la date du jour
                    $date = new \DateTime();
                    $dateCreation = $date->format('Y-m-d');

                    //insérer dans la base la distance + la duree

                    if (isset($distance, $duree)) {
                        $insert = $bdd->prepare("INSERT INTO  trajet (depart, destination, distance, duree, date_creation) VALUES ( :idStart, :idVille, :distance,:duree, :dateCreation);");
                        $insert -> bindParam(':idStart', $idStart);
                        $insert -> bindParam(':idVille', $idVille);
                        $insert -> bindParam(':distance', $distance);
                        $insert -> bindParam(':duree', $duree);
                        $insert -> bindParam(':dateCreation', $dateCreation);
                        $insert->execute();

                    }

                    array_push($distanceTotale, $distance);
                    array_push($dureeTotale, $duree);
                }
            } catch (Exception $e) {
                echo 'Exception reçue : ', $e->getMessage(), "\n";
            }


        }

        $retour = [];
        $retour[0] = $distanceTotale;
        $retour[1] = $dureeTotale;


        return $retour;

    }

    public function getListeLieux($idGroupe)
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

        $reqLieux = $bdd->prepare("SELECT id_liste_lieux FROM  groupe WHERE id = :id;");
        $reqLieux->bindParam(':id', $idGroupe);
        $reqLieux->execute();
        $reqLieux = $reqLieux->fetchColumn();

        $idListeLieux = intval($reqLieux);


        if(isset($reqLieux)){

             $listeLieux = $bdd->prepare("SELECT lieux FROM  liste_lieux WHERE id = :id ;");
             $listeLieux->bindParam(':id', $idListeLieux);
             $listeLieux->execute();
             $listeLieux = $listeLieux->fetchColumn();

             //convertir la chaine en chaine
            $listeLieux = explode(",", $listeLieux);


            //récuprer les noms de villes de terrains neutres

            //$nomsVilles = $bdd->prepare("SELECT ville FROM  entite WHERE find_in_set (id, :listeLieux)");

            $nomsVilles = [];
            $coordVilles = [];


            for ($i = 0; $i < count($listeLieux); $i++) {
                $stmt = $bdd->prepare("SELECT id, ville, code_postal, longitude, latitude FROM  entite WHERE id = $listeLieux[$i];");

                //$stmt->bindParam(':id', $listeLieux[$i]);
                $stmt->execute();
                 while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                    $idVille = $row['id'];
                    $lat = $row['latitude'];
                    $long = $row['longitude'];
                    $ville = $row['ville'];
                    $codePostal = $row['code_postal'];

                     if($long && $lat){
                         $coordVille =  $lat. '%2C' .$long ;
                         array_push($nomsVilles, $ville);
                         array_push($coordVilles, $coordVille);
                     }
                     else{



                         $reqGeocode = 'http://geocoder.api.here.com/6.2/geocode.json?country=France&city=' . $ville . '&postalCode=' . $codePostal . '&app_id=' . $app_id . '&app_code=' . $app_code . '&gen=8';

                         $reqGeocodeJson = file_get_contents($reqGeocode);

                         $reqGeocodeArray = json_decode($reqGeocodeJson, true);

                         $Latitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Latitude'];
                         $Longitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Longitude'];

                         $coordVille = $Latitude . '%2C' . $Longitude;

                         $update = $bdd->prepare("UPDATE entite SET longitude = $Longitude, latitude= $Latitude WHERE id = $idVille");
                         $update->execute();

                         array_push($nomsVilles, $ville);
                         array_push($coordVilles, $coordVille);


                     }



                }


            }
            $retour = [];

            $retour[0] = $nomsVilles;
            $retour[1] = $coordVilles;

            return $retour;


        }



    }

}


