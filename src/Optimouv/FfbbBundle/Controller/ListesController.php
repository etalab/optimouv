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

        error_log("\n Controller: Listes, Function: creerListeParticipantsAction, datetime: ".$dateTimeNow
            ."\n statutUpload: ".print_r($statutUpload, true), 3, "/tmp/optimouv.log");


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
            return new JsonResponse(array(
                "success" => false,
                "msg" => $statutUpload["msg"]
            ));
        }


    }

    public function creerListeLieuxAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # controler toutes le fichier uploadé
        $statutUpload = $this->get('service_listes')->controlerEntites();

        error_log("\n Controller: Listes, Function: creerListeLieuxAction, datetime: ".$dateTimeNow
            ."\n statutUpload: ".print_r($statutUpload, true), 3, "/tmp/optimouv.log");

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
            return new JsonResponse(array(
                "success" => false,
                "msg" => $statutUpload["msg"]
            ));
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

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('FfbbBundle:ListeParticipants')->find($idListeParticipants);

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

    public function deleteLieuxAction($idListeLieux)
    {

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('FfbbBundle:ListeLieux')->find($idListeLieux);

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
