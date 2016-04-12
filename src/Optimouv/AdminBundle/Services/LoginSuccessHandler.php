<?php

# src/AppBundle/Services/LoginSuccessHandler.php
namespace Optimouv\AdminBundle\Services;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
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

    public function __construct(Router $router, AuthorizationChecker $authorizationChecker, Statistiques $serviceStatistiques)
    {
        $this->router = $router;
        $this->authorizationChecker = $authorizationChecker;
        $this->serviceStatistiques = $serviceStatistiques;

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
                $this->serviceStatistiques->augmenterNombreTableStatistiques($utilisateurId, "nombreConnexions");

            }
            catch (PDOException $e){
                 error_log("\n erreur PDO, Service: LoginSuccessHandler, Function: onAuthenticationSuccess, erreur: ".print_r($e, true), 3, $this->error_log_path);
                 die('Une erreur interne est survenue. Veuillez recharger l\'application. ');
             }

        }
        $response = new RedirectResponse($this->router->generate('ffbb_accueil_connect'));
        return $response;
    }


}