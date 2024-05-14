<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiSubresource;

use App\Service\GenericFunction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProverbRepository")
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
class Proverb
{
	const FOLDER = "proverb";
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     * @Groups({"read", "write"})
     */
    protected $country;
	
   /**
    * @ORM\OneToMany(targetEntity=ProverbImage::class, cascade={"persist", "remove"}, mappedBy="proverb", orphanRemoval=true)
    * @Groups({"write"})
	* #[ApiSubresource(maxDepth: 1)]
    */
    protected $images;

   /**
    * @ORM\ManyToMany(targetEntity=Tag::class, inversedBy="proverbs", cascade={"persist"})
    * @Groups({"read", "write"})
    */
	protected $tags;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     * @Groups({"read", "write"})
     */
	protected $language;

    /**
     * @ORM\Column(type="string", length=500, unique=true, nullable=true)
     * #[ApiProperty(identifier: true)]
     * @Groups({"read", "write"})
     */
    protected $identifier;
	
    public function __construct()
    {
        $this->images = new ArrayCollection();
    }
	
	public function getSiteName(): String
	{
		return "Proverbius";
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

	public function getIdentifier()
	{
		return $this->identifier;
	}
	
	public function setIdentifier($identifier)
	{
		$this->identifier = $identifier;
	}
}