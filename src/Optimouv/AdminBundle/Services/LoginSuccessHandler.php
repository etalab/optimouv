<?php

# src/AppBundle/Services/LoginSuccessHandler.php
namespace Optimouv\AdminBundle\Services;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use PDO;
use Optimouv\FfbbBundle\Services\Statistiques;


/**
 * Custom authentication success handler
 *
 * Defines what happens after login success
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    /**
     * @var Router $router
     */
    protected $router;
    /**
     * @var AuthorizationChecker $authorizationChecker
     */
    protected $authorizationChecker;

    /**
     * @var Statistiques $serviceStatistiques
     */
    protected $serviceStatistiques;


//    private $error_log_path;
//    private $database_name;
//    private $database_user;
//    private $database_password;

//    public function __construct(Router $router, AuthorizationChecker $authorizationChecker, $database_name, $database_user, $database_password, $error_log_path)
    public function __construct(Router $router, AuthorizationChecker $authorizationChecker, Statistiques $serviceStatistiques)
    {
        $this->router = $router;
        $this->authorizationChecker = $authorizationChecker;
        $this->serviceStatistiques = $serviceStatistiques;

//        $this->database_name = $database_name;
//        $this->database_user = $database_user;
//        $this->database_password = $database_password;
//        $this->error_log_path = $error_log_path;
    }
    /**
     * Called when authentication succeeds
     *
     * @param  Request          $request
     * @param  TokenInterface   $token
     *
     * @return Response never null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        # obtenir l'id de l'utilisateur
        $utilisateur =  $token->getUser();
        $utilisateurId = $utilisateur->getId();
//        error_log(" utilisateurId: $utilisateurId \n", 3, $this->error_log_path);

        if($utilisateurId == ""){
            error_log("\n  l'identifiant de l'utilisateur est null, Service: LoginSuccessHandler, Function: onAuthenticationSuccess", 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN') || $this->authorizationChecker->isGranted('ROLE_ADMIN')
            || $this->authorizationChecker->isGranted('ROLE_USER'))
        {
            try{
                $this->serviceStatistiques->augmenterNombreTableStatistiques($utilisateurId, "nombreConnexions");

//                # obtenir l'objet PDO
//                $pdo = $this->getPdo();
//
//                if (!$pdo) {
//                    //erreur de connexion
//                    error_log("\n erreur récupération de l'objet PDO, Service: LoginSuccessHandler, Function: onAuthenticationSuccess ", 3, $this->error_log_path);
//                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
//                }
//
//                # obtenir l'id de la discipline
//                $sql = "SELECT id_discipline from fos_user where id=:id;";
//                $stmt = $pdo->prepare($sql);
//                $stmt->bindParam(':id', $utilisateurId);
//                $stmt->execute();
//                $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
//                $disciplineId = $resultat["id_discipline"];
//
//                if($disciplineId == ""){
//                    error_log("\n  l'identifiant de la discipline est null, Service: LoginSuccessHandler, Function: onAuthenticationSuccess", 3, $this->error_log_path);
//                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
//                }
//
//
//                # obtenir l'id de la fédération
//                $sql = "SELECT id_federation from discipline where id=:id;";
//                $stmt = $pdo->prepare($sql);
//                $stmt->bindParam(':id', $disciplineId);
//                $stmt->execute();
//                $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
//                $federationId = $resultat["id_federation"];
//
//                if($federationId == ""){
//                    error_log("\n  l'identifiant de la fédération est null, Service: LoginSuccessHandler, Function: onAuthenticationSuccess", 3, $this->error_log_path);
//                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
//                }
//
//
//                $typeStatistiques = "nombreConnexions";
//
//                # insérer dans la base de données
//                $sql = "INSERT INTO  statistiques_date (date_creation, type_statistiques, id_utilisateur, id_discipline, id_federation, valeur)
//                        VALUES (now(), :type_statistiques, :id_utilisateur, :id_discipline, :id_federation, 1)
//                        on duplicate key UPDATE valeur=valeur+1 ;";
//                $stmt = $pdo->prepare($sql);
//                $stmt->bindParam(':type_statistiques', $typeStatistiques);
//                $stmt->bindParam(':id_utilisateur', $utilisateurId);
//                $stmt->bindParam(':id_discipline', $disciplineId);
//                $stmt->bindParam(':id_federation', $federationId);
//                $statutInsert = $stmt->execute();
//
//                if(!$statutInsert){
//                    error_log("\n  Erreur d'insertion des données dans DB, details: ".print_r($stmt->errorInfo(), true)."\n Service: LoginSuccessHandler, Function: onAuthenticationSuccess", 3, $this->error_log_path);
//                    die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
//                }


            }
            catch (PDOException $e){
                 error_log("\n erreur PDO, Service: LoginSuccessHandler, Function: onAuthenticationSuccess, erreur: ".print_r($e, true), 3, $this->error_log_path);
                 die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
             }

        }
        $response = new RedirectResponse($this->router->generate('ffbb_accueil_connect'));
        return $response;
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
            error_log("\n Service: Poules, Function: getPdo, \n PDOException: ".print_r($e, true), 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }

        return $pdo;
    }

}