<?php

namespace Optimouv\FfbbBundle\Controller;

use Optimouv\FfbbBundle\Entity\RepartitionHomogene;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
class RepartitionController extends Controller
{

    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $categories  = $em->getRepository('FfbbBundle:RepartitionHomogene')->findAll();

        return $this->render('FfbbBundle:Repartition:list.html.twig', [
            "categories" => $categories
        ]);

    }
    //Ajouter une nouvelle categorie de repartition homogene
    public function addAction()
    {
        return $this->render('FfbbBundle:Repartition:add.html.twig');
    }

    //creer une nouvelle catégorie
    public function createAction()
    {

        $designation = $_POST['designation'];
        $dateCreation = new \DateTime("now");

        $categorie = new RepartitionHomogene();
        if (!$categorie) {
            throw $this->createNotFoundException('Federation non trouvée!');
        }
        $em = $this->getDoctrine()->getManager();
        $categorie->setDesignation($designation)
            ->setDateCreation($dateCreation);

        $em->persist($categorie);
        $em->flush();
        return $this->redirect($this->generateUrl('ffbb_repartition_list'));
    }

    public function updateAction($idCategorie)
    {
        $em = $this->getDoctrine()->getManager();
        $designation  = $em->getRepository('FfbbBundle:RepartitionHomogene')->findOneById($idCategorie)->getDesignation();

        return $this->render('FfbbBundle:Repartition:update.html.twig', [
            "idCategorie" => $idCategorie,
            "designation" => $designation,
        ]);

    }
    public function updateCatAction()
    {
        $designation = $_POST['designation'];
        $id= $_POST['idCategorie'];
        $em = $this->getDoctrine()->getManager();

        $update  = $em->getRepository('FfbbBundle:RepartitionHomogene')->updateCat($designation, $id);
        if($update){
            return $this->redirect($this->generateUrl('ffbb_repartition_list'));
        }
        else{
            print_r("Un problème de mise à jour des disciplines");
            exit;
        }

    }

    public function deleteAction($idCategorie)
    {

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('FfbbBundle:RepartitionHomogene')->find($idCategorie);

        if (!$entity) {
            throw $this->createNotFoundException('Catégorie introuvable.');
        }


        $em->remove($entity);
        $em->flush();

        return new JsonResponse(array(
            "success" => true,
            "msg" => "Catégorie supprimée"
        ));

    }
}
