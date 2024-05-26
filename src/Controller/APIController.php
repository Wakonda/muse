<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Quote;
use App\Entity\Proverb;
use App\Entity\Poem;
use App\Entity\Language;
use App\Entity\Country;
use App\Entity\Biography;
use App\Entity\Source;
use App\Entity\Tag;
use App\Entity\QuoteImage;
use App\Entity\ProverbImage;
use App\Entity\FileManagement;
use App\Service\GenericFunction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\Facebook;
use App\Service\Twitter;
use App\Service\Mastodon;

/**
 * @Route("/webservices")
 */
class APIController extends AbstractController
{
    /**
     * @Route("/quotes")
     */
    public function saveQuoteAction(EntityManagerInterface $em, Request $request)
    {
		$data = json_decode(file_get_contents('php://input'));

		$entity = $em->getRepository(Quote::class)->findOneBy(["identifier" => $data->identifier]);

		if(empty($entity))
		$entity = new Quote();

		$language = $em->getRepository(Language::class)->findOneBy(["abbreviation" => $data->language->abbreviation]);

		// if(empty($language))
		// throw new NotFoundHttpException();

		// if(empty($data->text))
		// throw new BadRequestHttpException();

		$entity->setText($data->text);

		$country = !empty($c = $data->biography->country->internationalName) ? $em->getRepository(Country::class)->findOneBy(["internationalName" => $c, "language" => $language]) : null;

		$currentTags = [];

		foreach($data->tags as $tag) {
			$currentTag = $em->getRepository(Tag::class)->findOneBy(["identifier" => $tag->identifier, "language" => $language]);

			if(!empty($currentTag))
				$currentTags[] = $currentTag;
			else {
				$newTag = new Tag();
				$newTag->setTitle($tag->title);
				$newTag->setInternationalName($tag->internationalName);
				$newTag->setIdentifier($tag->identifier);
				$newTag->setSlug($tag->slug);
				$newTag->setLanguage($language);
				$currentTags[] = $newTag;
			}
		}

		$entity->setTags($currentTags);

		$biography = $em->getRepository(Biography::class)->findOneBy(["wikidata" => $data->biography->wikidata, "language" => $language]);

		if(empty($biography)) {
			$biography = new Biography();
			$fileManagement = new FileManagement();
			$biography->setFileManagement($fileManagement);
		}
		else {
			$fileManagement = $biography->getFileManagement();
		}

		if(!empty($data->biography->fileManagement)) {
			$fileManagement->setPhoto($data->biography->fileManagement->photo);
			$fileManagement->setDescription($data->biography->fileManagement->description);
			$biography->getFileManagement()->setFolder(Biography::FOLDER);
			$imgBase64 = $data->biography->fileManagement->imgBase64;
		}

		$biography->setTitle($data->biography->title);
		$biography->setText($data->biography->text);
		$biography->setWikidata($data->biography->wikidata);
		$biography->setCountry($country);
		$biography->setLanguage($language);
		$biography->setDayBirth($data->biography->dayBirth);
		$biography->setMonthBirth($data->biography->monthBirth);
		$biography->setYearBirth($data->biography->yearBirth);
		$biography->setDayDeath($data->biography->dayDeath);
		$biography->setMonthDeath($data->biography->monthDeath);
		$biography->setYearDeath($data->biography->yearDeath);
		$em->persist($biography);

		if(empty($biography->getWikidata()))
			throw new NotFoundHttpException();

		$entity->setBiography($biography);
		$entity->setAuthorType(Quote::BIOGRAPHY_AUTHORTYPE);

		if(!empty($biography->getFileManagement())) {
			$biography->getFileManagement()->setFolder(Biography::FOLDER);
			$imgBase64 = $biography->getFileManagement()->imgBase64;

			if(empty($fileManagement->getPhoto()))
				$biography->setFileManagement(null);
		}

		/*$source = null; //$em->getRepository(Source::class)->findOneBy(["identifier" => $data->getSource()->getIdentifier(), "language" => $language]);

		$data->setSource($source);

		if(!empty($source)) {
		if($biography->isAuthor()) {
		$sa = $em->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::AUTHOR, $source->getIdentifier());

		if(empty($sa)) {
		$source->addAuthor($biography);
		$biography->addSource($source);
		}
		}
		if($biography->isFictionalCharacter()) {
		$sa = $em->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::FICTIONAL_CHARACTER, $source->getIdentifier());

		if(empty($sa)) {
		$source->addFictionalCharacter($biography);
		$biography->addArtwork($source);
		}
		}
		$em->persist($source);
		$em->persist($biography);
		}*/

		$entity->setLanguage($language);
		$entity->getBiography()->setCountry($country);
		$entity->getBiography()->setLanguage($language);

		$em->persist($entity);
		$entity->setIdentifier("muse-".uniqid());
		$em->flush();

		if(!empty($imgBase64))
			file_put_contents("photo/".$entity->getBiography()->getFileManagement()->getFolder()."/".$entity->getBiography()->getFileManagement()->getPhoto(), base64_decode($imgBase64));

        return new JsonResponse(["type" => "success", "identifier" => $entity->getIdentifier()]);
    }

    /**
     * @Route("/save_image")
     */
    public function saveImageAction(Request $request, EntityManagerInterface $em, UrlGeneratorInterface $router, Facebook $facebook, Twitter $twitter, Mastodon $mastodon) {
		$data = json_decode(file_get_contents('php://input'));

		switch($data->family) {
			case "quote":
				$entity = $em->getRepository(Quote::class)->findOneBy(["identifier" => $data->identifier]);
				file_put_contents("photo/quote/".$data->image, base64_decode($data->imgBase64));

				$newEntity = new QuoteImage();

				$newEntity->setQuote($entity);
				$newEntity->setImage($data->image);
				$newEntity->setIdentifier($data->identifier);
				$path = Quote::PATH_FILE;
			break;
			case "proverb":
				$entity = $em->getRepository(Proverb::class)->findOneBy(["identifier" => $data->identifier]);
				file_put_contents("photo/proverb/".$data->image, base64_decode($data->imgBase64));

				$newEntity = new ProverbImage();

				$newEntity->setProverb($entity);
				$newEntity->setImage($data->image);
				$newEntity->setIdentifier($data->identifier);
				$path = Proverb::PATH_FILE;
			break;
		}

		$em->persist($newEntity);
        $em->flush();

		// Publish on social network
		$text = $entity->getText();
		$url = $router->generate("app_indexquotus_read", ["id" => $entity->getId(), 'slug' => $entity->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
		$locale = $entity->getLanguage()->getAbbreviation();

		$biography = "#".strtolower(str_replace(["-", "'", " "], "", iconv('UTF-8','ASCII//TRANSLIT', $entity->getBiography()->getTitle())));

		$tags = implode(" ", array_map(function($e) { return "#".$e->getSlug(); }, $entity->getTags()))." ".$biography;

		$message = $text." ".$tags." ".$url;
		$image = $path.$data->getImage();

		$statues = $mastodon->postMessage($message, $image, $locale);

		if(!isset($statues->errors) or empty($statues->errors))
		$newEntity->addSocialNetwork("Mastodon");

		$statues = $twitter->sendTweet($message, $image, $locale);

		if(!isset($statues->errors) or empty($statues->errors))
		$newEntity->addSocialNetwork("Twitter");

		$url = $router->generate("app_indexquotus_read", ["id" => $entity->getId(), "slug" => $entity->getSlug(), "idImage" => $newEntity->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

		$res = json_decode($facebook->postMessage($url, $text, $locale));

		if(property_exists($res, "error"))
			$newEntity->addSocialNetwork("Facebook");

		$em->persist($newEntity);
        $em->flush();
        return new JsonResponse(["type" => "success"]);
	}

    /**
     * @Route("/proverbs")
     */
    public function saveProverbAction(EntityManagerInterface $em, Request $request)
    {
		$data = json_decode(file_get_contents('php://input'));

		$entity = $em->getRepository(Proverb::class)->findOneBy(["identifier" => $data->identifier]);

		if(empty($entity))
			$entity = new Proverb();

		$language = $em->getRepository(Language::class)->findOneBy(["abbreviation" => $data->language->abbreviation]);

		$entity->setText($data->text);
		$country = !empty($c = $data->country->internationalName) ? $em->getRepository(Country::class)->findOneBy(["internationalName" => $c, "language" => $language]) : null;

		$currentTags = [];

		foreach($data->tags as $tag) {
			$currentTag = $em->getRepository(Tag::class)->findOneBy(["identifier" => $tag->identifier, "language" => $language]);

			if(!empty($currentTag))
				$currentTags[] = $currentTag;
			else {
				$newTag = new Tag();
				$newTag->setTitle($tag->title);
				$newTag->setInternationalName($tag->internationalName);
				$newTag->setIdentifier($tag->identifier);
				$newTag->setSlug($tag->slug);
				$newTag->setLanguage($language);
				$currentTags[] = $newTag;
			}
		}

		$entity->setTags($currentTags);
		$entity->setCountry($country);
		$entity->setLanguage($language);

		$em->persist($entity);
		$entity->setIdentifier("muse-".uniqid());
		$em->flush();

        return new JsonResponse(["type" => "success", "identifier" => $entity->getIdentifier()]);
    }

    /**
     * @Route("/poems")
     */
    public function savePoemAction(EntityManagerInterface $em, Request $request)
    {
		$data = json_decode(file_get_contents('php://input'));

		$entity = $em->getRepository(Poem::class)->findOneBy(["identifier" => $data->identifier]);

		if(empty($entity))
			$entity = new Poem();

		$language = $em->getRepository(Language::class)->findOneBy(["abbreviation" => $data->language->abbreviation]);

		$entity->setTitle($data->title);
		$entity->setText($data->text);

		$country = !empty($c = $data->biography->country->internationalName) ? $em->getRepository(Country::class)->findOneBy(["internationalName" => $c, "language" => $language]) : null;
// die(", ".$country->getId());
		$currentTags = [];

		foreach($data->tags as $tag) {
			$currentTag = $em->getRepository(Tag::class)->findOneBy(["identifier" => $tag->identifier, "language" => $language]);

			if(!empty($currentTag))
				$currentTags[] = $currentTag;
			else {
				$newTag = new Tag();
				$newTag->setTitle($tag->title);
				$newTag->setInternationalName($tag->internationalName);
				$newTag->setIdentifier($tag->identifier);
				$newTag->setSlug($tag->slug);
				$newTag->setLanguage($language);
				$currentTags[] = $newTag;
			}
		}

		$entity->setTags($currentTags);

		$biography = $em->getRepository(Biography::class)->findOneBy(["wikidata" => $data->biography->wikidata, "language" => $language]);

		if(empty($biography)) {
			$biography = new Biography();
			$fileManagement = new FileManagement();
			$biography->setFileManagement($fileManagement);
		}
		else {
			$fileManagement = $biography->getFileManagement();
		}

		if(!empty($data->biography->fileManagement)) {
			$fileManagement->setPhoto($data->biography->fileManagement->photo);
			$fileManagement->setDescription($data->biography->fileManagement->description);
			$biography->getFileManagement()->setFolder(Biography::FOLDER);
			$imgBase64 = $data->biography->fileManagement->imgBase64;
		}

		$biography->setTitle($data->biography->title);
		$biography->setText($data->biography->text);
		$biography->setWikidata($data->biography->wikidata);
		$biography->setCountry($country);
		$biography->setLanguage($language);
		$biography->setDayBirth($data->biography->dayBirth);
		$biography->setMonthBirth($data->biography->monthBirth);
		$biography->setYearBirth($data->biography->yearBirth);
		$biography->setDayDeath($data->biography->dayDeath);
		$biography->setMonthDeath($data->biography->monthDeath);
		$biography->setYearDeath($data->biography->yearDeath);
		$em->persist($biography);

		if(empty($biography->getWikidata()))
			throw new NotFoundHttpException();

		$entity->setBiography($biography);
		$entity->setAuthorType(Quote::BIOGRAPHY_AUTHORTYPE);

		if(!empty($biography->getFileManagement())) {
			$biography->getFileManagement()->setFolder(Biography::FOLDER);
			$imgBase64 = $biography->getFileManagement()->imgBase64;

			if(empty($fileManagement->getPhoto()))
				$biography->setFileManagement(null);
		}

		/*$source = null; //$em->getRepository(Source::class)->findOneBy(["identifier" => $data->getSource()->getIdentifier(), "language" => $language]);

		$data->setSource($source);

		if(!empty($source)) {
		if($biography->isAuthor()) {
		$sa = $em->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::AUTHOR, $source->getIdentifier());

		if(empty($sa)) {
		$source->addAuthor($biography);
		$biography->addSource($source);
		}
		}
		if($biography->isFictionalCharacter()) {
		$sa = $em->getRepository(Source::class)->getSourceByBiographyAndTitle($biography, $source->getTitle(), Biography::FICTIONAL_CHARACTER, $source->getIdentifier());

		if(empty($sa)) {
		$source->addFictionalCharacter($biography);
		$biography->addArtwork($source);
		}
		}
		$em->persist($source);
		$em->persist($biography);
		}*/

		$entity->setLanguage($language);
		$entity->setCountry($country);

		$em->persist($entity);
		$entity->setIdentifier("muse-".uniqid());
		$em->flush();

		if(!empty($imgBase64))
			file_put_contents("photo/".$entity->getBiography()->getFileManagement()->getFolder()."/".$entity->getBiography()->getFileManagement()->getPhoto(), base64_decode($imgBase64));

        return new JsonResponse(["type" => "success", "identifier" => $entity->getIdentifier()]);
    }
}