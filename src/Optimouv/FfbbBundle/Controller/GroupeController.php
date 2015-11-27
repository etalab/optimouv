<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class GroupeController extends Controller
{
    public function indexAction()
    {
        return $this->render('FfbbBundle:Groupe:index.html.twig');
    }

    public function afficherParticipantsAction($idListeParticipants)
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        # obtenir listes des lieux de rencontres
        $idParticipants = $em->getRepository('FfbbBundle:ListeParticipants')->getEquipesPourListe($idListeParticipants);
        $idParticipants = $idParticipants[0]["equipes"];
        $idParticipants = explode(",", $idParticipants);

        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

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

//        error_log("\n Controller: Groupe, Function: afficherParticipantsAction, datetime: ".$dateTimeNow
//            ."\n detailsEntites : ".print_r($detailsEntites, true), 3, "/var/log/apache2/optimouv.log");

        $outputTableau = array("detailsEntites" => $detailsEntites );

        return $this->render('FfbbBundle:Groupe:indexUpdate.html.twig', $outputTableau);
    }

    public function afficherLieuxAction($idListeLieux)
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

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

}