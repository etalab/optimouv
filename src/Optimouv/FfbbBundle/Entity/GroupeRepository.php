<?php

namespace Optimouv\FfbbBundle\Entity;

/**
 * DisciplineRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class GroupeRepository extends \Doctrine\ORM\EntityRepository
{
    public function ajoutEntiteGroupe($idGroupe, $idEntite)
    {
        $query = $this->createQueryBuilder('G')
                ->update('FfbbBundle:Groupe', 'u')
                ->set('u.equipes', '?1')
                ->where('u.id = ?2')
                ->setParameter(1, $idEntite)
                ->setParameter(2, $idGroupe)
                ->getQuery();

        $result = $query->execute();

        return $result;

    }

    public function getGroupList($idListe){
        $query = $this->createQueryBuilder('e')
            ->select('e')
            ->where('e.idListeParticipant= :id')
            ->setParameter('id', $idListe)
            ->orderBy('e.id', 'DESC')
            ->getQuery();

        $result = $query->getResult();

        return $result;
    }

    public function getGroupesParIdUtilisateur($idUtilisateur){
        $query = $this->createQueryBuilder('g')
            ->select('g.id')
            ->where('g.idUtilisateur= :idUtilisateur')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->orderBy('g.id', 'DESC')
            ->getQuery();
        $result = $query->getResult();

        return $result;
    }

    public function getGroupesParIdListeParticipants($idListeParticipants){
        $query = $this->createQueryBuilder('g')
            ->select('g.id')
            ->where('g.idListeParticipant= :idListeParticipant')
            ->setParameter('idListeParticipant', $idListeParticipants)
            ->orderBy('g.id', 'DESC')
            ->getQuery();


        $result = $query->getResult();

        return $result;
    }


    public function getGroupesParIdListeLIeux($idListeLieux){
        $query = $this->createQueryBuilder('g')
            ->select('g.id')
            ->where('g.idListeLieux= :idListeLieux')
            ->setParameter('idListeLieux', $idListeLieux)
            ->orderBy('g.id', 'DESC')
            ->getQuery();

        $result = $query->getResult();

        return $result;
    }


    public function deleteGroupes($ids)
    {
        $query = $this->createQueryBuilder('e')
            ->delete()
            ->where('e.id IN (:id)')
            ->setParameter('id', array_values($ids))
            ->getQuery();

        $result = $query->getResult();


        return $result;

    }

    public function getIdListe($idGroupe)
    {

        $query = $this->createQueryBuilder('t')
            ->select('t.idListeParticipant')
            ->where('t.id = :id')
            ->setParameter('id', $idGroupe)
            ->getQuery();
        $result = $query->getResult();

        $result = $result[0]['idListeParticipant'];

        return $result;

    }

    public function getNomGroupe($idGroupe)
    {

        $query = $this->createQueryBuilder('t')
            ->select('t.nom')
            ->where('t.id = :id')
            ->setParameter('id', $idGroupe)
            ->getQuery();
        $result = $query->getResult();

        $result = $result[0]['nom'];

        return $result;

    }


}