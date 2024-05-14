<?php

namespace App\Controller;

use App\Entity\Page;
use App\Form\Type\PageType;
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
 * @Route("/admin/page")
 */
class PageAdminController extends AbstractController
{
    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Page/index.html.twig');
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

		$entities = $em->getRepository(Page::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository(Page::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			
			$show = $this->generateUrl('app_pageadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_pageadmin_edit', array('id' => $entity->getId()));
			
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
		$entity = new Page();
        $form = $this->genericCreateForm($entity);

		return $this->render('Page/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Page();
        $form = $this->genericCreateForm($entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $translator, $entity, $form);
		if($entity->getPhoto() == null or empty($entity->getPhoto()["title"]) or empty($entity->getPhoto()["content"]))
			$form->get("photo")["name"]->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if($form->isValid())
		{
			file_put_contents(Page::PATH_FILE.$entity->getPhoto()["title"], $entity->getPhoto()["content"]);
			$entity->setPhoto($entity->getPhoto()["title"]);
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('app_pageadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Page/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Page::class)->find($id);
	
		return $this->render('Page/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Page::class)->find($id);
		$form = $this->genericCreateForm($entity);
	
		return $this->render('Page/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Page::class)->find($id);
		$currentImage = $entity->getPhoto();
		$form = $this->genericCreateForm($entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $translator, $entity, $form);
		
		if($form->isValid())
		{
			if(!is_null($entity->getPhoto()) and (!empty($entity->getPhoto()["title"]) or !empty($entity->getPhoto()["content"])))
			{
				file_put_contents(Page::PATH_FILE.$entity->getPhoto()["title"], $entity->getPhoto()["content"]);
				$title = $entity->getPhoto()["title"];
			}
			else
				$title = $currentImage;

			$entity->setPhoto($title);
			$em->persist($entity);
			$em->flush();

			return $this->redirect($this->generateUrl('app_pageadmin_show', array('id' => $entity->getId())));
		}
	
		return $this->render('Page/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/upload_mce")
     */
	public function uploadImageMCEAction(Request $request)
	{
		$file = $request->files->get('image');
		$file->move(Page::PATH_FILE, $file->getClientOriginalName());
		
		$path = $request->getBaseUrl()."/".Page::PATH_FILE.$file->getClientOriginalName();
		
		return new Response(sprintf("<script>top.$('.mce-btn.mce-open').parent().find('.mce-textbox').val('%s');</script>", $path));
	}
	
	private function genericCreateForm($entity)
	{
		return $this->createForm(PageType::class, $entity);
	}
	
	private function checkForDoubloon(EntityManagerInterface $em, TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(Page::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}