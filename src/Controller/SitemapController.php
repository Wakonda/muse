<?php

namespace App\Controller;

use App\Entity\Proverb;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

use App\Service\SitemapGenerator;

/**
 * @Route("/sitemap")
 */
class SitemapController
{
    /**
     * @Route("/generate")
     */
    public function generateAction(EntityManagerInterface $em, Request $request)
    {
		$url_base = $request->getUriForPath("/");

		$sg = new SitemapGenerator($url_base, array("image" => true));
		
		// Generic
		$sg->addItem("", '1.0');
		$sg->addItem("page/copyright", '1.0');
		$sg->addItem("page/about", '1.0');
		$sg->addItem("contact", '1.0');
		$sg->addItem("version", '1.0');

		// Country
		$sg->addItem("bycountries");
		
		$entities = $em->getRepository(Country::class)->findAll();

		foreach($entities as $entity)
		{
			$sg->addItem("country/".$entity->getId());
		}

		// Proverb
		$entities = $em->getRepository(Proverb::class)->findAll();

		foreach($entities as $entity)
		{
			$sg->addItem("read/".$entity->getId()."/".$entity->getSlug());
		}

		$res = $sg->save();
		
		file_put_contents("sitemap/sitemap.xml", $res);

		return $this->render('Admin/index.html.twig');
    }

    /**
     * @Route("/sitemap")
     */
	public function sitemapAction(Request $request)
	{
		$response = new Response(file_get_contents("sitemap/sitemap.xml"));
		$response->headers->set('Content-Type', 'application/xml');
		$response->setCharset('UTF-8');
		
		return $response;
	}
}