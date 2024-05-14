<?php

namespace App\Controller;

use App\Entity\Version;
use App\Form\Type\VersionType;
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
 * @Route("/admin/version")
 */
class VersionAdminController extends AbstractController
{
    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Version/index.html.twig');
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

		$entities = $em->getRepository(Version::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository(Version::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			$row[] = $entity->getVersionNumber();
			$row[] = $entity->getReleaseDate()->format('d/m/Y');
			$row[] = $entity->getLanguage()->getTitle();
			
			$show = $this->generateUrl('versionadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('versionadmin_edit', array('id' => $entity->getId()));
			
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
		$entity = new Version();
        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Version/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Version();
        $form = $this->genericCreateForm($request->getLocale(), $entity);
		
		$form->handleRequest($request);
		$this->checkForDoubloon($em, $translator, $entity, $form);
		
		if($entity->getFile() == null)
			$form->get("file")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if($form->isValid())
		{
			$gf = new GenericFunction();
			$image = $gf->getUniqCleanNameForFile($entity->getFile());
			$entity->getFile()->move(Version::PATH_FILE, $image);
			$entity->setFile($image);
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('versionadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Version/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Version::class)->find($id);

		return $this->render('Version/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Version::class)->find($id);
		$form = $this->genericCreateForm($request->getLocale(), $entity);
	
		return $this->render('Version/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Version::class)->find($id);
		$currentImage = $entity->getFile();
		$form = $this->genericCreateForm($request->getLocale(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $translator, $entity, $form);
		
		if($form->isValid())
		{
			if(!is_null($entity->getFile()))
			{
				$gf = new GenericFunction();
				$image = $gf->getUniqCleanNameForFile($entity->getFile());
				$entity->getPhoto()->move(Version::PATH_FILE, $image);
			}
			else
				$image = $currentImage;

			$entity->setFile($image);
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('versionadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Version/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}
	
	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(VersionType::class, $entity, array('locale' => $locale));
	}
	
	private function checkForDoubloon(EntityManagerInterface $em, TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getVersionNumber() != null)
		{
			$checkForDoubloon = $em->getRepository(Version::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("versionNumber")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}