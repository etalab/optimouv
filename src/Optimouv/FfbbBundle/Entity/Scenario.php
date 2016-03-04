<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Scenario
 *
 * @ORM\Table(name="resultats", indexes={@ORM\Index(name="id_rapport_idx", columns={"id_rapport"})})
 * @ORM\Entity(repositoryClass="ScenarioRepository")
 */
class Scenario
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=50, nullable=false)
     */
    private $nom;

    /**
     * @var integer
     *
     * @ORM\Column(name="kilometres", type="integer", nullable=false)
     */
    private $kilometres;

    /**
     * @var integer
     *
     * @ORM\Column(name="duree", type="integer", nullable=false)
     */
    private $duree;

    /**
     * @var float
     *
     * @ORM\Column(name="co2_voiture", type="float", precision=10, scale=0, nullable=false)
     */
    private $co2Voiture;

    /**
     * @var float
     *
     * @ORM\Column(name="co2_covoiturage", type="float", precision=10, scale=0, nullable=false)
     */
    private $co2Covoiturage;

    /**
     * @var float
     *
     * @ORM\Column(name="co2_minibus", type="float", precision=10, scale=0, nullable=false)
     */
    private $co2Minibus;


    /**
     * @var float
     *
     * @ORM\Column(name="cout_voiture", type="float", precision=10, scale=0, nullable=false)
     */
    private $coutVoiture;

    /**
     * @var float
     *
     * @ORM\Column(name="cout_covoiturage", type="float", precision=10, scale=0, nullable=false)
     */
    private $coutCovoiturage;

    /**
     * @var float
     *
     * @ORM\Column(name="cout_minibus", type="float", precision=10, scale=0, nullable=false)
     */
    private $coutMinibus;


    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_creation", type="date", nullable=false)
     */
    private $dateCreation;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_modification", type="date", nullable=false)
     */
    private $dateModification;

    /**
     * @var \Optimouv\FfbbBundle\Entity\Rapport
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Rapport", cascade={"remove"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_rapport", referencedColumnName="id")
     * })
     */
    private $idRapport;

    /**
     * @var string
     *
     * @ORM\Column(name="details_calcul", type="text", nullable=true)
     */
    private $detailsCalcul;



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
     * Set nom
     *
     * @param string $nom
     *
     * @return Scenario
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
     * Set kilometres
     *
     * @param integer $kilometres
     *
     * @return Scenario
     */
    public function setKilometres($kilometres)
    {
        $this->kilometres = $kilometres;

        return $this;
    }

    /**
     * Get kilometres
     *
     * @return integer
     */
    public function getKilometres()
    {
        return $this->kilometres;
    }

    /**
     * Set duree
     *
     * @param integer $duree
     *
     * @return Scenario
     */
    public function setDuree($duree)
    {
        $this->duree = $duree;

        return $this;
    }

    /**
     * Get duree
     *
     * @return integer
     */
    public function getDuree()
    {
        return $this->duree;
    }

    /**
     * Set co2Voiture
     *
     * @param float $co2Voiture
     *
     * @return Scenario
     */
    public function setCo2Voiture($co2Voiture)
    {
        $this->co2Voiture = $co2Voiture;

        return $this;
    }

    /**
     * Get co2Covoiture
     *
     * @return float
     */
    public function getCo2Voiture()
    {
        return $this->co2Voiture;
    }

    /**
     * Set co2Covoiturage
     *
     * @param float $co2Covoiturage
     *
     * @return Scenario
     */
    public function setCo2Voiturage($co2Covoiturage)
    {
        $this->co2Covoiturage = $co2Covoiturage;

        return $this;
    }

    /**
     * Get co2Covoiturage
     *
     * @return float
     */
    public function getCo2Voiturage()
    {
        return $this->co2Covoiturage;
    }

    /**
     * Set co2Minibus
     *
     * @param float $co2Minibus
     *
     * @return Scenario
     */
    public function setCo2Minibus($co2Minibus)
    {
        $this->co2Minibus = $co2Minibus;

        return $this;
    }

    /**
     * Get co2Minibus
     *
     * @return float
     */
    public function getCo2Minibus()
    {
        return $this->co2Minibus;
    }



    /**
     * Set coutVoiture
     *
     * @param float $coutVoiture
     *
     * @return Scenario
     */
    public function setCoutVoiture($coutVoiture)
    {
        $this->coutVoiture = $coutVoiture;

        return $this;
    }

    /**
     * Get coutVoiture
     *
     * @return float
     */
    public function getCoutVoiture()
    {
        return $this->coutVoiture;
    }


    /**
     * Set coutCovoiturage
     *
     * @param float $coutCovoiturage
     *
     * @return Scenario
     */
    public function setCoutCovoiturage($coutCovoiturage)
    {
        $this->coutCovoiturage = $coutCovoiturage;

        return $this;
    }

    /**
     * Get coutCovoiturage
     *
     * @return float
     */
    public function getCoutCovoiturage()
    {
        return $this->coutCovoiturage;
    }


    /**
     * Set coutMinibus
     *
     * @param float $coutMinibus
     *
     * @return Scenario
     */
    public function setCoutMinibus($coutMinibus)
    {
        $this->coutMinibus = $coutMinibus;

        return $this;
    }

    /**
     * Get coutMinibus
     *
     * @return float
     */
    public function getCoutMinibus()
    {
        return $this->coutMinibus;
    }




    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Scenario
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
     * @return Scenario
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

    /**
     * Set idRapport
     *
     * @param \Optimouv\FfbbBundle\Entity\Rapport $idRapport
     *
     * @return Scenario
     */
    public function setIdRapport(\Optimouv\FfbbBundle\Entity\Rapport $idRapport = null)
    {
        $this->idRapport = $idRapport;

        return $this;
    }

    /**
     * Get idRapport
     *
     * @return \Optimouv\FfbbBundle\Entity\Rapport
     */
    public function getIdRapport()
    {
        return $this->idRapport;
    }

    /**
     * Set co2Covoiturage
     *
     * @param float $co2Covoiturage
     *
     * @return Scenario
     */
    public function setCo2Covoiturage($co2Covoiturage)
    {
        $this->co2Covoiturage = $co2Covoiturage;

        return $this;
    }

    /**
     * Get co2Covoiturage
     *
     * @return float
     */
    public function getCo2Covoiturage()
    {
        return $this->co2Covoiturage;
    }

    /**
     * Set detailsCalcul
     *
     * @param string $detailsCalcul
     *
     * @return Scenario
     */
    public function setDetailsCalcul($detailsCalcul)
    {
        $this->detailsCalcul = $detailsCalcul;

        return $this;
    }

    /**
     * Get detailsCalcul
     *
     * @return string
     */
    public function getDetailsCalcul()
    {
        return $this->detailsCalcul;
    }
}
