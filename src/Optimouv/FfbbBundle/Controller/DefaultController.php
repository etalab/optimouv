<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

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

    public function lancerProducerAction($idGroupe)
    {
        # récupérer chemin fichier log du fichier parameters.yml
        $this->error_log_path = $this->container->getParameter("error_log_path");

        $utilisateur = $this->get('security.token_storage')->getToken()->getUser();
        $utilisateurId = $utilisateur->getId();

        # incrémenter le nombre de lancements de calcul pour meilleur lieu
        $this->get('service_statistiques')->augmenterNombreTableStatistiques($utilisateurId, "nombreLancementsMeilleurLieu", 1);


        $typeAction =  $_POST["rencontre"];
        if($typeAction == "exclusion"){

            # incrémenter le nombre d'exclusion géographiques pour le scénario barycentre avec exclusion
            $this->get('service_statistiques')->augmenterNombreTableStatistiques($utilisateurId, "nombreExclusions", 1);


            $valeurExclusion =  $_POST["valeurExclusion"];

            $idTache = $this->get('service_rencontres')->producerExclusion($idGroupe, $valeurExclusion);

            if($idTache){

                $this->get('old_sound_rabbit_mq.rencontre_producer')->publish($idTache);

                return $this->redirect($this->generateUrl('ffbb_rapports'));

            }
            else{

                die("problème de récupération du job");

            }

        }

        else{

            $idTache = $this->get('service_rencontres')->Producer($idGroupe, $typeAction);

            if($idTache){
                $this->get('old_sound_rabbit_mq.rencontre_producer')->publish($idTache);

                return $this->redirect($this->generateUrl('ffbb_rapports'));

            }
            else{
                die("problème de récupération du job");

            }


        }


    }


}
