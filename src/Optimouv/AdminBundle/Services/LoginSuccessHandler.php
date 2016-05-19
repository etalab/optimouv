<?php

# src/AppBundle/Services/LoginSuccessHandler.php
namespace Optimouv\AdminBundle\Services;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Optimouv\FfbbBundle\Services\Statistiques;

use Doctrine\ORM\EntityManager;

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

    protected $timeLimitActiveUsers;
    protected $maxNumberActiveUsers;
    protected $em;
    private $error_log_path;

    public function __construct(Router $router, EntityManager $manager, AuthorizationChecker $authorizationChecker, Statistiques $serviceStatistiques, $maxNumberActiveUsers, $timeLimitActiveUsers, $error_log_path )
    {
        $this->router = $router;

        $this->authorizationChecker = $authorizationChecker;
        $this->serviceStatistiques = $serviceStatistiques;
        $this->em = $manager;

        $this->maxNumberActiveUsers = $maxNumberActiveUsers;
        $this->timeLimitActiveUsers = $timeLimitActiveUsers;
        $this->error_log_path= $error_log_path;

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

        if($utilisateurId == ""){
            error_log("\n  l'identifiant de l'utilisateur est null, Service: LoginSuccessHandler, Function: onAuthenticationSuccess", 3, $this->error_log_path);
            die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
        }


        if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN') || $this->authorizationChecker->isGranted('ROLE_ADMIN')
            || $this->authorizationChecker->isGranted('ROLE_USER'))
        {
            try{
                $this->serviceStatistiques->augmenterNombreTableStatistiques($utilisateurId, "nombreConnexions", 1);

            }
            catch (PDOException $e){
                 error_log("\n erreur PDO, Service: LoginSuccessHandler, Function: onAuthenticationSuccess, erreur: ".print_r($e, true), 3, $this->error_log_path);
                 die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
             }

        }


        // obtenir les autres utilisateurs qui sont actifs
        $users = $this->em->getRepository('AdminBundle:User')->getActiveOtherUsers($this->timeLimitActiveUsers, $utilisateurId);

//        error_log("\n utilisateurId: ".$utilisateurId, 3, $this->error_log_path);
//        error_log("\n timeLimitActiveUsers: ".$this->timeLimitActiveUsers, 3, $this->error_log_path);
//        error_log("\n users count: ".count($users), 3, $this->error_log_path);


        // bloquer login si le nombre maximum des utilisateurs actifs est atteint
        if(count($users) >= $this->maxNumberActiveUsers){
            $response = new RedirectResponse("/login");
        }
        // login normal
        else{
            $response = new RedirectResponse($this->router->generate('ffbb_accueil_connect'));

        }



        return $response;

    }


}