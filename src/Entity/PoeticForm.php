<?php

namespace App\Entity;

use App\Service\GenericFunction;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PoeticFormRepository")
 */
class PoeticForm
{
	const FOLDER = "poeticform";
	const PATH_FILE = "photo/".self::FOLDER."/";

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
     * @ORM\Column(type="text")
     */
    protected $text;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $slug;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $typeContentPoem;
	
	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\FileManagement")
     */
    protected $fileManagement;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Language")
     */
	protected $language;
	
	const IMAGETYPE = "image"; 
	const TEXTTYPE = "text"; 
	
	public function __construct()
	{
		$this->typeContentPoem = self::TEXTTYPE;
	}

	public function __toString()
	{
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

    public function getText()
    {
        return $this->text;
    }

    public function setText($text)
    {
        $this->text = $text;
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

    public function getTypeContentPoem()
    {
        return $this->typeContentPoem;
    }

    public function setTypeContentPoem($typeContentPoem)
    {
        $this->typeContentPoem = $typeContentPoem;
    }

	public function getLanguage()
	{
		return $this->language;
	}
	
	public function setLanguage($language)
	{
		$this->language = $language;
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