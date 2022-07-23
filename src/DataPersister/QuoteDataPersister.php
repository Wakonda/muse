<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Quote;
use App\Entity\language;
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
		
		$biography = $this->entityManager->getRepository(Biography::class)->findOneBy(["wikidata" => $data->getBiography()->getWikidata(), "language" => $language]);
		
		if(empty($biography))
			throw new NotFoundHttpException();
		
		if(empty($data->getText()))
			throw new BadRequestHttpException();
			
		$slug = GenericFunction::slugify($data->getText(), 30);
        
		$quote = $this->entityManager->getRepository(Quote::class)->findOneBy(["slug" => $slug, "biography" => $biography, "language" => $language]);
		
		if(!empty($quote))
			throw new \Exception("Data already exists on database");
		
		$source = $this->entityManager->getRepository(Source::class)->findOneBy(["identifier" => $data->getSource()->getIdentifier(), "language" => $language]);
		
		$data->setSource($source);
		
		if(!empty($source)) {
			if($biography->isAuthor()) {
				$sa = $this->entityManager->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::AUTHOR, $source->getIdentifier());
			
				if(empty($sa)) {//die("eeddd");
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
		$data->setBiography($biography);
// die("jjjj");
		$this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    public function remove($data, array $context = [])
    {
        // call your persistence layer to delete $data
    }
}