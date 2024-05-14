<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

use App\Service\Captcha;
use App\Service\Gravatar;

use App\Entity\Page;
use App\Entity\Language;
use App\Entity\Store;
use App\Entity\Version;
use App\Service\GenericFunction;

use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

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
	public function pageAction(EntityManagerInterface $em, Request $request, $name)
	{
		$language = $em->getRepository(Language::class)->findOneBy(['abbreviation' => $request->getLocale()]);
		$entity = $em->getRepository(Page::class)->findOneBy(["internationalName" => $name.ucfirst((new GenericFunction())->getSubDomain()), "language" => $language]);
		
		return $this->render('Index/page.html.twig', array("entity" => $entity));
	}

    /**
     * @Route("/store/{page}", defaults={"page": 1})
     */
    public function storeAction(EntityManagerInterface $em, Request $request, PaginatorInterface $paginator, $page)
    {
		$querySearch = $request->request->get("query", null);
		$query = $em->getRepository(Store::class)->getProducts($querySearch, $request->getLocale());

		$pagination = $paginator->paginate(
			$query, /* query NOT result */
			$page, /*page number*/
			10 /*limit per page*/
		);

		$pagination->setCustomParameters(['align' => 'center']);
		
		return $this->render('Index/store.html.twig', ['pagination' => $pagination, "query" => $querySearch]);
    }

    /**
     * @Route("/read_store/{id}/{slug}", defaults={"slug": null})
     */
	public function readStoreAction(EntityManagerInterface $em, $id)
	{
		$entity = $em->getRepository(Store::class)->find($id);
		
		return $this->render('Index/readStore.html.twig', [
			'entity' => $entity
		]);
	}

    /**
     * @Route("/version")
     */
	public function versionAction(EntityManagerInterface $em, Request $request)
	{
		$language = $em->getRepository(Language::class)->findOneBy(['abbreviation' => $request->getLocale()]);
		$entities = $em->getRepository(Version::class)->findBy(["language" => $language]);

		return $this->render('Index/version.html.twig', array('entities' => $entities));
	}
}