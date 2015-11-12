<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Groupe
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Optimouv\FfbbBundle\Entity\GroupeRepository")
 */
class Groupe
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_utilisateur", type="integer")
     */
    private $idUtilisateur;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=50)
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text")
     */
    private $description;

    /**
     * @var integer
     *
     * @ORM\Column(name="poules", type="integer")
     */
    private $poules;

    /**
     * @var boolean
     *
     * @ORM\Column(name="interdiction", type="boolean")
     */
    private $interdiction;

    /**
     * @var boolean
     *
     * @ORM\Column(name="repartition_homogene", type="boolean")
     */
    private $repartitionHomogene;

    /**
     * @var boolean
     *
     * @ORM\Column(name="nbr_min_match_accueillir", type="boolean")
     */
    private $nbrMinMatchAccueillir;

    /**
     * @var integer
     *
     * @ORM\Column(name="nb_min_match_accueillir", type="integer")
     */
    private $nbMinMatchAccueillir;

    /**
     * @var integer
     *
     * @ORM\Column(name="nb_exclusion_zone", type="integer")
     */
    private $nbExclusionZone;

    /**
     * @var integer
     *
     * @ORM\Column(name="nb_participants", type="integer")
     */
    private $nbParticipants;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_creation", type="date")
     */
    private $dateCreation;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_modification", type="date")
     */
    private $dateModification;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set idUtilisateur
     *
     * @param integer $idUtilisateur
     *
     * @return Groupe
     */
    public function setIdUtilisateur($idUtilisateur)
    {
        $this->idUtilisateur = $idUtilisateur;

        return $this;
    }

    /**
     * Get idUtilisateur
     *
     * @return integer
     */
    public function getIdUtilisateur()
    {
        return $this->idUtilisateur;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return Groupe
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Groupe
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set poules
     *
     * @param integer $poules
     *
     * @return Groupe
     */
    public function setPoules($poules)
    {
        $this->poules = $poules;

        return $this;
    }

    /**
     * Get poules
     *
     * @return integer
     */
    public function getPoules()
    {
        return $this->poules;
    }

    /**
     * Set interdiction
     *
     * @param boolean $interdiction
     *
     * @return Groupe
     */
    public function setInterdiction($interdiction)
    {
        $this->interdiction = $interdiction;

        return $this;
    }

    /**
     * Get interdiction
     *
     * @return boolean
     */
    public function getInterdiction()
    {
        return $this->interdiction;
    }

    /**
     * Set repartitionHomogene
     *
     * @param boolean $repartitionHomogene
     *
     * @return Groupe
     */
    public function setRepartitionHomogene($repartitionHomogene)
    {
        $this->repartitionHomogene = $repartitionHomogene;

        return $this;
    }

    /**
     * Get repartitionHomogene
     *
     * @return boolean
     */
    public function getRepartitionHomogene()
    {
        return $this->repartitionHomogene;
    }

    /**
     * Set nbrMinMatchAccueillir
     *
     * @param boolean $nbrMinMatchAccueillir
     *
     * @return Groupe
     */
    public function setNbrMinMatchAccueillir($nbrMinMatchAccueillir)
    {
        $this->nbrMinMatchAccueillir = $nbrMinMatchAccueillir;

        return $this;
    }

    /**
     * Get nbrMinMatchAccueillir
     *
     * @return boolean
     */
    public function getNbrMinMatchAccueillir()
    {
        return $this->nbrMinMatchAccueillir;
    }

    /**
     * Set nbMinMatchAccueillir
     *
     * @param integer $nbMinMatchAccueillir
     *
     * @return Groupe
     */
    public function setNbMinMatchAccueillir($nbMinMatchAccueillir)
    {
        $this->nbMinMatchAccueillir = $nbMinMatchAccueillir;

        return $this;
    }

    /**
     * Get nbMinMatchAccueillir
     *
     * @return integer
     */
    public function getNbMinMatchAccueillir()
    {
        return $this->nbMinMatchAccueillir;
    }

    /**
     * Set nbExclusionZone
     *
     * @param integer $nbExclusionZone
     *
     * @return Groupe
     */
    public function setNbExclusionZone($nbExclusionZone)
    {
        $this->nbExclusionZone = $nbExclusionZone;

        return $this;
    }

    /**
     * Get nbExclusionZone
     *
     * @return integer
     */
    public function getNbExclusionZone()
    {
        return $this->nbExclusionZone;
    }

    /**
     * Set nbParticipants
     *
     * @param integer $nbParticipants
     *
     * @return Groupe
     */
    public function setNbParticipants($nbParticipants)
    {
        $this->nbParticipants = $nbParticipants;

        return $this;
    }

    /**
     * Get nbParticipants
     *
     * @return integer
     */
    public function getNbParticipants()
    {
        return $this->nbParticipants;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Groupe
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation
     *
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set dateModification
     *
     * @param \DateTime $dateModification
     *
     * @return Groupe
     */
    public function setDateModification($dateModification)
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    /**
     * Get dateModification
     *
     * @return \DateTime
     */
    public function getDateModification()
    {
        return $this->dateModification;
    }
}

