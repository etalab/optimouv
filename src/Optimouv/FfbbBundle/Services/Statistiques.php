<?php

namespace Optimouv\FfbbBundle\Services;

use PDO;

class Statistiques {

    private $error_log_path;
    private $database_name;
    private $database_user;
    private $database_password;
    private $database_host;

    public function __construct($database_host, $database_name, $database_user, $database_password, $error_log_path)
    {
        $this->database_host = $database_host;
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->error_log_path = $error_log_path;
    }

    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    private function getPdo(){
        # récupérer les parametres de connexion
        $host = $this->database_host;
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            # créer une objet PDO
            $pdo = new PDO('mysql:host='.$host.';dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        }
        catch (PDOException $e) {
            error_log("\n Service: Statistiques, Function: getPdo, \n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $pdo;
    }

    public function getNomUtilisateurNomFederation($idUtilisateur, $idFederation){
        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getNomUtilisateurNomFederation ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }


            if($idUtilisateur != "tous"){
                $sql = "SELECT nom, prenom from fos_user where id =:id;";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $idUtilisateur);
                $stmt->execute();
                $resultat = $stmt->fetch(PDO::FETCH_ASSOC);

                $erreurInfo = $stmt->errorInfo();
                if( $erreurInfo[0] != "00000" ){
                    error_log("\n  Erreur de récupération des données depuis la DB, details: ".print_r($erreurInfo, true)."\n Service: Statistiques, Function: getNomUtilisateurNomFederation", 3, $this->error_log_path);
                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
                }

                if($resultat != []){
                    $nomUtilisateur = $resultat["nom"];
                    $prenomUtilisateur = $resultat["prenom"];
                }
                else{
                    $nomUtilisateur = "";
                    $prenomUtilisateur = "";
                }
            }
            else{
                $nomUtilisateur = "tous";
                $prenomUtilisateur = "tous";
            }

//            error_log("\n resultat: ".print_r($resultat, true), 3, $this->error_log_path);


            $sql = "SELECT nom from federation where id =:id;";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $idFederation);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
//            error_log("\n resultat: ".print_r($resultat, true), 3, $this->error_log_path);

            if($resultat != []){
                $nomFederation = $resultat["nom"];
            }
            else{
                $nomFederation = "";
            }

            $erreurInfo = $stmt->errorInfo();
            if( $erreurInfo[0] != "00000" ){
                error_log("\n  Erreur de récupération des données depuis la DB, details: ".print_r($erreurInfo, true)."\n Service: Statistiques, Function: getNomUtilisateurNomFederation", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            return array("nomUtilisateur"=>$nomUtilisateur, "prenomUtilisateur"=>$prenomUtilisateur,
                "nomFederation"=>$nomFederation);

        }
        catch (PDOException $e) {
            error_log("\n Service: Statistiques, Function: getPdo, \n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }

    public function getDonneesStatistiques(){

        # obtenir les params envoyé par l'utilisateur
        if(array_key_exists("typeRapport", $_POST)){
            $typeRapport = $_POST["typeRapport"];
        }
        else{
            $typeRapport  = "";
        }
        if(array_key_exists("idFederation", $_POST)){
            $idFederation = $_POST["idFederation"];
        }
        else{
            $idFederation  = -1;
        }
        if(array_key_exists("idDiscipline", $_POST)){
            $idDiscipline = $_POST["idDiscipline"];
        }
        else{
            $idDiscipline  = -1;
        }
        if(array_key_exists("idUtilisateur", $_POST)){
            $idUtilisateur = $_POST["idUtilisateur"];
        }
        else{
            $idUtilisateur  = -1;
        }
        if(array_key_exists("dateDebutFormatter", $_POST)){
            $dateDebutFormatter = $_POST["dateDebutFormatter"];
        }
        else{
            $dateDebutFormatter  = "";
        }
        if(array_key_exists("dateFinFormatter", $_POST)){
            $dateFinFormatter = $_POST["dateFinFormatter"];
        }
        else{
            $dateFinFormatter  = "";
        }

        if(array_key_exists("dateDebutStr", $_POST)){
            $dateDebutStr = $_POST["dateDebutStr"];
        }
        else{
            $dateDebutStr  = "";
        }
        if(array_key_exists("dateFinStr", $_POST)){
            $dateFinStr = $_POST["dateFinStr"];
        }
        else{
            $dateFinStr  = "";
        }


        # obtenir le nombre de jours entre les dates
        $dateDebut = strtotime($dateDebutFormatter);
        $dateFin = strtotime($dateFinFormatter);
        $datediff = ceil( ($dateFin - $dateDebut)/(60*60*24) )+1;
//        error_log("\n datediff: ".print_r($_POST, true), 3, $this->error_log_path);


        # determiner le type d'affichage pour les dates
        if($datediff <= 31){
            $formatResultat = "jour";
        }
        elseif($datediff <=365){
            $formatResultat = "mois";
        }
        else{
            $formatResultat = "annee";
        }
//        error_log("\n formatResultat: ".print_r($formatResultat, true), 3, $this->error_log_path);


        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getDonneesStatistiques ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

//            error_log("\n typeRapport: ".print_r($typeRapport, true), 3, $this->error_log_path);

            $lignesTableau = array();


            // données pour le tableau statistiques_date
            if($typeRapport == "utilisateur"){
                # obtenir l'id de la fédération
                $sql = "SELECT date_creation, type_statistiques, valeur from statistiques_date where date_creation between :dateDebut and :dateFin".
                    " and id_utilisateur=:id_utilisateur and id_discipline=:id_discipline and id_federation=:id_federation order by date_creation asc  ;";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id_utilisateur', $idUtilisateur);
                $stmt->bindParam(':id_discipline', $idDiscipline);

            }
            elseif($typeRapport == "federation" || $typeRapport == "systeme" ){
                if($idDiscipline == "tous"){
                    $sql = "SELECT date_creation, type_statistiques, valeur from statistiques_date where date_creation between :dateDebut and :dateFin".
                        " and id_federation=:id_federation order by date_creation asc  ;";
                    $stmt = $pdo->prepare($sql);
                }
                else{
                    $sql = "SELECT date_creation, type_statistiques, valeur from statistiques_date where date_creation between :dateDebut and :dateFin".
                        " and id_discipline=:id_discipline and id_federation=:id_federation order by date_creation asc  ;";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id_discipline', $idDiscipline);
                }
            }
            $stmt->bindParam(':dateDebut', $dateDebutFormatter);
            $stmt->bindParam(':dateFin', $dateFinFormatter);
            $stmt->bindParam(':id_federation', $idFederation);
            $stmt->execute();
            $resultat = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $erreurInfo = $stmt->errorInfo();

            if( $erreurInfo[0] != "00000" ){
                error_log("\n  Erreur de récupération des données depuis la DB, details: ".print_r($erreurInfo, true)."\n Service: Statistiques, Function: getDonneesStatistiques", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            // max valeur pour graphe y axis
            $maxValeurYAxis = 0;

            // loop pour le tableau statistiques_date
            foreach($resultat as $ligneDb){
                $dateLigneDb = $ligneDb["date_creation"];
                $valeur = $ligneDb["valeur"];
                $typeStatistiques = $ligneDb["type_statistiques"];


                # formater la date selon le format français
                $dateLigneTmp = explode("-", $dateLigneDb);

                # formater les données selon le type
                if($formatResultat == "jour"){
                    $dateLigneMod = $dateLigneTmp[2]. "/". $dateLigneTmp[1]."/".$dateLigneTmp[0];
                }
                elseif($formatResultat == "mois"){
                    $dateLigneMod = $dateLigneTmp[1]."/".$dateLigneTmp[0];
                }
                # pour l'année
                else{
                    $dateLigneMod = $dateLigneTmp[0];
                }

                # remplir les données pour la ligne
                if(array_key_exists($dateLigneMod, $lignesTableau)){
                    if(array_key_exists($typeStatistiques, $lignesTableau[$dateLigneMod])){
                        $lignesTableau[$dateLigneMod][$typeStatistiques] += $valeur  ;
                    }
                    else{
                        $lignesTableau[$dateLigneMod][$typeStatistiques] = $valeur  ;
                    }
                }
                else{
                    $lignesTableau[$dateLigneMod] = array($typeStatistiques => $valeur);
                }
            }


            // données pour le temps de réponse moyen (table statistiques_date_temps)
            if($typeRapport == "systeme"){
                if($idDiscipline == "tous"){
                    if($formatResultat == "jour"){
                        $sql = "SELECT date(temps_fin) as date_filtre, type_statistiques, avg(valeur) as avg_valeur from statistiques_date_temps ".
                            "where date(temps_fin) between :dateDebut and :dateFin ".
                            " and id_federation=:id_federation group by date(temps_fin), type_statistiques ;";
                    }
                    elseif($formatResultat == "mois"){
                        $sql = "SELECT date_format(temps_fin, '%Y-%m') as date_filtre, type_statistiques, avg(valeur) as avg_valeur from statistiques_date_temps ".
                            "where date(temps_fin) between :dateDebut and :dateFin ".
                            " and id_federation=:id_federation group by month(temps_fin), type_statistiques ;";
                    }
                    elseif($formatResultat == "annee"){
                        $sql = "SELECT year(temps_fin) as date_filtre, type_statistiques, avg(valeur) as avg_valeur from statistiques_date_temps ".
                            "where date(temps_fin) between :dateDebut and :dateFin ".
                            " and id_federation=:id_federation group by year(temps_fin), type_statistiques ;";
                    }

                    $stmt = $pdo->prepare($sql);
                }
                else
                {
                    if($formatResultat == "jour"){
                        $sql = "SELECT date(temps_fin) as date_filtre, type_statistiques, avg(valeur) as avg_valeur from statistiques_date_temps ".
                            " where date(temps_fin) between :dateDebut and :dateFin".
                            " and id_discipline=:id_discipline and id_federation=:id_federation group by date(temps_fin), type_statistiques  ;";
                    }
                    elseif($formatResultat == "mois"){
                        $sql = "SELECT date_format(temps_fin, '%Y-%m') as date_filtre, type_statistiques, avg(valeur) as avg_valeur from statistiques_date_temps ".
                            " where date(temps_fin) between :dateDebut and :dateFin".
                            " and id_discipline=:id_discipline and id_federation=:id_federation group by month(temps_fin), type_statistiques  ;";
                    }
                    elseif($formatResultat == "annee"){
                        $sql = "SELECT year(temps_fin) as date_filtre, type_statistiques, avg(valeur) as avg_valeur from statistiques_date_temps ".
                            " where date(temps_fin) between :dateDebut and :dateFin".
                            " and id_discipline=:id_discipline and id_federation=:id_federation group by year(temps_fin), type_statistiques  ;";
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id_discipline', $idDiscipline);
                }

                $stmt->bindParam(':dateDebut', $dateDebutFormatter);
                $stmt->bindParam(':dateFin', $dateFinFormatter);
                $stmt->bindParam(':id_federation', $idFederation);
                $stmt->execute();
                $resultat = $stmt->fetchAll(PDO::FETCH_ASSOC);
//                error_log("\n resultat: ".print_r($resultat, true), 3, $this->error_log_path);

                $erreurInfo = $stmt->errorInfo();


                if($erreurInfo[0] != "00000"){
                    error_log("\n  Erreur de récupération des données depuis la DB, details: ".print_r($erreurInfo, true)."\n Service: Statistiques, Function: getDonneesStatistiques", 3, $this->error_log_path);
                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
                }

                // loop pour le tableau statistiques_date_temps
                foreach($resultat as $ligneDb){

                    $dateLigneDb = $ligneDb["date_filtre"];
                    $valeurEnSecondes = round($ligneDb["avg_valeur"]);
                    $typeStatistiques = $ligneDb["type_statistiques"];

                    // convertir les secondes en format Heures:Minutes:Secondes
                    $valeur = sprintf('%02d:%02d:%02d', ($valeurEnSecondes/3600),($valeurEnSecondes/60%60), $valeurEnSecondes%60);

                    # formater la date selon le format français
                    $dateLigneTmp = explode("-", $dateLigneDb);

                    # formater les données selon le type
                    if($formatResultat == "jour"){
                        $dateLigneMod = $dateLigneTmp[2]. "/". $dateLigneTmp[1]."/".$dateLigneTmp[0];
                    }
                    elseif($formatResultat == "mois"){
                        $dateLigneMod = $dateLigneTmp[1]."/".$dateLigneTmp[0];
                    }
                    # pour l'année
                    else{
                        $dateLigneMod = $dateLigneTmp[0];
                    }


                    # remplir les données pour la ligne
                    if(array_key_exists($dateLigneMod, $lignesTableau)){
                        if(array_key_exists($typeStatistiques, $lignesTableau[$dateLigneMod])){
                            $lignesTableau[$dateLigneMod][$typeStatistiques] += $valeur  ;
                        }
                        else{
                            $lignesTableau[$dateLigneMod][$typeStatistiques] = $valeur  ;
                        }
                    }
                    else{
                        $lignesTableau[$dateLigneMod] = array($typeStatistiques => $valeur);
                    }


                }

            }



            # ajouter un flag pour indiquer s'il y a des données ou pas
            if(count($lignesTableau) == 0){
                $flagDonneesExiste = 0;
            }
            else{
                $flagDonneesExiste = 1;

            }

            # compléter les dates manquantes dans l'interval donné (s'il y a au moins deux lignes dans les données tabulaires)
            if(count($lignesTableau) > 0){
                $lignesTableauCompleter = $this->completerDateDonneesStatistiques($lignesTableau, $formatResultat, $dateDebutStr, $dateFinStr);
            }
            else{
                $lignesTableauCompleter = $lignesTableau;
            }
//            error_log("\n lignesTableauCompleter: ".print_r($lignesTableauCompleter, true), 3, $this->error_log_path);


            # données pour la graphique
            $donneesGraph = [];
            if(count($lignesTableauCompleter) > 0){
                $flagAfficheGraphique = 1;

                # obtenir la date de début pour la graphique
                reset($lignesTableauCompleter);
                $dateDebutGraph = key($lignesTableauCompleter);

                # obtenir la date de fin pour la graphique
                end($lignesTableauCompleter);
                $dateFinGraph = key($lignesTableauCompleter);

                if($formatResultat == "jour"){
                    $dateDebutGraph = date_format(date_create_from_format('d/m/Y', $dateDebutGraph), 'Y/m/d');
                    $dateFinGraph = date_format(date_create_from_format('d/m/Y', $dateFinGraph), 'Y/m/d');
                }
                elseif($formatResultat == "mois"){

                    $dateDebutGraph = date_format(date_create_from_format('m/Y', $dateDebutGraph), 'Y/m/d');
                    $dateFinGraph = date_format(date_create_from_format('m/Y', $dateFinGraph), 'Y/m/d');
                }
                elseif($formatResultat == "annee"){
                    $dateDebutGraph = date_format(date_create_from_format('Y', $dateDebutGraph), 'Y/m/d');
                    $dateFinGraph = date_format(date_create_from_format('Y', $dateFinGraph), 'Y/m/d');
                }


                # compter les nombre de labels affiché pour x-axis
                $nbrLabelXAxis = count($lignesTableauCompleter);

//                error_log("\n dateDebutGraph: ".print_r($dateDebutGraph, true), 3, $this->error_log_path);
//                error_log("\n dateFinGraph: ".print_r($dateFinGraph, true), 3, $this->error_log_path);
//                error_log("\n nbrLabelXAxis: ".print_r($nbrLabelXAxis, true), 3, $this->error_log_path);


                # données pour la graphique
                $donneesNbrConnexions = [];
                $donneesNbrLancementOptiPoule = [];
                $donneesNbrLancementMeilleurLieu = [];
                $donneesNbrRequetesHere = [];
                $donneesNbrExclusions = [];
                $donneesNbrInterdictions = [];
                $donneesNbrRepartitionsHomogenes = [];

                foreach($lignesTableauCompleter as $dateCle => $donneesLigne){

                    if(array_key_exists("nombreConnexions", $donneesLigne)){
                        $nombreConnexions = $donneesLigne["nombreConnexions"];
                        if($nombreConnexions > $maxValeurYAxis){
                            $maxValeurYAxis = $nombreConnexions;
                        }
                    }
                    else{
                        $nombreConnexions = 0;
                    }
                    if(array_key_exists("nombreLancementsOptiPoule", $donneesLigne)){
                        $nombreLancementsOptiPoule = $donneesLigne["nombreLancementsOptiPoule"];
                        if($nombreLancementsOptiPoule > $maxValeurYAxis){
                            $maxValeurYAxis = $nombreLancementsOptiPoule;
                        }
                    }
                    else{
                        $nombreLancementsOptiPoule = 0;
                    }
                    if(array_key_exists("nombreLancementsMeilleurLieu", $donneesLigne)){
                        $nombreLancementsMeilleurLieu = $donneesLigne["nombreLancementsMeilleurLieu"];
                        if($nombreLancementsMeilleurLieu > $maxValeurYAxis){
                            $maxValeurYAxis = $nombreLancementsMeilleurLieu;
                        }
                    }
                    else{
                        $nombreLancementsMeilleurLieu = 0;
                    }
                    if(array_key_exists("nombreRequetesHere", $donneesLigne)){
                        $nombreRequetesHere = $donneesLigne["nombreRequetesHere"];
                        if($nombreRequetesHere > $maxValeurYAxis){
                            $maxValeurYAxis = $nombreRequetesHere;
                        }
                    }
                    else{
                        $nombreRequetesHere = 0;
                    }
                    if(array_key_exists("nombreExclusions", $donneesLigne)) {
                        $nombreExclusions = $donneesLigne["nombreExclusions"];
                        if ($nombreExclusions > $maxValeurYAxis) {
                            $maxValeurYAxis = $nombreExclusions;
                        }
                    }
                    else{
                        $nombreExclusions = 0;
                    }
                    if(array_key_exists("nombreInterdictions", $donneesLigne)){
                        $nombreInterdictions = $donneesLigne["nombreInterdictions"];
                        if($nombreInterdictions > $maxValeurYAxis){
                            $maxValeurYAxis = $nombreInterdictions;
                        }
                    }
                    else{
                        $nombreInterdictions = 0;
                    }
                    if(array_key_exists("nombreRepartitionsHomogenes", $donneesLigne)){
                        $nombreRepartitionsHomogenes = $donneesLigne["nombreRepartitionsHomogenes"];
                        if($nombreRepartitionsHomogenes > $maxValeurYAxis){
                            $maxValeurYAxis = $nombreRepartitionsHomogenes;
                        }
                    }
                    else{
                        $nombreRepartitionsHomogenes = 0;
                    }


                    if($formatResultat == "jour"){
                        $formatDate = 'd/m/Y';
                    }
                    elseif($formatResultat == "mois"){
                        $formatDate = 'm/Y';
                    }
                    elseif($formatResultat == "annee"){
                        $formatDate = 'Y';
                    }
                    $dateMod = date_format(date_create_from_format($formatDate, $dateCle), 'Y/m/d');
//                    error_log("\n dateMod: ".print_r($dateMod, true), 3, $this->error_log_path);

                    array_push($donneesNbrConnexions, array("dateMod"=>$dateMod, "valeur"=> $nombreConnexions));
                    array_push($donneesNbrLancementOptiPoule, array("dateMod"=>$dateMod, "valeur"=> $nombreLancementsOptiPoule));
                    array_push($donneesNbrLancementMeilleurLieu, array("dateMod"=>$dateMod, "valeur"=> $nombreLancementsMeilleurLieu));
                    array_push($donneesNbrRequetesHere, array("dateMod"=>$dateMod, "valeur"=> $nombreRequetesHere));
                    array_push($donneesNbrExclusions, array("dateMod"=>$dateMod, "valeur"=> $nombreExclusions));
                    array_push($donneesNbrInterdictions, array("dateMod"=>$dateMod, "valeur"=> $nombreInterdictions));
                    array_push($donneesNbrRepartitionsHomogenes, array("dateMod"=>$dateMod, "valeur"=> $nombreRepartitionsHomogenes));
                }
//                error_log("\n donneesNbrConnexions: ".print_r($donneesNbrConnexions, true), 3, $this->error_log_path);


                # ajouter les dates dans les données de graph
                $donneesGraph["dateDebutGraph"] = $dateDebutGraph;
                $donneesGraph["dateFinGraph"] = $dateFinGraph;
                $donneesGraph["formatResultat"] = $formatResultat;
                $donneesGraph["nbrLabelXAxis"] = $nbrLabelXAxis;
                $donneesGraph["maxValeurYAxis"] = $maxValeurYAxis;

                $donneesGraph["typeRapport"] = $typeRapport;
                $donneesGraph["donneesNbrConnexions"] = $donneesNbrConnexions;
                $donneesGraph["donneesNbrLancementOptiPoule"] = $donneesNbrLancementOptiPoule;
                $donneesGraph["donneesNbrLancementMeilleurLieu"] = $donneesNbrLancementMeilleurLieu;
                $donneesGraph["donneesNbrRequetesHere"] = $donneesNbrRequetesHere;
                $donneesGraph["donneesNbrExclusions"] = $donneesNbrExclusions;
                $donneesGraph["donneesNbrInterdictions"] = $donneesNbrInterdictions;
                $donneesGraph["donneesNbrRepartitionsHomogenes"] = $donneesNbrRepartitionsHomogenes;

            }
            else{
                $flagAfficheGraphique = 0;
            }
//            error_log("\n flagAfficheGraphique: ".print_r($flagAfficheGraphique, true), 3, $this->error_log_path);
//            error_log("\n lignesTableauCompleter: ".print_r($lignesTableauCompleter, true), 3, $this->error_log_path);
//            error_log("\n donneesGraph: ".print_r($donneesGraph, true), 3, $this->error_log_path);
//            error_log("\n flagDonneesExiste: ".print_r($flagDonneesExiste, true), 3, $this->error_log_path);
//            error_log("\n flagAfficheGraphique: ".print_r($flagAfficheGraphique, true), 3, $this->error_log_path);

            return array("lignesTableau" => $lignesTableauCompleter,
                "donneesGraph" => $donneesGraph,
                "flagDonneesExiste" => $flagDonneesExiste,
                "flagAfficheGraphique" => $flagAfficheGraphique,

            );

        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getDonneesStatistiques, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }


    # Fonction pour completer les dates manquantes dans l'interval donné
    private function completerDateDonneesStatistiques($donneesStatistiques, $formatResultat, $dateDebutStr, $dateFinStr){

        # obtenir la zone par défault du système
        date_default_timezone_set('Europe/Paris');

        $donneesCompleter = array();
        error_log("\n donneesStatistiques: ".print_r($donneesStatistiques, true), 3, $this->error_log_path);

        if($formatResultat == "jour"){
            $formatDate = 'd/m/Y';
            $deltaTemps = "1 day";

        }
        elseif($formatResultat == "mois"){

            # modifier le format
            $dateDebutStr = date_format(date_create_from_format('d/m/Y', $dateDebutStr), 'm/Y');
            $dateFinStr = date_format(date_create_from_format('d/m/Y', $dateFinStr), 'm/Y');

            $formatDate = 'm/Y';
            $deltaTemps = "1 month";
        }
        elseif($formatResultat == "annee"){
            # modifier le format
            $dateDebutStr = date_format(date_create_from_format('d/m/Y', $dateDebutStr), 'Y');
            $dateFinStr = date_format(date_create_from_format('d/m/Y', $dateFinStr), 'Y');

            $formatDate = 'Y';
            $deltaTemps = "1 year";

        }

//        error_log("\n formatDate: ".print_r($formatDate, true), 3, $this->error_log_path);
//        error_log("\n deltaTemps: ".print_r($deltaTemps, true), 3, $this->error_log_path);

        # créer un objet DateTime pour la date de début
        $dateTimeDebut = \DateTime::createFromFormat($formatDate, $dateDebutStr);


        # créer un objet DateTime pour la date de fin
        $dateTimeFin = \DateTime::createFromFormat($formatDate, $dateFinStr);
        # ajouter un delta temps car l'objet DatePeriod n'inclut pas la date de fin
        if($dateTimeFin){
            $dateTimeFin = $dateTimeFin->modify( '+'.$deltaTemps );
        }

        # interval selon le delta temps
        $interval = \DateInterval::createFromDateString($deltaTemps);


        # periode depuis la date de début jusqu'à la date de fin
        $periode = new \DatePeriod($dateTimeDebut, $interval, $dateTimeFin);


        foreach ( $periode as $dt ){
            $dateCourante = $dt->format( $formatDate);

//            error_log("\n dateCourante: ".print_r($dateCourante, true)."\n", 3, $this->error_log_path);
            if(array_key_exists($dateCourante, $donneesStatistiques)){
                $donneesCompleter[$dateCourante] = $donneesStatistiques[$dateCourante];
            }
            else{
                $donneesCompleter[$dateCourante] = array();

            }
        }

//        error_log("\n donneesCompleter: ".print_r($donneesCompleter, true)."\n", 3, $this->error_log_path);
        return $donneesCompleter;


    }

    public function augmenterNombreTableStatistiques($utilisateurId, $typeStatistiques, $valeur){
        $disciplineId = $this->getDisciplineId($utilisateurId);
        $federationId = $this->getFederationId($disciplineId);

        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: augmenterNombreTableStatistiques ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # insérer dans la base de données
            $sql = "INSERT INTO  statistiques_date (date_creation, type_statistiques, id_utilisateur, id_discipline, id_federation, valeur)
                    VALUES (now(), :type_statistiques, :id_utilisateur, :id_discipline, :id_federation, :valeur)
                    on duplicate key UPDATE valeur=valeur+VALUES(valeur);";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':type_statistiques', $typeStatistiques);
            $stmt->bindParam(':id_utilisateur', $utilisateurId);
            $stmt->bindParam(':id_discipline', $disciplineId);
            $stmt->bindParam(':id_federation', $federationId);
            $stmt->bindParam(':valeur', $valeur);
            $statutInsert = $stmt->execute();

            if(!$statutInsert){
                error_log("\n  Erreur d'insertion des données dans DB, details: ".print_r($stmt->errorInfo(), true)."\n Service: Statistiques, Function: augmenterNombreTableStatistiques", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }




        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getFederationId, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }
    }

    public function getFederationId($disciplineId){
        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getFederationId ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # obtenir l'id de la fédération
            $sql = "SELECT id_federation from discipline where id=:id;";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $disciplineId);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            $federationId = $resultat["id_federation"];

            if($federationId == ""){
                error_log("\n  l'identifiant de la fédération est null, Service: Statistiques, Function: getFederationId", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            return $federationId;

        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getFederationId, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }


    public function getDisciplineId($utilisateurId){
        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getDisciplineId ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # obtenir l'id de la discipline
            $sql = "SELECT id_discipline from fos_user where id=:id;";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $utilisateurId);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            $disciplineId = $resultat["id_discipline"];

            if($disciplineId == ""){
                error_log("\n  l'identifiant de la discipline est null, Service: Statistiques, Function: getDisciplineId", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }
            return $disciplineId;
        }

        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getDisciplineId, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


    }



    public function getDetailsFederation($federationId){
        try{
            # obtenir l'objet PDO
            $pdo = $this->getPdo();

            if (!$pdo) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Statistiques, Function: getDetailsFederation ", 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            if($federationId == "tous"){
                $sql = "SELECT * from federation";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $federations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            else{
                $sql = "SELECT * from federation where id=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $federationId);
                $stmt->execute();
                $federations = $stmt->fetchALL(PDO::FETCH_ASSOC);
            }

            if(!$federations ){
                error_log("\n  Erreur lors de la récupération de la liste de fédérations, Service: Statistiques, Function: getDetailsFederation, errorInfo: "
                    .print_r($pdo->errorInfo(), true) , 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            // obtenir les détails des disciplines
            foreach($federations as $indexFederation => &$federation){
                $federationId = $federation["id"];
                $sql = "SELECT id, nom from discipline where id_federation=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $federationId);
                $stmt->execute();
                $disciplines = $stmt->fetchALL(PDO::FETCH_ASSOC);


//                if(!$disciplines ){
//                    error_log("\n  Erreur lors de la récupération de la liste de disciplines pour une fédération, Service: Statistiques, Function: getDetailsFederation, errorInfo: "
//                        .print_r($pdo->errorInfo(), true) , 3, $this->error_log_path);
//                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
//                }

                // obtenir les détails des utilisateurs
                foreach($disciplines as $indexDiscipline=> &$discipline ){
                    $disciplineId = $discipline["id"];

                    $sql = "SELECT id, nom, prenom from fos_user where id_discipline=:id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $disciplineId);
                    $stmt->execute();
                    $utilisateurs = $stmt->fetchALL(PDO::FETCH_ASSOC);

                    $discipline["utilisateurs"] = $utilisateurs;

                    # enlever la discipline si elle n'a pas d'utilisateurs
                    if($utilisateurs == []){
                        unset($disciplines[$indexDiscipline]);
                        // reset les clés
                        $disciplines = array_values($disciplines);
                        continue;

                    }

//                    error_log("\n utilisateurs: ".print_r($utilisateurs, true), 3, $this->error_log_path);

                }

                if($disciplines != []){
                    // ajouter une clé dans la liste de fédérations
                    $federation["disciplines"] = $disciplines;
                }
                elseif($disciplines == []){
                    unset($federations[$indexFederation]);
                    // reset les clés
                    $federations = array_values($federations);
                    continue;
                }

            }


            return $federations;

        }
        catch (PDOException $e){
            error_log("\n erreur PDO, Service: Statistiques, Function: getDetailsFederation, erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }



}

