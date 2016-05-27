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
            $path = $this->container->getParameter('error_log_path');
            error_log('mot de passe oublié n\'existe pas', 3, $path);
            return $this->redirect($this->generateUrl('ffbb_accueil'));
        }

        $idUser = $user->getId();
        $emailUser = $user->getEmail();
        $username = $user->getUsername();

        //récupération des params d'envoies de mail
        $mailer_sender = $this->container->getParameter('mailer_sender');
        $sender_name = $this->container->getParameter('sender_name');
        $body = $this->renderView('AdminBundle:Mails:resetting.html.twig',
            array(
                'idUser' => $idUser,
                'username' => $username

                ));

        $message = \Swift_Message::newInstance()
            ->setSubject('OPTIMOUV - Réinitialisation de votre mot de passe')
            ->setFrom(array($mailer_sender => $sender_name))
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
    public function updatePwdAction()
    {


        $idUser = $_POST['idUser'];
        $password = $_POST['password'];

        if (isset($_POST['role'])) {
            $role = $_POST['role'];
        }
        else{

            $role = null;
        }

        $em = $this->getDoctrine()->getManager();
        $username =  $em->getRepository('AdminBundle:User')->findOneById($idUser)->getUsername();
        $user = $this->container->get('fos_user.user_manager')->findUserByUsernameOrEmail($username);

        //encrypt password
        $factory = $this->get('security.encoder_factory');
        $encoder = $factory->getEncoder($user);
        $password = $encoder->encodePassword($password, $user->getSalt());

        $connection = $em->getConnection();

        $update = $connection->prepare("UPDATE fos_user SET password = :password WHERE id = :id");
        $update->bindParam(':password', $password);
        $update->bindParam(':id', $idUser);
        $update->execute();

        if($role){

            return $this->redirect($this->generateUrl('ffbb_accueil_connect'));
        }
        else{
            return $this->redirect($this->generateUrl('fos_user_security_login'));
        }

    }

     
}
