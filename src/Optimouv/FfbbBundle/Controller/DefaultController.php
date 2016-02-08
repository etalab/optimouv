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


        $typeAction =  $_POST["rencontre"];


        if($typeAction == "exclusion"){


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

    public function sendMailAction()
    {

//        $email = $this->getUser()->getEmail();
        $email = "oussema.ghodbane@it4pme.fr";
        $username =  $this->getUser()->getUsername();
        $body = $this->renderView('FfbbBundle:Mails:confirmationCalcul.html.twig', array('username' => $username));

            $message = \Swift_Message::newInstance()
            ->setSubject('Calcul terminé')
            ->setFrom('serviceclients@it4pme.fr')
            ->setTo($email)
            ->setBody($body)
        ;
        $this->get('mailer')->send($message);

//        return $this->redirect($this->generateUrl('ffbb_accueil'));
        return true;

    }


}
