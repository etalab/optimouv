<?php

namespace Optimouv\AdminBundle\Controller;

use Optimouv\AdminBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class UserController extends Controller
{
    public function userAddAction()
    {


        $em = $this->getDoctrine()->getManager();

        //récuperation de la liste de fede
        $federations = $em->getRepository('FfbbBundle:Federation')->findAll();

        //récuperation de la liste de discipline
        $disciplines = $this->get('service_poules')->getListDiscipline();


        return $this->render('AdminBundle:User:add.html.twig', array(
            "liste_federation" => $federations,
            "liste_disciplines" => $disciplines
        ));

    }

    public function userCreateAction()
    {


        if (isset($_POST['nom'])) {
            $nom = $_POST['nom'];
        }
        if (isset($_POST['prenom'])) {
            $prenom = $_POST['prenom'];
        }
        
        if (isset($_POST['prenom'])) {
            $prenom = $_POST['prenom'];
        }
        if (isset($_POST['civilite'])) {
            $civilite = $_POST['civilite'];
        }
        if (isset($_POST['federation'])) {
            $federation = $_POST['federation'];
        }
        if (isset($_POST['discipline'])) {
            $discipline = $_POST['discipline'];
        }
        if (isset($_POST['fonction'])) {
            $fonction = $_POST['fonction'];
        }else{
            $fonction ="";
        }
        if (isset($_POST['email'])) {
            $email = $_POST['email'];
        }
        if (isset($_POST['telephone'])) {
            $telephone = $_POST['telephone'];
        }else{
            $telephone ="";
        }
        if (isset($_POST['adresse'])) {
            $adresse = $_POST['adresse'];
        }else{
            $adresse ="";
        }
        if (isset($_POST['numLicencie'])) {
            $numLicencie = $_POST['numLicencie'];
        }else{
            $numLicencie ="";
        }
        if (isset($_POST['password'])) {
            $password = $_POST['password'];
        } else {
            $password = "";
        }

        if (isset($_POST['g-recaptcha-response'])) {
            $captcha = $_POST['g-recaptcha-response'];
        }
        if (!$captcha) {
            echo '<h2>Vérifiez svp le champs captcha.</h2>';
            exit;
        }

        $discipline = intval($discipline);

         $username = $nom.'_'.$prenom;
       

        $secretKey = "6Lf1NxwTAAAAAP6UYH4-vzxAFxxHYJfq0ddkkK3U";
        $ip = $_SERVER['REMOTE_ADDR'];
        $response=file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".$secretKey."&response=".$captcha."&remoteip=".$ip);
        $responseKeys = json_decode($response,true);
        if(intval($responseKeys["success"]) !== 1) {
            echo '<h2>Vous êtes un spammer ! Get the @$%K out</h2>';
        }

        //tester si l'utilisateur existe
        $user = $this->getDoctrine()
            ->getRepository('AdminBundle:User')
            ->findOneByEmail($email);
        if($user){
            echo '<h2>Utilisateur existe déjà.</h2>';
            exit;
        }
        else{

            $em = $this->getDoctrine()->getManager();
            $user = new User();

            $discipline = $em->getRepository('FfbbBundle:Discipline')->findOneById($discipline);

            $user->setUsername($username);
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setUsernameCanonical($username);
            $user->setEmail($email);
            $user->setEmailCanonical($email);
            $user->setCivilite($civilite);
            $user->setFederation($federation);
            $user->setDiscipline($discipline);
            $user->setFonction($fonction);
            $user->setTelephone($telephone);
            $user->setAdresse($adresse);
            $user->setNumLicencie($numLicencie);

             //encrypt password
            $factory = $this->get('security.encoder_factory');
            $encoder = $factory->getEncoder($user);
            $encryptedPassword = $encoder->encodePassword($password, $user->getSalt());
            $user->setPassword($encryptedPassword);
            $user->setExpired(false);
            $user->setCredentialsExpired(false);

            $user->setLocked(true);
            $user->setEnabled(false);
            $em->persist($user);
            $em->flush();
            $idUser = $user->getId();
            $sendingMail = $this->sendMail($idUser, $email);
            if($sendingMail){

                return $this->redirect($this->generateUrl('ffbb_accueil'));
            }
            else{
                echo '<h2>Erreur envoie de mail de confirmation</h2>';
            }


        }

    }

    public function sendMail($idUser, $email)
    {
        
        $body = $this->renderView('AdminBundle:Mails:register.html.twig',
            array(
                'idUser' => $idUser,

            ));

        $message = \Swift_Message::newInstance()
            ->setSubject('Activation de votre compte')
            ->setFrom('serviceclients@it4pme.fr')
            ->setTo($email)
            ->setBody($body, 'text/html')
        ;
        $this->get('mailer')->send($message);
        return true;
    }

    public function userActivateAction($idUser)
    {
        $em = $this->getDoctrine()->getManager();

        //Activation utilisateur
        $activation = $em->getRepository('AdminBundle:User')->activateUser($idUser);
        
       if($activation){
           return $this->redirect($this->generateUrl('fos_user_security_login '));
       }
    }
}
