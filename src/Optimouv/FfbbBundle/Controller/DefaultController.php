<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        $villes = $_POST["duallistbox_demo1"];

        $nomGroupe = $_POST["nomGroupe"];
        $idListeParticipants = $_POST["idListeParticipants"];
        $idListeLieux = null;
        if(!empty($_POST["idListeLieux"])){
            $idListeLieux = $_POST["idListeLieux"];

        }
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $idUtilisateur = $user->getId();

        $idGroupe = $this->get('service_rencontres')->creerGroupe($villes, $nomGroupe, $idListeParticipants, $idListeLieux, $idUtilisateur);

        $coordonneesVille = $this->get('service_rencontres')->index($idGroupe);

        $coordonneesVille = array_merge($coordonneesVille[0], $coordonneesVille[1]);

        $nomsVilles = $this->get('service_rencontres')->nomsVilles($idGroupe);

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();


         return $this->render('FfbbBundle:Default:index.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
             'idGroupe' => $idGroupe,
             'idListe' => $idListe,

        ));
    }

    public function groupeAction($idGroupe)
    {
        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();

        $coordonneesVille = $this->get('service_rencontres')->index($idGroupe);
        $coordonneesVille = array_merge($coordonneesVille[0], $coordonneesVille[1]);


        $nomsVilles = $this->get('service_rencontres')->nomsVilles($idGroupe);

        # récupérer idListe pour le breadcrump
        $idListe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $nomListe =  $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListe)->getNom();
        $nomGroupe =  $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getNom();

        return $this->render('FfbbBundle:Default:index.html.twig', array(

            'coordonneesVille' => $coordonneesVille,
            'nomsVilles' => $nomsVilles,
            'idGroupe' => $idGroupe,
            'idListe' => $idListe,
            'nomListe' => $nomListe,
            'nomGroupe' => $nomGroupe,

        ));
    }

}
