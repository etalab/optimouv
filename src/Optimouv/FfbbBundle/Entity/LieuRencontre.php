<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LieuRencontre
 *
 * @ORM\Table(name="lieu_rencontre", indexes={@ORM\Index(name="id_entite_idx", columns={"id_entite"})})
 * @ORM\Entity(repositoryClass="LieuRencontreRepository")
 */
class LieuRencontre
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
     * @var \Optimouv\FfbbBundle\Entity\Entite
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Entite")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_entite", referencedColumnName="id")
     * })
     */
    private $idEntite;



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

    /**
     * Set idEntite
     *
     * @param \Optimouv\FfbbBundle\Entity\Entite $idEntite
     *
     * @return LieuRencontre
     */
    public function setIdEntite(\Optimouv\FfbbBundle\Entity\Entite $idEntite = null)
    {
        $this->idEntite = $idEntite;

        return $this;
    }

    /**
     * Get idEntite
     *
     * @return \Optimouv\FfbbBundle\Entity\Entite
     */
    public function getIdEntite()
    {
        return $this->idEntite;
    }
}
