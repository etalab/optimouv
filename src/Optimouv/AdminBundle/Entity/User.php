<?php

/**
 * Created by PhpStorm.
 * User: IT4PME
 * Date: 21/12/2015
 * Time: 11:23
 */
namespace Optimouv\AdminBundle\Entity;

use FOS\UserBundle\Entity\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Optimouv\AdminBundle\Entity\UserRepository")
 * @ORM\Table(name="fos_user")
 */

class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @var string
     *
     * @ORM\Column(name="civilite", type="string", length=50, nullable=true)
     */
    private $civilite;

    /**
     * @var string
     *
     * @ORM\Column(name="fonction", type="string", length=50, nullable=true)
     */
    private $fonction;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=50, nullable=true)
     */
    private $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="prenom", type="string", length=50, nullable=true)
     */
    private $prenom;

    /**
     * @var string
     *
     * @ORM\Column(name="adresse", type="string", length=150, nullable=true)
     */
    private $adresse;

    /**
     * @var string
     *
     * @ORM\Column(name="num_licencie", type="string", length=150, nullable=true)
     */
    private $numLicencie;

    /**
     * @var integer
     *
     * @ORM\Column(name="telephone", type="integer", nullable=true)
     */
    private $telephone;

    /**
     * @var \Optimouv\FfbbBundle\Entity\Discipline
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Discipline")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_discipline", referencedColumnName="id")
     * })
     */
    public $discipline;

    /**
     * @var string
     *
     * @ORM\Column(name="federation", type="string", length=50, nullable=true)
     */
    private $federation;



    public function __construct()
    {
        parent::__construct();
        // your own logic
    }

    /**
     * Set civilite
     *
     * @param string $civilite
     *
     * @return User
     */
    public function setCivilite($civilite)
    {
        $this->civilite = $civilite;

        return $this;
    }

    /**
     * Get civilite
     *
     * @return string
     */
    public function getCivilite()
    {
        return $this->civilite;
    }

    /**
     * Set fonction
     *
     * @param string $fonction
     *
     * @return User
     */
    public function setFonction($fonction)
    {
        $this->fonction = $fonction;

        return $this;
    }

    /**
     * Get fonction
     *
     * @return string
     */
    public function getFonction()
    {
        return $this->fonction;
    }

    /**
     * Set adresse
     *
     * @param string $adresse
     *
     * @return User
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
     * Set numLicencie
     *
     * @param string $numLicencie
     *
     * @return User
     */
    public function setNumLicencie($numLicencie)
    {
        $this->numLicencie = $numLicencie;

        return $this;
    }

    /**
     * Get numLicencie
     *
     * @return string
     */
    public function getNumLicencie()
    {
        return $this->numLicencie;
    }

    /**
     * Set telephone
     *
     * @param integer $telephone
     *
     * @return User
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * Get telephone
     *
     * @return integer
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * Set federation
     *
     * @param integer $federation
     *
     * @return User
     */
    public function setFederation($federation)
    {
        $this->federation = $federation;

        return $this;
    }

    /**
     * Get federation
     *
     * @return integer
     */
    public function getFederation()
    {
        return $this->federation;
    }

    /**
     * Set discipline
     *
     * @param \Optimouv\FfbbBundle\Entity\Discipline $discipline
     *
     * @return User
     */
    public function setDiscipline(\Optimouv\FfbbBundle\Entity\Discipline $discipline = null)
    {
        $this->discipline = $discipline;

        return $this;
    }

    /**
     * Get discipline
     *
     * @return \Optimouv\FfbbBundle\Entity\Discipline
     */
    public function getDiscipline()
    {
        return $this->discipline;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return User
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
     * @return User
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
     * Get expiresAt
     *
     * @return \DateTime
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }
}
