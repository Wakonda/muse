<?php

namespace App\Entity;

use App\Service\GenericFunction;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TagRepository")
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
class Tag
{
	const FOLDER = "tag";
	const PATH_FILE = "photo/".self::FOLDER."/";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     * @Groups({"read", "write"})
     */
	protected $language;

   /**
    * @ORM\ManyToMany(targetEntity=Quote::class, mappedBy="tags")
    */
	protected $quotes;

	/**
	 * @ORM\Column(type="string", length=255)
     * @Groups({"read", "write"})
	 */
	protected $internationalName;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\FileManagement")
     */
    protected $fileManagement;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"read", "write"})
     */
    protected $slug;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * #[ApiProperty(identifier: true)]
     * @Groups({"read", "write"})
     */
    protected $identifier;

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

	public function getLanguage()
	{
		return $this->language;
	}
	
	public function setLanguage($language)
	{
		$this->language = $language;
	}

   /**
    * Get poems
    *
    * @return Doctrine\Common\Collections\Collection
    */
	public function getPoems()
	{
		return $this->poems;
	}

    /**
     * Set internationalName
     *
     * @param string $internationalName
     */
    public function setInternationalName($internationalName)
    {
        $this->internationalName = $internationalName;
    }

    /**
     * Get internationalName
     *
     * @return internationalName 
     */
    public function getInternationalName()
    {
        return $this->internationalName;
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

   /**
    * Get quotes
    *
    * @return Doctrine\Common\Collections\Collection
    */
	public function getQuotes()
	{
		return $this->quotes;
	}
	
	public function getFileManagement()
	{
		return $this->fileManagement;
	}
	
	public function setFileManagement($fileManagement)
	{
		$this->fileManagement = $fileManagement;
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