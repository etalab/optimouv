<?php

namespace Optimouv\FfbbBundle\Entity;

/**
 * DisciplineRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class DisciplineRepository extends \Doctrine\ORM\EntityRepository
{
    public function updateDisc($nom, $id){

        $query = $this->createQueryBuilder('G')
            ->update('FfbbBundle:Discipline', 'd')
            ->set('d.nom' , '?1')
            ->where('d.id = ?2')
            ->setParameter(1, $nom)
            ->setParameter(2, $id)
            ->getQuery();

        $update = $query->execute();

        return $update;
    }
    


}