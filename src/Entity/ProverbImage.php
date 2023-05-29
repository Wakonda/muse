<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiSubresource;

use App\Service\GenericFunction;

 /**
 * @ORM\Entity(repositoryClass="App\Repository\ProverbImageRepository")
 * @ApiResource(
 *     itemOperations={
 *          "get"={"security"="is_granted('ROLE_ADMIN')"},
 *          "put"={"security"="is_granted('ROLE_ADMIN')"}
 *      },
 *     collectionOperations={
 *          "get"={"security"="is_granted('ROLE_ADMIN')"},
 *          "post"={"security"="is_granted('ROLE_ADMIN')"}
 *      },
 *     normalizationContext={"groups"={"read"}},
 *     denormalizationContext={"groups"={"write"}}
 * )
 */
class ProverbImage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @ApiProperty(identifier=false)
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity=Proverb::class)
     * @Groups({"read", "write"})
     */
    protected $proverb;

    /**
     * @ORM\Column(type="text", length=255, nullable=true)
     * @Groups({"read", "write"})
     */
    protected $image;

    /**
     * @ORM\Column(type="json", length=255, nullable=true)
     */
    protected $socialNetwork;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"read", "write"})
     * @ApiProperty(identifier=true)
     */
    protected $identifier;

	/**
     * @Groups({"read", "write"})
	 */
	public $imgBase64;

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

    public function getProverb()
    {
        return $this->proverb;
    }

    public function setProverb(Proverb $proverb = null)
    {
        $this->proverb = $proverb;
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

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }
}