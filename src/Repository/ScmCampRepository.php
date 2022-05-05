<?php

namespace App\Repository;

use App\Entity\ScmCamp;
use App\Entity\Campaings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ScmCamp|null find($id, $lockMode = null, $lockVersion = null)
 * @method ScmCamp|null findOneBy(array $criteria, array $orderBy = null)
 * @method ScmCamp[]    findAll()
 * @method ScmCamp[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScmCampRepository extends ServiceEntityRepository
{

  public $result = ['abort' => false, 'msg' => 'ok', 'body' => ''];

  public function __construct(ManagerRegistry $registry)
  {
      parent::__construct($registry, ScmCamp::class);
  }

  /**
   * @throws ORMException
   * @throws OptimisticLockException
   */
  public function add(ScmCamp $entity, bool $flush = true): void
  {
      $this->_em->persist($entity);
      if ($flush) {
          $this->_em->flush();
      }
  }

  /**
   * @throws ORMException
   * @throws OptimisticLockException
   */
  public function remove(ScmCamp $entity, bool $flush = true): void
  {
      $this->_em->remove($entity);
      if ($flush) {
          $this->_em->flush();
      }
  }

  /**
   * Guardamos una orden que va a ser tomada por la scm para la busqueda de cotizaciones
   */
  public function setNewCampaing(array $data): array
  {
      $obj = new ScmCamp();
      $obj->setCampaing($this->_em->getPartialReference(Campaings::class, $data['camp']));
      $obj->setTarget($data['target']);
      $obj->setSrc($data['src']);
      $obj->getRemiter($this->_em->getPartialReference(NG2Contactos::class, $data['own']));
      $obj->setEmiter($this->_em->getPartialReference(NG2Contactos::class, $data['avo']));
      $obj->setSendAt($data['sendAt']);
      
      try {
          $this->_em->persist($obj);
          $this->_em->flush();
      } catch (\Throwable $th) {
          $this->result['abort'] = true;
          $this->result['msg'] = $th->getMessage();
          $this->result['body'] = 'ERROR al Guardar la nueva campaña.';
      }
      return $this->result;
  }

  /**
  * Recuperamos las campañas indicadas por parametro
  */
  public function getCampaingsOfTargetByIds(array $ids): \Doctrine\ORM\Query
  {
    $txt = 'partial %s.{id, curc, roles, nombre, cargo, celular, isCot}';
    $emi = sprintf($txt, 'emi');
    $rem = sprintf($txt, 'rem');
    $emp = 'partial emp.{id, nombre, domicilio, cp, telFijo, isLocal, latLng}';
    $dql = 'SELECT c, '.$emi.', '.$rem.', '.$emp.' FROM '.ScmCamp::class . ' c '.
    'JOIN c.emiter rem '.
    'JOIN c.remiter emi '.
    'JOIN emi.empresa emp '.
    'WHERE c.id IN (:ids) '.
    'ORDER BY c.id ASC';

    return $this->_em->createQuery($dql)->setParameter('ids', $ids);
  }
}
