<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use App\Service\GenericFunction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProverbRepository")
 */
class Proverb
{
	const FOLDER = "proverb";
	const PATH_FILE = "photo/".self::FOLDER."/";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="text")
     */
    protected $text;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $slug;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     */
    protected $country;
	
   /**
    * ORM\OneToMany(targetEntity=ProverbImage::class, cascade={"persist", "remove"}, mappedBy="proverb", orphanRemoval=true)
    */
    protected $images;

   /**
    * @ORM\ManyToMany(targetEntity=Tag::class, inversedBy="proverbs", cascade={"persist"})
    */
	protected $tags;
	
    public function __construct()
    {
        $this->images = new ArrayCollection();
    }
	
	public function getSiteName(): String
	{
		return "Proverbius";
	}
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     */
	protected $language;

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

    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($country)
    {
        $this->country = $country;
    }

    public function getImages()
    {
        return $this->images;
    }

    public function setImages($images)
    {
        $this->images = $images;
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

    public function getProverbImages()
    {
        return $this->proverbImages;
    }
     
    public function addImage(ProverbImage $image)
    {
        $this->images->add($image);
        $image->setProverb($this);
    }
	
    public function removeImage(ProverbImage $image)
    {
        $image->setProverb(null);
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
}