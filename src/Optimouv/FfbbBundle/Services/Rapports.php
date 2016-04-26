<?php
/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 21/04/2016
 * Time: 11:38
 */

namespace Optimouv\FfbbBundle\Services;
use PDO;

class Rapports
{

    private $error_log_path;
    private $database_name;
    private $database_user;
    private $database_password;

    public function __construct($database_name, $database_user, $database_password, $error_log_path)
    {
        $this->database_name = $database_name;
        $this->database_user = $database_user;
        $this->database_password = $database_password;
        $this->error_log_path = $error_log_path;
    }

    # retourner un objet PDO qu'on peut utiliser dans d'autres fonctions
    private function getPdo(){
        # récupérer les parametres de connexion
        $dbname = $this->database_name;
        $dbuser = $this->database_user;
        $dbpwd = $this->database_password;

        try {
            # créer une objet PDO
            $pdo = new PDO('mysql:host=localhost;dbname=' . $dbname . ';charset=utf8', $dbuser, $dbpwd);
        }
        catch (PDOException $e) {
            error_log("\n Service: Rapport, Function: getPdo, \n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $pdo;
    }

    public function getAllInfoRapprt($idUser, $role)
    {


        if($role == "ROLE_SUPER_ADMIN"){

            $infosRapports =  $this->getAllInfoRapprtForAdmin();
            return $infosRapports;

        }
        elseif ($role == "ROLE_ADMIN"){

            $infosRapports =  $this->getAllInfoRapprtForFederal($idUser);
            return $infosRapports;
        }
        else{
            $infosRapports =  $this->getAllInfoRapprtForUser($idUser);
            return $infosRapports;
        }



    }

    public function getAllInfoRapprtForUser($idUser)
    {

        $bdd= $this->getPdo();

//        déclarer le tableau des infosRapports
        $infosRapports = [];
        $infosRapport = [];
//        récupérer infos user
        $infoUser = $bdd->prepare("SELECT nom, prenom, federation FROM  fos_user WHERE id = :id ;");
        $infoUser->bindParam(':id', $idUser);
        $infoUser->execute();
        $infoUser = $infoUser->fetch(PDO::FETCH_ASSOC);
        $nomUser = $infoUser['nom'];
        $prenomUser = $infoUser['prenom'];
        $federationUser = substr($infoUser['federation'],2);

//        récupérer les infos des groupes
        $infoGroupe = $bdd->prepare("SELECT a.id as idGroupe, a.nom as nomGroupe, b.nom as nomListe FROM groupe a, liste_participants b

                                      where a.id_utilisateur = :id 

                                      and a.id_liste_participant = b.id");
        $infoGroupe->bindParam(':id', $idUser);
        $infoGroupe->execute();
        while ($row = $infoGroupe->fetch(PDO::FETCH_ASSOC)) {

            $idGroupe = $row['idGroupe'];
            $nomGroupe = $row['nomGroupe'];
            $nomListe = $row['nomListe'];

//            récupérer infos rapports
            $rapport = $bdd->prepare("SELECT * FROM parametres where id_groupe = :id ");
            $rapport->bindParam(':id', $idGroupe);
            $rapport->execute();

             while ($rowRapport = $rapport->fetch(PDO::FETCH_ASSOC)) {

                //rapport
                $infosRapport['idRapport'] = $rowRapport['id'];
                $infosRapport['nomRapport'] = $rowRapport['nom'];
                $infosRapport['typeActionRapport'] = $rowRapport['type_action'];
                $infosRapport['dateCreationRapport'] = $rowRapport['date_creation'];
                $infosRapport['paramsRapport'] = $rowRapport['params'];
                $infosRapport['statutRapport'] = $rowRapport['statut'];
                //groupe
                $infosRapport['idGroupe'] = $idGroupe;
                $infosRapport['nomGroupe'] = $nomGroupe;
                $infosRapport['nomListe'] = $nomListe;
                //liste
                $infosRapport['idUtilisateur'] = $idUser;
                $infosRapport['nomUser'] = $nomUser;
                $infosRapport['prenomUser'] = $prenomUser;
                $infosRapport['federationUser'] = $federationUser;
                 array_push($infosRapports, $infosRapport);
            }

        }

        return $infosRapports;
    }

    public function getAllInfoRapprtForFederal($idUser)
    {
        $bdd= $this->getPdo();
        $idUsers = "SELECT id FROM fos_user where federation = (select federation from fos_user where id = :id)" ;
        $idUsers = $bdd->prepare($idUsers);
        $idUsers->bindParam(':id', $idUser);
        $idUsers->execute();
        $infosRapports = [];
        while ($rowUser = $idUsers->fetch(PDO::FETCH_ASSOC)) {

            $idUser = $rowUser['id'];
            $infosRapport =  $this->getAllInfoRapprtForUser($idUser);
            $infosRapports = $infosRapports + $infosRapport;
        }
        
         return $infosRapports;
    }

    public function getAllInfoRapprtForAdmin()
    {
        $bdd= $this->getPdo();

        $idUsers = "SELECT id FROM fos_user" ;
        $idUsers = $bdd->prepare($idUsers);
        $idUsers->execute();

        $infosRapports = [];
        while ($rowUser = $idUsers->fetch(PDO::FETCH_ASSOC)) {

            $idUser = $rowUser['id'];
            echo '<pre>',print_r($idUser,1),'</pre>';
//            $infosRapport =  $this->getAllInfoRapprtForUser($idUser);
//            $infosRapports = $infosRapports + $infosRapport;

        }
       exit;
        return $infosRapports;

    }
}