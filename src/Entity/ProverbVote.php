<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProverbVoteRepository")
 */
class ProverbVote extends Vote
{
   /**
    * @ORM\ManyToOne(targetEntity="App\Entity\Proverb")
	* @ORM\JoinColumn(nullable=false)
	*/
    private $entity;
	
	public function getMainEntityClassName()
	{
		return Proverb::class;
	}

    /**
     * Set entity
     *
     * @param App\Entity\Proverb $entity
     */
    public function setEntity(Proverb $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Get entity
     *
     * @return App\Entity\Proverb 
     */
    public function getEntity()
    {
        return $this->entity;
    }
}