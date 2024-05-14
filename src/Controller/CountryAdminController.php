<?php

namespace App\Controller;

use App\Entity\Country;
use App\Form\Type\CountryType;
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
 * @Route("/admin/country")
 */
class CountryAdminController extends AbstractController
{
    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Country/index.html.twig');
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

		$entities = $em->getRepository(Country::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository(Country::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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

			$show = $this->generateUrl('app_countryadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_countryadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a> - <a href="'.$edit.'" alt="Edit">'.$translator->trans('admin.index.Update').'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/new")
     */
    public function newAction(Request $request)
    {
		$entity = new Country();
        $form = $this->createForm(CountryType::class, $entity);

		return $this->render('Country/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Country();
        $form = $this->createForm(CountryType::class, $entity);
		$form->handleRequest($request);
		$this->checkForDoubloon($em, $translator, $entity, $form);

		if($entity->getFlag() == null or empty($entity->getFlag()["title"]) or empty($entity->getFlag()["content"]))
			$form->get("flag")["name"]->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if($form->isValid())
		{
			file_put_contents(Country::PATH_FILE.$entity->getFlag()["title"], $entity->getFlag()["content"]);
			$entity->setFlag($entity->getFlag()["title"]);

			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('app_countryadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Country/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Country::class)->find($id);
	
		return $this->render('Country/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Country::class)->find($id);
		$form = $this->createForm(CountryType::class, $entity);
	
		return $this->render('Country/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Country::class)->find($id);
		$currentImage = $entity->getFlag();
		$form = $this->createForm(CountryType::class, $entity);
		$form->handleRequest($request);
		$this->checkForDoubloon($em, $translator, $entity, $form);
		
		if($form->isValid())
		{
			if(!is_null($entity->getFlag()) and (!empty($entity->getFlag()["title"]) or !empty($entity->getFlag()["content"]))) {
				file_put_contents(Country::PATH_FILE.$entity->getFlag()["title"], $entity->getFlag()["content"]);
				$title = $entity->getFlag()["title"];
			}
			else
				$image = $currentImage;

			$entity->setFlag($entity->getFlag()["title"]);
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('app_countryadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Country/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/countries")
     */
	public function countriesAction(EntityManagerInterface $em, Request $request)
	{
		$entities = $em->getRepository(Country::class)->findAllByLanguage($request->query->get("locale"));
		
		$res = [];

		foreach($entities as $entity)
			$res[] = array("id" => $entity->getId(), "name" => $entity->getTitle());

		return new JsonResponse($res);
	}

	private function checkForDoubloon(EntityManagerInterface $em, TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(Country::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}