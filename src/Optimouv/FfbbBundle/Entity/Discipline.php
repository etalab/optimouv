<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Discipline
 *
 * @ORM\Table(name="discipline")
 * @ORM\Entity(repositoryClass="DisciplineRepository")
 */

class Discipline
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
     * @var \Optimouv\FfbbBundle\Entity\Federation
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Federation", inversedBy="disciplines")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_federation", referencedColumnName="id")
     * })
     */
    private $federation;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_creation", type="date", nullable=false)
     */
    private $dateCreation;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_modification", type="date", nullable=true)
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
     * Set nom
     *
     * @param string $nom
     *
     * @return Discipline
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
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Discipline
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
     * @return Discipline
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
     * Set federation
     *
     * @param \Optimouv\FfbbBundle\Entity\Federation $federation
     *
     * @return Discipline
     */
    public function setFederation(\Optimouv\FfbbBundle\Entity\Federation $federation = null)
    {
        $this->federation = $federation;

        return $this;
    }

    /**
     * Get federation
     *
     * @return \Optimouv\FfbbBundle\Entity\Federation
     */
    public function getFederation()
    {
        return $this->federation;
    }
}
