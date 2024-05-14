<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Proverb;
use App\Entity\Language;
use App\Entity\Country;
use App\Entity\Biography;
use App\Entity\Source;
use App\Entity\Tag;
use App\Entity\ProverbImage;
use App\Service\GenericFunction;

final class ProverbDataPersister implements ContextAwareDataPersisterInterface
{
    private $em;
    
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof Proverb;
    }

    public function persist($data, array $context = [])
    {
		$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $data->getLanguage()->getAbbreviation()]);

		if(empty($language))
			throw new NotFoundHttpException();
		
		if(empty($data->getText()))
			throw new BadRequestHttpException();

		$country = !empty($c = $data->getCountry()->getInternationalName()) ? $this->em->getRepository(Country::class)->findOneBy(["internationalName" => $c, "language" => $language]) : null;

		$currentTags = [];

		if(!empty($data->getTags())) {
			foreach($data->getTags() as $tag) {
				$currentTag = $this->em->getRepository(Tag::class)->findOneBy(["identifier" => $tag->getIdentifier(), "language" => $language]);

				if(!empty($currentTag))
					$currentTags[] = $currentTag;
				else {
					$currentTags[] = $tag;
					$tag->setLanguage($language);
				}
				
				$data->addTag($tag);
			}
		}

		$data->setTags($currentTags);

		$data->setLanguage($language);
		$data->setCountry($country);
		
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