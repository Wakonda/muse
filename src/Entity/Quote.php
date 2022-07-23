<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Service\GenericFunction;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\QuoteRepository")
 * @ApiResource(
 *     itemOperations={
 *          "get"={"security"="is_granted('ROLE_ADMIN')"},
 *          "put"={"security"="is_granted('ROLE_ADMIN')"},
 *          "delete"={"security"="is_granted('ROLE_ADMIN')"}
 *      },
 *     collectionOperations={
 *          "get"={"security"="is_granted('ROLE_ADMIN')"},
 *          "post"={"security"="is_granted('ROLE_ADMIN')"}
 *      },
 *     normalizationContext={"groups"={"read"}},
 *     denormalizationContext={"groups"={"write"}}
 * )
 */
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     * @Groups({"read", "write"})
     */
    protected $country;

   /**
    * @ORM\OneToMany(targetEntity=QuoteImage::class, cascade={"persist", "remove"}, mappedBy="quote", orphanRemoval=true)
    */
    protected $images;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     * @Groups({"read", "write"})
     */
	protected $language;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Biography")
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