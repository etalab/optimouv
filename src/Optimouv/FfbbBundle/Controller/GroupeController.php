<?php

namespace Optimouv\FfbbBundle\Controller;

use Composer\DependencyResolver\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

 class GroupeController extends Controller
{
    public function indexAction()
    {
        return $this->render('FfbbBundle:Groupe:index.html.twig'); // on utilise plus cette fn!!

    }

//    public function afficherParticipantsAction($idListeParticipants)
    public function afficherParticipantsAction()
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        //récupérer la liste des groupes pour un utilisateur
        $tousLesGroupes = $this->getGroupe();


        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        // obtenir l'id de la liste
        $idListeParticipants = $_REQUEST["idListeParticipants"][0];



        # obtenir listes des lieux de rencontres
        $idParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getEquipesPourListe($idListeParticipants);
        $idParticipants = $idParticipants[0]["equipes"];
        $idParticipants = explode(",", $idParticipants);


        # obtenir les détails pour chaque entité
        $detailsEntites = [];
        for($i=0; $i<count($idParticipants); $i++){
            $detailsEntite = $em->getRepository('FfbbBundle:Entite')->getDetailsPourEntite($idParticipants[$i]);
            //TODO:rendre plus robuste le teste des lignes des fichiers
            //tester si la ligne est vide
            if($detailsEntite){
                array_push($detailsEntites, $detailsEntite[0] );
            }
            else{
                continue;
            }

        }



        return $this->render('FfbbBundle:Groupe:indexUpdate.html.twig', array(
            'detailsEntites' => $detailsEntites,
            'tousLesGroupes' => $tousLesGroupes,
            'idListeParticipants' => $idListeParticipants

        ));
    }

//    public function afficherLieuxAction($idListeLieux)
    public function afficherLieuxAction()
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        // obtenir l'id de la liste
        $idListeLieux = $_REQUEST["idListeLieux"][0];

        # obtenir listes des lieux de rencontres
        $idLieux = $em->getRepository('FfbbBundle:ListeLieux')->getEquipesPourListe($idListeLieux);
        $idLieux = $idLieux[0]["lieux"];
        $idLieux = explode(",", $idLieux);

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir les détails pour chaque entité
        $detailsEntites = [];
        for($i=0; $i<count($idLieux); $i++){
            $detailsEntite = $em->getRepository('FfbbBundle:Entite')->getDetailsPourEntite($idLieux[$i]);
            array_push($detailsEntites, $detailsEntite[0] );
        }

//        error_log("\n Controller: Groupe, Function: afficherParticipantsAction, datetime: ".$dateTimeNow
//            ."\n detailsEntites : ".print_r($detailsEntites, true), 3, "/var/log/apache2/optimouv.log");

        $outputTableau = array("detailsEntites" => $detailsEntites );

        return $this->render('FfbbBundle:Groupe:indexUpdate.html.twig', $outputTableau);
    }

    public function getGroupe($idListe)
    {

         $em = $this->getDoctrine()->getManager();

        //récupérer la liste de groupes

        $tousLesGroupes =  $em->getRepository('FfbbBundle:Groupe')->getGroupList($idListe);

       return $tousLesGroupes;
    }

    public function deleteAction($idGroupe)
    {

             $em = $this->getDoctrine()->getManager();
             $entity = $em->getRepository('FfbbBundle:Groupe')->find($idGroupe);

            if (!$entity) {
                throw $this->createNotFoundException('Entité groupe introuvable.');
            }


            $em->remove($entity);
            $em->flush();

        //return $this->redirect($this->generateUrl('ffbb_select_liste_participants'));

        return new JsonResponse(array(
            "success" => true,
            "msg" => "Groupe supprimé"
        ));

    }

    public function renommerAction($idGroupe)
    {

        $em = $this->getDoctrine()->getManager();
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();


        return $this->render('FfbbBundle:Groupe:renommer.html.twig', array(

             'idGroupe' => $idGroupe,
             'idListe' => $idListe,
        ));

    }

    public function renommerGroupeAction($idGroupe)
    {


        $em = $this->getDoctrine()->getManager();


        $entity = $em->getRepository('FfbbBundle:Groupe')->find($idGroupe);

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe non trouvée!');
        }

        $nomGroupe = $_POST["nomGroupe"];
        $idListe = $_POST["idListe"];

        $entity->setNom($nomGroupe);
        $em->persist($entity);
        $em->flush();

        //TODO: A verifier !!!
       // $idListeParticipants =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();


        return $this->redirect($this->generateUrl('ffbb_gerer_groupe', array('idListe' => $idListe)));


       /* return $this->redirectToRoute('ffbb_select_liste_participants', [
            'idListeParticipants' => $idListeParticipants
        ]);
       */

    }

    public function choisirListeParticipantsAction()
    {

        $em = $this->getDoctrine()->getManager();
        //récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();
        $listeParticipants = $em->getRepository('FfbbBundle:ListeParticipants')
                                ->findByIdUtilisateur(
                                    array('idUtilisateur'=>$idUtilisateur),
                                    array('id'=>'DESC'));

        $listeLieux =  $em->getRepository('FfbbBundle:ListeLieux')
                                 ->findByIdUtilisateur(
                                      array('idUtilisateur'=>$idUtilisateur),
                                      array('id'=>'DESC'));


        return $this->render('FfbbBundle:Groupe:choisirListe.html.twig', array(
            'listeParticipants'=>$listeParticipants,
            'listeLieux' => $listeLieux,
        ));

    }

    public function nouveauGroupeAction()
    {

        $idListeParticipants = $_POST['listeParticipants'];

        $em = $this->getDoctrine()->getManager();

        $participants = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getEquipes();


        //$participants de string a array

        $participants = explode(",", $participants);

        $detailsEntite = $em->getRepository('FfbbBundle:Entite')->getEntities($participants);


        if(!empty( $_POST['listeLieux'])){
            $idListeLieux = $_POST['listeLieux'];

            return $this->render('FfbbBundle:Groupe:nouveauGroupe.html.twig', [
                'idListeParticipants' => $idListeParticipants,
                'idListeLieux' => $idListeLieux,
                'entites' => $detailsEntite,

            ]);
        }
        else{
            return $this->render('FfbbBundle:Groupe:nouveauGroupe.html.twig', [
                'idListeParticipants' => $idListeParticipants,
                 'entites' => $detailsEntite,

            ]);
        }


    }

    public function choisirGroupeAction($idListe)
    {

        if(!isset($idListe)){
            die('Une erreur interne est survenue. Veuillez sélectionner une liste de participants. ');
        }
        $tousLesGroupes = $this->getGroupe($idListe);


        return $this->render('FfbbBundle:Groupe:choisirGroupe.html.twig', [
            'tousLesGroupes' => $tousLesGroupes,
            'idListe' => $idListe,
        ]);

    }

    public function gererGroupeAction($idListe)
    {

        $tousLesGroupes = $this->getGroupe($idListe);
        return $this->render('FfbbBundle:Groupe:gererGroupe.html.twig', [
            'tousLesGroupes' => $tousLesGroupes,
            'idListe' => $idListe,
        ]);

    }

    public function visualiserGroupeAction($idGroupe)
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir les participants pour cette liste de participants
        $participants = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();

        //$participants de string a array
        $participants = explode(",", $participants);

        # obtenir tout info pour chaque participant
        $detailsEntites = $em->getRepository('FfbbBundle:Entite')->getEntities($participants);

//        error_log("\n Controller: Listes, Function: visualiserListeParticipantsAction, datetime: ".$dateTimeNow
//            ."\n idGroupe : ".print_r($idGroupe, true), 3, "/tmp/optimouv.log");


        return $this->render('FfbbBundle:Groupe:visualiserGroupe.html.twig', array(

            'idGroupe' => $idGroupe,
            'detailsEntites' => $detailsEntites,
        ));
    }



}