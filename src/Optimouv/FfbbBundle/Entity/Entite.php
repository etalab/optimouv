<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entite
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Optimouv\FfbbBundle\Entity\EntiteRepository")
 */
class Entite
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
     * @ORM\Column(name="id_discipline", type="integer")
     * @ORMManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Discipline", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $idDiscipline;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_utilisateur", type="integer")
     */
    private $idUtilisateur;

    /**
     * @var string
     *
     * @ORM\Column(name="type_entite", type="string", length=50)
     */
    private $typeEntite;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=50)
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="prenom", type="string", length=50)
     */
    private $prenom;

    /**
     * @var string
     *
     * @ORM\Column(name="adresse", type="string", length=100)
     */
    private $adresse;

    /**
     * @var string
     *
     * @ORM\Column(name="code_postal", type="string", length=5)
     */
    private $codePostal;

    /**
     * @var string
     *
     * @ORM\Column(name="ville", type="string", length=50)
     */
    private $ville;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="float")
     */
    private $longitude;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="float")
     */
    private $latitude;

    /**
     * @var string
     *
     * @ORM\Column(name="projection", type="string", length=50)
     */
    private $projection;

    /**
     * @var string
     *
     * @ORM\Column(name="type_equipement", type="string", length=50)
     */
    private $typeEquipement;

    /**
     * @var integer
     *
     * @ORM\Column(name="nombre_equipement", type="integer")
     */
    private $nombreEquipement;

    /**
     * @var boolean
     *
     * @ORM\Column(name="capacite_rencontre", type="boolean")
     */
    private $capaciteRencontre;

    /**
     * @var boolean
     *
     * @ORM\Column(name="capacite_phase_finale", type="boolean")
     */
    private $capacitePhaseFinale;

    /**
     * @var integer
     *
     * @ORM\Column(name="participants", type="integer")
     */
    private $participants;

    /**
     * @var integer
     *
     * @ORM\Column(name="licencies", type="integer")
     */
    private $licencies;

    /**
     * @var boolean
     *
     * @ORM\Column(name="lieu_rencontre_possible", type="boolean")
     */
    private $lieuRencontrePossible;

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
     * Set idDiscipline
     *
     * @param integer $idDiscipline
     *
     * @return Entite
     */
    public function setIdDiscipline($idDiscipline)
    {
        $this->idDiscipline = $idDiscipline;

        return $this;
    }

    /**
     * Get idDiscipline
     *
     * @return integer
     */
    public function getIdDiscipline()
    {
        return $this->idDiscipline;
    }

    /**
     * Set idUtilisateur
     *
     * @param integer $idUtilisateur
     *
     * @return Entite
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
     * Set typeEntite
     *
     * @param string $typeEntite
     *
     * @return Entite
     */
    public function setTypeEntite($typeEntite)
    {
        $this->typeEntite = $typeEntite;

        return $this;
    }

    /**
     * Get typeEntite
     *
     * @return string
     */
    public function getTypeEntite()
    {
        return $this->typeEntite;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return Entite
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
     * Set prenom
     *
     * @param string $prenom
     *
     * @return Entite
     */
    public function setPrenom($prenom)
    {
        $this->prenom = $prenom;

        return $this;
    }

    /**
     * Get prenom
     *
     * @return string
     */
    public function getPrenom()
    {
        return $this->prenom;
    }

    /**
     * Set adresse
     *
     * @param string $adresse
     *
     * @return Entite
     */
    public function setAdresse($adresse)
    {
        $this->adresse = $adresse;

        return $this;
    }

    /**
     * Get adresse
     *
     * @return string
     */
    public function getAdresse()
    {
        return $this->adresse;
    }

    /**
     * Set codePostal
     *
     * @param string $codePostal
     *
     * @return Entite
     */
    public function setCodePostal($codePostal)
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    /**
     * Get codePostal
     *
     * @return string
     */
    public function getCodePostal()
    {
        return $this->codePostal;
    }

    /**
     * Set ville
     *
     * @param string $ville
     *
     * @return Entite
     */
    public function setVille($ville)
    {
        $this->ville = $ville;

        return $this;
    }

    /**
     * Get ville
     *
     * @return string
     */
    public function getVille()
    {
        return $this->ville;
    }

    /**
     * Set longitude
     *
     * @param float $longitude
     *
     * @return Entite
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude
     *
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set latitude
     *
     * @param float $latitude
     *
     * @return Entite
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude
     *
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set projection
     *
     * @param string $projection
     *
     * @return Entite
     */
    public function setProjection($projection)
    {
        $this->projection = $projection;

        return $this;
    }

    /**
     * Get projection
     *
     * @return string
     */
    public function getProjection()
    {
        return $this->projection;
    }

    /**
     * Set typeEquipement
     *
     * @param string $typeEquipement
     *
     * @return Entite
     */
    public function setTypeEquipement($typeEquipement)
    {
        $this->typeEquipement = $typeEquipement;

        return $this;
    }

    /**
     * Get typeEquipement
     *
     * @return string
     */
    public function getTypeEquipement()
    {
        return $this->typeEquipement;
    }

    /**
     * Set nombreEquipement
     *
     * @param integer $nombreEquipement
     *
     * @return Entite
     */
    public function setNombreEquipement($nombreEquipement)
    {
        $this->nombreEquipement = $nombreEquipement;

        return $this;
    }

    /**
     * Get nombreEquipement
     *
     * @return integer
     */
    public function getNombreEquipement()
    {
        return $this->nombreEquipement;
    }

    /**
     * Set capaciteRencontre
     *
     * @param boolean $capaciteRencontre
     *
     * @return Entite
     */
    public function setCapaciteRencontre($capaciteRencontre)
    {
        $this->capaciteRencontre = $capaciteRencontre;

        return $this;
    }

    /**
     * Get capaciteRencontre
     *
     * @return boolean
     */
    public function getCapaciteRencontre()
    {
        return $this->capaciteRencontre;
    }

    /**
     * Set capacitePhaseFinale
     *
     * @param boolean $capacitePhaseFinale
     *
     * @return Entite
     */
    public function setCapacitePhaseFinale($capacitePhaseFinale)
    {
        $this->capacitePhaseFinale = $capacitePhaseFinale;

        return $this;
    }

    /**
     * Get capacitePhaseFinale
     *
     * @return boolean
     */
    public function getCapacitePhaseFinale()
    {
        return $this->capacitePhaseFinale;
    }

    /**
     * Set participants
     *
     * @param integer $participants
     *
     * @return Entite
     */
    public function setParticipants($participants)
    {
        $this->participants = $participants;

        return $this;
    }

    /**
     * Get participants
     *
     * @return integer
     */
    public function getParticipants()
    {
        return $this->participants;
    }

    /**
     * Set licencies
     *
     * @param integer $licencies
     *
     * @return Entite
     */
    public function setLicencies($licencies)
    {
        $this->licencies = $licencies;

        return $this;
    }

    /**
     * Get licencies
     *
     * @return integer
     */
    public function getLicencies()
    {
        return $this->licencies;
    }

    /**
     * Set lieuRencontrePossible
     *
     * @param boolean $lieuRencontrePossible
     *
     * @return Entite
     */
    public function setLieuRencontrePossible($lieuRencontrePossible)
    {
        $this->lieuRencontrePossible = $lieuRencontrePossible;

        return $this;
    }

    /**
     * Get lieuRencontrePossible
     *
     * @return boolean
     */
    public function getLieuRencontrePossible()
    {
        return $this->lieuRencontrePossible;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Entite
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
     * @return Entite
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

