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
use Doctrine\ORM\EntityManagerInterface;

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

		$entities = $em->getRepository(Advertising::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state);
		$iTotal = $em->getRepository(Advertising::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $state, true);

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
    public function newAction(EntityManagerInterface $em, Request $request)
    {
		$entity = new Advertising();
		$entity->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Advertising/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Advertising();
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

			return $this->redirect($this->generateUrl('app_advertisingadmin_show', array('id' => $entity->getId())));
		}
		
		return $this->render('Advertising/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Advertising::class)->find($id);
	
		return $this->render('Advertising/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Advertising::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);
	
		return $this->render('Advertising/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Advertising::class)->find($id);
		
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();

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
		return $this->createForm(AdvertisingType::class, $entity, ["locale" => $locale]);
	}
}