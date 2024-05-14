<?php

namespace App\Controller;

use App\Entity\Biography;
use App\Entity\Language;
use App\Form\Type\BiographyType;
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
 * @Route("/admin/biography")
 */
class BiographyAdminController extends AbstractController
{
	private $formName = "biography";

    /**
     * @Route("/")
     */
	public function indexAction()
	{
		return $this->render('Biography/index.html.twig');
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

		$entities = $em->getRepository(Biography::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state);
		$iTotal = $em->getRepository(Biography::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state, true);

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
			
			$show = $this->generateUrl('app_biographyadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_biographyadmin_edit', array('id' => $entity->getId()));
			
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
		$entity = new Biography();
		$entity->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Biography/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Biography();
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);

		$this->checkForDoubloon($em, $translator, $entity, $form);

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			return $this->redirect($this->generateUrl('app_biographyadmin_show', array('id' => $entity->getId())));
		}

		return $this->render('Biography/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Biography::class)->find($id);

		return $this->render('Biography/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Biography::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);
	
		return $this->render('Biography/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Biography::class)->find($id);
		
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $translator, $entity, $form);
		
		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			return $this->redirect($this->generateUrl('app_biographyadmin_show', array('id' => $entity->getId())));
		}
	
		return $this->render('Biography/edit.html.twig', ['form' => $form->createView(), 'entity' => $entity]);
	}

    /**
     * @Route("/biographies")
     */
	public function getBiographiesByAjaxAction(EntityManagerInterface $em, Request $request)
	{
		$rq = $request->getMethod() == Request::METHOD_GET ? $request->query : $request->request;
		$locale = $rq->get("language");
		
		$rsp = new Response();
		$rsp->headers->set('Content-Type', 'application/json');

		if($rq->has("pkey_val")) {
			$pkeyVal = $rq->has("pkey_val");
			
			if(empty($pkeyVal))
			{
				$rsp->setContent([]);
				return $rsp;
			}

			$parameters = array("pkey_val" => $rq->get("pkey_val"));
			$response =  $em->getRepository(Biography::class)->getDatasCombobox($parameters, $locale);

			$resObj = new \stdClass();
			$resObj->id = $response["id"];
			$resObj->name = $response["title"];

			$rsp->setContent(json_encode($resObj));
			return $rsp;
		}

		if($rq->has("q_word")) {
			$parameters = array(
			  'page_num'     => $rq->get('page_num'),
			  'per_page'     => $rq->get('per_page'),
			  'q_word'       => $rq->get('q_word')
			);
		} else {
			$parameters = array(
			  'page_num'     => 1,
			  'per_page'     => $rq->get('page_limit'),
			  'q_word'       => $rq->get('q')
			);
		}

		$response =  $em->getRepository(Biography::class)->getDatasCombobox($parameters, $locale);
		$count =  $em->getRepository(Biography::class)->getDatasCombobox($parameters, $locale, true);

		if($rq->has("q_word")) {
			$results = [];

			foreach($response as $res) {
				$obj = new \stdClass();
				$obj->id = $res['id'];
				$obj->name = $res['title'];
				
				$results[] = $obj;
			}

			$resObj = new \stdClass();
			$resObj->result = $results;
			$resObj->cnt_whole = $count;
		} else {
			$resObj = [];
			foreach($response as $res) {
				$resObj["results"][] = [
					"id" => $res['id'],
					"text" => $res['title']
				];
			}
		}

		$rsp->setContent(json_encode($resObj));
		return $rsp;
	}
	
	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(BiographyType::class, $entity, array("locale" => $locale));
	}
	
	private function checkForDoubloon(EntityManagerInterface $em, TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(Biography::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}