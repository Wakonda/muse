<?php

namespace App\Entity;

use App\Service\GenericFunction;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PoemRepository")
 */
class Poem
{
	const FOLDER = "poem";
	const PATH_FILE = "photo/".self::FOLDER."/";

	const PUBLISHED_STATE = 0;
	const DRAFT_STATE = 1;
	const DELETE_STATE = 2;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $text;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $releasedDate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $authorType;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $slug;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\PoeticForm")
     */
    protected $poeticForm;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Biography")
     */
    protected $biography;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     */
    protected $country;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Source")
     */
    protected $collection;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    protected $user;

    /**
     * @ORM\Column(type="integer")
     */
    protected $state;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\FileManagement")
     */
    protected $fileManagement;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     */
	protected $language;

    /**
     * ORM\OneToMany(targetEntity=PoemImage::class, cascade={"persist", "remove"}, mappedBy="poem", orphanRemoval=true)
     */
    protected $images;

   /**
    * ORM\ManyToMany(targetEntity=Tag::class, inversedBy="poems", cascade={"persist"})
    */
	protected $tags;

    public function __construct()
    {
        $this->images = new ArrayCollection();
		$this->state = self::PUBLISHED_STATE;
    }
	
	public function getSiteName(): String
	{
		return "Poéticus";
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
	
	public function isBiography()
	{
		return $this->authorType == "biography";
	}

	public function isUser()
	{
		return $this->authorType == "user";
	}
	
	public function getAuthor()
	{
		if($this->isBiography())
			return $this->biography;
		else
			return $this->user;
	}
	
	public function getShowRoute(): String {
		return "app_indexpoeticus_read";
	}
	
	public function getAbstract(): String {
		return $this->title;
	}

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
		$this->setSlug();
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function setSlug()
    {
		if(empty($this->slug))
			$this->slug = GenericFunction::slugify($this->title);
    }

    public function getText()
    {
        return $this->text;
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function getReleasedDate()
    {
        return $this->releasedDate;
    }

    public function setReleasedDate($releasedDate)
    {
        $this->releasedDate = $releasedDate;
    }

    public function getAuthorType()
    {
        return $this->authorType;
    }

    public function setAuthorType($authorType)
    {
        $this->authorType = $authorType;
    }

    public function getBiography()
    {
        return $this->biography;
    }

    public function setBiography($biography)
    {
        $this->biography = $biography;
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($country)
    {
        $this->country = $country;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function setCollection($collection)
    {
        $this->collection = $collection;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getPoeticForm()
    {
        return $this->poeticForm;
    }

    public function setPoeticForm($poeticForm)
    {
        $this->poeticForm = $poeticForm;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = $state;
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
     
    public function addImage(PoemImage $image)
    {
        $this->images->add($image);
        $image->setPoem($this);
    }
	
    public function removeImage(PoemImage $image)
    {
        $image->setPoem(null);
        $this->images->removeElement($image);
    }
	
   /**
    * Add tags
    *
    * @param Tag $tags
    */
	public function addTag(Tag $tag)
	{
		$this->tags[] = $tag;
	}

    /**
     * Set tags
     *
     * @param string $tags
     */
    public function setTags($tags)
    {
        $this->tags = $tags;
    }

   /**
    * Remove tags
    *
    * @param Tag $tag
    */
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

   /**
    * Get tags
    *
    * @return Doctrine\Common\Collections\Collection
    */
	public function getTags()
	{
		return $this->tags;
	}
	
	public function getFileManagement()
	{
		return $this->fileManagement;
	}
	
	public function setFileManagement($fileManagement)
	{
		$this->fileManagement = $fileManagement;
	}
}