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
    public function getListes($rencontre)
    {

        if ($rencontre == 0) {
            $query = $this->createQueryBuilder('l')
                ->select('l.id, l.nom, l.dateCreation')
                ->where('l.rencontre = ?1')
                ->orderBy('l.id', 'desc')
                ->setParameter(1, $rencontre)
                ->getQuery();

            $result = $query->getResult();
        } else {

            $query = $this->createQueryBuilder('l')
                ->select('l.id, l.nom, l.dateCreation')
                ->where('l.rencontre != ?1')
                ->orderBy('l.id', 'desc')
                ->setParameter(1, $rencontre)
                ->getQuery();

            $result = $query->getResult();
        }


        return $result;

    }

    public function getEquipesPourListe($idListe)
    {
        $query = $this->createQueryBuilder('l')
            ->select('l.equipes')
            ->where('l.id= :id')
            ->setParameter('id', $idListe)
            ->getQuery();

        $result = $query->getResult();

        return $result;
    }

    public function ajoutEntiteListe($idListeParticipant, $ajoutEntiteListe)
    {
        $query = $this->createQueryBuilder('G')
            ->update('FfbbBundle:ListeParticipants', 'u')
            ->set('u.equipes', '?1')
            ->where('u.id = ?2')
            ->setParameter(1, $ajoutEntiteListe)
            ->setParameter(2, $idListeParticipant)
            ->getQuery();

        $result = $query->execute();

        return $result;

    }

    public function getListesEquipes($idUtilisateur, $rencontre)
    {

        $query = $this->createQueryBuilder('e')
            ->select('e.id, e.nom, e.dateCreation')
            ->where('e.idUtilisateur = ?1')
            ->andWhere('e.rencontre = ?2')
            ->setParameter(1, $idUtilisateur)
            ->setParameter(2, $rencontre)
            ->orderBy('e.id', 'desc')
            ->getQuery();

        $result = $query->getResult();

        return $result;

    }

    public function getListesParticipants($idUtilisateur)
    {

        $query = $this->createQueryBuilder('e')
            ->select('e.id, e.nom, e.dateCreation')
            ->where('e.idUtilisateur = ?1')
            ->andWhere('e.rencontre is NULL')
            ->setParameter(1, $idUtilisateur)
            ->orderBy('e.id', 'desc')
            ->getQuery();

        $result = $query->getResult();


        return $result;

    }
}
