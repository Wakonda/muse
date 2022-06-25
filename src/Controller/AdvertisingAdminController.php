<?php

namespace App\Controller;

use App\Entity\Advertising;
use App\Entity\Language;
use App\Form\Type\AdvertisingType;
use App\Service\GenericFunction;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/advertising")
 */
class AdvertisingAdminController extends AbstractController
{
	private $formName = "advertising";

    /**
     * @Route("/")
     */
	public function indexAction()
	{
		return $this->render('Advertising/index.html.twig');
	}

    /**
     * @Route("/datatables")
     */
	public function indexDatatablesAction(Request $request, TranslatorInterface $translator)
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

		$entityManager = $this->getDoctrine()->getManager();
		
		$entities = $entityManager->getRepository(Advertising::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state);
		$iTotal = $entityManager->getRepository(Advertising::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state, true);

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
			
			$show = $this->generateUrl('app_advertisingadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_advertisingadmin_edit', array('id' => $entity->getId()));
			
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
    public function newAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Advertising();
		$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Advertising/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(Request $request, TranslatorInterface $translator)
	{
		$entity = new Advertising();
		$locale = $request->request->get($this->formName)["language"];
		$language = $this->getDoctrine()->getManager()->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);

		if($form->isValid())
		{
			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();

			return $this->redirect($this->generateUrl('app_advertisingadmin_show', array('id' => $entity->getId())));
		}
		
		return $this->render('Advertising/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Advertising::class)->find($id);
	
		return $this->render('Advertising/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Advertising::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);
	
		return $this->render('Advertising/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(Request $request, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Advertising::class)->find($id);
		
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);

		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		if($form->isValid())
		{
			$entityManager->persist($entity);
			$entityManager->flush();

			return $this->redirect($this->generateUrl('app_advertisingadmin_show', array('id' => $entity->getId())));
		}
	
		return $this->render('Advertising/edit.html.twig', ['form' => $form->createView(), 'entity' => $entity]);
	}

    /**
     * @Route("/upload_image")
     */
    public function WYSIWYGUploadFileGenericAction(Request $request)
    {
		$file = $request->files->get('image');
		$file->move(Advertising::PATH_FILE, $file->getClientOriginalName());
		
		$path = $request->getBaseUrl()."/".Advertising::PATH_FILE.$file->getClientOriginalName();
		
		return new Response(sprintf("<script>top.$('.mce-btn.mce-open').parent().find('.mce-textbox').val('%s');</script>", $path));
    }
	
	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(AdvertisingType::class, $entity, array("locale" => $locale));
	}
}