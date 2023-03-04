<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Quote;
use App\Entity\QuoteImage;

final class QuoteImageDataPersister implements ContextAwareDataPersisterInterface
{
    private $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof QuoteImage;
    }

    public function persist($data, array $context = [])
    {
		$quote = $this->entityManager->getRepository(Quote::class)->findOneBy(["identifier" => $data->getQuote()->getIdentifier()]);

		file_put_contents("photo/quote/".$data->getImage(), base64_decode($data->imgBase64));
		$data->setQuote($quote);
		

		$this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    public function remove($data, array $context = [])
    {
        // call your persistence layer to delete $data
    }
}