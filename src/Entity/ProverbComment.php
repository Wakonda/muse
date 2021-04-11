<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProverbCommentRepository")
 */
class ProverbComment extends Comment
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