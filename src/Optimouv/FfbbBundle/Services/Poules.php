<?php
/**
 * Created by PhpStorm.
 * User: henz
 * Date: 21/01/16
 * Time: 11:40
 */

namespace Optimouv\FfbbBundle\Services;

use PDO;

class Poules{

    private $database_name;
    private $database_user;
    private $database_password;
    private $app_id;
    private $app_code;
    private $error_log_path;

    public function __construct($database_name, $database_user, $database_password, $app_id, $app_code, $error_log_path)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->app_id = $app_id;
        $this->app_code = $app_code;
        $this->error_log_path = $error_log_path;
    }

    public function sauvegarderParamsEnDB()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try{

            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            # params d'insertion
            $idGroupe = $_POST["idGroupe"];
            $poulesNbr = intval($_POST["poulesNbr"]);
            $typeAction = $_POST["typeMatch"];

//            error_log("\n Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow
//                ."\n post: ".print_r($_POST , true), 3, $this->error_log_path);
            # contraintes d'interdiction
            if(array_key_exists("interdictions", $_POST)){
                $interdictions = $_POST["interdictions"];
            }
            else{
                $interdictions = [];
            }

            # contraintes de repartitions homogenes
            if(array_key_exists("repartitionsHomogenes", $_POST)){
                $repartitionsHomogenes = $_POST["repartitionsHomogenes"];
            }
            else{
                $repartitionsHomogenes  = [];
            }

            # variation du nombre d'equipes par poule
            if(array_key_exists("varEquipeParPoule", $_POST)){
                $varEquipeParPoule = $_POST["varEquipeParPoule"];
            }
            else{
                $varEquipeParPoule   = 0;
            }

            # id de l'ancien résultat
            if(array_key_exists("idAncienResultat", $_POST)){
                $idAncienResultat = $_POST["idAncienResultat"];
            }
            else{
                $idAncienResultat = -1;
            }

            # controler l'existence de la contrainte d'accueil pour le match plateau
            if(array_key_exists("contrainteAccueilPlateauExiste", $_POST)){
                $contrainteAccueilPlateauExiste = intval($_POST["contrainteAccueilPlateauExiste"]);

            }
            else{
                $contrainteAccueilPlateauExiste = 0;
            }


            $nom = "rapport_groupe_".$idGroupe."_action_".$typeAction;
            $statut = 0;
            $params = json_encode(array("nbrPoule" => $poulesNbr, "interdictions"=> $interdictions,
                "repartitionHomogene"=> $repartitionsHomogenes, "varEquipeParPoule"=> $varEquipeParPoule,
                "idAncienResultat"=> $idAncienResultat, "contrainteAccueilPlateauExiste" => $contrainteAccueilPlateauExiste
            ));

            # insérer dans la base de données
            $sql = "INSERT INTO  parametres (nom, id_groupe, type_action, date_creation, statut, params) VALUES ( :nom, :id_groupe, :type_action, :date_creation, :statut, :params);";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':id_groupe', $idGroupe);
            $stmt->bindParam(':type_action', $typeAction);
            $stmt->bindParam(':date_creation', $dateCreation);
            $stmt->bindParam(':statut', $statut);
            $stmt->bindParam(':params', $params);
            $stmt->execute();

            # afficher le statut de la requete executée
            error_log("\n Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow
                ."\n Error Info: ".print_r($stmt->errorInfo(), true), 3, $this->error_log_path);

            # obtenir l'id de l"entité créée
            $idParams = $bdd->lastInsertId();

            $retour = array(
                "success" => true,
                "data" => $idParams
            );


            return $retour;

        } catch (PDOException $e) {
            error_log("\n erreur PDO, Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow."\n"
            ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }

    public function parserComparaisonScenario($detailsVilles, $scenarioOptimalAvecContrainte, $scenarioOptimalSansContrainte, $scenarioEquitableAvecContrainte, $scenarioEquitableSansContrainte, $scenarioRef, $refExiste, $contraintsExiste, $typeMatch  )
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateCreation = date('Y-m-d', time());
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try{
            $detailsEquipeComparaison = [];

            for($i=0; $i<count($detailsVilles); $i++){
                $idEquipe = $detailsVilles[$i]["id"];
                $nomEquipe = $detailsVilles[$i]["nom"];

                $distanceScenarioOptimalAvecContrainte = 0;
                $distanceScenarioOptimalSansContrainte = 0;
                $distanceScenarioEquitableAvecContrainte = 0;
                $distanceScenarioEquitableSansContrainte = 0;
                $distanceScenarioRef = 0;

                $distanceTotaleScenarioOptimalAvecContrainte = 0;
                $distanceTotaleScenarioOptimalSansContrainte = 0;
                $distanceTotaleScenarioEquitableAvecContrainte = 0;
                $distanceTotaleScenarioEquitableSansContrainte = 0;
                $distanceTotaleScenarioRef = 0;

                $dureeScenarioOptimalAvecContrainte = 0;
                $dureeScenarioOptimalSansContrainte = 0;
                $dureeScenarioEquitableAvecContrainte = 0;
                $dureeScenarioEquitableSansContrainte = 0;
                $dureeScenarioRef = 0;

                # calcul données pour comparaison depuis rencontre details pour scenario optimal sans contrainte
                $rencontreDetails = $scenarioOptimalSansContrainte["rencontreDetails"];

                foreach($rencontreDetails as $poule => $contenuPoule){

                    if($typeMatch == "allerRetour" or $typeMatch == "allerSimple"){
                        foreach($contenuPoule as $rencontre => $contenuRencontre){

                            if($contenuRencontre["equipeDepartId"] == $idEquipe){
                                $distanceScenarioOptimalSansContrainte += $contenuRencontre["distance"];
                                $distanceTotaleScenarioOptimalSansContrainte += $contenuRencontre["distanceTousParticipants"];
                                $dureeScenarioOptimalSansContrainte += $contenuRencontre["duree"];

                            }
                        }
                    }
                    elseif($typeMatch == "plateau"){
                        foreach($contenuPoule as $jour => $contenuJour){

                            foreach($contenuJour as $groupe => $contenuGroupe){
//                                error_log("\n Service: Poules, Function: parserComparaisonScenario, datetime: ".$dateTimeNow
//                                    ."\n contenuGroupe: ".print_r($contenuGroupe , true), 3, $this->error_log_path);

                                # chercher la ville courante dans la liste de premier ou deuxième équipe
                                if($idEquipe == $contenuGroupe["premierEquipeId"] or $idEquipe == $contenuGroupe["deuxiemeEquipeId"] ){
                                    $departId = $idEquipe;
                                    $destinationId = $contenuGroupe["hoteId"];

                                    $resultat = $this->getDetailsTrajet($departId, $destinationId);
                                    $distanceScenarioOptimalSansContrainte += $resultat["distance"];
                                    $distanceTotaleScenarioOptimalSansContrainte += ($resultat["distance"] * $contenuGroupe["nbrParticipants"]  );
                                    $dureeScenarioOptimalSansContrainte += $resultat["duree"];

                                }

                            }

                        }

                    }

                }

                # calcul données pour comparaison depuis rencontre details pour scenario équitable sans contrainte
                $rencontreDetails = $scenarioEquitableSansContrainte["rencontreDetails"];
                foreach($rencontreDetails as $poule => $contenuPoule){
                    if($typeMatch == "allerRetour" or $typeMatch == "allerSimple"){
                        foreach($contenuPoule as $rencontre => $contenuRencontre){
                            if($contenuRencontre["equipeDepartId"] == $idEquipe){
                                $distanceScenarioEquitableSansContrainte += $contenuRencontre["distance"];
                                $distanceTotaleScenarioEquitableSansContrainte += $contenuRencontre["distanceTousParticipants"];
                                $dureeScenarioEquitableSansContrainte += $contenuRencontre["duree"];

                            }
                        }

                    }
                    elseif($typeMatch == "plateau"){

                        foreach($contenuPoule as $jour => $contenuJour){

                            foreach($contenuJour as $groupe => $contenuGroupe){
                                # chercher la ville courante dans la liste de premier ou deuxième équipe
                                if($idEquipe == $contenuGroupe["premierEquipeId"] or $idEquipe == $contenuGroupe["deuxiemeEquipeId"] ){
                                    $departId = $idEquipe;
                                    $destinationId = $contenuGroupe["hoteId"];

                                    $resultat = $this->getDetailsTrajet($departId, $destinationId);

                                    $distanceScenarioEquitableSansContrainte += $resultat["distance"];
                                    $distanceTotaleScenarioEquitableSansContrainte += ($resultat["distance"] * $contenuGroupe["nbrParticipants"]  );
                                    $dureeScenarioEquitableSansContrainte += $resultat["duree"];

                                }

                            }

                        }

                    }
                }


                if($contraintsExiste){
                    # calcul données pour comparaison depuis rencontre details pour scenario optimal avec contrainte
                    $rencontreDetails = $scenarioOptimalAvecContrainte["rencontreDetails"];
                    foreach($rencontreDetails as $poule => $contenuPoule){

                        if($typeMatch == "allerRetour" or $typeMatch == "allerSimple"){
                            foreach($contenuPoule as $rencontre => $contenuRencontre){
                                if($contenuRencontre["equipeDepartId"] == $idEquipe){
                                    $distanceScenarioOptimalAvecContrainte += $contenuRencontre["distance"];
                                    $distanceTotaleScenarioOptimalAvecContrainte += $contenuRencontre["distanceTousParticipants"];
                                    $dureeScenarioOptimalAvecContrainte += $contenuRencontre["duree"];

                                }
                            }

                        }
                        elseif($typeMatch == "plateau"){
                            foreach($contenuPoule as $jour => $contenuJour){
                                foreach($contenuJour as $groupe => $contenuGroupe){
                                    # chercher la ville courante dans la liste de premier ou deuxième équipe
                                    if($idEquipe == $contenuGroupe["premierEquipeId"] or $idEquipe == $contenuGroupe["deuxiemeEquipeId"] ){
                                        $departId = $idEquipe;
                                        $destinationId = $contenuGroupe["hoteId"];

                                        $resultat = $this->getDetailsTrajet($departId, $destinationId);

                                        $distanceScenarioOptimalAvecContrainte += $resultat["distance"];
                                        $distanceTotaleScenarioOptimalAvecContrainte += ($resultat["distance"] * $contenuGroupe["nbrParticipants"] );
                                        $dureeScenarioOptimalAvecContrainte += $resultat["duree"];

                                    }


                                }


                            }

                        }
                    }

                    # calcul données pour comparaison depuis rencontre details pour scenario équitable avec contrainte
                    $rencontreDetails = $scenarioEquitableAvecContrainte["rencontreDetails"];
                    foreach($rencontreDetails as $poule => $contenuPoule){
                        if($typeMatch == "allerRetour" or $typeMatch == "allerSimple"){
                            foreach($contenuPoule as $rencontre => $contenuRencontre){
                                if($contenuRencontre["equipeDepartId"] == $idEquipe){
                                    $distanceScenarioEquitableAvecContrainte += $contenuRencontre["distance"];
                                    $distanceTotaleScenarioEquitableAvecContrainte += $contenuRencontre["distanceTousParticipants"];
                                    $dureeScenarioEquitableAvecContrainte += $contenuRencontre["duree"];

                                }
                            }

                        }
                        elseif($typeMatch == "plateau"){
                            foreach($contenuPoule as $jour => $contenuJour){
                                foreach($contenuJour as $groupe => $contenuGroupe) {
                                    # chercher la ville courante dans la liste de premier ou deuxième équipe
                                    if ($idEquipe == $contenuGroupe["premierEquipeId"] or $idEquipe == $contenuGroupe["deuxiemeEquipeId"]) {
                                        $departId = $idEquipe;
                                        $destinationId = $contenuGroupe["hoteId"];

                                        $resultat = $this->getDetailsTrajet($departId, $destinationId);

                                        $distanceScenarioEquitableAvecContrainte += $resultat["distance"];
                                        $distanceTotaleScenarioEquitableAvecContrainte += ($resultat["distance"] * $contenuGroupe["nbrParticipants"]);
                                        $dureeScenarioEquitableAvecContrainte += $resultat["duree"];

                                    }

                                }
                            }

                        }
                    }

                }



                if($refExiste){
                    # calcul données pour comparaison depuis rencontre details pour scenario ref
                    $rencontreDetails = $scenarioRef["rencontreDetails"];
                    foreach($rencontreDetails as $poule => $contenuPoule){
                        if($typeMatch == "allerRetour" or $typeMatch == "allerSimple"){
                            foreach($contenuPoule as $rencontre => $contenuRencontre){
                                if($contenuRencontre["equipeDepartId"] == $idEquipe){
                                    $distanceScenarioRef += $contenuRencontre["distance"];
                                    $distanceTotaleScenarioRef += $contenuRencontre["distanceTousParticipants"];
                                    $dureeScenarioRef += $contenuRencontre["duree"];

                                }
                            }

                        }
                        elseif($typeMatch == "plateau"){

                            foreach($contenuPoule as $jour => $contenuJour){
                                foreach($contenuJour as $groupe => $contenuGroupe) {
                                    # chercher la ville courante dans la liste de premier ou deuxième équipe
                                    if ($idEquipe == $contenuGroupe["premierEquipeId"] or $idEquipe == $contenuGroupe["deuxiemeEquipeId"]) {
                                        $departId = $idEquipe;
                                        $destinationId = $contenuGroupe["hoteId"];

                                        $resultat = $this->getDetailsTrajet($departId, $destinationId);

                                        $distanceScenarioRef += $resultat["distance"];
                                        $distanceTotaleScenarioRef += ($resultat["distance"] * $contenuGroupe["nbrParticipants"]);
                                        $dureeScenarioRef += $resultat["duree"];


                                    }

                                }
                            }

                        }
                    }
                }


                # obtenir l'id de l'equipe
                $detailsEquipeComparaison[$i] = array(
                    "id"=> $idEquipe,
                    "nom" => $nomEquipe,
                    "distance"=> array(
                        "scenarioOptimalAvecContrainte"=> $distanceScenarioOptimalAvecContrainte,
                        "scenarioOptimalSansContrainte"=> $distanceScenarioOptimalSansContrainte,
                        "scenarioEquitableAvecContrainte"=> $distanceScenarioEquitableAvecContrainte,
                        "scenarioEquitableSansContrainte"=> $distanceScenarioEquitableSansContrainte,
                        "scenarioRef"=> $distanceScenarioRef,
                        ),
                    "distanceTotale"=> array(
                        "scenarioOptimalAvecContrainte"=> $distanceTotaleScenarioOptimalAvecContrainte,
                        "scenarioOptimalSansContrainte"=> $distanceTotaleScenarioOptimalSansContrainte,
                        "scenarioEquitableAvecContrainte"=> $distanceTotaleScenarioEquitableAvecContrainte,
                        "scenarioEquitableSansContrainte"=> $distanceTotaleScenarioEquitableSansContrainte,
                        "scenarioRef"=> $distanceTotaleScenarioRef,

                    ),
                    "duree"=> array(
                        "scenarioOptimalAvecContrainte"=> $dureeScenarioOptimalAvecContrainte,
                        "scenarioOptimalSansContrainte"=> $dureeScenarioOptimalSansContrainte,
                        "scenarioEquitableAvecContrainte"=> $dureeScenarioEquitableAvecContrainte,
                        "scenarioEquitableSansContrainte"=> $dureeScenarioEquitableSansContrainte,
                        "scenarioRef"=> $dureeScenarioRef,

                    ),
                );



            }




            return $detailsEquipeComparaison ;

        } catch (Exception $e) {
            error_log("\n erreur générique, Service: Poules, Function: parserComparaisonScenario, datetime: ".$dateTimeNow."\n"
                ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }

    # retourner les infos pour changement d'affectation d'équipes par poule
    public function getInfoChangeAffectation($scenarioOptimalSansContrainte, $scenarioEquitableSansContrainte, $scenarioOptimalAvecContrainte, $scenarioEquitableAvecContrainte){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try{
            $infoChangeAffectation = [];


            if($scenarioOptimalSansContrainte != []){
                $infoChangeAffectation["optimalSansContrainte"] = array();
                foreach($scenarioOptimalSansContrainte["poulesId"] as $poule => $contenuPoule){

                    $listeTmp = [];
                    foreach($contenuPoule as $equipeId){
                        $detailEntite = $this->getDetailsEntite($equipeId);

                        $infoEquipe = array("equipeId" => $equipeId, "equipeNom" => $detailEntite["nom"]);
                        array_push($listeTmp, $infoEquipe);
                    }

                    $infoChangeAffectation["optimalSansContrainte"][$poule] = $listeTmp;

                }




            }
            if($scenarioEquitableSansContrainte != []){
                $infoChangeAffectation["equitableSansContrainte"] = array();

                foreach($scenarioEquitableSansContrainte["poulesId"] as $poule => $contenuPoule){

                    $listeTmp = [];
                    foreach($contenuPoule as $equipeId){
                        $detailEntite = $this->getDetailsEntite($equipeId);

                        $infoEquipe = array("equipeId" => $equipeId, "equipeNom" => $detailEntite["nom"]);
                        array_push($listeTmp, $infoEquipe);
                    }

                    $infoChangeAffectation["equitableSansContrainte"][$poule] = $listeTmp;

                }
            }
            if($scenarioOptimalAvecContrainte != []){
                $infoChangeAffectation["optimalAvecContrainte"] = array();

                foreach($scenarioOptimalAvecContrainte["poulesId"] as $poule => $contenuPoule){

                    $listeTmp = [];
                    foreach($contenuPoule as $equipeId){
                        $detailEntite = $this->getDetailsEntite($equipeId);

                        $infoEquipe = array("equipeId" => $equipeId, "equipeNom" => $detailEntite["nom"]);
                        array_push($listeTmp, $infoEquipe);
                    }

                    $infoChangeAffectation["optimalAvecContrainte"][$poule] = $listeTmp;

                }
            }
            if($scenarioEquitableAvecContrainte != []){
                $infoChangeAffectation["equitableAvecContrainte"] = array();

                foreach($scenarioEquitableAvecContrainte["poulesId"] as $poule => $contenuPoule){

                    $listeTmp = [];
                    foreach($contenuPoule as $equipeId){
                        $detailEntite = $this->getDetailsEntite($equipeId);

                        $infoEquipe = array("equipeId" => $equipeId, "equipeNom" => $detailEntite["nom"]);
                        array_push($listeTmp, $infoEquipe);
                    }

                    $infoChangeAffectation["equitableAvecContrainte"][$poule] = $listeTmp;

                }
            }


            return $infoChangeAffectation;

        } catch (Exception $e) {
            error_log("\n erreur générique, Service: Poules, Function: getInfoChangeAffectation, datetime: ".$dateTimeNow."\n"
                ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }
    }


    # retourner les détails d'une entité
    private function getDetailsEntite($id){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try{
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            $sql = "SELECT nom from entite where id=:id;";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultat;

        }
        catch (Exception $e) {
            error_log("\n erreur PDO, Service: Poules, Function: getDetailsEntite, datetime: ".$dateTimeNow."\n"
                ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

    }


    # retourner la distance et la dureer pour un trajet donné
    private function getDetailsTrajet($departId, $destinationId){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try{
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }

            $sql = "SELECT distance, duree from trajet where depart=:departId and destination=:destinationId;";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':departId', $departId);
            $stmt->bindParam(':destinationId', $destinationId);
            $stmt->execute();
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);

            return $resultat;

        }
        catch (Exception $e) {
            error_log("\n erreur PDO, Service: Poules, Function: getDetailsTrajet, datetime: ".$dateTimeNow."\n"
            ."erreur: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

}


    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    private function getPdo(){
        # récupérer les parametres de connexion
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # créer une objet PDO
            $bdd = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        } catch (PDOException $e) {
            error_log("\n Service: Poules, Function: getPdo, datetime: ".$dateTimeNow
                ."\n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $bdd;
    }

    # retourner le statut de la tache
    public function getStatut($idResultat){
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        try {
            # obtenir l'objet PDO
            $bdd = $this->getPdo();

            if (!$bdd) {
                //erreur de connexion
                error_log("\n erreur récupération de l'objet PDO, Service: Poules, Function: sauvegarderParamsEnDB, datetime: ".$dateTimeNow, 3, $this->error_log_path);
                die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
            }


            # obtenir le statut depuis la db
            $sql = "SELECT statut from parametres where id=:id;";
            $stmt = $bdd->prepare($sql);
            $stmt->bindParam(':id', $idResultat);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $result;

        } catch (PDOException $e) {
            error_log("\n Service: Poules, Function: getPdo, datetime: ".$dateTimeNow
                ."\n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $bdd;
    }


}