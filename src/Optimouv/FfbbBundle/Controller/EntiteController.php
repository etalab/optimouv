<?php

namespace Optimouv\FfbbBundle\Controller;

use Optimouv\FfbbBundle\Entity\Entite;
use Optimouv\FfbbBundle\Form\EntiteType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EntiteController extends Controller
{
    public function indexAction($idGroupe)
    {


        return $this->render('FfbbBundle:Entite:creer.html.twig', [
            'idGroupe' => $idGroupe,
        ]);
    }

    public function creerEntiteAction()
    {

        $idGroupe = $_POST['idGroupe'];



        //TODO: rendre dynamique $idUtilisateur
        $idUtilisateur = 1;
        $typeEntite = $_POST['typeLieu'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $adresse = $_POST['adresse'];
        $codePostal = $_POST['codePostal'];
        $ville = $_POST['ville'];
        $longitude = $_POST['longitude'];
        $latitude = $_POST['latitude'];
        $projection = $_POST['projection'];
        $typeEquipement = $_POST['typeEquipement'];
        $nbrEquipement = $_POST['nbrEquipement'];
        $capaciteRencontreStandard = $_POST['capaciteRencontreStandard'];
        $capacitePhasefinale = $_POST['capacitePhasefinale'];

        if($typeEntite == "Personne"){
            $nbrParticipants = 1;
        }
        elseif($typeEntite == "Lieu"){
            $nbrParticipants = 0;
        }
        else{
            $nbrParticipants = $_POST['nbrParticipants'];
        }


        $nbrLicencies = $_POST['nbrLicencies'];
        $lieuRencontrePossible = $_POST['lieuRencontrePossible'];

        $dateCreation = new \DateTime('11/22/2015'); //this returns the current date time

        //récupérer ville_id de la table villefrance

        $villeId = $this->get('service_listes')->verifierExistenceCodePostalNomVille($codePostal, $ville);

        $villeId = $villeId['idVille'];


        $em = $this->getDoctrine()->getManager();


        $entity = $entity = new Entite();

        if (!$entity) {
            throw $this->createNotFoundException('Entité groupe non trouvée!');
        }


        $entity->setTypeEntite($typeEntite)
                ->setNom($nom)
                ->setIdUtilisateur($idUtilisateur)
                ->setPrenom($prenom)
                ->setAdresse($adresse)
                ->setCodePostal($codePostal)
                ->setVille($ville)
                ->setLongitude($longitude)
                ->setLatitude($latitude)
                ->setProjection($projection)
                ->setTypeEquipement($typeEquipement)
                ->setNombreEquipement($nbrEquipement)
                ->setCapaciteRencontre($capaciteRencontreStandard)
                ->setCapacitePhaseFinale($capacitePhasefinale)
                ->setParticipants($nbrParticipants)
                ->setLicencies($nbrLicencies)
                ->setLieuRencontrePossible($lieuRencontrePossible)
                ->setDateCreation($dateCreation)
                ->setIdVilleFrance($villeId);
        $em->persist($entity);
        $em->flush();
        $idEntite = $entity->getId();

        //rajouter l'équipe au groupe
        $groupe = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getEquipes();

        $ajoutEntite = $groupe.",".$idEntite;

        $ajout = $em->getRepository('FfbbBundle:Groupe')->ajoutEntiteGroupe($idGroupe, $ajoutEntite);


        //rajouter l'équipe au groupe
        $idListeParticipant = $em->getRepository('FfbbBundle:Groupe')->findOneById($idGroupe)->getIdListeParticipant();
        $listeParticipant = $em->getRepository('FfbbBundle:ListeParticipants')->findOneById($idListeParticipant)->getEquipes();

        $ajoutEntiteListe = $listeParticipant.",".$idEntite;


        $ajoutListe = $em->getRepository('FfbbBundle:ListeParticipants')->ajoutEntiteListe($idListeParticipant, $ajoutEntiteListe);

        if($ajout && $ajoutListe){
            return $this->redirect($this->generateUrl('ffbb_gerer_groupe', array('idListe' => $idListeParticipant)));


        }
        else{
            return new Response("Problème de mise à jour du groupe");
        }




    }
}