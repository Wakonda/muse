<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\PoemImage;

/**
 * PoemImage repository
 */
class PoemImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PoemImage::class);
    }

	public function getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $count = false)
	{
		$qb = $this->createQueryBuilder("ip");

		$aColumns = array( 'pf.title', null, 'pf.id');

		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
	   
	    $qb->join('ip.poem', 'pf');
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->where('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(ip) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}

	public function getPaginator($locale)
	{
		$qb = $this->createQueryBuilder("ip");

		$qb->join('ip.poem', 'pf')
		   ->join('pf.language', 'la')
		   ->where('la.abbreviation = :locale')
		   ->setParameter("locale", $locale)
		   ->orderBy("ip.id", "DESC");

		return $qb->getQuery();
	}
}