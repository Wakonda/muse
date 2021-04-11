<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Service\GenericFunction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PoemImageRepository")
 */
class PoemImage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
    * @ORM\ManyToOne(targetEntity=Poem::class)
    */
    protected $poem;

    /**
     * @ORM\Column(type="text", length=255, nullable=true)
     */
    protected $image;

    /**
     * @ORM\Column(type="json", length=255, nullable=true)
     */
    protected $socialNetwork;

	public function __construct(String $image = null)
	{
		$this->image = $image;
	}

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getPoem()
    {
        return $this->poem;
    }

    public function setPoem(Poem $poem = null)
    {
        $this->poem = $poem;
    }

    public function getSocialNetwork()
    {
        return $this->socialNetwork;
    }
	
	public function addSocialNetwork($socialNetwork)
	{
		if(!is_array($this->socialNetwork))
			$this->socialNetwork = [];
		
		if(!in_array($socialNetwork, $this->socialNetwork))
			$this->socialNetwork[] = $socialNetwork;
	}

    public function setSocialNetwork($socialNetwork)
    {
        $this->socialNetwork = $socialNetwork;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setImage($image)
    {
        $this->image = $image;
    }
}