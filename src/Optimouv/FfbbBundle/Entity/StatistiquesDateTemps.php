<?php

namespace Optimouv\FfbbBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * StatistiquesDateTemps
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Optimouv\FfbbBundle\Entity\StatistiquesDateTempsRepository")
 */
class StatistiquesDateTemps
{

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="temps_debut", type="datetime")
     * @ORM\Id
     */
    private $tempsDebut;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="temps_fin", type="datetime")
     * @ORM\Id
     */
    private $tempsFin;

    /**
     * @var \Optimouv\AdminBundle\Entity\User
     *
     * @ORM\ManyToOne(targetEntity="Optimouv\AdminBundle\Entity\User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="id_utilisateur", referencedColumnName="id")
     * })
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
     * Set tempsDebut
     *
     * @param \DateTime $tempsDebut
     *
     * @return StatistiquesDateTemps
     */
    public function setTempsDebut($tempsDebut)
    {
        $this->tempsDebut = $tempsDebut;

        return $this;
    }

    /**
     * Get tempsDebut
     *
     * @return \DateTime
     */
    public function getTempsDebut()
    {
        return $this->tempsDebut;
    }

    /**
     * Set tempsFin
     *
     * @param \DateTime $tempsFin
     *
     * @return StatistiquesDateTemps
     */
    public function setTempsFin($tempsFin)
    {
        $this->tempsFin = $tempsFin;

        return $this;
    }

    /**
     * Get tempsFin
     *
     * @return \DateTime
     */
    public function getTempsFin()
    {
        return $this->tempsFin;
    }

    /**
     * Set idUtilisateur
     *
     * @param integer $idUtilisateur
     *
     * @return StatistiquesDateTemps
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
     * @return StatistiquesDateTemps
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
     * @return StatistiquesDateTemps
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
     * @return StatistiquesDateTemps
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
     * @return StatistiquesDateTemps
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

