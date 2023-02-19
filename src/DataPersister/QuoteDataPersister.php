<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Quote;
use App\Entity\language;
use App\Entity\Country;
use App\Entity\Biography;
use App\Entity\Source;
use App\Service\GenericFunction;

final class QuoteDataPersister implements ContextAwareDataPersisterInterface
{
    private $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof Quote;
    }

    public function persist($data, array $context = [])
    {
		$language = $this->entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $data->getLanguage()->getAbbreviation()]);

		if(empty($language))
			throw new NotFoundHttpException();
		
		if(empty($data->getText()))
			throw new BadRequestHttpException();
		
		$country = $this->entityManager->getRepository(Country::class)->findOneBy(["internationalName" => $data->getBiography()->getCountry()->getInternationalName(), "language" => $language]);
		
		$biography = $this->entityManager->getRepository(Biography::class)->findOneBy(["wikidata" => $data->getBiography()->getWikidata(), "language" => $language]);

		if(empty($biography)) {
			$biography = $data->getBiography();
			$fileManagement = $data->getBiography()->getFileManagement();			
		}
		else {
			$fileManagement = $biography->getFileManagement();
			$fileManagement->setDescription($data->getBiography()->getFileManagement()->getDescription());
		}
		
		if(empty($biography->getWikidata()))
			throw new NotFoundHttpException();
		
		$data->setAuthorType(Quote::BIOGRAPHY_AUTHORTYPE);

		$fileManagement = $data->getBiography()->getFileManagement();

		$imgBase64 = $data->getBiography()->getFileManagement()->imgBase64;
		$source = null; //$this->entityManager->getRepository(Source::class)->findOneBy(["identifier" => $data->getSource()->getIdentifier(), "language" => $language]);
		
		$data->setSource($source);
		
		if(!empty($source)) {
			if($biography->isAuthor()) {
				$sa = $this->entityManager->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::AUTHOR, $source->getIdentifier());
			
				if(empty($sa)) {
					$source->addAuthor($biography);
					$biography->addSource($source);
				}
			}
			if($biography->isFictionalCharacter()) {
				$sa = $this->entityManager->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::FICTIONAL_CHARACTER, $source->getIdentifier());
			
				if(empty($sa)) {
					$source->addFictionalCharacter($biography);
					$biography->addArtwork($source);
				}
			}
			$this->entityManager->persist($source);
			$this->entityManager->persist($biography);
		}
		
		$data->setLanguage($language);
		$data->getBiography()->setCountry($country);
		$data->getBiography()->setLanguage($language);
		$data->getBiography()->getFileManagement()->setFolder(Biography::FOLDER);
		$data->setBiography($biography);

		
		$this->entityManager->persist($data);
        $this->entityManager->flush();

		$data->setIdentifier("muse-".$data->getId());
		
		$this->entityManager->flush();

		$this->entityManager->persist($data);
        $this->entityManager->flush();

		if(!empty($imgBase64))
			file_put_contents("photo/".$data->getBiography()->getFileManagement()->getFolder()."/".$data->getBiography()->getFileManagement()->getPhoto(), base64_decode($imgBase64));

        return $data;
    }

    public function remove($data, array $context = [])
    {
        // call your persistence layer to delete $data
    }
}