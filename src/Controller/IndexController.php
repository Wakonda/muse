<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Service\Captcha;
use App\Service\Gravatar;

use App\Entity\Page;
use App\Entity\Language;
use App\Service\GenericFunction;

use Symfony\Component\Routing\Annotation\Route;

    /**
     * @Route("/")
     */
class IndexController extends AbstractController
{
    /**
     * @Route("/", priority=-10)
     */
	public function indexAction(Request $request)
	{
		return $this->render('Index/index.html.twig');
	}

    /**
     * @Route("/captcha")
     */
	public function captchaAction(Request $request)
	{
		$captcha = new Captcha($request->getSession());

		$wordOrNumberRand = rand(1, 2);
		$length = rand(3, 7);

		if($wordOrNumberRand == 1)
			$word = $captcha->wordRandom($length);
		else
			$word = $captcha->numberRandom($length);

		return new JsonResponse(["new_captcha" => $captcha->generate($word)]);
	}

    /**
     * @Route("/gravatar")
     */
	public function gravatarAction(Request $request)
	{
		$gr = new Gravatar();

		return new JsonResponse(["new_gravatar" => $gr->getURLGravatar()]);
	}

    /**
     * @Route("/page/{name}")
     */
	public function pageAction(Request $request, $name)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$language = $entityManager->getRepository(Language::class)->findOneBy(['abbreviation' => $request->getLocale()]);
		$entity = $entityManager->getRepository(Page::class)->findOneBy(["internationalName" => $name.ucfirst((new GenericFunction())->getSubDomain()), "language" => $language]);
		
		return $this->render('IndexPoeticus/page.html.twig', array("entity" => $entity));
	}
}