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

    public $database_name;
    public $database_user;
    public $database_password;
    public $app_id;
    public $app_code;
    public $error_log_path;
    public $database_host;
    public $here_request_limit;

    /**
     * @var Statistiques $serviceStatistiques
     */
    protected $serviceStatistiques;

    public function __construct($database_host, $database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path, $serviceStatistiques,$here_request_limit )
    {
        $this->database_host = $database_host;
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
        $this->error_log_path = $error_log_path;
        $this->serviceStatistiques = $serviceStatistiques;
        $this->here_request_limit = $here_request_limit;

    }

    public function connexion()
    {
        //params de connexion

        $host = $this->database_host;
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            $bdd = new PDO('mysql:host='.$host.';dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);

        } catch (PDOException $e) {
            die('Erreur : ' . $e->getMessage());
        }

        return $bdd;
    }

    public function index($idGroupe)
    {
        $bdd= Rencontres::connexion();

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

                $retour = Rencontres::geocoderUneVille($idVille);

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

        $bdd= Rencontres::connexion();

        //Récupération de détail de la liste de lieux

        $listeLieux = Rencontres::getListeLieux($idGroupe);

        $nomsTerrainsNeutres = $listeLieux[0];

        //on récupère le tableau des villes
        $retourIndex = Rencontres::index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $retourIndex[2];
        $idsEntitesPasRencontre = $retourIndex[3];


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

        $idEntitesCombination = []; // tableau de toutes les combinations des ids de participants

        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; ++$i) {

            # enlever le premier élément pour la ville
            $start = $villes[0];
            unset($villes[0]);
            $villesRencontre = array_values($villes);


            # enlever le premier élément pour le nombre de participants
            $startIdEntite = $idsEntites[0];
            unset($idsEntites[0]);
            $idsEntitesRencontre = array_values($idsEntites);


            $T2 = array_merge($villesRencontre, $villesPasRencontre);
            $idEntitesTemp = array_merge($idsEntitesRencontre, $idsEntitesPasRencontre);

            $Coordonnes = explode("%2C", $start);
            $lanY = $Coordonnes[0];
            $lanX = $Coordonnes[1];

            # obtenir l'id utilisateur
            $idUtilisateur = $this->getUtilisateurIdParGroupeId($idGroupe);


            $resultat = Rencontres::calculRoute($lanX, $lanY, $T2, $idUtilisateur);

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
            array_push($idEntitesCombination, $idEntitesTemp);


            array_push($villesRencontre, $start);
            $villes = $villesRencontre;
            array_push($idsEntitesRencontre, $startIdEntite);
            $idsEntites = $idsEntitesRencontre;



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

        $stmt1 = $bdd->prepare("SELECT ville, code_postal from entite where longitude = :longitude AND latitude = :latitude");
        $stmt1->bindParam(':longitude', $latY);
        $stmt1->bindParam(':latitude', $lanX);
        $stmt1->execute();
        $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
        $codePostal = $maVille['code_postal'];
        $nomVille = $maVille['ville'];

        $villeDepart = $codePostal." | ".$nomVille;


        $mesVillesXY = $coordonneesVilles[$key];
        //Récupérer les noms de villes de destination
        $mesVilles = Rencontres::mesVilles($mesVillesXY);


        //distance ville
        $distVille = $lesDistances[$key];
        $dureeVille = $lesDurees[$key];

        // obtenir les ids choisis selon la clé donnée
        $idsEntitesChoisis = $idEntitesCombination[$key];


        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = Rencontres::getNombreParticipants($idsEntitesChoisis);


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
        $retour['nbrParticipants'] = $nbrParticipants;


        // obtenir la distance totale pour toutes équipes
        $distanceTotale = Rencontres::getDistanceTotale($distVille, $nbrParticipants);


        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;

        # ajouter le nombre de participants dans les résultats
        $retour["nbrParticipantsTotal"] = Rencontres::getTotalNombreParticipants($nbrParticipants);


        return $retour;
    }

    //Calcul du barycentre
    public function Barycentre($idGroupe)
    {

        $bdd= Rencontres::connexion();

        //recuperer la date du jour
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');


        //on récupère le tableau des villes
//        $villes = $this->index($idGroupe);
        $villes = Rencontres::index($idGroupe);

        
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

        $retour =  Rencontres::routingMatrix($coord, $villes, $idsEntitesMerge, $idGroupe);


        # ajouter le nombre de participants dans les résultats
        $retour["nbrParticipantsTotal"] =  Rencontres::getTotalNombreParticipants($retour[9]);


        return $retour;
    }

    //Calcul exclusion géographique
    public function Exclusion($valeurExclusion, $idGroupe)
    {


        $bdd= Rencontres::connexion();

        if ($valeurExclusion) {

            //on récupère le tableau des villes
            $villes = Rencontres::index($idGroupe);

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


            $stmt1 = $bdd->prepare("SELECT ville_longitude_deg, ville_latitude_deg,ville_code_postal,ville_nom_reel, ville_population_2012,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                          from villes_france_free
                          where ville_population_2012 < :valeurExclusion
                          order by Proximite limit 1;");
            $stmt1->bindParam(':valeurExclusion', $valeurExclusion);

            $stmt1->execute();
            $result = $stmt1->fetch(PDO::FETCH_ASSOC);

            $lanX = $result['ville_latitude_deg'];
            $latY = $result['ville_longitude_deg'];
            $coord = $latY . '%2C' . $lanX;
            $ville = $result['ville_nom_reel'];
            $codePostal = $result['ville_code_postal'];

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


            $retour = Rencontres::routingMatrix($coord, $villes, $idsEntitesMerge, $idGroupe);

            # ajouter le nombre de participants dans les résultats
            $retour["nbrParticipantsTotal"] = Rencontres::getTotalNombreParticipants($retour[9]);


        } else {

            error_log("service: rencontres, function: Exclusion ", 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        return $retour;

    }

    //Calcul du scénario équitable
    public function scenarioEquitable($idGroupe)
    {
        $bdd= Rencontres::connexion();
        //on récupère le tableau des villes
        $retourIndex = Rencontres::index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $retourIndex[2];
        $idsEntitesPasRencontre = $retourIndex[3];

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
        $idEntitesCombination = []; // tableau de toutes les combinations des ids de participants


        $longueurTab = count($villes);
        for ($i = 0; $i < $longueurTab; ++$i) {
            $start = $villes[0];

            unset($villes[0]);
            $villesRencontre = array_values($villes);
            $T2 = array_merge($villesRencontre, $villesPasRencontre);

            # enlever le premier élément pour le nombre de participants
            $startIdEntite = $idsEntites[0];
            unset($idsEntites[0]);
            $idsEntitesRencontre = array_values($idsEntites);
            $idEntitesTemp = array_merge($idsEntitesRencontre, $idsEntitesPasRencontre);


            $Coordonnes = explode("%2C", $start);
            $lanY = $Coordonnes[0];
            $lanX = $Coordonnes[1];


            # obtenir l'id utilisateur
            $idUtilisateur = $this->getUtilisateurIdParGroupeId($idGroupe);


            $resultat = Rencontres::calculRoute($lanX, $lanY, $T2, $idUtilisateur);

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
            array_push($idEntitesCombination, $idEntitesTemp);


            array_push($villesRencontre, $start);
            $villes = $villesRencontre;
            array_push($idsEntitesRencontre, $startIdEntite);
            $idsEntites = $idsEntitesRencontre;
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

        $stmt1 = $bdd->prepare("SELECT ville_code_postal,ville_nom,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                                    from villes_france_free
                                    order by Proximite limit 1;");
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);
        $nomVille = $result['ville_nom'];
        $codePostal = $result['ville_code_postal'];
        $villeDepart = $codePostal." | ".$nomVille;

        $mesVillesXY = $coordonneesVilles[$key];
        //Récupérer les noms de villes de destination
        $mesVilles = Rencontres::mesVilles($mesVillesXY);

        //distance ville
        $distVille = $lesDistances[$key];
        $dureeVille = $lesDurees[$key];

        // obtenir les ids choisis selon la clé donnée
        $idsEntitesChoisis = $idEntitesCombination[$key];

        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = Rencontres::getNombreParticipants($idsEntitesChoisis);

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
        $retour['nbrParticipants'] = $nbrParticipants;


        // obtenir la distance totale pour toutes équipes
        $distanceTotale = Rencontres::getDistanceTotale($distVille, $nbrParticipants);

        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;

        return $retour;
    }

    public function routingMatrix($coord, $villes, $idsEntites, $idGroupe)
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $bdd=  Rencontres::connexion();

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];


        //////////////////////
        $stmt1 = $bdd->prepare("SELECT ville, code_postal from entite where longitude=:longitude and latitude = :latitude ;");

        $stmt1->bindParam(':longitude', $lanX);
        $stmt1->bindParam(':latitude', $latY);
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);
        $codePostal = $result['code_postal'];
        $nomVille = $result['ville'];

        $barycentreVille = $codePostal." | ".$nomVille;


        if (!$barycentreVille) {

            $stmt1 = $bdd->prepare("SELECT ville_nom, ville_code_postal,
                        (6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                        from villes_france_free
                        order by Proximite limit 1;");

            $stmt1->execute();
            $barycentreVille = $stmt1->fetch(PDO::FETCH_ASSOC);
            $codePostal = $result['ville_code_postal'];
            $nomVille = $result['ville_nom'];

            $barycentreVille = $nomVille." | ".$codePostal;

        }


        # obtenir l'id utilisateur
        $idUtilisateur = $this->getUtilisateurIdParGroupeId($idGroupe);

        $calculRoute =  Rencontres::calculRoute($lanX, $latY, $villes, $idUtilisateur);

        $distanceEquipe = $calculRoute[0];
        $dureeEquipe = $calculRoute[1];

        //Récupérer les noms de villes de destination
        $mesVilles =  Rencontres::mesVilles($villes);

        //somme des distances
        $distance = array_sum($distanceEquipe) / 1000;
        $distance = round($distance, 0);

        //somme des durées
        $duree = array_sum($dureeEquipe);

        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants =  Rencontres::getNombreParticipants($idsEntites);


        // obtenir la distance totale pour toutes équipes
        $distanceTotale =  Rencontres::getDistanceTotale($distanceEquipe, $nbrParticipants);


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
        $retour['nbrParticipants'] = $nbrParticipants;


        return $retour;
    }

    public function terrainNeutre($idGroupe)
    {

        $bdd= Rencontres::connexion();
        # obtenir le nombre de participants pour cette groupe
        $nbrParticipants = Rencontres::getParticipantsPourGroupe($idGroupe);

        $equipe = Rencontres::index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $equipe[2];
        $idsEntitesPasRencontre = $equipe[3];
        $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );


        $equipe = array_merge($equipe[0], $equipe[1]);

        $listeLieux = Rencontres::getListeLieux($idGroupe);
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

            # obtenir l'id utilisateur
            $idUtilisateur = $this->getUtilisateurIdParGroupeId($idGroupe);


            $calculRoute = Rencontres::calculRoute($lanX, $latY, $equipe, $idUtilisateur);

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
        $mesVilles = Rencontres::mesVilles($equipe);

        $stmt1 = $bdd->prepare("SELECT code_postal, ville from entite where longitude = :longitude AND latitude = :latitude");
        $stmt1->bindParam(':longitude', $latY);
        $stmt1->bindParam(':latitude', $lanX);
        $stmt1->execute();
        $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
        $codePostal = $maVille['code_postal'];
        $nomVille = $maVille['ville'];

        $maVille = $codePostal." | ".$nomVille;


        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = Rencontres::getNombreParticipants($idsEntitesMerge);

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
        $retour['nbrParticipants'] = $nbrParticipants;


        // obtenir la distance totale pour toutes équipes
        $distanceTotale = Rencontres::getDistanceTotale($distanceVilles, $nbrParticipants);

        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;

        # ajouter le nombre de participants dans les résultats
        $retour["nbrParticipantsTotal"] = Rencontres::getTotalNombreParticipants($nbrParticipants);

        return $retour;
    }

    public function terrainNeutreEquitable($idGroupe)
    {

        $bdd= Rencontres::connexion();

        $equipe = Rencontres::index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $equipe[2];
        $idsEntitesPasRencontre = $equipe[3];
        $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );

        $equipe = array_merge($equipe[0], $equipe[1]);

        $listeLieux = Rencontres::getListeLieux($idGroupe);
        $terrainNeutre = $listeLieux[1];

        $toutesLesDistances = [];
        $toutesLesDurees = [];
        $tousLesCalculs = [];
        for ($i = 0; $i < count($terrainNeutre); ++$i) {
            $start = $terrainNeutre[$i];

            $start = explode('%2C', $start);
            $latY = $start[0];
            $lanX = $start[1];

            # obtenir l'id utilisateur
            $idUtilisateur = $this->getUtilisateurIdParGroupeId($idGroupe);

            $calculRoute = Rencontres::calculRoute($lanX, $latY, $equipe, $idUtilisateur);

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
        $mesVilles = Rencontres::mesVilles($equipe);

        $stmt1 = $bdd->prepare("SELECT code_postal, ville from entite where longitude = :longitude AND latitude = :latitude");
        $stmt1->bindParam(':longitude', $latY);
        $stmt1->bindParam(':latitude', $lanX);
        $stmt1->execute();
        $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
        $codePostal = $maVille['code_postal'];
        $nomVille = $maVille['ville'];

        $maVille = $codePostal." | ".$nomVille;

        //récupérer le nombre de participant pour chaque entité
        $nbrParticipants = Rencontres::getNombreParticipants($idsEntitesMerge);


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
        $retour['nbrParticipants'] = $nbrParticipants;

        // obtenir la distance totale pour toutes équipes
        $distanceTotale = Rencontres::getDistanceTotale($distanceVilles, $nbrParticipants);

        # ajouter le nombre de participants dans les résultats
        $retour["distanceTotale"] = $distanceTotale;


        return $retour;
    }

    public function nomsVilles($idGroupe)
    {

        //params de connexion

        $bdd= Rencontres::connexion();

        $reqVilles = $bdd->prepare("SELECT equipes FROM  groupe where id = :idGroupe ;");
        $reqVilles->bindParam(':idGroupe', $idGroupe);
        $reqVilles->execute();
        $reqVilles = $reqVilles->fetchColumn();
        $reqVilles = explode(",", $reqVilles);

        $villes = [];

        for ($i = 0; $i < count($reqVilles); $i++) {
            $stmt = $bdd->prepare("SELECT ville, code_postal FROM  entite WHERE id = :idEntite ;");
            $stmt->bindParam(':idEntite', $reqVilles[$i]);
            $stmt->execute();
            $maVille = $stmt->fetch(PDO::FETCH_ASSOC);
            $codePostal = $maVille['code_postal'];
            $nomVille = $maVille['ville'];

            $maVille = $codePostal." | ".$nomVille;

            array_push($villes, $maVille);

        }

        return $villes;


    }

    public function mesVilles($villes)
    {

        $bdd= Rencontres::connexion();

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($villes); ++$l) {

            $start = explode('%2C', $villes[$l]);
            $lanX = $start[0];
            $latY = $start[1];

            $stmt1 = $bdd->prepare("SELECT code_postal, ville from entite where longitude = :longitude AND latitude = :latitude");
            $stmt1->bindParam(':longitude', $latY);
            $stmt1->bindParam(':latitude', $lanX);
            $stmt1->execute();
            $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
            $codePostal = $maVille['code_postal'];
            $nomVille = $maVille['ville'];

            $maVille = $codePostal." | ".$nomVille;

            //Ramener tous les noms des villes
            array_push($mesVilles, $maVille);
        }
        return $mesVilles;
    }

    public function creerGroupe($villes, $nomGroupe, $idListeParticipants, $idListeLieux, $idUtilisateur)
    {

        $bdd= Rencontres::connexion();

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

        $bdd= Rencontres::connexion();

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

    public function calculRoute($lanX, $latY, $villes, $idUtilisateur)
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $app_id = $this->app_id;
        $app_code = $this->app_code;

        $bdd = Rencontres::connexion();

        $stmt1 = $bdd->prepare("SELECT id from entite where longitude= :longitude and latitude = :latitude ;");
        $stmt1->bindParam(':longitude', $lanX);
        $stmt1->bindParam(':latitude', $latY);
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

        $idStart = $result['id'];

        $coordStart = $latY . '%2C' . $lanX;

        $distanceTotale = [];
        $dureeTotale = [];


        # nombre des requetes HERE
        $nbrRequetesHere = 0;

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

//                error_log("\n Service: Rencontres".print_r($res, true)."\n", 3, $this->error_log_path);


                if ($res) {

                    $distance = $res['distance'];
                    $duree = $res['duree'];
                    array_push($distanceTotale, $distance);
                    array_push($dureeTotale, $duree);

                } else {
                    $reqRoute = 'http://route.api.here.com/routing/7.2/calculateroute.json?waypoint0=' . $coordStart . '&waypoint1=' . $villes[$i] . '&mode=fastest%3Bcar%3Btraffic%3Adisabled&app_id=' . $app_id . '&app_code=' . $app_code;

                    $nbrRequetesHere += 1;

                    $decoded =  Rencontres::getReponseCurl($reqRoute);

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

        # incrémenter le nombre des requetes HERE
        if($nbrRequetesHere > 0){
            $this->serviceStatistiques->augmenterNombreTableStatistiques($idUtilisateur, "nombreRequetesHere", $nbrRequetesHere);
        }



        $retour = [];
        $retour[0] = $distanceTotale;
        $retour[1] = $dureeTotale;

        return $retour;

    }

    public function getUtilisateurIdParGroupeId($idGroupe){
        //on recupere les parametres de connexion
        $bdd= $this->connexion();

        $stmt1 = $bdd->prepare("select id_utilisateur from groupe where id = :id ;");

        $stmt1->bindParam(':id', $idGroupe);
        $stmt1->execute();
        $idUtilisateur = $stmt1->fetchColumn();

        return $idUtilisateur;

    }

    public function getListeLieux($idGroupe)
    {

        $app_id = $this->app_id;
        $app_code = $this->app_code;
        $here_request_limit = $this->here_request_limit;

        $bdd= Rencontres::connexion();

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

            # obtenir l'id de l'utilisateur
            $utilisateurId = $this->getUtilisateurIdParGroupeId($idGroupe);

            # nombre des requetes HERE de géocodage
            $nbrRequetesGeoHere = 0;

            // obtenir le nombre de requetes de géo-codage
//            $listeLieux = $bdd->prepare("SELECT valeur FROM  statistiques_date WHERE type_statistiques = :id ;");
//            $listeLieux->bindParam(':id', $idListeLieux);
//            $listeLieux->execute();
//            $listeLieux = $listeLieux->fetchColumn();




            for ($i = 0; $i < count($listeLieux); $i++) {
                //
                $stmt = $bdd->prepare("SELECT id, ville, code_postal, longitude, latitude FROM  entite WHERE id = :id");
                $stmt->bindParam(':id', $listeLieux[$i]);
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                    $idVille = $row['id'];
                    $lat = $row['latitude'];
                    $long = $row['longitude'];
                    $nomVille = $row['ville'];
                    $codePostal = $row['code_postal'];


                    // récupérer lat et lon s'il y en a
                    if ($long && $lat) {
                        $coordVille = $lat . '%2C' . $long;
                        //ramner le nom de la ville concatiner avec le code postal
                        $ville =  $codePostal." | ".$nomVille;
                        array_push($nomsVilles, $ville);
                        array_push($coordVilles, $coordVille);
                    } // sinon il faut interroger le serveur HERE
                    else {

                        $v = urlencode($nomVille);
                        $reqGeocode = 'http://geocoder.api.here.com/6.2/geocode.json?country=France&city=' . $v . '&postalCode=' . $codePostal . '&app_id=' . $app_id . '&app_code=' . $app_code . '&gen=8';

                        $nbrRequetesGeoHere += 1;

                        $reqGeocodeArray = $this->getReponseCurl($reqGeocode);


                        if (isset($reqGeocodeArray->response->status) && $reqGeocodeArray->response->status == 'ERROR') {
                            die('Erreur: ' . $reqGeocodeArray->response->errormessage);
                        }

                        // détecter si la réponse est vide
                        if ($reqGeocodeArray['Response']['View'] == []) {
                            die("Erreur interne, l\'API HERE ne reconnait pas cette ville: " . $nomVille.
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
                        //ramner le nom de la ville concatiner avec le code postal
                        $ville =  $codePostal." | ".$nomVille;
                        array_push($nomsVilles, $ville);
                        array_push($coordVilles, $coordVille);

                    }


                }


            }
            $retour = [];

            # incrémenter le nombre des requetes HERE
            if($nbrRequetesGeoHere > 0){
                $this->serviceStatistiques->augmenterNombreTableStatistiques($utilisateurId, "nombreRequetesGeoHere", $nbrRequetesGeoHere);
            }


            $retour[0] = $nomsVilles;
            $retour[1] = $coordVilles;

            return $retour;


        }
    }


    public function creerRapport($idGroupe, $typeAction, $valeurExclusion){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $tabAction[0]= "barycentre";
        $tabAction[1]= "exclusion";
        $tabAction[2]= "meilleurLieu";
        $tabAction[3]= "terrainNeutre";
        if(in_array($typeAction,$tabAction)){
            $prefixe_nom = "Meilleur lieu";
        }
        else{
            $prefixe_nom = "Poules";
        }
        # obtenir l'objet PDO
        $pdo = Rencontres::connexion();

        if(!$pdo){
            error_log("\n erreur récupération de l'objet PDO, Service: Rencontres, Function: creerRapport, datetime: ".$dateTimeNow, 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        //récupérer le nom du groupe
        $getNomGroupe = $pdo->prepare("select nom from groupe where id = :id ;");
        $getNomGroupe->bindParam(':id', $idGroupe);
        $getNomGroupe->execute();
        $nomGroupe = $getNomGroupe->fetchColumn();


        # controler si le rapport est déjà dans la table rapport
        try {
            $sql = "SELECT id FROM parametres WHERE id_groupe = :id_groupe and type_action = :type_action and params = :valeur_exclusion " ;
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_groupe', $idGroupe);
            $stmt->bindParam(':type_action', $typeAction);
            $stmt->bindParam(':valeur_exclusion', $valeurExclusion);

            # executer la requete
            $stmt->execute();

            # obtenir le résultat
            $resultat = $stmt->fetchAll(PDO::FETCH_ASSOC);


            # insérer dans la table rapport si le rapport est nouveau
            if(!$resultat){

               //attribuer un nom au groupe
                $nom = $prefixe_nom.$nomGroupe;

                $sql = "INSERT INTO parametres (nom, id_groupe, params, date_creation)
                          VALUES (:nom, :id_groupe, :type_action, :valeur_exclusion, :date_creation)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':id_groupe', $idGroupe);
                $stmt->bindParam(':type_action', $typeAction);
                $stmt->bindParam(':valeur_exclusion', $valeurExclusion);
                $stmt->bindParam(':date_creation', $dateTimeNow);

                # executer la requete
                $stmt->execute();

                # afficher le statut de la requete executée
//                error_log("\n Service: Rencontres, Function: creerRapport, datetime: ".$dateTimeNow
//                    ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, $this->error_log_path);

                # obtenir l'id de l"entité créée
                $idRapport = $pdo->lastInsertId();
            }
            else{
                $idRapport = -1; # l'id rapport si on ne fait pas l'insertion
            }

        } catch (PDOException $e) {
            error_log("\n Service: Rencontres, Function: creerRapport, datetime: ".$dateTimeNow
                ."\n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        return $idRapport;


    }

    public function creerScenario($idRapport, $typeScenario, $distanceKm, $duree){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir l'objet PDO
        $pdo = Rencontres::connexion();

        if(!$pdo){
            error_log("\n erreur récupération de l'objet PDO, Service: Rencontres, Function: creerScenario, datetime: ".$dateTimeNow, 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        try {
                $nom = "scenario_".$typeScenario."_rapport_".$idRapport;

                $sql = "INSERT INTO resultats (id_rapport, nom, kilometres, duree, date_creation, date_modification)
                          VALUES (:id_rapport, :nom, :kilometres, :duree, :date_creation, :date_modification)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id_rapport', $idRapport);
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':kilometres', $distanceKm);
                $stmt->bindParam(':duree', $duree);
                $stmt->bindParam(':date_creation', $dateTimeNow);
                $stmt->bindParam(':date_modification', $dateTimeNow);

                # executer la requete
                       # afficher le statut de la requete executée
//                error_log("\n Service: Rencontres, Function: creerRapport, datetime: ".$dateTimeNow
//                    ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, $this->error_log_path);, 3, $this->error_log_path);

                # obtenir l'id de l"entité créée
                $idScenario = $pdo->lastInsertId();

        } catch (PDOException $e) {
            error_log("\n Service: Rencontres, Function: creerScenario, datetime: ".$dateTimeNow
                ."\n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }



        return $idScenario;

    }


    private function getDistanceTotale($distanceEquipe, $nbrParticipants){

        # controler si les tableaux ont la même taille
        if(count($distanceEquipe) != count($nbrParticipants)){
            error_log("service: rencontres, function: getDistanceTotale ", 3, $this->error_log_path);
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

    public function getParticipantsPourGroupe($idGroupe){

        $bdd= Rencontres::connexion();

        if(!$bdd){
            error_log("service: rencontres, function: getParticipantsPourGroupe ", 3, $this->error_log_path);
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
            }


        } catch (Exception $e) {
            error_log(print_r($e, TRUE), 3, $this->error_log_path);
            die('Erreur : ' . $e->getMessage());
        }


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
            error_log(print_R($errorInfo, TRUE), 3, $this->error_log_path);

            error_log("\n service: rencontres, function: getReponseCurl ", 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');

        }
        curl_close($curl);


        $reqGeocodeArray = json_decode($curl_response, true);


        return $reqGeocodeArray;
    }

    private function getNombreParticipants($idsEntites)
    {

        $bdd= Rencontres::connexion();
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

    public function getTotalNombreParticipants($nbrParticipants){

        $totalNombreParticipants = 0;

        for($i=0; $i<count($nbrParticipants); $i++){
            $totalNombreParticipants += $nbrParticipants[$i];
        }

        return $totalNombreParticipants;

    }

    //Ajouter le producer

    public function Producer($idGroupe, $typeAction)
    {


        $bdd= Rencontres::connexion();

 
        $prefixe_nom = "Meilleur lieu";
        

        //récupérer le nom du groupe
        $getNomGroupe = $bdd->prepare("select nom from groupe where id = :id ;");
        $getNomGroupe->bindParam(':id', $idGroupe);
        $getNomGroupe->execute();
        $nomGroupe = $getNomGroupe->fetchColumn();

        //déclaration des parametres pour la req insert dans la table parametres

        $statut = 0;

        //recuperer la date du jour
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');
//        $nomRapport = "rapport_groupe_".$idGroupe."_action_".$typeAction;
        $nomRapport = $prefixe_nom."_".$nomGroupe;

        //on ajoute un job dans la table parametres
        //TODO:changer le nom de la table rapport en paramètres
        $insert = $bdd->prepare("INSERT INTO  parametres (nom, id_groupe, type_action, statut, date_creation) VALUES (:nomRapport, :idGroupe, :typeAction, :statut, :dateCreation );");
        $insert->bindParam(':nomRapport', $nomRapport);
        $insert->bindParam(':idGroupe', $idGroupe);
        $insert->bindParam(':typeAction', $typeAction);
        $insert->bindParam(':statut', $statut);
        $insert->bindParam(':dateCreation', $dateCreation);
        $insert->execute();
        $idTache = $bdd->lastInsertId();

        return $idTache;

    }

    public function producerExclusion($idGroupe, $valeurExclusion)
    {

        $bdd = Rencontres::connexion();


        //déclaration des parametres pour la req insert dans la table parametres

        $statut = 0;
        $typeAction = "exclusion";
        $prefixe_nom = "Meilleur lieu";

        //récupérer le nom du groupe
        $getNomGroupe = $bdd->prepare("select nom from groupe where id = :id ;");
        $getNomGroupe->bindParam(':id', $idGroupe);
        $getNomGroupe->execute();
        $nomGroupe = $getNomGroupe->fetchColumn();


        //recuperer la date du jour
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');
//        $nomRapport = "rapport_groupe_".$idGroupe."_action_".$typeAction;
        $nomRapport = $prefixe_nom."_".$nomGroupe;

        //on ajoute un job dans la table parametres
        //TODO:changer le nom de la table rapport en paramètres
        $insert = $bdd->prepare("INSERT INTO  parametres (nom, id_groupe, type_action, statut, params, date_creation) VALUES (:nomRapport, :idGroupe, :typeAction, :statut, :params, :dateCreation );");
        $insert->bindParam(':nomRapport', $nomRapport);
        $insert->bindParam(':idGroupe', $idGroupe);
        $insert->bindParam(':typeAction', $typeAction);
        $insert->bindParam(':statut', $statut);
        $insert->bindParam(':params', $valeurExclusion);
        $insert->bindParam(':dateCreation', $dateCreation);
        $insert->execute();
        $idTache = $bdd->lastInsertId();

        return $idTache;

    }

    public function getNomRencontre($typeRencontre){
        $nomRencontre = "";

        if($typeRencontre == "barycentre"){
            $nomRencontre = "barycentre";
        }
        elseif($typeRencontre == "barycentreAvecExlcusion"){
            $nomRencontre = "barycentre avec exclusion";
        }
        elseif($typeRencontre == "meilleurLieu"){
            $nomRencontre = "lieux définis";
        }
        elseif($typeRencontre == "terrainNeutre"){
            $nomRencontre = "lieux définis avec liste de lieux";
        }

        return $nomRencontre;
    }

    # fonction pour trier le tableau à partir d'un clé
    public function sksort(&$array, $subkey="id", $sort_ascending=false) {

        if (count($array))
            $temp_array[key($array)] = array_shift($array);

        foreach($array as $key => $val){
            $offset = 0;
            $found = false;
            foreach($temp_array as $tmp_key => $tmp_val)
            {
                if(!$found and strtolower(trim($val[$subkey])) > strtolower(trim($tmp_val[$subkey])))
                {
                    $temp_array = array_merge(    (array)array_slice($temp_array,0,$offset),
                        array($key => $val),
                        array_slice($temp_array,$offset)
                    );
                    $found = true;
                }
                $offset++;
            }
            if(!$found) $temp_array = array_merge($temp_array, array($key => $val));
        }

        if ($sort_ascending) $array = array_reverse($temp_array);
        else $array = $temp_array;
    }


}
