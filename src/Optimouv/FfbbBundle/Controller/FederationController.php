<?php

namespace Optimouv\FfbbBundle\Controller;

use Optimouv\FfbbBundle\Entity\Federation;
use Optimouv\FfbbBundle\FfbbBundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
class FederationController extends Controller
{

    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $fede  = $em->getRepository('FfbbBundle:Federation')->findAll();



        return $this->render('FfbbBundle:Federation:list.html.twig', [
            "fede" => $fede
        ]);

    }
    
    //Ajouter une nouvelle fédération
    public function addAction()
    {
        return $this->render('FfbbBundle:Federation:add.html.twig');
    }

    public function addFedeAction()
    {
        
        $nom = $_POST['nom'];
        $designation = $_POST['designation'];
        $dateCreation = new \DateTime("now");

        $fede = new Federation();
        if (!$fede) {
            throw $this->createNotFoundException('Federation non trouvée!');
        }
        $em = $this->getDoctrine()->getManager();

        $fede->setNom($nom)
            ->setDesignation($designation)
            ->setDateCreation($dateCreation);

        $em->persist($fede);
        $em->flush();
        return $this->redirect($this->generateUrl('ffbb_federation_list'));
    }

    //supprimer une fédération

    public function deleteAction($idFede)
    {

        $em = $this->getDoctrine()->getManager();

        $connection = $em->getConnection();
        $statement = $connection->prepare('SET foreign_key_checks = 0');
        $statement->execute();
        $entity = $em->getRepository('FfbbBundle:Federation')->find($idFede);

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe introuvable.');
        }


        $em->remove($entity);
        $em->flush();

        return new JsonResponse(array(
            "success" => true,
            "msg" => "Fédération supprimée"
        ));

    }
    
    //update fédération

    public function updateAction($idFede)
    {
        $em = $this->getDoctrine()->getManager();
        $nom  = $em->getRepository('FfbbBundle:Federation')->findOneById($idFede)->getNom();
        $designation  = $em->getRepository('FfbbBundle:Federation')->findOneById($idFede)->getdesignation();

        return $this->render('FfbbBundle:Federation:update.html.twig', [
            "idFede" => $idFede,
            "nom" => $nom,
            "designation" => $designation
        ]);

    }

    public function updateFedeAction()
    {
        $params =[];
        $nom = $_POST['nom'];
        $designation = $_POST['designation'];
        $id = $_POST['idFede'];

        //stocker les variables dans une seule
        $params[0] = $id;
        $params[1] = $nom;
        $params[2] = $designation;

        $em = $this->getDoctrine()->getManager();
        $update  = $em->getRepository('FfbbBundle:Federation')->updateFede($params);

        if($update){
            return $this->redirect($this->generateUrl('ffbb_federation_list'));
        }
        else{
            print_r("Un problème de mise à jour des fédérations");
            exit;
        }

    }
}
