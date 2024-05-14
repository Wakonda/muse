<?php

namespace App\Controller;

use App\Entity\Store;
use App\Entity\Language;
use App\Entity\Biography;
use App\Form\Type\StoreType;
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
 * @Route("/admin/store")
 */
class StoreAdminController extends AbstractController
{
	private $formName = "store";

	/**
	 * @Route("/")
	 */
	public function indexAction(Request $request)
	{
		return $this->render('Store/index.html.twig');
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

		$entities = $em->getRepository(Store::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository(Store::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			
			$show = $this->generateUrl('storeadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('storeadmin_edit', array('id' => $entity->getId()));
			
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
		$entity = new Store();
		$entity->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Store/new.html.twig', array('form' => $form->createView()));
    }

	/**
	 * @Route("/create")
	 */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Store();
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $translator, $entity, $form);

		if($form->has("photo") and ($entity->getPhoto() == null or empty($entity->getPhoto()["title"]) or empty($entity->getPhoto()["content"])))
			$form->get("photo")["name"]->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if($form->isValid())
		{
			if(!empty($title = $entity->getPhoto()["title"]) and !empty($content = $entity->getPhoto()["content"]))
				file_put_contents(Store::PATH_FILE.$title, $content);

			if(empty($entity->getBiography()) and !empty($form->get("newBiography")->getData())) {
				$biography = new Biography();
				$biography->setTitle($form->get("newBiography")->getData());
				$biography->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $entity->getLanguage()->getAbbreviation()]));
				$em->persist($biography);
				$entity->setBiography($biography);
			}

			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('storeadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Store/new.html.twig', array('form' => $form->createView()));
	}

	/**
	 * @Route("/show/{id}")
	 */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Store::class)->find($id);
	
		return $this->render('Store/show.html.twig', array('entity' => $entity));
	}

	/**
	 * @Route("/edit/{id}")
	 */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Store::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);
	
		return $this->render('Store/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	/**
	 * @Route("/update/{id}")
	 */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Store::class)->find($id);

		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);
		
		$currentImage = $entity->getPhoto();
		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $translator, $entity, $form);

		if($form->isValid())
		{
			if(!is_null($entity->getPhoto()) and (!empty($entity->getPhoto()["title"]) or !empty($entity->getPhoto()["content"])))
			{
				file_put_contents(Store::PATH_FILE.$entity->getPhoto()["title"], $entity->getPhoto()["content"]);
				$title = $entity->getPhoto()["title"];
			}
			else
				$title = $currentImage;

			$entity->setPhoto($title);

			if(empty($entity->getBiography())) {
				$biography = new Biography();
				$biography->setTitle($form->get("newBiography")->getData());
				$biography->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $entity->getLanguage()->getAbbreviation()]));
				$em->persist($biography);
				$entity->setBiography($biography);
			}
			
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('storeadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Store/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(StoreType::class, $entity, array("locale" => $locale));
	}

	private function checkForDoubloon(EntityManagerInterface $em, $translator, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(Store::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}