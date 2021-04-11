<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\PoemVote;

/**
 * PoemVote repository
 */
class PoemVoteRepository extends VoteRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        ServiceEntityRepository::__construct($registry, PoemVote::class);
    }

	public function checkIfUserAlreadyVote($id, $idUser)
	{
		return parent::checkIfUserAlreadyVote($id, $idUser);
	}

	public function countVoteBy($id, $vote)
	{
		return parent::countVoteBy($id, $vote);
	}

	public function findVoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $count = false)
	{
		return parent::findVoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $count);
	}
}