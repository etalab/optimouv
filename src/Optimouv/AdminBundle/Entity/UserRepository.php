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
            ->where('u.id = ?3')
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
            ->where('u.id = ?4')
            ->setParameter(1, true)
            ->setParameter(2, null)
            ->setParameter(3, false)
            ->setParameter(4, $idUser)
            ->getQuery();

        $activation = $query->execute();

        return $activation;
    }

    public function desactivateUserByAdmin($idUser){


        $query = $this->createQueryBuilder('G')
            ->update('AdminBundle:User', 'u')
            ->set('u.enabled' , '?1')
            ->where('u.id = ?2')
            ->setParameter(1, false)
            ->setParameter(2, $idUser)
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
            ->set('u.username' , '?1')
            ->set('u.email' , '?2')
            ->set('u.telephone' , '?4')
            ->set('u.adresse' , '?5')
            ->set('u.numLicencie' , '?6')
            ->set('u.nom' , '?7')
            ->set('u.prenom' , '?8')
            ->where('u.id = ?9')
            ->setParameter(1, $params['login'])
            ->setParameter(2, $params['email'])
            ->setParameter(4, $params['tel'])
            ->setParameter(5, $params['adresse'])
            ->setParameter(6, $params['numLicencie'])
            ->setParameter(7, $params['nom'])
            ->setParameter(8, $params['prenom'])
            ->setParameter(9, $params['id'])
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
   

    
}

