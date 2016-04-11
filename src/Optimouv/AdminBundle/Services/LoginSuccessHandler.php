<?php

# src/AppBundle/Services/LoginSuccessHandler.php
namespace Optimouv\AdminBundle\Services;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
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
    private $error_log_path;
    /**
     * @var AuthorizationChecker $authorizationChecker
     */
    protected $authorizationChecker;
    public function __construct(Router $router, AuthorizationChecker $authorizationChecker, $error_log_path)
    {
        $this->router = $router;
        $this->authorizationChecker = $authorizationChecker;
        $this->error_log_path = $error_log_path;
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

        $utilisateur =  $token->getUser();
        $utilisateurId = $utilisateur->getId();
        error_log(" utilisateurId: $utilisateurId \n", 3, $this->error_log_path);

        if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN') && $this->authorizationChecker->isGranted('ROLE_ADMIN')
            && $this->authorizationChecker->isGranted('ROLE_USER'))
        {


        }
        $response = new RedirectResponse($this->router->generate('ffbb_accueil_connect'));
        return $response;
    }
}