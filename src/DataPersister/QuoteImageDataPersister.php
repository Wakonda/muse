<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\Entity\Quote;
use App\Entity\QuoteImage;
use App\Service\Facebook;
use App\Service\Twitter;
use App\Service\Mastodon;

final class QuoteImageDataPersister implements ContextAwareDataPersisterInterface
{
    private $entityManager;
	private $router;
	private $facebook;
	private $twitter;
	private $mastodon;
    
    public function __construct(EntityManagerInterface $entityManager, UrlGeneratorInterface $router, Facebook $facebook, Twitter $twitter, Mastodon $mastodon)
    {
        $this->entityManager = $entityManager;
		$this->router = $router;
		$this->facebook = $facebook;
		$this->twitter = $twitter;
		$this->mastodon = $mastodon;
    }

    public function supports($data, array $context = []): bool
    {
        return $data instanceof QuoteImage;
    }

    public function persist($data, array $context = [])
    {
		// die(var_dump($data->getQuote()->getIdentifier()));
		$quote = $this->entityManager->getRepository(Quote::class)->findOneBy(["identifier" => $data->getQuote()->getIdentifier()]);

		file_put_contents("photo/quote/".$data->getImage(), base64_decode($data->imgBase64));
		$data->setQuote($quote);

		$this->entityManager->persist($data);
        $this->entityManager->flush();

		// Publish on social network
		$text = $quote->getText();
		$url = $this->router->generate("app_indexquotus_read", ["id" => $quote->getId(), 'slug' => $quote->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
		$locale = $quote->getLanguage()->getAbbreviation();
		
		$tags = implode(" ", array_map(function($e) { return "#".$e->getSlug(); }, $quote->getTags()));

		$message = $text." ".$tags." ".$url;
		$image = Quote::PATH_FILE.$data->getImage();
		
		$statues = $this->mastodon->postMessage($message, $image, $locale);
		
		if(!isset($statues->errors) or empty($statues->errors))
			$data->addSocialNetwork("Mastodon");

		$statues = $this->twitter->sendTweet($message, $image, $locale);
		
		if(!isset($statues->errors) or empty($statues->errors))
			$data->addSocialNetwork("Twitter");
		
		$res = json_decode($this->facebook->postMessage($url, $text, $locale));
		
		if(property_exists($res, "error"))
			$data->addSocialNetwork("Facebook");

		$this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    public function remove($data, array $context = [])
    {
        // call your persistence layer to delete $data
    }
}