<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LieuRencontre
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Optimouv\FfbbBundle\Entity\LieuRencontreRepository")
 */
class LieuRencontre
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
     * @ORM\Column(name="id_entite", type="integer")
     * @ORMManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Entite", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $idEntite;

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
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set idEntite
     *
     * @param integer $idEntite
     *
     * @return LieuRencontre
     */
    public function setIdEntite($idEntite)
    {
        $this->idEntite = $idEntite;

        return $this;
    }

    /**
     * Get idEntite
     *
     * @return integer
     */
    public function getIdEntite()
    {
        return $this->idEntite;
    }

    /**
     * Set kilometres
     *
     * @param integer $kilometres
     *
     * @return LieuRencontre
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
     * @return LieuRencontre
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
     * @return LieuRencontre
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
     * @return LieuRencontre
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
}

