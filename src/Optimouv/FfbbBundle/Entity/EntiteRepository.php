<?php

namespace Optimouv\FfbbBundle\Entity;

/**
 * DisciplineRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class EntiteRepository extends \Doctrine\ORM\EntityRepository
{

    public function getDetailsPourEntite($idEntite){
        $query = $this->createQueryBuilder('e')
            ->select('e.codePostal, e.ville')
            ->where('e.id= :id')
            ->setParameter('id', $idEntite)
            ->getQuery();

        $result = $query->getResult();

        return $result;
    }

    public function getEntities($participants)
    {

        $query = $this->createQueryBuilder('e')
            ->select('e.id, e.codePostal, e.ville', 'e.nom')
            ->where('e.id IN (:id)')
            ->setParameter('id', array_values($participants))
            ->orderBy('e.id','DESC')
            ->getQuery();

        $result = $query->getResult();


        return $result;

    }


}