<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Advertising;

/**
 * Advertising repository
 */
class AdvertisingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Advertising::class);
    }
	
	public function getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state = "all", $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.id', 'pf.title', 'la.title', 'pf.id');
		
		$qb->leftjoin("pf.language", "la");
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->where('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}

		if($state == "toComplete")
		{
			$qb->andWhere('pf.text IS NULL OR pf.photo IS NULL');
		}

		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
	public function getOneRandomAdsByWidthAndHeight(int $maxWidth, int $maxHeight) {
		$qb = $this->createQueryBuilder("a");

		$qb->select("COUNT(a.id) AS countRow")->where("a.width <= :maxWidth")
		   ->andWhere("a.height <= :maxHeight")
		   ->setParameter("maxWidth", $maxWidth)
		   ->setParameter("maxHeight", $maxHeight);

		$max = max($qb->getQuery()->getSingleScalarResult() - 1, 0);
		$offset = rand(0, $max);
		
		$qb = $this->createQueryBuilder("a");
		
		$qb->where("a.width <= :maxWidth")
		   ->andWhere("a.height <= :maxHeight")
		   ->setParameter("maxWidth", $maxWidth)
		   ->setParameter("maxHeight", $maxHeight)
		   ->setFirstResult($offset)
		   ->setMaxResults(1);
		   
		return $qb->getQuery()->getOneOrNullResult();
	}
}