<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Service\GenericFunction;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FileManagementRepository")
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
class FileManagement
{
    /**
     * @var integer $id
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var text $description
     *
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"read", "write"})
     */
    private $description;

    /**
     * @ORM\Column(name="photo", type="string", length=255)
     * @Groups({"read", "write"})
     */
    private $photo;

    /**
     * @var text $folder
     *
     * @ORM\Column(type="text")
     */
    private $folder;

	/**
     * @Groups({"read", "write"})
	 */
	public $imgBase64;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set photo
     *
     * @param text $photo
     */
    public function setPhoto($photo)
    {
        $this->photo = $photo;
    }

    /**
     * Get photo
     *
     * @return text 
     */
    public function getPhoto()
    {
        return $this->photo;
    }

    protected function getUploadRootDir() {
        // the absolute directory path where uploaded documents should be saved
        return $this->getTmpUploadRootDir();
    }

	public function getAssetImagePath()
	{
		return "extended/photo/photolicence/";
	}

    protected function getTmpUploadRootDir() {
        // the absolute directory path where uploaded documents should be saved
        return __DIR__ . '/../../../../public/'.$this->getAssetImagePath();
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function uploadPhoto() {
        // the file property can be empty if the field is not required
        if (null === $this->photo) {
            return;
        }

		if(is_object($this->photo))
		{
			$NameFile = basename($this->photo->getClientOriginalName());
			$reverseNF = strrev($NameFile);
			$explodeNF = explode(".", $reverseNF, 2);
			$NNFile = strrev($explodeNF[1]);
			$ExtFile = strrev($explodeNF[0]);
			$NewNameFile = uniqid().'-'.$NNFile.".".$ExtFile;
			if(!$this->id){
				$this->photo->move($this->getTmpUploadRootDir(), $NewNameFile);
			}else{
				if (is_object($this->photo))
					$this->photo->move($this->getUploadRootDir(), $NewNameFile);
			}
			if (is_object($this->photo))
				$this->setPhoto($NewNameFile);
		}
    }

    public function getFolder()
    {
        return $this->folder;
    }

    public function setFolder($folder)
    {
        $this->folder = $folder;
    }
}