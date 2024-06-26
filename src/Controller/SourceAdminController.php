<?php

namespace App\Controller;

use App\Entity\Source;
use App\Entity\User;
use App\Entity\Biography;
use App\Entity\Language;
use App\Form\Type\SourceType;
use App\Service\GenericFunction;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @Route("/admin/source")
 */
class SourceAdminController extends AbstractController
{
	private $formName = "source";

    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Source/index.html.twig');
	}

    /**
     * @Route("/datatables")
     */
	public function indexDatatablesAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');
		$state = $request->query->get('state');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i<intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}
		
		$entities = $em->getRepository(Source::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state);
		$iTotal = $em->getRepository(Source::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity->getId();
			$row[] = $entity->getTitle();
			$row[] = $entity->getLanguage()->getTitle();
			
			$show = $this->generateUrl('app_sourceadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_sourceadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a> - <a href="'.$edit.'" alt="Edit">'.$translator->trans('admin.index.Update').'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/new")
     */
    public function newAction(EntityManagerInterface $em, Request $request)
    {
		$entity = new Source();

		$language = $em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		
		$entity->setLanguage($language);

        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Source/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Source();
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);

		$this->checkForDoubloon($em, $translator, $entity, $form);

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('app_sourceadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Source/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Source::class)->find($id);
	
		return $this->render('Source/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Source::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);

		return $this->render('Source/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Source::class)->find($id);
		
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$entity->setSlug(true);
		$this->checkForDoubloon($em, $translator, $entity, $form);

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			return $this->redirect($this->generateUrl('app_sourceadmin_show', array('id' => $entity->getId())));
		}
	
		return $this->render('Source/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/biographies")
     */
	public function getBiographiesByAjaxAction(EntityManagerInterface $em, Request $request)
	{
		$locale = $request->query->get("locale", null);
		$type = $request->query->get("type", null);
		$query = $request->query->get("q", null);
		
		$datas =  $em->getRepository(Biography::class)->getDatasSelect($type, $locale, $query, null);
		
		$res = [];
		
		foreach($datas as $data)
		{
			$row = [];
			$row["id"] = $data->getId();
			$row["text"] = $data->getTitle();
			$res["results"][] = $row;
		}

		return new JsonResponse($res);
	}

    /**
     * @Route("/sources")
     */
	public function getSourcesByAjaxAction(EntityManagerInterface $em, Request $request)
	{
		$locale = $request->query->get("locale", null);
		$query = $request->query->get("q", null);

		$datas =  $em->getRepository(Source::class)->getDatasSelect($locale, $query);

		$res = [];
		
		foreach($datas as $data)
		{
			$row = [];
			$row["id"] = $data->getId();
			$row["text"] = $data->getTitle();
			$res["results"][] = $row;
		}

		return new JsonResponse($res);
	}

	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(SourceType::class, $entity, array('locale' => $locale));
	}

	private function checkForDoubloon(EntityManagerInterface $em, TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(Source::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}