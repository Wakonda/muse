<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Vote;

/**
 * Vote repository
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

	public function checkIfUserAlreadyVote($id, $idUser)
	{
		$qb = $this->createQueryBuilder("vo");
		
		$qb->select("COUNT(vo)")
		   ->leftjoin("vo.user", "bp")
		   ->leftjoin("vo.entity", "pf")
		   ->where("bp.id = :bpId")
		   ->andWhere("pf.id = :pfId")
		   ->setParameter("bpId", $idUser)
		   ->setParameter("pfId", $id);

		return $qb->getQuery()->getSingleScalarResult();
	}
	
	public function countVoteBy($id, $vote)
	{
		$qb = $this->createQueryBuilder("vo");
		
		$qb->select("COUNT(vo)")
		   ->leftjoin("vo.entity", "pf")
		   ->where("vo.vote = :vote")
		   ->andWhere("pf.id = :pfId")
		   ->setParameter("vote", $vote)
		   ->setParameter("pfId", $id);

		return $qb->getQuery()->getSingleScalarResult();
	}

	public function findVoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $count = false)
	{
		$qb = $this->createQueryBuilder("vo");

		$aColumns = array('pf.text', 'vo.vote');
		
		$qb->leftjoin("vo.user", "bp")
		   ->leftjoin("vo.entity", "pf")
		   ->where("bp.username = :username")
		   ->setParameter("username", $username);
		   
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andhere('pf.text LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(vo) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);
// dump($qb->getQuery()->getResult());die;
		return $qb->getQuery()->getResult();
	}
}