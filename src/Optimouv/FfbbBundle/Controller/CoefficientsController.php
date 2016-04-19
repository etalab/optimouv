<?php

namespace Optimouv\FfbbBundle\Controller;

use Proxies\__CG__\Optimouv\FfbbBundle\Entity\Federation;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Validator\Tests\Fixtures\Reference;

class CoefficientsController extends Controller
{
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();

        $coefficients  = $em->getRepository('FfbbBundle:Reference')->findAll();

        return $this->render('FfbbBundle:Coefficients:list.html.twig', [
            "coefficients" => $coefficients
        ]);

    }


    public function updateAction($idReference)
    {
        $em = $this->getDoctrine()->getManager();
        $reference  = $em->getRepository('FfbbBundle:Reference')->findOneById($idReference);

        return $this->render('FfbbBundle:Coefficients:update.html.twig', [
            "reference" => $reference,
        ]);

    }
    public function updateCoefAction()
    {

        $coefficient = $_POST['coefficient'];
        $code = $_POST['code'];
        $id= $_POST['idReference'];
        $dateModification = new \DateTime("now");
        $em = $this->getDoctrine()->getManager();

        $update  = $em->getRepository('FfbbBundle:Reference')->updateCoef($code,$coefficient,$dateModification, $id);
        
        if($update){
            return $this->redirect($this->generateUrl('ffbb_coefficients_list'));
        }
        else{
            print_r("Un problème de mise à jour des coefficients");
            exit;
        }

    }


    


    //Ajouter une nouvelle categorie de repartition homogene
    public function addAction()
    {
        return $this->render('FfbbBundle:Coefficients:add.html.twig');
    }

    public function createAction()
    {

        $code = $_POST['code'];
        $valeur = $_POST['valeur'];
        $dateCreation = new \DateTime("now");

        $coefficient = new \Optimouv\FfbbBundle\Entity\Reference();
        if (!$coefficient) {
            throw $this->createNotFoundException('Federation non trouvée!');
        }
        $em = $this->getDoctrine()->getManager();
        $coefficient->setCode($code)
                     ->setValeur($valeur)
                     ->setDateCreation($dateCreation);

        $em->persist($coefficient);
        $em->flush();
        return $this->redirect($this->generateUrl('ffbb_coefficients_list'));
    }


}
