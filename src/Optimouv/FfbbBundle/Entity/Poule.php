<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Poule
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Optimouv\FfbbBundle\Entity\PouleRepository")
 */
class Poule
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
     * @ORM\Column(name="id_scenario", type="integer")
     * @ORMManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Scenario", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $idScenario;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=50)
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="numero", type="string", length=50)
     */
    private $numero;

    /**
     * @var integer
     *
     * @ORM\Column(name="kilometres", type="integer")
     */
    private $kilometres;

    /**
     * @var integer
     *
     * @ORM\Column(name="duree", type="integer")
     */
    private $duree;

    /**
     * @var float
     *
     * @ORM\Column(name="co2", type="float")
     */
    private $co2;

    /**
     * @var float
     *
     * @ORM\Column(name="cout", type="float")
     */
    private $cout;

    /**
     * @var integer
     *
     * @ORM\Column(name="kilometres_moyens", type="integer")
     */
    private $kilometresMoyens;

    /**
     * @var integer
     *
     * @ORM\Column(name="duree_moyenne", type="integer")
     */
    private $dureeMoyenne;

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
     * Set idScenario
     *
     * @param integer $idScenario
     *
     * @return Poule
     */
    public function setIdScenario($idScenario)
    {
        $this->idScenario = $idScenario;

        return $this;
    }

    /**
     * Get idScenario
     *
     * @return integer
     */
    public function getIdScenario()
    {
        return $this->idScenario;
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
}

