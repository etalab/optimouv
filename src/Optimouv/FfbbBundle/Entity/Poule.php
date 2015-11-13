<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Poule
 *
 * @ORM\Table(name="poule", indexes={@ORM\Index(name="id_scenario_idx", columns={"id_scenario"})})
 * @ORM\Entity(repositoryClass=PouleRepository)
 */
class Poule
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
     * @var string
     *
     * @ORM\Column(name="numero", type="string", length=50, nullable=false)
     */
    private $numero;

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
     * @ORM\Column(name="co2", type="float", precision=10, scale=0, nullable=false)
     */
    private $co2;

    /**
     * @var float
     *
     * @ORM\Column(name="cout", type="float", precision=10, scale=0, nullable=false)
     */
    private $cout;

    /**
     * @var integer
     *
     * @ORM\Column(name="kilometres_moyens", type="integer", nullable=false)
     */
    private $kilometresMoyens;

    /**
     * @var integer
     *
     * @ORM\Column(name="duree_moyenne", type="integer", nullable=false)
     */
    private $dureeMoyenne;

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
     * @var \Optimouv\FfbbBundle\Entity\Scenario
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Scenario")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_scenario", referencedColumnName="id")
     * })
     */
    private $idScenario;



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
     * @return Poule
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
     * Set numero
     *
     * @param string $numero
     *
     * @return Poule
     */
    public function setNumero($numero)
    {
        $this->numero = $numero;

        return $this;
    }

    /**
     * Get numero
     *
     * @return string
     */
    public function getNumero()
    {
        return $this->numero;
    }

    /**
     * Set kilometres
     *
     * @param integer $kilometres
     *
     * @return Poule
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
     * @return Poule
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
     * Set co2
     *
     * @param float $co2
     *
     * @return Poule
     */
    public function setCo2($co2)
    {
        $this->co2 = $co2;

        return $this;
    }

    /**
     * Get co2
     *
     * @return float
     */
    public function getCo2()
    {
        return $this->co2;
    }

    /**
     * Set cout
     *
     * @param float $cout
     *
     * @return Poule
     */
    public function setCout($cout)
    {
        $this->cout = $cout;

        return $this;
    }

    /**
     * Get cout
     *
     * @return float
     */
    public function getCout()
    {
        return $this->cout;
    }

    /**
     * Set kilometresMoyens
     *
     * @param integer $kilometresMoyens
     *
     * @return Poule
     */
    public function setKilometresMoyens($kilometresMoyens)
    {
        $this->kilometresMoyens = $kilometresMoyens;

        return $this;
    }

    /**
     * Get kilometresMoyens
     *
     * @return integer
     */
    public function getKilometresMoyens()
    {
        return $this->kilometresMoyens;
    }

    /**
     * Set dureeMoyenne
     *
     * @param integer $dureeMoyenne
     *
     * @return Poule
     */
    public function setDureeMoyenne($dureeMoyenne)
    {
        $this->dureeMoyenne = $dureeMoyenne;

        return $this;
    }

    /**
     * Get dureeMoyenne
     *
     * @return integer
     */
    public function getDureeMoyenne()
    {
        return $this->dureeMoyenne;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Poule
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
     * @return Poule
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
     * Set idScenario
     *
     * @param \Optimouv\FfbbBundle\Entity\Scenario $idScenario
     *
     * @return Poule
     */
    public function setIdScenario(\Optimouv\FfbbBundle\Entity\Scenario $idScenario = null)
    {
        $this->idScenario = $idScenario;

        return $this;
    }

    /**
     * Get idScenario
     *
     * @return \Optimouv\FfbbBundle\Entity\Scenario
     */
    public function getIdScenario()
    {
        return $this->idScenario;
    }
}
