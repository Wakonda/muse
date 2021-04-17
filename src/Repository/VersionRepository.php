<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Version;

/**
 * Version repository
 */
class VersionRepository extends ServiceEntityRepository implements iRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Version::class);
    }

	public function getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $count = false)
	{
		$qb = $this->createQueryBuilder("v");

		$aColumns = array('v.id', 'v.versionNumber', 'v.releaseDate', 'la.title', 'v.id');
		
		$qb->leftjoin("v.language", "la");
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->where('v.versionNumber LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(v) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
	public function getCurrentVersion()
	{
		$qb = $this->createQueryBuilder("v");
		
		$qb->select("v.versionNumber AS version")
		   ->orderBy("v.id", "DESC")
		   ->setMaxResults(1);
		
		$res = $qb->getQuery()->getOneOrNullResult();
		
		return (empty($res)) ? "1" : $res["version"];
	}
	
	public function checkForDoubloon($entity)
	{
		$qb = $this->createQueryBuilder("v");

		$qb->select("COUNT(v) AS number")
		   ->leftjoin("v.language", "la")
		   ->where("v.versionNumber = :versionNumber")
		   ->setParameter('versionNumber', $entity->getVersionNumber())
		   ->andWhere("la.id = :idLanguage")
		   ->setParameter("idLanguage", $entity->getLanguage());

		if($entity->getId() != null)
		{
			$qb->andWhere("v.id != :id")
			   ->setParameter("id", $entity->getId());
		}

		return $qb->getQuery()->getSingleScalarResult();
	}
}