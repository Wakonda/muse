<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\PoeticForm;

/**
 * PoeticForm repository
 */
class PoeticFormRepository extends ServiceEntityRepository implements iRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PoeticForm::class);
    }

	public function getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $count = false)
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
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
	public function findAllForChoice($locale)
	{
		$qb = $this->createQueryBuilder("pf");
		
		$qb->leftjoin("pf.language", "la")
		   ->where('la.abbreviation = :locale')
		   ->setParameter('locale', $locale)
		   ->orderBy("pf.title", "ASC");

		return $qb;
	}

	public function findAllByLanguage($locale)
	{
		$qb = $this->createQueryBuilder("pf");

		$qb->leftjoin("pf.language", "la")
		   ->where("la.id = :id")
		   ->setParameter("id", $locale);

		return $qb->getQuery()->getResult();
	}

	public function checkForDoubloon($entity)
	{
		$qb = $this->createQueryBuilder("pf");

		$qb->select("COUNT(pf) AS number")
		   ->leftjoin("pf.language", "la")
		   ->where("pf.slug = :slug")
		   ->setParameter('slug', $entity->getSlug())
		   ->andWhere("la.id = :idLanguage")
		   ->setParameter("idLanguage", $entity->getLanguage());

		if($entity->getId() != null)
		{
			$qb->andWhere("pf.id != :id")
			   ->setParameter("id", $entity->getId());
		}

		return $qb->getQuery()->getSingleScalarResult();
	}
}