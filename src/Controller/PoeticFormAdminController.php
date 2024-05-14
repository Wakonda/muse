<?php

namespace App\Controller;

use App\Entity\PoeticForm;
use App\Entity\Language;
use App\Form\Type\PoeticFormType;
use App\Service\GenericFunction;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @Route("/poeticform")
 */
class PoeticFormAdminController extends AbstractController
{
    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('PoeticForm/index.html.twig');
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

		$entities = $em->getRepository(PoeticForm::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository(PoeticForm::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			
			$show = $this->generateUrl('poeticformadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('poeticformadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a> - <a href="'.$edit.'" alt="Edit">'.$translator->trans('admin.index.Update').'</a>';

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

    /**
     * @Route("/new")
     */
    public function newAction(EntityManagerInterface $em, Request $request)
    {
		$entity = new PoeticForm();
		$entity->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));
        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('PoeticForm/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/login")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new PoeticForm();
        $form = $this->genericCreateForm($request->getLocale(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $entity, $form);

		if(empty($entity->getFileManagement()) or $entity->getFileManagement()->getPhoto() == null) {
			$form->get("fileManagement")->get("id")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
		}

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('poeticformadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('PoeticForm/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(PoeticForm::class)->find($id);
	
		return $this->render('PoeticForm/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(PoeticForm::class)->find($id);
		$form = $this->genericCreateForm($request->getLocale(), $entity);
	
		return $this->render('PoeticForm/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(PoeticForm::class)->find($id);

		$form = $this->genericCreateForm($request->getLocale(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $entity, $form);

		if(empty($entity->getFileManagement()) or $entity->getFileManagement()->getPhoto() == null) {
			$form->get("fileManagement")->get("id")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
		}

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('poeticformadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('PoeticForm/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("languages")
     */
	public function languageAction(EntityManagerInterface $em, Request $request)
	{
		$locale = $request->query->get("locale");
		$entities = $em->getRepository(PoeticForm::class)->findAllByLanguage($locale);
		
		$res = array();
		
		foreach($entities as $entity)
		{
			$res[] = array("id" => $entity->getId(), "name" => $entity->getTitle());
		}
		
		$response = new Response(json_encode($res));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}

	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(PoeticFormType::class, $entity, array('locale' => $locale));
	}

	private function checkForDoubloon(EntityManagerInterface $em, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(PoeticForm::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError('Cette entrée existe déjà !'));
		}
	}
}