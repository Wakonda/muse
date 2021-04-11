<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\ProverbComment;

/**
 * ProverbComment repository
 */
class ProverbCommentRepository extends CommentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        ServiceEntityRepository::__construct($registry, ProverbComment::class);
    }

	public function countAllComments($id)
	{
		return parent::countAllComments($id);
	}

	public function displayComments($id, $max_comment_by_page, $first_message_to_display)
	{
		return parent::displayComments($id, $max_comment_by_page, $first_message_to_display);
	}

	public function findCommentByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $count = false)
	{
		return parent::findCommentByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $count);
	}
}