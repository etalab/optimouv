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
        $statutUpload = $this->get('service_listes')->controlerEntites();

//        error_log("\n Controller: Listes, Function: creerListeParticipantsAction, datetime: ".$dateTimeNow
//            ."\n statutUpload: ".print_r($statutUpload, true), 3, "/tmp/optimouv.log");

        if($statutUpload["success"]){

            # créer des entités dans la table entite
            $retourEntites = $this->get('service_listes')->creerEntites();
            $idsEntite = $retourEntites["idsEntite"];

            # créer une liste dans la table liste_participants
            $retourListe = $this->get('service_listes')->creerListeParticipants($idsEntite);

            # obtenir entity manager
            $em = $this->getDoctrine()->getManager();

            # obtenir listes des participants
            $listesParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getListes();

            return new JsonResponse(array(
                "success" => true,
                "msg" => "Votre liste a été correctement importée",
                "data" => $listesParticipants
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

//        error_log("\n Controller: Listes, Function: visualiserListeParticipantsAction, datetime: ".$dateTimeNow
//            ."\n typeListe : ".print_r($typeListe, true), 3, "/tmp/optimouv.log");


        return $this->render('FfbbBundle:Listes:visualiserListeParticipants.html.twig', array(
            'idListeParticipants' => $idListeParticipants,
            'detailsEntites' => $detailsEntites,
            'typeListe' => $typeListe,

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



        return $this->render('FfbbBundle:Listes:visualiserListeLieux.html.twig', array(

            'idListeLieux' => $idListeLieux,
            'detailsEntites' => $detailsEntites,
        ));

    }



    public function creerListeLieuxAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites();


        if($statutUpload["success"]){
            # créer des entités dans la table entite
            $retourEntites = $this->get('service_listes')->creerEntites();
            $idsEntite = $retourEntites["idsEntite"];

            # créer une liste dans la table liste_participants
            $retourListe = $this->get('service_listes')->creerListeLieux($idsEntite);

            # obtenir entity manager
            $em = $this->getDoctrine()->getManager();

            # obtenir listes des participants
            $listesLieux = $em->getRepository('FfbbBundle:ListeLieux')->getListes();

            return new JsonResponse(array(
                "success" => true,
                "msg" => "Votre liste a été correctement importée",
                "data" => $listesLieux
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

        $idUtilisateur = 1; //TODO: à rendre dynamique lorsqu'on a plusieurs utilisateurs
        $em = $this->getDoctrine()->getManager();

        //récupérer la liste de groupes

        $listesParticipants =  $em->getRepository('FfbbBundle:ListeParticipants')->findByIdUtilisateur($idUtilisateur, array('id'=>'DESC'));

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

//        error_log("\n Controller: Listes, Function: deleteAction, datetime: ".$dateTimeNow
//            ."\n groupeIds : ".print_r($groupeIds, true), 3, "/tmp/optimouv.log");


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

        return $this->render('FfbbBundle:Listes:renommerListe.html.twig', array(

            'idListeParticipants' => $idListeParticipants,
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


        /* return $this->redirectToRoute('ffbb_select_liste_participants', [
             'idListeParticipants' => $idListeParticipants
         ]);
        */

    }

    //Renommer une liste de lieux
    public function renommerLieuxAction($idListeLieux)
    {

        return $this->render('FfbbBundle:Listes:renommerLieu.html.twig', array(

            'idListeLieux' => $idListeLieux,
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
