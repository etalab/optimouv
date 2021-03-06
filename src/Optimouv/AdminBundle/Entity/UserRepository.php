<?php

namespace Optimouv\AdminBundle\Entity;
use PDO;
/**
 * UserRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UserRepository extends \Doctrine\ORM\EntityRepository
{
    public function activateUser($idUser){
        

        $query = $this->createQueryBuilder('G')
            ->update('AdminBundle:User', 'u')
            ->set('u.enabled' , '?1')
            ->set('u.locked' , '?2')
            ->where('u.confirmationToken = ?3')
            ->setParameter(1, true)
            ->setParameter(2, false)
            ->setParameter(3, $idUser)
            ->getQuery();

        $activation = $query->execute();

        return $activation;
    }
    public function activateUserByAdmin($idUser){

        
        $query = $this->createQueryBuilder('G')
            ->update('AdminBundle:User', 'u')
            ->set('u.enabled' , '?1')
            ->set('u.expiresAt' , '?2')
            ->set('u.locked' , '?3')
            ->set('u.expired' , '?4')
            ->where('u.id = ?5')
            ->setParameter(1, true)
            ->setParameter(2, null)
            ->setParameter(3, false)
            ->setParameter(4, false)
            ->setParameter(5, $idUser)
            ->getQuery();

        $activation = $query->execute();

        return $activation;
    }

    public function desactivateUserByAdmin($idUser){


        $query = $this->createQueryBuilder('G')
            ->update('AdminBundle:User', 'u')
            ->set('u.enabled' , '?1')
            ->set('u.expired' , '?2')
            ->where('u.id = ?3')
            ->setParameter(1, false)
            ->setParameter(2, true)
            ->setParameter(3, $idUser)
            ->getQuery();

        $activation = $query->execute();

        return $activation;
    }
    

    public function getListUsers()
    {
        $query = $this->createQueryBuilder('f')
            ->join('f.discipline', 'd')
            ->getQuery();
        $result = $query->getResult();
        return $result;

    }
    public function getListUsersByDiscipline($idDiscipline)
    {
        $query = $this->createQueryBuilder('f')
            ->join('f.discipline', 'd')
            ->where('f.discipline= ?1')
            ->setParameter(1, $idDiscipline)
            ->getQuery();
        $result = $query->getResult();

        return $result;

    }

    public function updateUser($params)
    {

        $query = $this->createQueryBuilder('G')
            ->update('AdminBundle:User', 'u')
            ->set('u.civilite' , '?1')
            ->set('u.nom' , '?2')
            ->set('u.prenom' , '?3')
            ->set('u.fonction' , '?4')
            ->set('u.niveauUtilisateur' , '?5')
            ->set('u.discipline' , '?6')
            ->set('u.roles' , '?7')
            ->set('u.telephone' , '?8')
            ->set('u.adresse' , '?9')
            ->set('u.numLicencie' , '?10')
            ->where('u.id = ?11')
            ->setParameter(1, $params['civilite'])
            ->setParameter(2, $params['nom'])
            ->setParameter(3, $params['prenom'])
            ->setParameter(4, $params['fonction'])
            ->setParameter(5, $params['niveauUtilisateur'])
            ->setParameter(6, $params['discipline'])
            ->setParameter(7, $params['profil'])
            ->setParameter(8, $params['tel'])
            ->setParameter(9, $params['adresse'])
            ->setParameter(10, $params['numLicencie'])
            ->setParameter(11, $params['id'])
            ->getQuery();

        $update = $query->execute();

        return $update;
        
    }

    public function getActiveOtherUsers($timeLimit, $userId)
    {
        // Comme vous le voyez, le délais est redondant ici, l'idéale serait de le rendre configurable via votre bundle
        $delay = new \DateTime();
        $delay->setTimestamp(strtotime($timeLimit.' minutes ago'));

        $qb = $this->createQueryBuilder('u')
            ->where('u.lastActivity > :delay')
            ->setParameter('delay', $delay)
            ->andWhere('u.id != :userId')
            ->setParameter('userId', $userId)
        ;

        return $qb->getQuery()->getResult();
    }

    public function insertToken($idUser, $tokenGenerator)
    {
        $query = $this->createQueryBuilder('G')
            ->update('AdminBundle:User', 'u')
            ->set('u.confirmationToken' , '?1')
            ->where('u.id = ?2')
            ->setParameter(1, $tokenGenerator)
            ->setParameter(2, $idUser)
            ->getQuery();

        $update = $query->execute();
        return $update;
    }

    public function updatePwd($idUser, $password)
    {
        $query = $this->createQueryBuilder('G')
            ->update('AdminBundle:User', 'u')
            ->set('u.password' , '?1')
            ->where('u.id = ?2')
            ->setParameter(1, $password)
            ->setParameter(2, $idUser)
            ->getQuery();

        $update = $query->execute();
        return $update;
    }
    
}

