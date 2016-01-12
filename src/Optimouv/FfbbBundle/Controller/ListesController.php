<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class ListesController extends Controller
{
    public function indexAction()
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir listes des participants
        $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes();

        # obtenir listes des lieux de rencontres
        $listesLieux = $em->getRepository('FfbbBundle:ListeLieux')->getListes();

        return $this->render('FfbbBundle:Listes:index.html.twig', array(
            "listesParticipants" => $listesParticipants,
            "listesLieux" => $listesLieux
        ));

    }

    public function creerListeParticipantsAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites("participants");

        # récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        if($statutUpload["success"]){

            # créer des entités dans la table entite
            $retourEntites = $this->get('service_listes')->creerEntites($idUtilisateur);
            $idsEntite = $retourEntites["idsEntite"];
            $nomFichier = $retourEntites["nomFichier"];
            $rencontre = 1;

            # créer une liste dans la table liste_participants
            $retourListe = $this->get('service_listes')->creerListeParticipants($idsEntite, $nomFichier, $idUtilisateur, $rencontre);

            # obtenir entity manager
            $em = $this->getDoctrine()->getManager();

            # obtenir listes des participants
            $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes($rencontre);

            return new JsonResponse(array(
                "success" => true,
                "msg" => "Votre liste a été correctement importée",
                "data" => $listesParticipants,
                "dateCreation" =>  $retourListe["data"]["dateCreation"]
            ));

        }
        else{

            # convertir str au tableau
            if(!is_array($statutUpload["msg"])  ){
                return new JsonResponse(array(
                    "success" => false,
                    "msg" => array($statutUpload["msg"])
                ));

            }
            else{
                return new JsonResponse(array(
                    "success" => false,
                    "msg" => $statutUpload["msg"]
                ));

            }


        }
    }

    public function visualiserListeParticipantsAction($idListeParticipants){

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir les participants pour cette liste de participants
        $participants = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getEquipes();

        //$participants de string a array
        $participants = explode(",", $participants);

        # obtenir tout info pour chaque participant
        $detailsEntites = $em->getRepository('FfbbBundle:Entite')->getEntities($participants);

        # obtenir le type de la liste (equipes ou personnes)
        $typeListe = $detailsEntites[0]["typeEntite"];


        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getNom();

        return $this->render('FfbbBundle:Listes:visualiserListeParticipants.html.twig', array(
            'idListeParticipants' => $idListeParticipants,
            'detailsEntites' => $detailsEntites,
            'typeListe' => $typeListe,
            'nomListe' =>$nomListe,

        ));

    }


    public function visualiserListeLieuxAction($idListeLieux){

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir les participants pour cette liste de participants
        $lieux = $em->getRepository('FfbbBundle:ListeLieux')->findOneById($idListeLieux)->getLieux();

        //$lieux de string a array
        $lieux = explode(",", $lieux);


        # obtenir tout info pour chaque participant
        $detailsEntites = $em->getRepository('FfbbBundle:Entite')->getEntities($lieux);

         # récupérer idListe pour le breadcrump
        $nomListeLieux =  $em->getRepository('FfbbBundle:ListeLieux')->findOneById($idListeLieux)->getNom();

        return $this->render('FfbbBundle:Listes:visualiserListeLieux.html.twig', array(

            'idListeLieux' => $idListeLieux,
            'detailsEntites' => $detailsEntites,
            'nomListeLieux' => $nomListeLieux,
        ));

    }



    public function creerListeLieuxAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites("lieux");

        # récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        if($statutUpload["success"]){
            # créer des entités dans la table entite
            $retourEntites = $this->get('service_listes')->creerEntites($idUtilisateur);
            $idsEntite = $retourEntites["idsEntite"];
            $nomFichier = $retourEntites["nomFichier"];


            # créer une liste dans la table liste_participants
            $retourListe = $this->get('service_listes')->creerListeLieux($idsEntite, $nomFichier, $idUtilisateur);

            # obtenir entity manager
            $em = $this->getDoctrine()->getManager();

            # obtenir listes des participants
            $listesLieux = $em->getRepository('FfbbBundle:ListeLieux')->getListes();

            return new JsonResponse(array(
                "success" => true,
                "msg" => "Votre liste a été correctement importée",
                "data" => $listesLieux,
                "dateCreation" =>  $retourListe["data"]["dateCreation"]
            ));


        }else{

            # convertir str au tableau
            if(!is_array($statutUpload["msg"])  ){
                return new JsonResponse(array(
                    "success" => false,
                    "msg" => array($statutUpload["msg"])
                ));

            }
            else{
                return new JsonResponse(array(
                    "success" => false,
                    "msg" => $statutUpload["msg"]
                ));

            }

        }


    }


    public function gererListesAction()
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        $em = $this->getDoctrine()->getManager();

        //récupérer la liste de groupes

        $listesParticipants =  $em->getRepository('FfbbBundle:ListeParticipants')->getListesParticipants($idUtilisateur);

        $listesLieux =  $em->getRepository('FfbbBundle:ListeLieux')->findByIdUtilisateur($idUtilisateur, array('id'=>'DESC'));


        return $this->render('FfbbBundle:Listes:gererListes.html.twig', [
            'listesParticipants' => $listesParticipants,
            'listesLieux' => $listesLieux,
        ]);
    }

    public function deleteAction($idListeParticipants)
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('FfbbBundle:ListeParticipants')->find($idListeParticipants);
        $equipes = $em->getRepository('FfbbBundle:ListeParticipants')->find($idListeParticipants)->getEquipes();

        # convertir equipes en tableau
        $equipes = explode(',', $equipes);

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe introuvable.');
        }

        # supprimer la liste de participants
        $em->remove($entity);
        $em->flush();


        # supprimer les entités associées
        $em->getRepository('FfbbBundle:Entite')->deleteEntities($equipes);

        # obtenir tous les groupes
        $groupes = $em->getRepository('FfbbBundle:Groupe')->getGroupesParIdListeParticipants($idListeParticipants);


        $groupeIds = [];
        # construire une liste des ids de groupes
        for($i=0; $i<count($groupes); $i++){
            array_push($groupeIds, $groupes[$i]["id"]);
        }

        if(count($groupeIds)>0){
            # supprimer les groupes associés
            $groupes = $em->getRepository('FfbbBundle:Groupe')->deleteGroupes($groupeIds);

        }

        return new JsonResponse(array(
            "success" => true,
            "msg" => "Groupe supprimé"
        ));

    }

    public function deleteLieuxAction($idListeLieux)
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('FfbbBundle:ListeLieux')->find($idListeLieux);
        $lieux = $em->getRepository('FfbbBundle:ListeLieux')->find($idListeLieux)->getLieux();

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe introuvable.');
        }

        # convertir equipes en tableau
        $lieux = explode(',', $lieux);

        $em->remove($entity);
        $em->flush();

        # supprimer les entités associées
        $em->getRepository('FfbbBundle:Entite')->deleteEntities($lieux);


        # supprimer les groupes associés
        $groupes = $em->getRepository('FfbbBundle:Groupe')->getGroupesParIdListeLIeux($idListeLieux);


        $groupeIds = [];
        # construire une liste des ids de groupes
        for($i=0; $i<count($groupes); $i++){
            array_push($groupeIds, $groupes[$i]["id"]);
        }

        if(count($groupeIds)>0){
            # supprimer les groupes associés
            $groupes = $em->getRepository('FfbbBundle:Groupe')->deleteGroupes($groupeIds);

        }




        return new JsonResponse(array(
            "success" => true,
            "msg" => "Groupe supprimé"
        ));

    }
    //Renommer une liste de participants
    public function renommerListeAction($idListeParticipants)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # récupérer idListe pour le breadcrump
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipants)->getNom();

        return $this->render('FfbbBundle:Listes:renommerListe.html.twig', array(

            'idListeParticipants' => $idListeParticipants,
            'nomListe' => $nomListe,
        ));

    }

    public function renommerListeParticipantsAction($idListeParticipants)
    {



        $em = $this->getDoctrine()->getManager();


        $entity = $em->getRepository('FfbbBundle:ListeParticipants')->find($idListeParticipants);

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe non trouvée!');
        }

        $nomGroupe = $_POST["nomListeParticipants"];

        $entity->setNom($nomGroupe);
        $em->persist($entity);
        $em->flush();

        return $this->redirect($this->generateUrl('ffbb_gerer_liste_participants' ));

    }

    //Renommer une liste de lieux
    public function renommerLieuxAction($idListeLieux)
    {

        $em = $this->getDoctrine()->getManager();
        # récupérer idListe pour le breadcrump
        $nomListeLieux =  $em->getRepository('FfbbBundle:ListeLieux')->findOneById($idListeLieux)->getNom();

        return $this->render('FfbbBundle:Listes:renommerLieu.html.twig', array(

            'idListeLieux' => $idListeLieux,
            'nomListeLieux' => $nomListeLieux,
        ));

    }

    public function renommerListeLieuxAction($idListeLieux)
    {


        $em = $this->getDoctrine()->getManager();


        $entity = $em->getRepository('FfbbBundle:ListeLieux')->find($idListeLieux);

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe non trouvée!');
        }

        $nomGroupe = $_POST["nomListeLieux"];

        $entity->setNom($nomGroupe);
        $em->persist($entity);
        $em->flush();


        return $this->redirect($this->generateUrl('ffbb_gerer_liste_participants' ));

    }
}
