<?php

namespace App\Entity;

use App\Service\GenericFunction;
use Doctrine\ORM\Mapping as ORM;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiProperty;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BiographyRepository")
 */
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_ADMIN')"),
        new GetCollection(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['read']],
    denormalizationContext: ['groups' => ['write']]
)]
class Biography
{
	const AUTHOR = "author";
	const FICTIONAL_CHARACTER = "fictionalCharacter";
	
	const AUTHOR_CANONICAL = "biography.type.Author";
	const FICTIONAL_CHARACTER_CANONICAL = "biography.type.FictionalCharacter";

	const FOLDER = "biography";
	const PATH_FILE = "photo/".self::FOLDER."/";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * #[ApiProperty(identifier: false)]
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"read", "write"})
     */
    protected $title;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $slug;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"read", "write"})
     */
    protected $text;
	
    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"read", "write"})
     */
    protected $dayBirth;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"read", "write"})
     */
    protected $monthBirth;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"read", "write"})
     */
    protected $yearBirth;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"read", "write"})
     */
    protected $dayDeath;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"read", "write"})
     */
    protected $monthDeath;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"read", "write"})
     */
    protected $yearDeath;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     * @Groups({"read", "write"})
     */
    protected $country;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     * @Groups({"read", "write"})
     */
	protected $language;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $type;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Source", mappedBy="authors")
     */
    private $sources;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Source", mappedBy="fictionalCharacters")
     */
    private $artworks;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\FileManagement", cascade={"persist"})
     * @Groups({"read", "write"})
     */
    protected $fileManagement;

	/**
	 * @var string $wikidata
	 *
	 * @ORM\Column(name="wikidata", type="string", length=15, nullable=true)
     * @Groups({"read", "write"})
     * #[ApiProperty(identifier: true)]
	 */
	private $wikidata;
	
	public function getTypeCanonical()
	{
		switch($this->type)
		{
			case self::AUTHOR:
				return self::AUTHOR_CANONICAL;
			case self::FICTIONAL_CHARACTER:
				return self::FICTIONAL_CHARACTER_CANONICAL;
		}
		
		return null;
	}
	
	public function isFictionalCharacter()
	{
		return $this->type == self::FICTIONAL_CHARACTER;
	}
	
	public function isAuthor()
	{
		return $this->type == self::AUTHOR;
	}

	public function __toString()
	{
		return $this->title;
	}

	public function __construct()
	{
		$this->type = self::AUTHOR;
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

    public function getDayBirth()
    {
        return $this->dayBirth;
    }

    public function setDayBirth($dayBirth)
    {
        $this->dayBirth = $dayBirth;
    }

    public function getMonthBirth()
    {
        return $this->monthBirth;
    }

    public function setMonthBirth($monthBirth)
    {
        $this->monthBirth = $monthBirth;
    }

    public function getYearBirth()
    {
        return $this->yearBirth;
    }

    public function setYearBirth($yearBirth)
    {
        $this->yearBirth = $yearBirth;
    }

    public function getDayDeath()
    {
        return $this->dayDeath;
    }

    public function setDayDeath($dayDeath)
    {
        $this->dayDeath = $dayDeath;
    }

    public function getMonthDeath()
    {
        return $this->monthDeath;
    }

    public function setMonthDeath($monthDeath)
    {
        $this->monthDeath = $monthDeath;
    }

    public function getYearDeath()
    {
        return $this->yearDeath;
    }

    public function setYearDeath($yearDeath)
    {
        $this->yearDeath = $yearDeath;
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($country)
    {
        $this->country = $country;
    }
	
	public function getLanguage()
	{
		return $this->language;
	}
	
	public function setLanguage($language)
	{
		$this->language = $language;
	}
	
	public function getType()
	{
		return $this->type;
	}
	
	public function setType($type)
	{
		$this->type = $type;
	}
	
	public function getFileManagement()
	{
		return $this->fileManagement;
	}
	
	public function setFileManagement($fileManagement)
	{
		$this->fileManagement = $fileManagement;
	}

    /**
     * Set wikidata
     *
     * @param String $wikidata
     */
    public function setWikidata($wikidata)
    {
        $this->wikidata = $wikidata;
    }

    /**
     * Get wikidata
     *
     * @return String
     */
    public function getWikidata()
    {
        return $this->wikidata;
    }

    public function setSources($sources)
    {
        $this->sources = $sources;
    }

	public function removeSource($source)
	{
		$this->sources->removeElement($source);
	}

	public function getSources()
	{
		return $this->sources;
	}

	public function addSource($source)
	{
		$this->sources[] = $source;
	}

    public function setArtworks($artworks)
    {
        $this->artworks = $artworks;
    }

	public function removeArtwork($artwork)
	{
		$this->artworks->removeElement($artwork);
	}

	public function getArtworks()
	{
		return $this->artworks;
	}

	public function addArtwork($artwork)
	{
		$this->artworks[] = $artwork;
	}
}