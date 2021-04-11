<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\QuoteCommentRepository")
 */
class QuoteComment extends Comment
{
   /**
    * @ORM\ManyToOne(targetEntity="App\Entity\Quote")
	* @ORM\JoinColumn(nullable=false)
	*/
    private $entity;
	
	public function getMainEntityClassName()
	{
		return Quote::class;
	}

    /**
     * Set entity
     *
     * @param App\Entity\Quote $entity
     */
    public function setEntity(Quote $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Get entity
     *
     * @return App\Entity\Quote 
     */
    public function getEntity()
    {
        return $this->entity;
    }
}