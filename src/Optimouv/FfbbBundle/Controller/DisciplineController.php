<?php

namespace Optimouv\FfbbBundle\Controller;

use Optimouv\FfbbBundle\Entity\Discipline;
use Optimouv\FfbbBundle\FfbbBundle;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
class DisciplineController extends Controller
{

    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $disciplines  = $em->getRepository('FfbbBundle:Discipline')->findAll();
        
        return $this->render('FfbbBundle:Discipline:list.html.twig', [
            "disciplines" => $disciplines
        ]);

    }
    
    //Ajouter une nouvelle fédération
    public function addAction()
    {
        $em = $this->getDoctrine()->getManager();

        $fede  = $em->getRepository('FfbbBundle:Federation')->findAll();
        return $this->render('FfbbBundle:Discipline:add.html.twig', [
            "fede" => $fede
        ]);
    }

    public function addDisciplineAction()
    {
        
        $nom = $_POST['nom'];
        $federation_id = intval($_POST['federation']);
        $dateCreation = new \DateTime("now");

        $discipline = new Discipline();
        if (!$discipline) {
            throw $this->createNotFoundException('Federation non trouvée!');
        }
        $em = $this->getDoctrine()->getManager();
        $federation = $em->getRepository('FfbbBundle:Federation')->findOneById($federation_id);
        $discipline->setNom($nom)
            ->setFederation($federation)
            ->setDateCreation($dateCreation);

        $em->persist($discipline);
        $em->flush();
        return $this->redirect($this->generateUrl('ffbb_discipline_list'));
    }

    //supprimer une fédération

    public function deleteAction($idDiscipline)
    {

        $em = $this->getDoctrine()->getManager();

        $connection = $em->getConnection();
        $statement = $connection->prepare('SET foreign_key_checks = 0');
        $statement->execute();
        $entity = $em->getRepository('FfbbBundle:Discipline')->find($idDiscipline);

        if (!$entity) {
            throw $this->createNotFoundException('Discipline introuvable.');
        }


        $em->remove($entity);
        $em->flush();

        return new JsonResponse(array(
            "success" => true,
            "msg" => "Discipline supprimée"
        ));

    }
    
    //update Discipline

    public function updateAction($idDiscipline)
    {
        $em = $this->getDoctrine()->getManager();
        $nom  = $em->getRepository('FfbbBundle:Discipline')->findOneById($idDiscipline)->getNom();

        return $this->render('FfbbBundle:Discipline:update.html.twig', [
            "idDiscipline" => $idDiscipline,
            "nom" => $nom,
        ]);

    }

    public function updateDiscAction()
    {
        $nom = $_POST['nom'];
        $id= $_POST['idDiscipline'];
        $em = $this->getDoctrine()->getManager();
        $update  = $em->getRepository('FfbbBundle:Discipline')->updateDisc($nom, $id);
        if($update){
            return $this->redirect($this->generateUrl('ffbb_discipline_list'));
        }
        else{
            print_r("Un problème de mise à jour des disciplines");
            exit;
        }

    }
    
}
