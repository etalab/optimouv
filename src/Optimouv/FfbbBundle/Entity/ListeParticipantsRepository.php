<?php

namespace Optimouv\FfbbBundle\Entity;
use Doctrine\ORM\EntityRepository;

/**
 * ListeParticipantsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ListeParticipantsRepository extends EntityRepository
{
    public function getListes(){

        $query = $this->createQueryBuilder('l')
            ->select('l.id, l.nom')
            ->getQuery();

        $result = $query->getResult();

        return $result;

    }

    public function getEquipesPourListe($idListe){
        $query = $this->createQueryBuilder('l')
            ->select('l.equipes')
            ->where('l.id= :id')
            ->setParameter('id', $idListe)
            ->getQuery();

        $result = $query->getResult();

        return $result;
    }

}
