<?php

namespace Optimouv\FfbbBundle\Entity;

/**
 * DisciplineRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ScenarioRepository extends \Doctrine\ORM\EntityRepository
{
    public function getDetailsCalcul($idTache)
    {

        $query = $this->createQueryBuilder('t')
            ->select('t.detailsCalcul')
            ->where('t.idRapport = :id')
            ->setParameter('id', $idTache)
            ->getQuery();
        $result = $query->getResult();

        return $result;

    }

    public function getIdScenarioByIdRapport($idRapport)
    {

        $query = $this->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.idRapport = :idRapport')
            ->setParameter('idRapport', $idRapport)
            ->getQuery();
        $result = $query->getResult();

        return $result;

    }


    public function getIdRapportByIdScenario($idScenario)
    {

        $query = $this->createQueryBuilder('t')
            ->select('IDENTITY (t.idRapport) as idRapport')
            ->where('t.id = :id')
            ->setParameter('id', $idScenario)
            ->getQuery();
        $result = $query->getResult();

        return $result;

    }



}