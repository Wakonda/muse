<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PoemCommentRepository")
 */
class PoemComment extends Comment
{
   /**
    * @ORM\ManyToOne(targetEntity="App\Entity\Poem")
	* @ORM\JoinColumn(nullable=false)
	*/
    private $entity;
	
	public function getMainEntityClassName()
	{
		return Poem::class;
	}

    /**
     * Set entity
     *
     * @param App\Entity\Poem $entity
     */
    public function setEntity(Poem $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Get entity
     *
     * @return App\Entity\Poem 
     */
    public function getEntity()
    {
        return $this->entity;
    }
}