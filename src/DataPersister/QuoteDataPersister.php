<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Quote;
use App\Entity\language;
use App\Entity\Biography;
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
		
		if(!empty($data->getText()))
			throw new \Exception("Data already exists on database");
		
		$data->setLanguage($language);
		$data->setBiography($biography);

		$this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    public function remove($data, array $context = [])
    {
        // call your persistence layer to delete $data
    }
}