<?php

namespace Optimouv\AdminBundle\Controller;

use Optimouv\AdminBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    public function userAddAction()
    {


        $em = $this->getDoctrine()->getManager();

        //tester si ustilisateur connecté
        $user = $this->get('security.token_storage')->getToken()->getUser();

        //test si utilisateur connecté
        if(!is_string($user)){
            $roleUser = $user->getRoles();
            
            //connecté en tant qu'admin fédéral
            if (in_array('ROLE_ADMIN',$roleUser)) {

                $discipline = $user->getDiscipline();
                $federation = $user->getFederation();
                return $this->render('AdminBundle:User:addByAdmin.html.twig', array(
                    'discipline' => $discipline,
                    'federation' => $federation
                ));

            }          
            //connecté en tant qu'admin général
            elseif (in_array('ROLE_SUPER_ADMIN',$roleUser)){

                //récuperation de la liste de fede
                $federations = $em->getRepository('FfbbBundle:Federation')->findAll();

                //récuperation de la liste de discipline
                $disciplines = $this->get('service_poules')->getListDiscipline();
                
                return $this->render('AdminBundle:User:addByAdmin.html.twig', array(
                    "liste_federation" => $federations,
                    "liste_disciplines" => $disciplines
                ));   
            }
        }
        else{

            //récuperation de la liste de fede
            $federations = $em->getRepository('FfbbBundle:Federation')->findAll();

            //récuperation de la liste de discipline
            $disciplines = $this->get('service_poules')->getListDiscipline();


            return $this->render('AdminBundle:User:add.html.twig', array(
                "liste_federation" => $federations,
                "liste_disciplines" => $disciplines
            ));
        }
       


    }

    public function userCreateAction()
    {


        if (isset($_POST['nom'])) {
            $nom = $_POST['nom'];
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
        else {
            $captcha = "";
        }
        if (!$captcha) {
            echo '<h2>Vérifiez svp le champs captcha.</h2>';
            exit;
        }
        //récupérer le profil utilisateur
        if (isset($_POST['profil'])) {
            $role = $_POST['profil'];
        } else {
            $role = "";
        }
        //récupérer role_utilisateur si exist
        if (isset($_POST['role'])) {
            $roleUtilisateur = $_POST['role'];
        }
        else{
            $roleUtilisateur = null;
        }

        $dateCreation = new \DateTime("now");

        $discipline = intval($discipline);

        //spécifier le username
        $username = substr($prenom, 0,1);
        $username = strtolower($username.$nom);

        if ($roleUtilisateur) {
            $dateExpiration = null;
        } else {
            $dateExpiration = new \DateTime("now");
            date_add($dateExpiration, date_interval_create_from_date_string('10 days'));
        }


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
            echo '<h2>Utilisateur existe déjà. Veuillez contacter votre administrateur</h2>';
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
            $user->setDateCreation($dateCreation);

            if($role == "admin"){
                $user->setRoles(array('ROLE_ADMIN'));
            }

             //encrypt password
            $factory = $this->get('security.encoder_factory');
            $encoder = $factory->getEncoder($user);
            $encryptedPassword = $encoder->encodePassword($password, $user->getSalt());
            $user->setPassword($encryptedPassword);
            $user->setExpired(false);
            if(isset($dateExpiration)){
                $user->setExpiresAt($dateExpiration);
            }
            $user->setCredentialsExpired(false);

            $user->setLocked(true);
            $user->setEnabled(false);
            $em->persist($user);
            $em->flush();
            $idUser = $user->getId();
            $sendingMail = $this->sendMail($idUser, $email);
            if($sendingMail){

                //tester si ustilisateur connecté
                $userConnect = $this->get('security.token_storage')->getToken()->getUser();

                //test si utilisateur connecté
                if($userConnect) {

                    return $this->redirect($this->generateUrl('administration_users_list'));
                }
                else{
                    return $this->redirect($this->generateUrl('ffbb_accueil'));
                }

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
        $em->getRepository('AdminBundle:User')->activateUser($idUser);
       
//        return $this->redirect($this->generateUrl('fos_user_security_login '));
        return $this->redirectToRoute('fos_user_security_login');
    }

    public function UsersListAction()
    {
        $em = $this->getDoctrine()->getManager();

        //récupérer toute la liste des utilisateurs pour l'admin fédéral
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $roleUser = $user->getRoles();

        //récupérer id utilisateur
        $idUser = $user->id;


       
        if (in_array('ROLE_ADMIN',$roleUser)) {

            $idDiscipline = $em->getRepository('AdminBundle:User')->findOneById($idUser)->discipline;

            $users  = $em->getRepository('AdminBundle:User')->getListUsersByDiscipline($idDiscipline);

        }
        elseif ( in_array('ROLE_SUPER_ADMIN',$roleUser) ){

            //récupérer la liste selon l'admin général
            $users  = $em->getRepository('AdminBundle:User')->getListUsers();
        }
        else{
            print_r("vous n'êtes pas autorisé à accéder à cette page");
        }


       

        return $this->render('AdminBundle:User:list.html.twig', [
            "users" => $users
        ]);

    }

    public function activateUserByAdminAction($idUser)
    {

        $em = $this->getDoctrine()->getManager();
        $activation = $em->getRepository('AdminBundle:User')->activateUserByAdmin($idUser);
        if(!$activation){
            
            die("problème activation utilisateur ".$idUser);
        }
        return new JsonResponse(array(
            "success" => true,
            "msg" => "utilisateur activé"
        ));
    }

    public function desactivateUserByAdminAction($idUser)
    {

        $em = $this->getDoctrine()->getManager();
        $activation = $em->getRepository('AdminBundle:User')->desactivateUserByAdmin($idUser);
        if(!$activation){

            die("problème activation utilisateur ".$idUser);
        }
        return new JsonResponse(array(
            "success" => true,
            "msg" => "utilisateur desactivé"
        ));
    }

    public function editProfilAction()
    {

        $em = $this->getDoctrine()->getManager();
        //récupérer toute la liste des utilisateurs pour l'admin fédéral
        $user = $this->get('security.token_storage')->getToken()->getUser();

        //récupérer id utilisateur
        $idUser = $user->id;

        $user  = $em->getRepository('AdminBundle:User')->findOneById($idUser);

        return $this->render('AdminBundle:User:update.html.twig', [
            "user" => $user,

        ]);

    }

    public function editUserAction($idUser)
    {
        $em = $this->getDoctrine()->getManager();
        $user  = $em->getRepository('AdminBundle:User')->findOneById($idUser);

        return $this->render('AdminBundle:User:update.html.twig', [
            "user" => $user,

        ]);
    }
    
    public function updateUserAction($idUser)
    {
        $em = $this->getDoctrine()->getManager();

        $params =[];
        $params['id'] = $idUser;
        $params['nom'] = $_POST['nom'];
        $params['prenom'] = $_POST['prenom'];
        $params['login'] = $_POST['username'];
        $params['email'] = $_POST['email'];
        $params['tel'] = $_POST['tel'];
//        $password = $_POST['password'];

        /*
        //encrypt password
        $factory = $this->get('security.encoder_factory');
        $user = $em->getRepository('AdminBundle:User')->findOneById($idUser);
        $encoder = $factory->getEncoder($user);
        $params['password'] = $encoder->encodePassword($password, $user->getSalt());
*/

        $params['adresse'] = $_POST['adresse'];
        $params['numLicencie'] = $_POST['numLicencie'];

        $update  = $em->getRepository('AdminBundle:User')->updateUser($params);

        if($update){
            //récupérer toute la liste des utilisateurs pour l'admin fédéral
            $user = $this->get('security.token_storage')->getToken()->getUser();
            $idAdmin = $user->getId();
            $roleUser = $user->getRoles();

            if($idAdmin == $idUser){
                return $this->redirect($this->generateUrl('ffbb_accueil'));
            }

            elseif(in_array('ROLE_ADMIN',$roleUser) or in_array('ROLE_SUPER_ADMIN',$roleUser)){
                return $this->redirect($this->generateUrl('administration_users_list'));
            }
            else{
                return $this->redirect($this->generateUrl('ffbb_accueil_connect'));
            }

        }
        else{
            print_r("Un problème de mise à jour de l'utilisateur");
            exit;
        }


    }


}
