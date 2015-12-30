<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rapport
 *
 * @ORM\Table(name="rapport")
 * @ORM\Entity(repositoryClass="Optimouv\FfbbBundle\Entity\RapportRepository")
 */
class Rapport
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
     * @ORM\Column(name="nom", type="string", length=100)
     */
    private $nom;

    /**
     * @var \Optimouv\FfbbBundle\Entity\Groupe
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\FfbbBundle\Entity\Groupe")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_groupe", referencedColumnName="id")
     * })
     */
    private $idGroupe;


    /**
     * @var string
     *
     * @ORM\Column(name="type_action", type="string", length=50)
     */
    private $typeAction;

    /**
     * @var integer
     *
     * @ORM\Column(name="valeur_exclusion", type="integer")
     */
    private $valeurExclusion;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_utilisateur", type="integer", nullable=false)
     */
    private $idUtilisateur;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_creation", type="date")
     */
    private $dateCreation;


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
     * @return Rapport
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
     * Set idGroupe
     *
     * @param integer $idGroupe
     *
     * @return Rapport
     */
    public function setIdGroupe($idGroupe)
    {
        $this->idGroupe = $idGroupe;

        return $this;
    }

    /**
     * Get idGroupe
     *
     * @return integer
     */
    public function getIdGroupe()
    {
        return $this->idGroupe;
    }

    /**
     * Set typeAction
     *
     * @param string $typeAction
     *
     * @return Rapport
     */
    public function setTypeAction($typeAction)
    {
        $this->typeAction = $typeAction;

        return $this;
    }

    /**
     * Get typeAction
     *
     * @return string
     */
    public function getTypeAction()
    {
        return $this->typeAction;
    }

    /**
     * Set valeurExclusion
     *
     * @param integer $valeurExclusion
     *
     * @return Rapport
     */
    public function setValeurExclusion($valeurExclusion)
    {
        $this->valeurExclusion = $valeurExclusion;

        return $this;
    }

    /**
     * Get valeurExclusion
     *
     * @return integer
     */
    public function getValeurExclusion()
    {
        return $this->valeurExclusion;
    }

    /**
     * Set idUtilisateur
     *
     * @param integer $idUtilisateur
     *
     * @return Rapport
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
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return Rapport
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
}

