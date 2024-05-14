<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Service\GenericFunction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\QuoteImageRepository")
 */
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new GetCollection(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class QuoteImage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * #[ApiProperty(identifier: false)]
     */
    protected $id;

    /**
    * @ORM\ManyToOne(targetEntity=Quote::class, inversedBy="images", cascade={"persist"})
    * @Groups({"read", "write"})
    */
    protected $quote;

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
     * @ORM\Column(type="string", length=255, unique=true)
     * @Groups({"read", "write"})
     * #[ApiProperty(identifier: true)]
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

    public function getQuote()
    {
        return $this->quote;
    }

    public function setQuote(Quote $quote = null)
    {
        $this->quote = $quote;
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