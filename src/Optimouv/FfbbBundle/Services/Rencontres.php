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
use Symfony\Component\Config\Definition\Exception\Exception;

class Rencontres
{

    public $database_name;
    public $database_user;
    public $database_password;
    public $error_log_path;
    public $database_host;
    public $here_request_limit;
    public $here_request_limit_debut;
    public $here_request_limit_fin;

    public $route_app_id;
    public $route_app_code;
    public $geocode_app_id;
    public $geocode_app_code;

    /**
     * @var Statistiques $serviceStatistiques
     */
    protected $serviceStatistiques;
    /**
     * @var FonctionsCommunes $fonctionsCommunes
     */
    protected $fonctionsCommunes;

    public function __construct($database_host, $database_name, $database_user, $database_password, $route_app_id, $route_app_code, $geocode_app_id, $geocode_app_code, $error_log_path, $serviceStatistiques, $here_request_limit, $fonctionsCommunes, $here_request_limit_debut, $here_request_limit_fin )
    {
        $this->database_host = $database_host;
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->error_log_path = $error_log_path;
        $this->serviceStatistiques = $serviceStatistiques;
        $this->here_request_limit = $here_request_limit;
        $this->fonctionsCommunes = $fonctionsCommunes;
        $this->here_request_limit_debut = $here_request_limit_debut;
        $this->here_request_limit_fin = $here_request_limit_fin;
        $this->route_app_id = $route_app_id;
        $this->route_app_code = $route_app_code;
        $this->geocode_app_id = $geocode_app_id;
        $this->geocode_app_code = $geocode_app_code;

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

            $bdd= Rencontres::connexion();
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

                $bdd= Rencontres::connexion();
                $update = $bdd->prepare("UPDATE entite SET longitude = :Longitude, latitude= :Latitude, date_modification = :dateModification WHERE id = :idEntite");
                $update->bindParam(':idEntite', $idEntite);
                $update->bindParam(':Longitude', $longitude);
                $update->bindParam(':Latitude', $latitude);
                $update->bindParam(':dateModification', $dateModification);
                $update->execute();
                
            }

        }

        $bdd= Rencontres::connexion();
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
        $bdd= Rencontres::connexion();
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
        $retourListeLieux = Rencontres::getListeLieux($idGroupe);

        // retourner le retour à la fonction appellante (donne une erreur)
        if( is_array($retourListeLieux) && array_key_exists("success", $retourListeLieux) && $retourListeLieux["success"] === FALSE){
            return $retourListeLieux;
        }
        // traitement normal
        else{

                $listeLieux = $retourListeLieux["donneesRetour"];

                if( is_array($listeLieux) && isset($listeLieux[0])) {

                    // s'il y a une liste de lieux
                    $nomsTerrainsNeutres = $listeLieux[0];
                }
                else{
                    // s'il n'y a pas de liste de lieux
                    $nomsTerrainsNeutres = NULL;

                }

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

                $stmt1 = $bdd->prepare("SELECT nom, ville, code_postal from entite where longitude = :longitude AND latitude = :latitude");
                $stmt1->bindParam(':longitude', $latY);
                $stmt1->bindParam(':latitude', $lanX);
                $stmt1->execute();
                $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
                $codePostal = $maVille['code_postal'];
                $nomVille = $maVille['ville'];
                $nomEntite = $maVille['nom'];

                $villeDepart = $codePostal." | ".$nomEntite." | ".$nomVille;

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


                $donneesRetour = [];

                $donneesRetour[0] = $villeDepart;
                $donneesRetour[1] = $lanX;
                $donneesRetour[2] = $latY;
                $donneesRetour[3] = $distanceTotale;
                $donneesRetour[4] = $dureeTotale;
                $donneesRetour[5] = $mesVillesXY;
                $donneesRetour[6] = $mesVilles;
                $donneesRetour[7] = $distVille;
                $donneesRetour[8] = $dureeVille;
                $donneesRetour[9] = $nomsTerrainsNeutres;
                $donneesRetour[10] = $nbrParticipants;
                $donneesRetour['nbrParticipants'] = $nbrParticipants;


                // obtenir la distance totale pour toutes équipes
                $distanceTotale = Rencontres::getDistanceTotale($distVille, $nbrParticipants);


                # ajouter le nombre de participants dans les résultats
                $donneesRetour["distanceTotale"] = $distanceTotale;

                # ajouter le nombre de participants dans les résultats
                $donneesRetour["nbrParticipantsTotal"] = Rencontres::getTotalNombreParticipants($nbrParticipants);

            return array('success' => True, 'donneesRetour'=>$donneesRetour, 'codeErreur'=> 0);
        }


    }

    //Calcul du barycentre
    public function Barycentre($idGroupe)
    {

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

        $bdd= Rencontres::connexion();
        if ($bdd === null)
            die("connexion failed nom groupe");
        //ramener le nom du groupe pour l'attribuer au nom du barycentre
        $nomGroupe = $bdd->prepare("SELECT nom from groupe where id = :id");
        $nomGroupe->bindParam(':id', $idGroupe);
        if ($nomGroupe->execute() !== true)
            die("execute failed nom groupe");
        $nomGroupe = $nomGroupe->fetchColumn();


        $bdd= Rencontres::connexion();
        $stmt1 = $bdd->prepare("SELECT ville_nom, ville_longitude_deg, ville_latitude_deg, ville_code_postal,(6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                                from villes_france_free
                                order by Proximite limit 1;");
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);

        
        $latY = $result['ville_latitude_deg'];
        $lanX = $result['ville_longitude_deg'];
        $ville = $result['ville_nom'];
        $codePostal = $result['ville_code_postal'];
        $nom = 'Barycentre_' . $nomGroupe;

        $bdd= Rencontres::connexion();
        if ($bdd === null)
            die("connexion failed select entite");
        //vérifier si le barycentre existe deja
        $barycentre = $bdd->prepare("SELECT id from entite where longitude = :longitude AND latitude = :latitude");
        $barycentre->bindParam(':longitude', $lanX);
        $barycentre->bindParam(':latitude', $latY);
        if ($barycentre->execute() !== true)
            die("execute failed select entite");
        $res = $barycentre->fetchColumn();

        
        if (!$res) {
            $bdd= Rencontres::connexion();
            if ($bdd === null)
                die("connexion failed insert entite");
            $insert = $bdd->prepare("INSERT INTO  entite (nom, ville, code_postal, longitude, latitude, date_creation) VALUES ( :nom, :ville, :codePostal, :Longitude,:Latitude, :dateCreation );");
            $insert->bindParam(':nom', $nom);
            $insert->bindParam(':ville', $ville);
            $insert->bindParam(':codePostal', $codePostal);
            $insert->bindParam(':Longitude', $lanX);
            $insert->bindParam(':Latitude', $latY);
            $insert->bindParam(':dateCreation', $dateCreation);
            if ($insert->execute() !== true)
                die("execute failed insert entite");

        }

        $coord = $lanX . '%2C' . $latY; // pour appel la fn routing matrix

        $donneesRetour =  Rencontres::routingMatrix($coord, $villes, $idsEntitesMerge, $idGroupe);


        # ajouter le nombre de participants dans les résultats
        $donneesRetour["nbrParticipantsTotal"] =  Rencontres::getTotalNombreParticipants($donneesRetour[9]);

        return array('success' => True, 'donneesRetour'=>$donneesRetour, 'codeErreur'=> 0);

    }

    //Calcul exclusion géographique
    public function Exclusion($valeurExclusion, $idGroupe)
    {


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

            $bdd= Rencontres::connexion();
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

            $bdd= Rencontres::connexion();
            //vérifier si le barycentre existe deja
            $barycentre = $bdd->prepare("SELECT id from entite where longitude = :longitude AND latitude = :latitude");
            $barycentre->bindParam(':longitude', $latY);
            $barycentre->bindParam(':latitude',  $lanX);
            $barycentre->execute();
            $res = $barycentre->fetchColumn();


            $bdd= Rencontres::connexion();
            //ramener le nom du groupe pour l'attribuer au nom du barycentre
            $nomGroupe = $bdd->prepare("SELECT nom from groupe where id = :id");
            $nomGroupe->bindParam(':id', $idGroupe);
            $nomGroupe->execute();
            $nomGroupe = $nomGroupe->fetchColumn();


            $nom = 'Barycentre_' . $nomGroupe;
            //recuperer la date du jour
            $date = new \DateTime();
            $dateCreation = $date->format('Y-m-d');

            if (!$res) {
                $bdd= Rencontres::connexion();
                $insert = $bdd->prepare("INSERT INTO  entite (nom, ville, code_postal, longitude, latitude, date_creation) VALUES ( :nom, :ville, :codePostal, :Longitude,:Latitude, :dateCreation );");
                $insert->bindParam(':nom', $nom);
                $insert->bindParam(':ville', $ville);
                $insert->bindParam(':codePostal', $codePostal);
                $insert->bindParam(':Longitude', $latY);
                $insert->bindParam(':Latitude', $lanX);
                $insert->bindParam(':dateCreation', $dateCreation);

                $insert->execute();

            }

            $donneesRetour = Rencontres::routingMatrix($coord, $villes, $idsEntitesMerge, $idGroupe);

            # ajouter le nombre de participants dans les résultats
            $donneesRetour["nbrParticipantsTotal"] = Rencontres::getTotalNombreParticipants($donneesRetour[9]);


        } else {

            error_log("service: rencontres, function: Exclusion ", 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return array('success' => True, 'donneesRetour'=>$donneesRetour, 'codeErreur'=> 0);

    }

    //Calcul du scénario équitable
    public function scenarioEquitable($idGroupe)
    {

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

        $bdd= Rencontres::connexion();
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

        $donneesRetour = [];

        $donneesRetour[0] = $villeDepart;
        $donneesRetour[1] = $lanX;
        $donneesRetour[2] = $latY;
        $donneesRetour[3] = $distanceTotale;
        $donneesRetour[4] = $dureeTotale;
        $donneesRetour[5] = $mesVillesXY;
        $donneesRetour[6] = $mesVilles;
        $donneesRetour[7] = $distVille;
        $donneesRetour[8] = $dureeVille;
        $donneesRetour[9] = $nbrParticipants;
        $donneesRetour['nbrParticipants'] = $nbrParticipants;


        // obtenir la distance totale pour toutes équipes
        $distanceTotale = Rencontres::getDistanceTotale($distVille, $nbrParticipants);

        # ajouter le nombre de participants dans les résultats
        $donneesRetour["distanceTotale"] = $distanceTotale;

        return array('success' => True, 'donneesRetour'=>$donneesRetour, 'codeErreur'=> 0);
    }

    public function routingMatrix($coord, $villes, $idsEntites, $idGroupe)
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $coord = explode('%2C', $coord);
        $lanX = $coord[0];
        $latY = $coord[1];

        $bdd=  Rencontres::connexion();
        //////////////////////
        $stmt1 = $bdd->prepare("SELECT nom, ville, code_postal from entite where longitude=:longitude and latitude = :latitude ;");

        $stmt1->bindParam(':longitude', $lanX);
        $stmt1->bindParam(':latitude', $latY);
        $stmt1->execute();
        $result = $stmt1->fetch(PDO::FETCH_ASSOC);


        $codePostal = $result['code_postal'];
        $nomVille = $result['ville'];
        $nomEntite = $result['nom'];

        $barycentreVille = $codePostal." | ".$nomEntite." | ".$nomVille;


        if (!$barycentreVille) {

            $bdd=  Rencontres::connexion();
            $stmt1 = $bdd->prepare("SELECT ville_nom, ville_code_postal,
                        (6366*acos(cos(radians($lanX))*cos(radians(ville_latitude_deg))*cos(radians(ville_longitude_deg)-radians($latY))+sin(radians($lanX))*sin(radians(ville_latitude_deg)))) as Proximite
                        from villes_france_free
                        order by Proximite limit 1;");

            $stmt1->execute();
            $barycentreVille = $stmt1->fetch(PDO::FETCH_ASSOC);

            $codePostal = $result['ville_code_postal'];
            $nomVille = $result['ville_nom'];

            $barycentreVille = $codePostal." | ".$nomVille;

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

        # obtenir le nombre de participants pour cette groupe
        $nbrParticipants = Rencontres::getParticipantsPourGroupe($idGroupe);

        $equipe = Rencontres::index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $equipe[2];
        $idsEntitesPasRencontre = $equipe[3];
        $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );


        $equipe = array_merge($equipe[0], $equipe[1]);

        $retourListeLieux = Rencontres::getListeLieux($idGroupe);

        // retourner le retour à la fonction appellante
        if( is_array($retourListeLieux) && array_key_exists("success", $retourListeLieux) && $retourListeLieux["success"] === FALSE){
            return $retourListeLieux;
        }
        // traitement normal
        else {
            $listeLieux = $retourListeLieux["donneesRetour"];

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
            $bdd= Rencontres::connexion();
            $stmt1 = $bdd->prepare("SELECT code_postal, ville, nom from entite where longitude = :longitude AND latitude = :latitude");
            $stmt1->bindParam(':longitude', $latY);
            $stmt1->bindParam(':latitude', $lanX);
            $stmt1->execute();
            $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
            $codePostal = $maVille['code_postal'];
            $nomVille = $maVille['ville'];
            $nomEntite = $maVille['nom'];

            $maVille = $codePostal." | ".$nomEntite." | ".$nomVille;

            //récupérer le nombre de participant pour chaque entité
            $nbrParticipants = Rencontres::getNombreParticipants($idsEntitesMerge);

            $donneesRetour = [];

            $donneesRetour[0] = $maVille;
            $donneesRetour[1] = $lanX;
            $donneesRetour[2] = $latY;
            $donneesRetour[3] = $distance;
            $donneesRetour[4] = $duree;
            $donneesRetour[5] = $equipe;
            $donneesRetour[6] = $mesVilles;
            $donneesRetour[7] = $distanceVilles;
            $donneesRetour[8] = $dureeTotale;
            $donneesRetour[9] = $listeTerrain;
            $donneesRetour[10] = $nbrParticipants;
            $donneesRetour['nbrParticipants'] = $nbrParticipants;


            // obtenir la distance totale pour toutes équipes
            $distanceTotale = Rencontres::getDistanceTotale($distanceVilles, $nbrParticipants);

            # ajouter le nombre de participants dans les résultats
            $donneesRetour["distanceTotale"] = $distanceTotale;

            # ajouter le nombre de participants dans les résultats
            $donneesRetour["nbrParticipantsTotal"] = Rencontres::getTotalNombreParticipants($nbrParticipants);

            return array('success' => True, 'donneesRetour'=>$donneesRetour, 'codeErreur'=> 0);



        }


    }

    public function terrainNeutreEquitable($idGroupe)
    {

        $equipe = Rencontres::index($idGroupe);

        # récupérer l'ids de toutes les entités
        $idsEntites = $equipe[2];
        $idsEntitesPasRencontre = $equipe[3];
        $idsEntitesMerge = array_merge($idsEntites,$idsEntitesPasRencontre );

        $equipe = array_merge($equipe[0], $equipe[1]);

        $retourListeLieux = Rencontres::getListeLieux($idGroupe);

        // retourner le retour à la fonction appellante
        if( is_array($retourListeLieux) && array_key_exists("success", $retourListeLieux) && $retourListeLieux["success"] === FALSE){
            return $retourListeLieux;
        }
        // traitement normal
        else {
            $listeLieux = $retourListeLieux["donneesRetour"];

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
            $bdd= Rencontres::connexion();
            $stmt1 = $bdd->prepare("SELECT code_postal, ville, nom from entite where longitude = :longitude AND latitude = :latitude");
            $stmt1->bindParam(':longitude', $latY);
            $stmt1->bindParam(':latitude', $lanX);
            $stmt1->execute();
            $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
            $codePostal = $maVille['code_postal'];
            $nomVille = $maVille['ville'];
            $nomEntite = $maVille['nom'];

            $maVille = $codePostal." | ".$nomEntite." | ".$nomVille;

            //récupérer le nombre de participant pour chaque entité
            $nbrParticipants = Rencontres::getNombreParticipants($idsEntitesMerge);


            $donneesRetour = [];

            $donneesRetour[0] = $maVille;
            $donneesRetour[1] = $lanX;
            $donneesRetour[2] = $latY;
            $donneesRetour[3] = $distance;
            $donneesRetour[4] = $duree;
            $donneesRetour[5] = $equipe;
            $donneesRetour[6] = $mesVilles;
            $donneesRetour[7] = $distanceVilles;
            $donneesRetour[8] = $dureeTotale;
            $donneesRetour[9] = $nbrParticipants;
            $donneesRetour['nbrParticipants'] = $nbrParticipants;

            // obtenir la distance totale pour toutes équipes
            $distanceTotale = Rencontres::getDistanceTotale($distanceVilles, $nbrParticipants);

            # ajouter le nombre de participants dans les résultats
            $donneesRetour["distanceTotale"] = $distanceTotale;

            return array('success' => True, 'donneesRetour'=>$donneesRetour, 'codeErreur'=> 0);


        }

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
            $bdd= Rencontres::connexion();
            $stmt = $bdd->prepare("SELECT nom, ville, code_postal FROM  entite WHERE id = :idEntite ;");
            $stmt->bindParam(':idEntite', $reqVilles[$i]);
            $stmt->execute();
            $maVille = $stmt->fetch(PDO::FETCH_ASSOC);
            $codePostal = $maVille['code_postal'];
            $nomVille = $maVille['ville'];
            $nomEntite = $maVille['nom'];

            $maVille = $codePostal." | ".$nomEntite." | ".$nomVille;

            array_push($villes, $maVille);

        }

        return $villes;


    }

    public function mesVilles($villes)
    {

        $mesVilles = [];
        //geocoder inversement les villes pour ramener les noms de villes
        for ($l = 0; $l < count($villes); ++$l) {

            $start = explode('%2C', $villes[$l]);
            $lanX = $start[0];
            $latY = $start[1];
            $bdd= Rencontres::connexion();
            $stmt1 = $bdd->prepare("SELECT code_postal, ville, nom from entite where longitude = :longitude AND latitude = :latitude");
            $stmt1->bindParam(':longitude', $latY);
            $stmt1->bindParam(':latitude', $lanX);
            $stmt1->execute();
            $maVille = $stmt1->fetch(PDO::FETCH_ASSOC);
            $codePostal = $maVille['code_postal'];
            $nomVille = $maVille['ville'];
            $nomEntite = $maVille['nom'];

            $maVille = $codePostal." | ".$nomEntite." | ".$nomVille;

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

        $route_app_id = $this->route_app_id;
        $route_app_code = $this->route_app_code;

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
                $bdd = Rencontres::connexion();
                $reqID = $bdd->prepare("SELECT id FROM entite where longitude = :X AND  latitude= :Y;");
                $reqID->bindParam(':X', $X);
                $reqID->bindParam(':Y', $Y);
                $reqID->execute();
                $idVille = $reqID->fetchColumn();


                //tester si on a deja le calcul de trajet entre le point start et notre point actuel
                $bdd = Rencontres::connexion();
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
                    $reqRoute = 'http://route.api.here.com/routing/7.2/calculateroute.json?waypoint0=' . $coordStart . '&waypoint1=' . $villes[$i] . '&mode=fastest%3Bcar%3Btraffic%3Adisabled&app_id=' . $route_app_id. '&app_code=' . $route_app_code;

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
                        $bdd = Rencontres::connexion();
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

        $geocode_app_id = $this->geocode_app_id;
        $geocode_app_code = $this->geocode_app_code;
        $here_request_limit = $this->here_request_limit;

        $here_request_limit_debut =  $this->here_request_limit_debut;
        $here_request_limit_fin = $this->here_request_limit_fin ;

        //convert string to date
       $dateDebut =  \DateTime::createFromFormat('Y/m/d', $here_request_limit_debut)->format('Y-d-m');
       $dateFin =  \DateTime::createFromFormat('Y/m/d', $here_request_limit_fin)->format('Y-d-m');
        

        $bdd= Rencontres::connexion();

        $reqLieux = $bdd->prepare("SELECT id_liste_lieux FROM  groupe WHERE id = :id;");
        $reqLieux->bindParam(':id', $idGroupe);
        $reqLieux->execute();
        $reqLieux = $reqLieux->fetchColumn();

        $idListeLieux = intval($reqLieux);

        if (isset($reqLieux)) {
            $bdd = Rencontres::connexion();
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

            //recuperer la date du jour
            $date = new \DateTime();
            $mois = $date->format('m');
            $annee = $date->format('Y');

            // obtenir le nombre de requetes de géo-codage
            $typeStatistiques = "nombreRequetesGeoHere";

            $bdd = Rencontres::connexion();
            $sql = "SELECT sum(valeur) FROM  statistiques_date".
                " WHERE type_statistiques = :type_statistiques".
                " and date_creation >= :dateDebut and date_creation <=:dateFin;";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':type_statistiques', $typeStatistiques);
            $stmt->bindParam(':dateDebut', $dateDebut);
            $stmt->bindParam(':dateFin', $dateFin);
            $stmt->execute();
            $nombreRequetesGeoHere = intval($stmt->fetchColumn());

        
//            error_log("\n nombreRequetesGeoHere: ".print_r($nombreRequetesGeoHere, true), 3, $this->error_log_path);

            // retourner un message d'erreur quand il y a dépassement du nombre de requetes de Géo-codage HERE
            if(($nombreRequetesGeoHere + count($listeLieux)) > $here_request_limit ){
                // code d'erreur 1 indique une erreur pour le dépassement du quota de requestes HERE
                return array('success' => False, 'donneesRetour'=>array(), 'codeErreur'=>1);
            }


            for ($i = 0; $i < count($listeLieux); $i++) {
                $bdd = Rencontres::connexion();
                $stmt = $bdd->prepare("SELECT id, nom, ville, code_postal, longitude, latitude FROM  entite WHERE id = :id");
                $stmt->bindParam(':id', $listeLieux[$i]);
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

                    $idVille = $row['id'];
                    $lat = $row['latitude'];
                    $long = $row['longitude'];
                    $nomVille = $row['ville'];
                    $nomEntite = $row['nom'];
                    $codePostal = $row['code_postal'];


                    // récupérer lat et lon s'il y en a
                    if ($long && $lat) {
                        $coordVille = $lat . '%2C' . $long;
                        //ramner le nom de la ville concatiner avec le code postal
                        $ville =  $codePostal." | ".$nomEntite." | ".$nomVille;
                        array_push($nomsVilles, $ville);
                        array_push($coordVilles, $coordVille);
                    } // sinon il faut interroger le serveur HERE
                    else {

                        $v = urlencode($nomVille);
                        $reqGeocode = 'http://geocoder.api.here.com/6.2/geocode.json?country=France&city=' . $v . '&postalCode=' . $codePostal . '&app_id=' . $geocode_app_id . '&app_code=' . $geocode_app_code . '&gen=8';

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
                        $ville =  $codePostal." | ".$nomEntite." | ".$nomVille;
                        array_push($nomsVilles, $ville);
                        array_push($coordVilles, $coordVille);

                    }
                }
            }


            # incrémenter le nombre des requetes HERE
            if($nbrRequetesGeoHere > 0){
                $this->serviceStatistiques->augmenterNombreTableStatistiques($utilisateurId, "nombreRequetesGeoHere", $nbrRequetesGeoHere);
            }


            $donneesRetour = [];
            $donneesRetour[0] = $nomsVilles;
            $donneesRetour[1] = $coordVilles;

            // retourne les infos concernant la liste de lieux, code d'erreur = 0
            return array('success' => True, 'donneesRetour'=>$donneesRetour, 'codeErreur'=> 0);

        }
        else{
            // indiquer qu'il n'y a pas de liste de lieux, code d'erreur = 0
            return array('success' => True, 'donneesRetour'=>array(), 'codeErreur'=>0);

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
            $pdo = Rencontres::connexion();
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
                $pdo = Rencontres::connexion();
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
            error_log("url: $url\n", 3, $this->error_log_path);
            error_log(print_R($errorInfo, TRUE)."\n", 3, $this->error_log_path);
            error_log("service: rencontres, function: getReponseCurl \n", 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');

        }
        curl_close($curl);


        $reqGeocodeArray = json_decode($curl_response, true);


        return $reqGeocodeArray;
    }

    private function getNombreParticipants($idsEntites)
    {

        $bdd = Rencontres::connexion();
        var_dump($bdd);
        if (is_null($bdd))
            die("connexion failed participants");

        $count = count($idsEntites);

        $nbrParticipants = [];

        for($i=0; $i<$count; $i++){

            $id = $idsEntites[$i];

             $stmt1 = $bdd->prepare("SELECT participants from entite where id=:id ;");

            $stmt1->bindParam(':id', $id);
            if ($stmt1->execute() !== true)
                die("execute failed participants");
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

        // récupérer l'id du rapport
        $courantRapportId = $this->fonctionsCommunes->getNextIdParametres();

        $prefixe_nom = "Meilleur lieu";

        $bdd = Rencontres::connexion();
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
        $nomRapport = $prefixe_nom."_".$nomGroupe."_".$courantRapportId;
        $bdd = Rencontres::connexion();
        //on ajoute un job dans la table parametres
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

        //déclaration des parametres pour la req insert dans la table parametres

        $statut = 0;
        $typeAction = "exclusion";
        $prefixe_nom = "Meilleur lieu";

        $bdd = Rencontres::connexion();
        //récupérer le nom du groupe
        $getNomGroupe = $bdd->prepare("select nom from groupe where id = :id ;");
        $getNomGroupe->bindParam(':id', $idGroupe);
        $getNomGroupe->execute();
        $nomGroupe = $getNomGroupe->fetchColumn();


        //recuperer la date du jour
        $date = new \DateTime();
        $dateCreation = $date->format('Y-m-d');
//        $nomRapport = "rapport_groupe_".$idGroupe."_action_".$typeAction;

        // récupérer l'id du rapport
        $courantRapportId = $this->fonctionsCommunes->getNextIdParametres();
        $nomRapport = $prefixe_nom."_".$nomGroupe."_".$courantRapportId;

        //on ajoute un job dans la table parametres
        $bdd = Rencontres::connexion();
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
