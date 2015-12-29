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

    public function connexion()
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

        return $bdd;
    }

    public function index($idGroupe)
    {
        $bdd= $this->connexion();

        $reqVilles = $bdd->prepare("SELECT equipes FROM  groupe WHERE id = :id ;");
        $reqVilles->bindParam(':id', $idGroupe);
        $reqVilles->execute();
        $listeVilles = $reqVilles->fetchColumn();
        $reqVilles = explode(",", $listeVilles);


        $villes = [];
        $villesPasRencontre = [];
        $identites = [];
        $identitesPasRencontre = [];

        $lieuRencontrePossible = 1;

        for ($i = 0; $i < count($reqVilles); $i++) {

            //on teste si toutes les entites sont géocodées
            $stmt = $bdd->prepare("SELECT id, longitude, latitude, id_ville_france FROM  entite WHERE id = :id ");
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

            }

        }


        //$villes va contenir toutes les entites qui peuvent accepter des matchs
        $stmt = $bdd->prepare("SELECT id, longitude, latitude FROM  entite WHERE find_in_set (id, :id) AND lieu_rencontre_possible = :lieuRencontre");
        $stmt->bindParam(':id', $listeVilles);
        $stmt->bindParam(':lieuRencontre', $lieuRencontrePossible);
        $stmt->execute();

        while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $lat = $res['latitude'];
            $long = $res['longitude'];
            $idEntite = $res['id'];
            array_push($identites, $idEntite);

            $coordonne = $lat . "%2C" . $long;

            array_push($villes, $coordonne);

        }

        //$villesPasRencontre va contenir toutes les entites qui ne peuvent pas accepter des matchs
        $stmt = $bdd->prepare("SELECT id, longitude, latitude FROM  entite WHERE find_in_set (id, :id) AND lieu_rencontre_possible <> :lieuRencontre");
        $stmt->bindParam(':id', $listeVilles);
        $stmt->bindParam(':lieuRencontre', $lieuRencontrePossible);
        $stmt->execute();

        while ($res = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $lat = $res['latitude'];
            $long = $res['longitude'];
            $idEntite = $res['id'];
            array_push($identitesPasRencontre, $idEntite);

            $coordonne = $lat . "%2C" . $long;

            array_push($villesPasRencontre, $coordonne);

        }

        $retour = [];

        $retour[0] = $villes;
        $retour[1] = $villesPasRencontre;
        $retour[2] = $identites;
        $retour[3] = $identitesPasRencontre;

        return $retour;
    }

    //Calcul du meilleur lieu de rencontre
    public function meilleurLieuRencontre($idGroupe)
    {

        $bdd= $this->connexion();

        //Récupération de détail de la liste de lieux

        $listeLieux = $this->getListeLieux($idGroupe);

        $nomsTerrainsNeutres = $listeLieux[0];

        //on récupère le tableau des villes
        $retourIndex = $this->index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $retourIndex[2];
        $idsEntitesPasRencontre = $retourIndex[3];
        $idsEntitesMerge = array_merge($idsEntites, $idsEntitesPasRencontre );


        $villes = $retourIndex[0];
        $villesPasRencontre = $retourIndex[1];

        //        $result = array_merge($villes, $villesPasRencontre);

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
            $villesRencontre = array_values($villes);
            $T2 = array_merge($villesRencontre, $villesPasRencontre);

            $Coordonnes = explode("%2C", $start);
            $lanY = $Coordonnes[0];
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


            array_push($villesRencontre, $start);
            $villes = $villesRencontre;
        }//fin parcourir longuerTab

        //Min Somme des distances
        $distanceMin = min($sommeDistances);
        $key = array_search($distanceMin, $sommeDistances);//on récupère la position de la somme min

        $coord = $lesPtsDeparts[$key]; //on récupère le point de depart

        $distanceTotale = $sommeDistances[$key];//on recupere la somme des tistances pour notre ville de depart
        $distanceTotale = $distanceTotale / 1000;
        $distanceTotale = round($distanceTotale, 0);//on fait l'arrondie de la distance totale

        $dureeTotale = $sommeDurees[$key];//on recupere la somme des durees trajets pour notre ville de depart

        //Nom de la ville de depart
        $coordVille = explode('%2C', $coord);

        $lanX = $coordVille[0];
        $latY = $coordVille[1];

        $stmt1 = $bdd->prepare("SELECT ville from entite where longitude = :longitude AND latitude = :latitude");
        $stmt1->bindParam(':longitude', $latY);
        $stmt1->bindParam(':latitude', $lanX);
        $stmt1->execute();
        $villeDepart = $stmt1->fetchColumn();


        $mesVillesXY = $coordonneesVilles[$key];
        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($mesVillesXY);

        //distance ville
        $distVille = $lesDistances[$key];
        $dureeVille = $lesDurees[$key];



        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = $this->getNombreParticipants($idsEntites);


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
        $retour[10] = $nbrParticipants;





        // obtenir la distance totale pour toutes équipes
        $distanceTotale = $this->getDistanceTotale($distVille, $nbrParticipants);


        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;

        # ajouter le nombre de participants dans les résultats
        $retour["nbrParticipantsTotal"] = $this->getTotalNombreParticipants($nbrParticipants);


        return $retour;
    }

    //Calcul du barycentre
    public function Barycentre($idGroupe)
    {

        $bdd= $this->connexion();

        //on récupère le tableau des villes
        $villes = $this->index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $villes[2];
        $idsEntitesPasRencontre = $villes[3];
        $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );

        $villes = array_merge($villes[0], $villes[1]);

        $length = count($villes);
        $lan = $lat = null;
        for ($i = 0; $i < $length; $i++) {

            $Coordonnes = explode("%2C", $villes[$i]);
            $lan += $Coordonnes[0];
            $lat += $Coordonnes[1];

        }

        $lanX = $lan / $length;
        $latY = $lat / $length;


        $stmt1 = $bdd->prepare("SELECT ville_nom, ville_longitude_deg, ville_latitude_deg, ville_code_postal,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                                from villes_france_free
                                order by Proximite limit 1;");
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

        $latY = $result['ville_latitude_deg'];
        $lanX = $result['ville_longitude_deg'];
        $ville = $result['ville_nom'];
        $codePostal = $result['ville_code_postal'];
        $nom = 'Barycentre_Groupe_' . $idGroupe;
        //recuperer la date du jour
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');

        //vérifier si le barycentre existe deja
        $barycentre = $bdd->prepare("SELECT id from entite where longitude = :longitude AND latitude = :latitude");
        $barycentre->bindParam(':longitude', $lanX);
        $barycentre->bindParam(':latitude', $latY);
        $barycentre->execute();
        $res = $barycentre->fetchColumn();


        if (!$res) {
            $insert = $bdd->prepare("INSERT INTO  entite (nom, ville, code_postal, longitude, latitude, date_creation) VALUES ( :nom, :ville, :codePostal, :Longitude,:Latitude, :dateCreation );");
            $insert->bindParam(':nom', $nom);
            $insert->bindParam(':ville', $ville);
            $insert->bindParam(':codePostal', $codePostal);
            $insert->bindParam(':Longitude', $lanX);
            $insert->bindParam(':Latitude', $latY);
            $insert->bindParam(':dateCreation', $dateCreation);

            $insert->execute();
        }

        $coord = $lanX . '%2C' . $latY; // pour appel la fn routing matrix


        $retour = $this->routingMatrix($coord, $villes, $idsEntitesMerge);




        # ajouter le nombre de participants dans les résultats
        $retour["nbrParticipantsTotal"] = $this->getTotalNombreParticipants($retour[9]);


        return $retour;
    }

    //Calcul exclusion géographique
    public function Exclusion($valeurExclusion, $idGroupe)
    {
        $bdd= $this->connexion();

        if ($valeurExclusion) {

            //on récupère le tableau des villes
            $villes = $this->index($idGroupe);

            # récupérer l'ids de toutes les entités
            $idsEntites = $villes[2];
            $idsEntitesPasRencontre = $villes[3];
            $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );

            $villes = array_merge($villes[0], $villes[1]);

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


            $stmt1 = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg,ville_code_postal, ville_population_2012,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                          from villes_france_free
                          where ville_population_2012 < :valeurExclusion
                          order by Proximite limit 1;");
            $stmt1->bindParam(':valeurExclusion', $valeurExclusion);

            $stmt1->execute();
            $result = $stmt1->fetch(PDO::FETCH_ASSOC);

            $lanX = $result['ville_latitude_deg'];
            $latY = $result['ville_longitude_deg'];
            $coord = $latY . '%2C' . $lanX;


            //vérifier si le barycentre existe deja
            $barycentre = $bdd->prepare("SELECT id from entite where longitude = :longitude AND latitude = :latitude");
            $barycentre->bindParam(':longitude', $latY);
            $barycentre->bindParam(':latitude',  $lanX);
            $barycentre->execute();
            $res = $barycentre->fetchColumn();

            $nom = 'Barycentre_Groupe_' . $idGroupe;
            //recuperer la date du jour
            $date = new \DateTime();
            $dateCreation = $date->format('Y-m-d');

            if (!$res) {
                $insert = $bdd->prepare("INSERT INTO  entite (nom, ville, code_postal, longitude, latitude, date_creation) VALUES ( :nom, :ville, :codePostal, :Longitude,:Latitude, :dateCreation );");
                $insert->bindParam(':nom', $nom);
                $insert->bindParam(':ville', $ville);
                $insert->bindParam(':codePostal', $codePostal);
                $insert->bindParam(':Longitude', $latY);
                $insert->bindParam(':Latitude', $lanX);
                $insert->bindParam(':dateCreation', $dateCreation);

                $insert->execute();
            }


            $retour = $this->routingMatrix($coord, $villes, $idsEntitesMerge);

            # ajouter le nombre de participants dans les résultats
            $retour["nbrParticipantsTotal"] = $this->getTotalNombreParticipants($retour[9]);


        } else {

            error_log("service: rencontres, function: Exclusion ", 3, "error_log_optimouv.txt");
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        return $retour;

    }

    //Calcul du scénario équitable
    public function scenarioEquitable($idGroupe)
    {
        $bdd= $this->connexion();
        //on récupère le tableau des villes
        $retourIndex = $this->index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $retourIndex[2];

        $villes = $retourIndex[0];
        $villesPasRencontre = $retourIndex[1];

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

//            unset($villes[0]);
//            $T2 = array_values($villes);

            unset($villes[0]);
            $villesRencontre = array_values($villes);
            $T2 = array_merge($villesRencontre, $villesPasRencontre);


            $Coordonnes = explode("%2C", $start);
            $lanY = $Coordonnes[0];
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


//            array_push($T2, $start);
//            $villes = $T2;
            array_push($villesRencontre, $start);
            $villes = $villesRencontre;
        }//fin parcourir longuerTab

        //Min des distances Max
        $distanceMin = min($distancesMax);
        $key = array_search($distanceMin, $distancesMax);//on récupère la position de la somme min

        $coord = $lesPtsDeparts[$key]; //on récupère le point de depart

        $distanceTotale = $sommeDistances[$key];//on recupere la somme des tistances pour notre ville de depart
        $distanceTotale = $distanceTotale / 1000;
        $distanceTotale = round($distanceTotale, 0);//on fait l'arrondie de la distance totale

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


        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = $this->getNombreParticipants($idsEntites);

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
        $retour[9] = $nbrParticipants;


        // obtenir la distance totale pour toutes équipes
        $distanceTotale = $this->getDistanceTotale($distVille, $nbrParticipants);

        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;

        return $retour;
    }

    public function routingMatrix($coord, $villes, $idsEntites)
    {

        $bdd= $this->connexion();

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];


        //////////////////////
        $stmt1 = $bdd->prepare("SELECT ville from entite where longitude=:longitude and latitude = :latitude ;");

        $stmt1->bindParam(':longitude', $lanX);
        $stmt1->bindParam(':latitude', $latY);
        $stmt1->execute();
        $result = $stmt1->fetchColumn();

        $barycentreVille = $result;

        if (!$barycentreVille) {

            $stmt1 = $bdd->prepare("SELECT ville_nom, ville_code_postal,
                        (6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                        from villes_france_free
                        order by Proximite limit 1;");

            $stmt1->execute();
            $barycentreVille = $stmt1->fetchColumn();

        }

         $calculRoute = $this->calculRoute($lanX, $latY, $villes);

        $distanceEquipe = $calculRoute[0];
        $dureeEquipe = $calculRoute[1];

        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($villes);

        //somme des distances
        $distance = array_sum($distanceEquipe) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeEquipe);

        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = $this->getNombreParticipants($idsEntites);


        // obtenir la distance totale pour toutes équipes
        $distanceTotale = $this->getDistanceTotale($distanceEquipe, $nbrParticipants);


        $retour = [];

        $retour[0] = $barycentreVille;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $villes;
        $retour[6] = $mesVilles;
        $retour[7] = $distanceEquipe;
        $retour[8] = $dureeEquipe;
        $retour[9] = $nbrParticipants;
        $retour[10] = $distanceTotale;


        return $retour;
    }

    public function terrainNeutre($idGroupe)
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;

        # obtenir le nombre de participants pour cette groupe
        $nbrParticipants = $this->getParticipantsPourGroupe($idGroupe);

        $equipe = $this->index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $equipe[2];
        $idsEntitesPasRencontre = $equipe[3];
        $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );


        $equipe = array_merge($equipe[0], $equipe[1]);

        $listeLieux = $this->getListeLieux($idGroupe);
        $terrainNeutre = $listeLieux[1];
        $listeTerrain = $listeLieux[0];

        $toutesLesDistances = [];
        $toutesLesDurees = [];
        $tousLesCalculs = [];
        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];

            $start = explode('%2C', $start);
            $latY = $start[0];
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

        $distanceVilles = $tousLesCalculs[0][$key];
        $dureeTotale = $tousLesCalculs[1][$key];

        //somme des distances
        $distance = array_sum($distanceVilles) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);


        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($equipe);

        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' . $lanX . '%2C' . $latY . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

        $coor_array = $this->getReponseCurl($coor_url);

        if (isset($coor_array->response->status) && $coor_array->response->status == 'ERROR') {
            die('Erreur: ' . $coor_array->response->errormessage);
        }

        $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];


        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = $this->getNombreParticipants($idsEntitesMerge);

        $retour = [];

        $retour[0] = $maVille;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $equipe;
        $retour[6] = $mesVilles;
        $retour[7] = $distanceVilles;
        $retour[8] = $dureeTotale;
        $retour[9] = $listeTerrain;
        $retour[10] = $nbrParticipants;


        // obtenir la distance totale pour toutes équipes
        $distanceTotale = $this->getDistanceTotale($distanceVilles, $nbrParticipants);

        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;

        # ajouter le nombre de participants dans les résultats
        $retour["nbrParticipantsTotal"] = $this->getTotalNombreParticipants($nbrParticipants);

        return $retour;
    }

    public function terrainNeutreEquitable($idGroupe)
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $equipe = $this->index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $equipe[2];
        $idsEntitesPasRencontre = $equipe[3];
        $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );

        $equipe = array_merge($equipe[0], $equipe[1]);

        $listeLieux = $this->getListeLieux($idGroupe);
        $terrainNeutre = $listeLieux[1];

        $toutesLesDistances = [];
        $toutesLesDurees = [];
        $tousLesCalculs = [];
        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];

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
        $distanceVilles = $tousLesCalculs[0][$key];
        $dureeTotale = $tousLesCalculs[1][$key];

        //somme des distances
        $distance = array_sum($distanceVilles) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeTotale);

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        //Récupérer les noms de villes de destination
        $mesVilles = $this->mesVilles($equipe);

        $coor_url = 'http://reverse.geocoder.api.here.com/6.2/reversegeocode.json?prox=' . $lanX . '%2C' . $latY . '&mode=retrieveAddresses&maxresults=1&gen=8&app_id=' . $app_id . '&app_code=' . $app_code;

        $coor_array = $this->getReponseCurl($coor_url);

        if (isset($coor_array->response->status) && $coor_array->response->status == 'ERROR') {
            die('Erreur: ' . $coor_array->response->errormessage);
        }

        $maVille = $coor_array['Response']['View'][0]['Result'][0]['Location']['Address']['City'];

        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = $this->getNombreParticipants($idsEntitesMerge);


        $retour = [];

        $retour[0] = $maVille;
        $retour[1] = $lanX;
        $retour[2] = $latY;
        $retour[3] = $distance;
        $retour[4] = $duree;
        $retour[5] = $equipe;
        $retour[6] = $mesVilles;
        $retour[7] = $distanceVilles;
        $retour[8] = $dureeTotale;
        $retour[9] = $nbrParticipants;

        // obtenir la distance totale pour toutes équipes
        $distanceTotale = $this->getDistanceTotale($distanceVilles, $nbrParticipants);

        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;


        return $retour;
    }

    public function nomsVilles($idGroupe)
    {

        //params de connexion

        $bdd= $this->connexion();

        $reqVilles = $bdd->prepare("SELECT equipes FROM  groupe where id = :idGroupe ;");
        $reqVilles->bindParam(':idGroupe', $idGroupe);
        $reqVilles->execute();
        $reqVilles = $reqVilles->fetchColumn();
        $reqVilles = explode(",", $reqVilles);

        $villes = [];

        for ($i = 0; $i < count($reqVilles); $i++) {
            $stmt = $bdd->prepare("SELECT ville FROM  entite WHERE id = :idEntite ;");
            $stmt->bindParam(':idEntite', $reqVilles[$i]);
            $stmt->execute();
            $nomVille = $stmt->fetchColumn();
            array_push($villes, $nomVille);

        }

        return $villes;


    }

    public function mesVilles($villes)
    {

        $bdd= $this->connexion();

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($villes); ++$l) {

            $start = explode('%2C', $villes[$l]);
            $lanX = $start[0];
            $latY = $start[1];

            $stmt1 = $bdd->prepare("SELECT ville from entite where longitude = :longitude AND latitude = :latitude");
            $stmt1->bindParam(':longitude', $latY);
            $stmt1->bindParam(':latitude', $lanX);
            $stmt1->execute();
            $maVille = $stmt1->fetchColumn();

            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);
        }
        return $mesVilles;
    }

    public function creerGroupe($villes, $nomGroupe, $idListeParticipants, $idListeLieux, $idUtilisateur)
    {

        $bdd= $this->connexion();

        $nbrVilles = count($villes);
        $villes = implode(",", $villes);

        $dateCreation = date("Y-m-d");

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

        $bdd= $this->connexion();

        $reqVille = $bdd->prepare("SELECT ville_latitude_deg, ville_longitude_deg FROM villes_france_free where ville_id = :idVille;");
        $reqVille->bindParam(':idVille', $idVille);
        $reqVille->execute();
        $row = $reqVille->fetch(PDO::FETCH_ASSOC);
        $Latitude = $row['ville_latitude_deg'];
        $Longitude = $row['ville_longitude_deg'];

        $retour = [];
        $retour[0] = $Latitude;
        $retour[1] = $Longitude;

        return $retour;

    }

    public function calculRoute($lanX, $latY, $villes)
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $bdd= $this->connexion();

        $stmt1 = $bdd->prepare("SELECT id from entite where longitude= :longitude and latitude = :latitude ;");
        $stmt1->bindParam(':longitude', $lanX);
        $stmt1->bindParam(':latitude', $latY);
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

        $idStart = $result['id'];

        $coordStart = $latY . '%2C' . $lanX;

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

                    $decoded = $this->getReponseCurl($reqRoute);


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
                        $insert->bindParam(':idStart', $idStart);
                        $insert->bindParam(':idVille', $idVille);
                        $insert->bindParam(':distance', $distance);
                        $insert->bindParam(':duree', $duree);
                        $insert->bindParam(':dateCreation', $dateCreation);
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

        $bdd= $this->connexion();

        $reqLieux = $bdd->prepare("SELECT id_liste_lieux FROM  groupe WHERE id = :id;");
        $reqLieux->bindParam(':id', $idGroupe);
        $reqLieux->execute();
        $reqLieux = $reqLieux->fetchColumn();

        $idListeLieux = intval($reqLieux);

        if (isset($reqLieux)) {

            $listeLieux = $bdd->prepare("SELECT lieux FROM  liste_lieux WHERE id = :id ;");
            $listeLieux->bindParam(':id', $idListeLieux);
            $listeLieux->execute();
            $listeLieux = $listeLieux->fetchColumn();

            //convertir la chaine en chaine
            $listeLieux = explode(",", $listeLieux);


            $nomsVilles = [];
            $coordVilles = [];


            for ($i = 0; $i < count($listeLieux); $i++) {
                //
                $stmt = $bdd->prepare("SELECT id, ville, code_postal, longitude, latitude FROM  entite WHERE id = :id");
                $stmt->bindParam(':id', $listeLieux[$i]);
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                    $idVille = $row['id'];
                    $lat = $row['latitude'];
                    $long = $row['longitude'];
                    $ville = $row['ville'];
                    $codePostal = $row['code_postal'];

                    // récupérer lat et lon s'il y en a
                    if ($long && $lat) {
                        $coordVille = $lat . '%2C' . $long;
                        array_push($nomsVilles, $ville);
                        array_push($coordVilles, $coordVille);
                    } // sinon il faut interroger le serveur HERE
                    else {

                        $v = urlencode($ville);
                        $reqGeocode = 'http://geocoder.api.here.com/6.2/geocode.json?country=France&city=' . $v . '&postalCode=' . $codePostal . '&app_id=' . $app_id . '&app_code=' . $app_code . '&gen=8';

                        $reqGeocodeArray = $this->getReponseCurl($reqGeocode);


                        if (isset($reqGeocodeArray->response->status) && $reqGeocodeArray->response->status == 'ERROR') {
                            die('Erreur: ' . $reqGeocodeArray->response->errormessage);
                        }

                        // détecter si la réponse est vide
                        if ($reqGeocodeArray['Response']['View'] == []) {
                            die("Erreur interne, l\'API HERE ne reconnait pas cette ville: " . $ville.
                                ".\r Veuillez assurer que tous les lieux se trouvent en France Métropolitaine");
                        }

                        $Latitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Latitude'];
                        $Longitude = $reqGeocodeArray['Response']['View'][0]['Result'][0]['Location']['DisplayPosition']['Longitude'];

                        $coordVille = $Latitude . '%2C' . $Longitude;

                        $update = $bdd->prepare("UPDATE entite SET longitude = :longitude, latitude= :latitude WHERE id = :id");
                        $update->bindParam(':longitude', $Longitude);
                        $update->bindParam(':latitude', $Latitude);
                        $update->bindParam(':id', $idVille);
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


    private function getDistanceTotale($distanceEquipe, $nbrParticipants){

        # controler si les tableaux ont la même taille
        if(count($distanceEquipe) != count($nbrParticipants)){
//            error_log("\n service: rencontres, function: getDistanceTotale, nbrParticipants: ".print_r($nbrParticipants, true)."\n" , 3, "error_log_optimouv.txt");
//            error_log("\n service: rencontres, function: getDistanceTotale, distanceEquipe: ".print_r($distanceEquipe, true)."\n" , 3, "error_log_optimouv.txt");

            error_log("service: rencontres, function: getDistanceTotale ", 3, "error_log_optimouv.txt");
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        $distanceTotale = 0;

        for($i=0; $i<count($distanceEquipe); $i++){

            $distanceTotale += $distanceEquipe[$i]/1000 * $nbrParticipants[$i];

        }

        # arrondir le chiffre
        $distanceTotale = round($distanceTotale);

        return $distanceTotale;


    }

    private function getParticipantsPourGroupe($idGroupe){

        $bdd= $this->connexion();

        if(!$bdd){
            error_log("service: rencontres, function: getParticipantsPourGroupe ", 3, "error_log_optimouv.txt");
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        try {
            // obtenir la liste de participants
            $stmt1 = $bdd->prepare("SELECT equipes from groupe where id= :id");
            $stmt1->bindParam(':id', $idGroupe);
            $stmt1->execute();
            $idParticipants = $stmt1->fetchColumn();

            $idParticipants = explode(",", $idParticipants);

            // obtenir le nombre de participants pour toutes les équipes
            $nbrParticipants = 0;

            for($i=0; $i<count($idParticipants); $i++){
                $stmt1 = $bdd->prepare("SELECT participants from entite where id = :id");
                $stmt1->bindParam(':id', $idParticipants[$i]);

                $stmt1->execute();
                $nbrParticipantsTemp = $stmt1->fetchColumn();

                $nbrParticipants += $nbrParticipantsTemp;
//                error_log("\n Service: Rencontres, Function: getParticipantsPourGroupe "
//                    ."\n nbrParticipants : ".print_r($nbrParticipants, true), 3, "/tmp/optimouv.log");
            }


        } catch (Exception $e) {
            error_log(print_r($e, TRUE), 3, "error_log_optimouv.txt");
            die('Erreur : ' . $e->getMessage());
        }


//        return $nbrParticipants;
        return array(
            "nbrParticipantsTotal" => $nbrParticipants
        );
    }



    private function getReponseCurl($url)
    {

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);

        $curl_response = curl_exec($curl);

        if ($curl_response === false) {
            $errorInfo = curl_error($curl);
            curl_close($curl);
            error_log(print_R($errorInfo, TRUE), 3, "error_log_optimouv.txt");

            error_log("\n service: rencontres, function: getReponseCurl ", 3, "error_log_optimouv.txt");
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');

        }
        curl_close($curl);


        $reqGeocodeArray = json_decode($curl_response, true);


        return $reqGeocodeArray;
    }

    private function getNombreParticipants($idsEntites)
    {

        $bdd= $this->connexion();
        $count = count($idsEntites);

        $nbrParticipants = [];

        for($i=0; $i<$count; $i++){

            $id = $idsEntites[$i];

             $stmt1 = $bdd->prepare("SELECT participants from entite where id=:id ;");

            $stmt1->bindParam(':id', $id);
            $stmt1->execute();
            $result = $stmt1->fetchColumn();

            array_push($nbrParticipants, $result);


        }
        return $nbrParticipants;
    }

    private function getTotalNombreParticipants($nbrParticipants){

        $totalNombreParticipants = 0;

        for($i=0; $i<count($nbrParticipants); $i++){
            $totalNombreParticipants += $nbrParticipants[$i];
        }

        return $totalNombreParticipants;

    }

}
