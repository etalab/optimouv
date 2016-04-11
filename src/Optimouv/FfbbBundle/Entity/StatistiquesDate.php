<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StatistiquesDate
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Optimouv\FfbbBundle\Entity\StatistiquesDateRepository")
 */
class StatistiquesDate
{

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_creation", type="date")
     * @ORM\Id
     */
    private $dateCreation;

    /**
     * @var \Optimouv\AdminBundle\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\AdminBundle\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_utilisateur", referencedColumnName="id")
     * })
     * @ORM\Id
     */
    private $idUtilisateur;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_discipline", type="smallint")
     */
    private $idDiscipline;

    /**
     * @var integer
     *
     * @ORM\Column(name="id_federation", type="smallint")
     */
    private $idFederation;

    /**
     * @var string
     *
     * @ORM\Column(name="type_statistiques", type="string", length=50)
     * @ORM\Id
     */
    private $typeStatistiques;

    /**
     * @var integer
     *
     * @ORM\Column(name="valeur", type="integer")
     */
    private $valeur;



    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return StatistiquesDate
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
     * Set idUtilisateur
     *
     * @param integer $idUtilisateur
     *
     * @return StatistiquesDate
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
     * Set idDiscipline
     *
     * @param integer $idDiscipline
     *
     * @return StatistiquesDate
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
     * Set idFederation
     *
     * @param integer $idFederation
     *
     * @return StatistiquesDate
     */
    public function setIdFederation($idFederation)
    {
        $this->idFederation = $idFederation;

        return $this;
    }

    /**
     * Get idFederation
     *
     * @return integer
     */
    public function getIdFederation()
    {
        return $this->idFederation;
    }

    /**
     * Set typeStatistiques
     *
     * @param string $typeStatistiques
     *
     * @return StatistiquesDate
     */
    public function setTypeStatistiques($typeStatistiques)
    {
        $this->typeStatistiques = $typeStatistiques;

        return $this;
    }

    /**
     * Get typeStatistiques
     *
     * @return string
     */
    public function getTypeStatistiques()
    {
        return $this->typeStatistiques;
    }

    /**
     * Set valeur
     *
     * @param integer $valeur
     *
     * @return StatistiquesDate
     */
    public function setValeur($valeur)
    {
        $this->valeur = $valeur;

        return $this;
    }

    /**
     * Get valeur
     *
     * @return integer
     */
    public function getValeur()
    {
        return $this->valeur;
    }
}

