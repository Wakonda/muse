<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Quote;
use App\Entity\Language;
use App\Entity\Country;
use App\Entity\Biography;
use App\Entity\Source;
use App\Entity\Tag;
use App\Entity\QuoteImage;
use App\Service\GenericFunction;

final class QuoteDataPersister implements ContextAwareDataPersisterInterface
{
    private $em;
    
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof Quote;
    }

    public function persist($data, array $context = [])
    {
		$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $data->getLanguage()->getAbbreviation()]);

		if(empty($language))
			throw new NotFoundHttpException();
		
		if(empty($data->getText()))
			throw new BadRequestHttpException();

		$country = !empty($c = $data->getBiography()->getCountry()->getInternationalName()) ? $this->em->getRepository(Country::class)->findOneBy(["internationalName" => $c, "language" => $language]) : null;

		$currentTags = [];

		foreach($data->getTags() as $tag) {
			$currentTag = $this->em->getRepository(Tag::class)->findOneBy(["identifier" => $tag->getIdentifier(), "language" => $language]);

			if(!empty($currentTag))
				$currentTags[] = $currentTag;
			else {
				$currentTags[] = $tag;
				$tag->setLanguage($language);
			}
		}

		$data->setTags($currentTags);

		$biography = $this->em->getRepository(Biography::class)->findOneBy(["wikidata" => $data->getBiography()->getWikidata(), "language" => $language]);

		if(empty($biography)) {
			$biography = $data->getBiography();
			$fileManagement = $data->getBiography()->getFileManagement();			
		}
		else {
			$fileManagement = $biography->getFileManagement();
			
			if(!empty($fileManagement))
				$fileManagement->setDescription($data->getBiography()->getFileManagement()->getDescription());
		}
		
		if(empty($biography->getWikidata()))
			throw new NotFoundHttpException();
		
		$data->setBiography($biography);
		$data->setAuthorType(Quote::BIOGRAPHY_AUTHORTYPE);

		if(!empty($biography->getFileManagement())) {
			$biography->getFileManagement()->setFolder(Biography::FOLDER);
			$imgBase64 = $biography->getFileManagement()->imgBase64;

			if(empty($fileManagement->getPhoto()))
				$biography->setFileManagement(null);
		}

		/*$source = null; //$this->em->getRepository(Source::class)->findOneBy(["identifier" => $data->getSource()->getIdentifier(), "language" => $language]);
		
		$data->setSource($source);
		
		if(!empty($source)) {
			if($biography->isAuthor()) {
				$sa = $this->em->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::AUTHOR, $source->getIdentifier());
			
				if(empty($sa)) {
					$source->addAuthor($biography);
					$biography->addSource($source);
				}
			}
			if($biography->isFictionalCharacter()) {
				$sa = $this->em->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::FICTIONAL_CHARACTER, $source->getIdentifier());
			
				if(empty($sa)) {
					$source->addFictionalCharacter($biography);
					$biography->addArtwork($source);
				}
			}
			$this->em->persist($source);
			$this->em->persist($biography);
		}*/
		
		$data->setLanguage($language);
		$data->getBiography()->setCountry($country);
		$data->getBiography()->setLanguage($language);
		
		$this->em->persist($data);
        $this->em->flush();

		$data->setIdentifier("muse-".$data->getId());
		
		$this->em->flush();

		if(!empty($imgBase64))
			file_put_contents("photo/".$data->getBiography()->getFileManagement()->getFolder()."/".$data->getBiography()->getFileManagement()->getPhoto(), base64_decode($imgBase64));

        return $data;
    }

    public function remove($data, array $context = [])
    {
        // call your persistence layer to delete $data
    }
}