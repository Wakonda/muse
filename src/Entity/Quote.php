<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use App\Service\GenericFunction;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiSubresource;

/**
 * @ORM\Entity(repositoryClass="App\Repository\QuoteRepository")
 */
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
		new Delete(security: "is_granted('ROLE_ADMIN')"),
		new Post(security: "is_granted('ROLE_ADMIN')"),
        new GetCollection(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class Quote
{
	const BIOGRAPHY_AUTHORTYPE = "biography";
	const USER_AUTHORTYPE = "user";

	const PUBLISHED_STATE = 0;
	const DRAFT_STATE = 1;
	const DELETE_STATE = 2;

	const FOLDER = "quote";
	const PATH_FILE = "photo/".self::FOLDER."/";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * #[ApiProperty(identifier: false)]
     */
    protected $id;

    /**
     * @ORM\Column(type="text")
     * @Groups({"read", "write"})
     */
    protected $text;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $slug;

    /**
     * @ORM\Column(type="string", length=500, unique=true)
     * #[ApiProperty(identifier: true)]
     * @Groups({"read", "write"})
     */
    protected $identifier;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     */
    protected $country;

   /**
    * @ORM\OneToMany(targetEntity=QuoteImage::class, cascade={"persist", "remove"}, mappedBy="quote", orphanRemoval=true)
    * @Groups({"write"})
	* #[ApiSubresource(maxDepth: 1)]
    */
    protected $images;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     * @Groups({"read", "write"})
     */
	protected $language;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Biography", cascade={"persist"})
     * @Groups({"read", "write"})
     */
    protected $biography;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Source")
     * @Groups({"read", "write"})
     */
    protected $source;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    protected $user;

    /**
     * @ORM\Column(type="integer")
     */
    protected $state;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"read", "write"})
     */
    protected $authorType;

   /**
    * @ORM\ManyToMany(targetEntity=Tag::class, inversedBy="quotes", cascade={"persist"})
    * @Groups({"read", "write"})
    */
	protected $tags;

	public function isBiographyAuthorType()
	{
		return $this->authorType == self::BIOGRAPHY_AUTHORTYPE;
	}
	
	public function isUserAuthorType()
	{
		return $this->authorType == self::USER_AUTHORTYPE;
	}

	public function getStateString()
	{
		$res = "";
		
		switch($this->state)
		{
			case self::PUBLISHED_STATE:
				$res = "publication.state.Published";
				break;
			case self::DRAFT_STATE:
				$res = "publication.state.Draft";
				break;
			case self::DELETE_STATE:
				$res = "publication.state.Deleted";
				break;
			default:
				$res = "";
		}
		
		return $res;
	}
	
	public function getShowRoute(): String {
		return "app_indexquotus_read";
	}

	public function getStateRealName()
	{
		$res = "";
		
		switch($this->state)
		{
			case self::PUBLISHED_STATE:
				$res = "published";
				break;
			case self::DRAFT_STATE:
				$res = "draft";
				break;
			case self::DELETE_STATE:
				$res = "deleted";
				break;
			default:
				$res = "";
		}
		
		return $res;
	}

    public function __construct()
    {
        $this->quoteImages = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->tags = new ArrayCollection();
		$this->authorType = self::BIOGRAPHY_AUTHORTYPE;
		$this->state = self::PUBLISHED_STATE;
    }
	
	public function getSiteName(): String
	{
		return "Quotus";
	}
	
	public function isBiography()
	{
		return $this->authorType == self::BIOGRAPHY_AUTHORTYPE;
	}

	public function isUser()
	{
		return $this->authorType == self::USER_AUTHORTYPE;
	}
	
	public function getAuthor()
	{
		if($this->isBiography())
			return $this->biography;
		else
			return $this->user;
	}

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setText($text)
    {
        $this->text = $text;
		$this->setSlug();
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function setSlug()
    {
		if(empty($this->slug))
			$this->slug = GenericFunction::slugify($this->text, 30);
    }

	public function getLanguage()
	{
		return $this->language;
	}
	
	public function setLanguage($language)
	{
		$this->language = $language;
	}

	public function getIdentifier()
	{
		return $this->identifier;
	}
	
	public function setIdentifier($identifier)
	{
		$this->identifier = $identifier;
	}

    public function getImages()
    {
        return $this->images;
    }

    public function setImages($images)
    {
        $this->images = $images;
    }
     
    public function addImage(QuoteImage $quoteImage)
    {
        $this->images->add($quoteImage);
        $quoteImage->setQuote($this);
    }
	
    public function removeImage(QuoteImage $quoteImage)
    {
        $quoteImage->setQuote(null);
        $this->images->removeElement($quoteImage);
    }

    public function getBiography()
    {
        return $this->biography;
    }

    public function setBiography($biography)
    {
        $this->biography = $biography;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function setSource($source)
    {
        $this->source = $source;
    }

    public function setState($state)
    {
        $this->state = $state;
    }

    public function getPhoto()
    {
        return $this->photo;
    }

    public function getAuthorType()
    {
        return $this->authorType;
    }

    public function setAuthorType($authorType)
    {
        $this->authorType = $authorType;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

	public function addTag(Tag $tag)
	{
		if (!$this->tags->contains($tag))
		    $this->tags[] = $tag;
	}

    public function setTags($tags)
    {
        $this->tags = $tags;
    }

	public function removeTag(Tag $tag)
	{
		$this->tags->removeElement($tag);
	}
	
	public function isTagExisted(Tag $tag)
	{
		foreach($this->tags as $t)
			if($tag->getId() == $t->getId())
				return true;
		
		return false;
	}

	public function getTags()
	{
		return $this->tags->getValues();
	}
}