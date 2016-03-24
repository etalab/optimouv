<?php

namespace Optimouv\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ResettingController extends Controller
{
    public function indexAction()
    {

        $username = $_POST['username'];
        /** @var $user UserInterface */
        $user = $this->container->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);

        //si l'utilisateur n'existe pas
        if (null === $user) {
            return $this->container->get('templating')->renderResponse('FOSUserBundle:Resetting:request.html.'.$this->getEngine(), array('invalid_username' => $username));
        }

        $idUser = $user->getId();
        $emailUser = $user->getEmail();
        $username = $user->getUsername();


        $body = $this->renderView('AdminBundle:Mails:resetting.html.twig',
            array(
                'idUser' => $idUser,
                'username' => $username

                ));

        $message = \Swift_Message::newInstance()
            ->setSubject('Réinitialisation de votre mot de passe')
            ->setFrom('serviceclients@it4pme.fr')
            ->setTo($emailUser)
            ->setBody($body, 'text/html')
        ;
        $this->get('mailer')->send($message);
        
        return $this->redirect($this->generateUrl('ffbb_accueil'));

    }

    public function updateAction($idUser)
    {

        return $this->render('AdminBundle:resetPwd:updatePassword.html.twig', array('idUser' => $idUser));

    }
}
