<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Spipu\Html2Pdf\Html2Pdf;

use App\Entity\Poem;
use App\Entity\PoemImage;
use App\Entity\Biography;
use App\Entity\Store;
use App\Entity\User;
use App\Entity\Language;
use App\Entity\Country;
use App\Entity\PoeticForm;
use App\Entity\Source;
use App\Entity\Version;
use App\Entity\Tag;

use App\Form\Type\PoemUserType;
use App\Form\Type\IndexPoeticusSearchType;

use App\Service\GenericFunction;

use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(
 *     host="poeticus.wakonda.{domain}",
 *     defaults={"domain": "%domain_name%"}
 * )
 */
class IndexPoeticusController extends AbstractController
{
    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();

		$form = $this->createFormIndexSearch($request->getLocale(), null);
		$random = $entityManager->getRepository(Poem::class)->getRandomPoem($request->getLocale());

		return $this->render('IndexPoeticus/index.html.twig', ['form' => $form->createView(), 'random' => $random]);
	}

    /**
     * @Route("/random")
     */
    public function randomAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$random = $entityManager->getRepository(Poem::class)->getRandomPoem($request->getLocale());

        return $this->render('IndexPoeticus/random.html.twig', array('random' => $random));
    }

    /**
     * @Route("/change_language/{locale}")
     */
	public function changeLanguageAction(Request $request, $locale)
	{
		$request->getSession()->set('_locale', $locale);
		return $this->redirect($this->generateUrl('app_indexpoeticus_index'));
	}

    /**
     * @Route("/search")
     */
	public function searchAction(Request $request, TranslatorInterface $translator)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$search = $request->request->get("index_search", []);
		
		unset($search["_token"]);

		$criteria = $search;
		
		if(isset($search['type'])) {
			if($search['type'] == "biography")
				$criteria['type'] =  $translator->trans('main.field.GreatWriters');
			elseif($search['type'] == "user")
				$criteria['type'] =  $translator->trans('main.field.YourPoems');
		}

		$criteria['country'] = (empty($search['country'])) ? null : $entityManager->getRepository(Country::class)->find($search['country'])->getTitle();
		$criteria = array_filter(array_values($criteria));
		$criteria = empty($criteria) ? $translator->trans("search.result.None") : $criteria;

		return $this->render('IndexPoeticus/resultIndexSearch.html.twig', ['search' => base64_encode(json_encode($search)), 'criteria' => $criteria]);
	}

    /**
     * @Route("/result_search/{search}")
     */
	public function searchDatatablesAction(Request $request, $search)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i < intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}
		$sSearch = json_decode(base64_decode($search));
		$entities = $entityManager->getRepository(Poem::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Poem::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexpoeticus_read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getTitle().'</a>';
			$row[] =$entity->getBiography()->getTitle();

			$country = $entity->getCountry();
			$row[] = '<img src="'.$request->getBaseUrl().'/'.Country::PATH_FILE.$country->getFlag().'" class="flag">';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/read/{id}/{slug}", defaults={"slug": null})
     */
	public function readAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Poem::class)->find($id);
		
		if(empty($entity))
			throw $this->createNotFoundException('404');

		$image = (!empty($idImage)) ? $entityManager->getRepository(PoemImage::class)->find($idImage) : null;
		
		$params = array();
		
		if($entity->isBiography()) {
			$biography = $entity->getBiography();
			$params["author"] = $biography->getId();
			$params["field"] = "biography";
		}
		else {
			$params["author"] = $entity->getUser()->getId();
			$params["field"] = "user";			
		}

		$browsingPoems = $entityManager->getRepository(Poem::class)->browsingPoemShow($params, $id);

		return $this->render('IndexPoeticus/read.html.twig', ['entity' => $entity, 'browsingPoems' => $browsingPoems, 'image' => $image]);
	}

    /**
     * @Route("/byimages", defaults={"page": 1})
     */
	public function byImagesAction(Request $request, PaginatorInterface $paginator, $page)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$query = $entityManager->getRepository(PoemImage::class)->getPaginator($request->getLocale());

		$pagination = $paginator->paginate(
			$query, /* query NOT result */
			$page, /*page number*/
			10 /*limit per page*/
		);
		
		$pagination->setCustomParameters(['align' => 'center']);
		
		return $this->render('IndexPoeticus/byimage.html.twig', ['pagination' => $pagination]);
	}

    /**
     * @Route("/read_pdf/{id}/{slug}", defaults={"slug": null})
     */
	public function readPDFAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Poem::class)->find($id);
		
		if(empty($entity))
			throw $this->createNotFoundException('404');
		
		$content = $this->renderView('IndexPoeticus/pdf_poem.html.twig', array('entity' => $entity));

		$html2pdf = new Html2Pdf('P','A4','fr');
		$html2pdf->WriteHTML($content);
		$file = $html2pdf->Output('poem.pdf');

		$response = new Response($file);
		$response->headers->set('Content-Type', 'application/pdf');

		return $response;
	}

    /**
     * @Route("/author/{id}/{slug}", defaults={"slug": null})
     */
	public function authorAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Biography::class)->find($id);
		$stores = $entityManager->getRepository(Store::class)->findBy(["biography" => $entity]);

		return $this->render('IndexPoeticus/author.html.twig', array('entity' => $entity, "stores" => $stores));
	}

    /**
     * @Route("/author_datatables/{authorId}")
     */
	public function authorDatatablesAction(Request $request, $authorId)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i < intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Poem::class)->getPoemByAuthorDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $authorId);
		$iTotal = $entityManager->getRepository(Poem::class)->getPoemByAuthorDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $authorId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexpoeticus_read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getTitle().'</a>';

			$collection = $entity->getCollection();
			
			if(!empty($collection))
			{
				$show = $this->generateUrl('app_indexpoeticus_collection', array('id' => $collection->getId(), 'slug' => $collection->getSlug()));
				$row[] = '<a class="underline italic" href="'.$show.'" alt="Show">'.$collection->getTitle().'</a>';
			}
			else
				$row[] = "-";
			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}
	
	public function lastAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Poem::class)->getLastEntries($request->getLocale());

		return $this->render('IndexPoeticus/lastPoem.html.twig', array('entities' => $entities));
    }

	public function statAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$statistics = $entityManager->getRepository(Poem::class)->getStat($request->getLocale());

		return $this->render('IndexPoeticus/statPoem.html.twig', array('statistics' => $statistics));
    }

    /**
     * @Route("/byauthors")
     */
	public function byAuthorsAction()
    {
        return $this->render('IndexPoeticus/byauthor.html.twig');
    }

    /**
     * @Route("/byauthors_datatables")
     */
	public function byAuthorsDatatablesAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
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

		$entities = $entityManager->getRepository(Poem::class)->findPoemByAuthor($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Poem::class)->findPoemByAuthor($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		$gf = new GenericFunction();

		foreach($entities as $entity)
		{
			if(!empty($entity['id']))
			{
				$img = $gf->adaptImageSize(Biography::PATH_FILE.$entity["photo"]);
				$row = array();
				$show = $this->generateUrl('app_indexpoeticus_author', array('id' => $entity['id'], 'slug' => $entity['slug']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['author'].'</a>';
				$row[] = "<img src='".$img."' alt='".$entity['author']."'>";
				$row[] = '<span class="badge badge-secondary">'.$entity['number_poems_by_author'].'</span>';

				$output['aaData'][] = $row;
			}
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/tag/{id}/{slug}", defaults={"slug": null})
     */
	public function tagAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Tag::class)->find($id);

		return $this->render('IndexPoeticus/tag.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/tag_datatables/{tagId}")
     */
	public function tagDatatablesAction(Request $request, $tagId)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i < intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Poem::class)->getPoemByTagDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $tagId);
		$iTotal = $entityManager->getRepository(Poem::class)->getPoemByTagDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $tagId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexpoeticus_read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getTitle().'</a>';

			$collection = $entity->getCollection();
			
			if(!empty($collection))
			{
				$show = $this->generateUrl('app_indexpoeticus_collection', array('id' => $collection->getId(), 'slug' => $collection->getSlug()));
				$row[] = '<a class="underline italic" href="'.$show.'" alt="Show">'.$collection->getTitle().'</a>';
			}
			else
				$row[] = "-";
			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/poeticform/{id}/{slug}", defaults={"slug": null})
     */
	public function poeticFormAction($id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(PoeticForm::class)->find($id);
		
		return $this->render('IndexPoeticus/poeticForm.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/poeticform_datatables/{poeticformId}")
     */
	public function poeticFormDatatablesAction(Request $request, $poeticformId)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i < intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entities = $entityManager->getRepository(Poem::class)->getPoemByPoeticFormDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $poeticformId);
		$iTotal = $entityManager->getRepository(Poem::class)->getPoemByPoeticFormDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $poeticformId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexpoeticus_read', array('id' => $entity["poem_id"], 'slug' => $entity['slug']));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity["poem_title"].'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/bypoeticforms")
     */
	public function byPoeticFormsAction()
    {
        return $this->render('IndexPoeticus/bypoeticform.html.twig');
    }

    /**
     * @Route("/bypoeticforms_datatables")
     */
	public function byPoeticFormsDatatablesAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i < intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entities = $entityManager->getRepository(Poem::class)->findPoemByPoeticForm($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Poem::class)->findPoemByPoeticForm($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();

			if(!empty($entity['poeticform_id']))
			{
				$show = $this->generateUrl('app_indexpoeticus_poeticform', array('id' => $entity['poeticform_id'], 'slug' => $entity['poeticform_slug']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['poeticform'].'</a>';
			}
			else
				$row[] = "-";

			$row[] = '<span class="badge badge-secondary">'.$entity['number_poems_by_poeticform'].'</span>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/collection/{id}/{slug}", defaults={"slug": null})
     */
	public function collectionAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Source::class)->find($id);

		return $this->render('IndexPoeticus/collection.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/collection_datatables/{collectionId}")
     */
	public function collectionDatatablesAction(Request $request, $collectionId)
	{
		$entityManager = $this->getDoctrine()->getManager();
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

		$entities = $entityManager->getRepository(Poem::class)->getPoemByCollectionDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $collectionId);
		$iTotal = $entityManager->getRepository(Poem::class)->getPoemByCollectionDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $collectionId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexpoeticus_read', array('id' => $entity["poem_id"], 'slug' => $entity['slug']));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity["poem_title"].'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/bycollections")
     */
	public function byCollectionsAction(Request $request)
    {
        return $this->render('IndexPoeticus/bycollection.html.twig');
    }

    /**
     * @Route("/bycollections_datatables")
     */
	public function byCollectionsDatatablesAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
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

		$entities = $entityManager->getRepository(Poem::class)->findPoemByCollection($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Poem::class)->findPoemByCollection($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();

			if(!empty($entity['collection_id']))
			{
				$show = $this->generateUrl('app_indexpoeticus_collection', array('id' => $entity['collection_id'], 'slug' => $entity['collection_slug']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['collection'].'</a>';
			}
			else
				$row[] = "-";

			if(!empty($entity['author_id']))
			{
				$show = $this->generateUrl('app_indexpoeticus_author', array('id' => $entity['author_id'], 'slug' => $entity['author_slug']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['author'].'</a>';
			}
			else
				$row[] = "-";

			$row[] = '<span class="badge badge-secondary">'.$entity['number_poems_by_collection'].'</span>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/collection_pdf/{collectionId}")
     */
	public function readCollectionPDFAction(Request $request, $collectionId)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$collection = $entityManager->getRepository(Source::class)->find($collectionId);
		$biography = $collection->getAuthors()->first();
		$entities = $entityManager->getRepository(Poem::class)->getAllPoemsByCollectionAndAuthorForPdf($collectionId);

		$content = $this->renderView('IndexPoeticus/pdf_poem_collection.html.twig', array('biography' => $biography, 'collection' => $collection, 'entities' => $entities));

		$html2pdf = new Html2Pdf('P','A4','fr');
		$html2pdf->WriteHTML($content);
		$html2pdf->createIndex('Sommaire', 25, 12, false, true, null, "times");
		
		$file = $html2pdf->Output('poem.pdf');

		$response = new Response($file);
		$response->headers->set('Content-Type', 'application/pdf');

		return $response;
	}

    /**
     * @Route("/country/{id}/{slug}", defaults={"slug": null})
     */
	public function countryAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Country::class)->find($id);

		return $this->render('IndexPoeticus/country.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/country_datatables/{countryId}")
     */
	public function countryDatatablesAction(Request $request, $countryId)
	{
		$entityManager = $this->getDoctrine()->getManager();
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

		$entities = $entityManager->getRepository(Poem::class)->getPoemByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId);
		$iTotal = $entityManager->getRepository(Poem::class)->getPoemByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexpoeticus_read', array('id' => $entity["poem_id"], 'slug' => $entity["poem_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity["poem_title"].'</a>';
			
			$show = $this->generateUrl('app_indexpoeticus_author', array('id' => $entity["biography_id"], 'slug' => $entity['biography_slug']));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity["biography_title"].'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/bycountries")
     */
	public function byCountriesAction(Request $request)
    {
        return $this->render('IndexPoeticus/bycountry.html.twig');
    }

    /**
     * @Route("/bycountries_datatables")
     */
	public function byCountriesDatatablesAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
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

		$entities = $entityManager->getRepository(Poem::class)->findPoemByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Poem::class)->findPoemByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();

			$show = $this->generateUrl('app_indexpoeticus_country', array('id' => $entity['country_id'], 'slug' => $entity['country_slug']));
			$row[] = '<a href="'.$show.'" alt="Show"><img src="'.$request->getBaseUrl().'/'.Country::PATH_FILE.$entity['flag'].'" class="flag" /> '.$entity['country_title'].'</a>';

			$row[] = '<span class="badge badge-secondary">'.$entity['number_poems_by_country'].'</span>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/bypoemusers")
     */
	public function byPoemUsersAction(Request $request)
    {
        return $this->render('IndexPoeticus/bypoemuser.html.twig');
    }

    /**
     * @Route("/bypoemusers_datatables")
     */
	public function byPoemUsersDatatablesAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i < intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entities = $entityManager->getRepository(Poem::class)->findPoemByPoemUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Poem::class)->findPoemByPoemUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			if(!empty($entity['id']))
			{
				$row = array();

				$show = $this->generateUrl('app_indexpoeticus_read', array('id' => $entity['id'], 'slug' => $entity['slug']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['title'].'</a>';

				$show = $this->generateUrl('app_user_show', array('username' => $entity['username']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['username'].'</a>';

				$output['aaData'][] = $row;
			}
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/version")
     */
	public function versionAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$language = $entityManager->getRepository(Language::class)->findOneBy(['abbreviation' => $request->getLocale()]);
		$entities = $entityManager->getRepository(Version::class)->findBy(["language" => $language]);

		return $this->render('IndexPoeticus/version.html.twig', array('entities' => $entities));
	}

    /**
     * @Route("/poemuser/new")
     */
	public function poemUserNewAction(Request $request)
	{
		$form = $this->createForm(PoemUserType::class, null);

		return $this->render("IndexPoeticus/poemUserNew.html.twig", array("form" => $form->createView()));
	}

    /**
     * @Route("/poemuser/create")
     */
	public function poemUserCreateAction(Request $request, TokenStorageInterface $tokenStorage)
	{
		$entity = new Poem();
		$form = $this->createForm(PoemUserType::class, $entity);
		$form->handleRequest($request);
		
		if(array_key_exists("draft", $request->request->get($form->getName())))
			$entity->setState(1);
		else
			$entity->setState(0);
		
		if($form->isValid())
		{
			$user = $tokenStorage->getToken()->getUser();

			$entity->setUser($user);
			$entity->setAuthorType("user");
			$entity->setCountry($user->getCountry());
			
			$now = new \DateTime();
			$entity->setReleasedDate($now->format('Y'));
			$entityManager = $this->getDoctrine()->getManager();
			$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));
			$entity->setText(nl2br($entity->getText()));

			$entityManager->persist($entity);
			$entityManager->flush();

			return $this->redirect($this->generateUrl('app_user_show', array('id' => $user->getId())));
		}
		
		return $this->render('IndexPoeticus/poemUserNew.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/poemuser/edit/{id}")
     */
	public function poemUserEditAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Poem::class)->find($id);
		$entity->setText(strip_tags($entity->getText()));
		
		$form = $this->createForm(PoemUserType::class, $entity);

		return $this->render("IndexPoeticus/poemUserEdit.html.twig", ["form" => $form->createView(), "entity" => $entity]);
	}

    /**
     * @Route("/poemuser/update/{id}")
     */
	public function poemUserUpdateAction(Request $request, TokenStorageInterface $tokenStorage, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Poem::class)->find($id);
		$form = $this->createForm(PoemUserType::class, $entity);
		$form->handleRequest($request);

		if(array_key_exists("draft", $request->request->get($form->getName())))
			$entity->setState(1);
		else
			$entity->setState(0);
		
		if($form->isValid())
		{
			$entity->setText(nl2br($entity->getText()));

			$user = $tokenStorage->getToken()->getUser();

			$entity->setUser($user);
			$entity->setCountry($user->getCountry());
			
			$language = $entityManager->getRepository(Language::class)->findOneBy(['abbreviation' => $request->getLocale()]);

			$entity->setLanguage($language->getId());
			
			$entityManager->persist($entity);
			$entityManager->flush();

			return $this->redirect($this->generateUrl('app_user_show', array('id' => $user->getId())));
		}
		
		return $this->render('IndexPoeticus/poemUserEdit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/poemuser/delete")
     */
	public function poemUserDeleteAction(Request $request, TokenStorageInterface $tokenStorage)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$id = $request->query->get("id");
		
		$entity = $entityManager->getRepository(Poem::class)->find($id, false);
		$entity->setState(2);
		
		$entity->setText(nl2br($entity->getText()));
		$user = $tokenStorage->getToken()->getUser();

		$entity->setUser($user);

		$entityManager->persist($entity);
		$entityManager->flush();
		
		return new Response();
	}

    /**
     * @Route("/download_image/{fileName}")
     */
	public function downloadImageAction($fileName)
	{
		$response = new BinaryFileResponse(Poem::PATH_FILE.$fileName);
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
		return $response;
	}

	private function createFormIndexSearch($locale, $entity)
	{
		return $this->createForm(IndexPoeticusSearchType::class, null, ["locale" => $locale]);
	}
}