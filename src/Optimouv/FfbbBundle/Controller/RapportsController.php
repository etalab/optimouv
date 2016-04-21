<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class RapportsController extends Controller
{
    public function indexAction()
    {
        # obtenir la date courante du système
        date_default_timezone_set('Europe/Paris');
        $dateTimeNow = date('Y-m-d_G:i:s', time());

        # récupérer idUtilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $role = $user->getRoles();
        if (in_array("ROLE_SUPER_ADMIN", $role) ){
            $role = "ROLE_SUPER_ADMIN";
        }
        elseif (in_array("ROLE_ADMIN", $role)){
            $role = "ROLE_ADMIN";
        }
        else{
            $role = "ROLE_USER";
        }

         $idUtilisateur = $user->getId();

        $infosRapports = $this->get('service_rapports')->getAllInfoRapprt($idUtilisateur, $role);
        
//
//
//        # récupérer chemin fichier log du fichier parameters.yml
//        $this->error_log_path = $this->container->getParameter("error_log_path");
//
//        # obtenir entity manager
//        $em = $this->getDoctrine()->getManager();
//
//        # récupérer tous les groupes appartenant à l'utilisateur courant
//        $groupesTemp = $em->getRepository('FfbbBundle:Groupe')->getGroupesParIdUtilisateur($idUtilisateur);
//        $idGroupes = [];
//        for($i=0; $i<count($groupesTemp); $i++){
//            array_push($idGroupes, $groupesTemp[$i]["id"]);
//        }
//
//
//
//        # récupérer tous les rapports de tous les groupes
//        $infoRapports = $em->getRepository('FfbbBundle:Rapport')->getRapportsParIdGroupe($idGroupes);
//
//
//        # récupérer l'id du rapport (résultat)
//        # BUG sur Symphony quand on récupère le statut directement en utilisant le repository du rapport
//        for($i=0; $i<count($infoRapports); $i++){
//            $idResultat = $infoRapports[$i]["id"];
//            $statut  = $this->get('service_poules')->getStatut($idResultat);
//            # ajouter le statut dans les infos retournées sur le front
//            $infoRapports[$i]["statut"] = $statut[0]["statut"];
//
//        }



        return $this->render('FfbbBundle:Rapports:index.html.twig', [
            "infoRapports" => $infosRapports
        ]);
    }

    public function consulterRapportAction($idRapport, $typeAction)
    {

        # obtenir entity manager
        $em = $this->getDoctrine()->getManager();


//        $typeAction = $_POST["typeAction"];

        if($typeAction == "barycentre"){

            return $this->redirect($this->generateUrl('ffbb_barycentre', array('idRapport' => $idRapport)));
        }
        elseif($typeAction == "exclusion"){

            return $this->redirect($this->generateUrl('ffbb_exclusion', array('idRapport' => $idRapport)));

        }
        elseif($typeAction == "meilleurLieu"){

            return $this->redirect($this->generateUrl('ffbb_rencontres', array('idRapport' => $idRapport)));
        }

        elseif($typeAction == "terrainNeutre"){

            return $this->redirect($this->generateUrl('ffbb_terrain', array('idRapport' => $idRapport)));
        }
        elseif($typeAction == "allerRetour"){


            $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);
            $idResultat =$idResultat[0]['id'];


            return $this->redirect($this->generateUrl('ffbb_poules_resultat_calcul', array('idResultat' => $idResultat)));
        }
        elseif($typeAction == "allerSimple"){


            $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);

            $idResultat =$idResultat[0]['id'];

            return $this->redirect($this->generateUrl('ffbb_poules_resultat_calcul', array('idResultat' => $idResultat)));
        }
        elseif($typeAction == "plateau"){


            $idResultat = $em->getRepository('FfbbBundle:Scenario')->getIdScenarioByIdRapport($idRapport);
            $idResultat =$idResultat[0]['id'];
            return $this->redirect($this->generateUrl('ffbb_poules_resultat_calcul', array('idResultat' => $idResultat)));
        }
        else{

            die('type d\'action non reconnu! ~rapport controller');
        }


    }


//   Mise à jour du nom du rapport
    public function updateAction($idRapport)
    {

         $em = $this->getDoctrine()->getManager();
        $nom  = $em->getRepository('FfbbBundle:Rapport')->findOneById($idRapport)->getNom();

         return $this->render('FfbbBundle:Rapports:update.html.twig', [
            "idRapport" => $idRapport,
            "nom" => $nom,
        ]);

    }

    public function updateRapportAction()
    {
        $nom = $_POST['nom'];
        $id= $_POST['idRapport'];
        $em = $this->getDoctrine()->getManager();
        $update  = $em->getRepository('FfbbBundle:Rapport')->updateRapport($nom, $id);
        if($update){
            return $this->redirect($this->generateUrl('ffbb_rapports'));
        }
        else{
            print_r("Un problème de mise à jour des rapports");
            exit;
        }

    }

//    Supprimer un rapport
    public function deleteAction($idRapport)
    {

        $em = $this->getDoctrine()->getManager();

        $connection = $em->getConnection();
        $statement = $connection->prepare('SET foreign_key_checks = 0');
        $statement->execute();
        $entity = $em->getRepository('FfbbBundle:Rapport')->find($idRapport);

        if (!$entity) {
            throw $this->createNotFoundException('Rapport introuvable.');
        }


        $em->remove($entity);
        $em->flush();

        return new JsonResponse(array(
            "success" => true,
            "msg" => "Rapport supprimée"
        ));

    }

}