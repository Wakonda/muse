<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Entity\Language;
use App\Form\Type\TagType;
use App\Service\GenericFunction;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @Route("/admin/tag")
 */
class TagAdminController extends AbstractController
{
    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Tag/index.html.twig');
	}

    /**
     * @Route("/datatables")
     */
	public function indexDatatablesAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

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

		$entities = $em->getRepository(Tag::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository(Tag::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			
			$show = $this->generateUrl('app_tagadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_tagadmin_edit', array('id' => $entity->getId()));
			
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
		$entity = new Tag();
		$entity->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));
        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Tag/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Tag();
        $form = $this->genericCreateForm($request->getLocale(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $entity, $form);

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('app_tagadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Tag/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Tag::class)->find($id);
	
		return $this->render('Tag/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Tag::class)->find($id);
		$form = $this->genericCreateForm($request->getLocale(), $entity);
	
		return $this->render('Tag/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Tag::class)->find($id);
		$form = $this->genericCreateForm($request->getLocale(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $entity, $form);
		
		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('app_tagadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Tag/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/tags")
     */
	public function getTagsByAjaxAction(EntityManagerInterface $em, Request $request)
	{
		$locale = $request->query->get("locale", null);
		$type = $request->query->get("type", null);
		$query = $request->query->get("q", null);
		
		$datas =  $em->getRepository(Tag::class)->getDatasSelect($type, $locale, $query, null);
		
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
		return $this->createForm(TagType::class, $entity, array('locale' => $locale));
	}

	private function checkForDoubloon(EntityManagerInterface $em, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(Tag::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError('Cette entrée existe déjà !'));
		}
	}
}