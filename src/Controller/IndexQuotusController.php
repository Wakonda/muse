<?php

namespace App\Controller;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use App\Form\Type\QuoteUserType;
use App\Form\Type\IndexQuotusSearchType;
use App\Service\GenericFunction;

use App\Entity\Country;
use App\Entity\Page;
use App\Entity\Store;
use App\Entity\Quote;
use App\Entity\Source;
use App\Entity\QuoteImage;
use App\Entity\Language;
use App\Entity\Biography;
use App\Entity\Tag;

use Spipu\Html2Pdf\Html2Pdf;
use MatthiasMullie\Minify;

use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
/**
 * @Route(
 *     host="quotus.wakonda.{domain}",
 *     defaults={"domain": "%domain_name%"}
 * )
 */
class IndexQuotusController extends AbstractController
{
    /**
     * @Route("/", priority=10)
     */
    public function indexAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();

		$form = $this->createFormIndexSearch($request->getLocale(), null);
		$random = $entityManager->getRepository(Quote::class)->getRandom($request->getLocale());

        return $this->render('IndexQuotus/index.html.twig', ['form' => $form->createView(), 'random' => $random]);
    }

    /**
     * @Route("/random")
     */
    public function randomAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$random = $entityManager->getRepository(Quote::class)->getRandom($request->getLocale());

        return $this->render('IndexQuotus/random.html.twig', array('random' => $random));
    }

    /**
     * @Route("/change_language/{locale}")
     */
	public function changeLanguageAction(Request $request, $locale)
	{
		$request->getSession()->set('_locale', $locale);
		return $this->redirect($this->generateUrl('app_indexquotus_index'));
	}

    /**
     * @Route("/search")
     */
	public function searchAction(Request $request, TranslatorInterface $translator)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$search = $request->request->get("index_quotus_search", []);
		
		unset($search["_token"]);

		$criteria = $search;
		
		if($search['type'] == Biography::AUTHOR)
			$criteria['type'] = $translator->trans(Biography::AUTHOR_CANONICAL);
		elseif($search['type'] == Biography::FICTIONAL_CHARACTER)
			$criteria['type'] = $translator->trans(Biography::FICTIONAL_CHARACTER_CANONICAL);
		
		$criteria = array_filter(array_values($criteria));
		$criteria = empty($criteria) ? $translator->trans("search.result.None") : $criteria;

		return $this->render('IndexQuotus/resultIndexSearch.html.twig', ['search' => base64_encode(json_encode($search)), 'criteria' => $criteria]);
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
		$entities = $entityManager->getRepository(Quote::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Quote::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexquotus_read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getText().'</a>';
			$row[] = $entity->isBiographyAuthorType() ? $entity->getBiography()->getTitle() : $entity->getUser()->getUsername();

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/read/{id}/{slug}/{idImage}", defaults={"slug": null, "idImage": null})
     */
	public function readAction(Request $request, $id, $idImage)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		$image = (!empty($idImage)) ? $entityManager->getRepository(QuoteImage::class)->find($idImage) : null;
		
		$browsing = $entityManager->getRepository(Quote::class)->browsingShow($id);

		return $this->render('IndexQuotus/read.html.twig', array('entity' => $entity, 'browsing' => $browsing, 'image' => $image));
	}

    /**
     * @Route("/read_pdf/{id}/{slug}", defaults={"slug": null})
     */
	public function readPDFAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		
		if(empty($entity))
			throw $this->createNotFoundException('404');
		
		$content = $this->renderView('IndexQuotus/pdf.html.twig', array('entity' => $entity));

		$html2pdf = new Html2Pdf('P','A4','fr');
		$html2pdf->WriteHTML($content);
		$file = $html2pdf->Output('quote.pdf');

		$response = new Response($file);
		$response->headers->set('Content-Type', 'application/pdf');

		return $response;
	}

    /**
     * @Route("/byimages/{page}", defaults={"page": 1})
     */
	public function byImagesAction(Request $request, PaginatorInterface $paginator, $page)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$query = $entityManager->getRepository(QuoteImage::class)->getPaginator($request->getLocale());

		$pagination = $paginator->paginate(
			$query, /* query NOT result */
			$page, /*page number*/
			10 /*limit per page*/
		);
		
		$pagination->setCustomParameters(['align' => 'center']);
		
		return $this->render('IndexQuotus/byimage.html.twig', ['pagination' => $pagination]);
	}

    /**
     * @Route("/tag/{id}/{slug}", defaults={"slug": null})
     */
	public function tagAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Tag::class)->find($id);

		return $this->render('IndexQuotus/tag.html.twig', array('entity' => $entity));
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
		$entities = $entityManager->getRepository(Quote::class)->getEntityByTagDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $tagId);
		$iTotal = $entityManager->getRepository(Quote::class)->getEntityByTagDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $tagId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$show = $this->generateUrl('app_indexquotus_read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getText().'</a>';
			$row[] = $entity->isBiographyAuthorType() ? $entity->getBiography()->getTitle() : $entity->getUser()->getUsername();

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/bysources")
     */
	public function bySourcesAction(Request $request)
    {
        return $this->render('IndexQuotus/bysource.html.twig');
    }

    /**
     * @Route("/bysources_datatables")
     */
	public function bySourcesDatatablesAction(Request $request)
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

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Quote::class)->findQuoteBySource($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Quote::class)->findQuoteBySource($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		$gf = new GenericFunction();

		foreach($entities as $entity)
		{
			$row = array();
			
			$img = $gf->adaptImageSize(Source::PATH_FILE.$entity['source_photo']);

			$show = $this->generateUrl('app_indexquotus_source', array('id' => $entity['source_id'], 'slug' => $entity['source_slug']));
			$row[] = "<img src='".$img."' alt='".$entity['source_photo']."'>";
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity['source_title'].'</a>';

			$row[] = '<span class="badge badge-secondary">'.$entity['number_by_source'].'</span>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/source/{id}/{slug}", defaults={"slug": null})
     */
	public function sourceAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Source::class)->find($id);
		$stores = $entityManager->getRepository(Store::class)->findBy(["source" => $entity]);

		return $this->render('IndexQuotus/source.html.twig', array('entity' => $entity, "stores" => $stores));
	}

    /**
     * @Route("/source_datatables/{sourceId}")
     */
	public function sourceDatatablesAction(Request $request, TranslatorInterface $translator, $sourceId)
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

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Quote::class)->getQuoteBySourceDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $sourceId);
		$iTotal = $entityManager->getRepository(Quote::class)->getQuoteBySourceDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $sourceId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity["quote_text"];
			$row[] = $entity["quote_author"];
			$show = $this->generateUrl('app_indexquotus_read', array('id' => $entity["quote_id"], 'slug' => $entity["quote_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans("source.table.Read").'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/byauthors")
     */
	public function byAuthorsAction(Request $request)
    {
        return $this->render('IndexQuotus/byauthor.html.twig');
    }

    /**
     * @Route("/byauthors_datatables")
     */
	public function byAuthorsDatatablesAction(Request $request)
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

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Quote::class)->findQuoteByBiography(Biography::AUTHOR, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Quote::class)->findQuoteByBiography(Biography::AUTHOR, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		$gf = new GenericFunction();

		foreach($entities as $entity)
		{
			$row = array();
			
			$img = $gf->adaptImageSize(Biography::PATH_FILE.$entity['biography_photo']);

			$show = $this->generateUrl('app_indexquotus_author', array('id' => $entity['biography_id'], 'slug' => $entity['biography_slug']));
			$row[] = "<img src='".$img."' alt='".$entity['biography_photo']."'>";
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity['biography_title'].'</a>';

			$row[] = '<span class="badge badge-secondary">'.$entity['number_by_biography'].'</span>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/author/{id}/{slug}", defaults={"slug": null})
     */
	public function authorAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Biography::class)->find($id);
		$stores = $entityManager->getRepository(Store::class)->findBy(["biography" => $entity]);

		return $this->render('IndexQuotus/author.html.twig', array('entity' => $entity, 'stores' => $stores));
	}

    /**
     * @Route("/author_datatables/{biographyId}")
     */
	public function authorDatatablesAction(Request $request, TranslatorInterface $translator, $biographyId)
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

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Quote::class)->getQuoteByBiographyDatatables(Biography::AUTHOR, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $biographyId);
		$iTotal = $entityManager->getRepository(Quote::class)->getQuoteByBiographyDatatables(Biography::AUTHOR, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $biographyId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity["quote_text"];
			$row[] = !empty($entity["source_id"]) ? '<u><a href="'.$this->generateUrl("app_indexquotus_source", ['id' => $entity["source_id"], 'slug' => $entity["source_slug"]]).'">'.$entity["source_text"].'</a></u>' : "-";
			$show = $this->generateUrl('app_indexquotus_read', array('id' => $entity["quote_id"], 'slug' => $entity["quote_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans("biography.table.Read").'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/byfictionalcharacters")
     */
	public function byFictionalCharactersAction(Request $request)
    {
        return $this->render('IndexQuotus/byfictionalcharacter.html.twig');
    }

    /**
     * @Route("/byfictionalcharacters_datatables")
     */
	public function byFictionalCharactersDatatablesAction(Request $request)
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

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Quote::class)->findQuoteByBiography(Biography::FICTIONAL_CHARACTER, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Quote::class)->findQuoteByBiography(Biography::FICTIONAL_CHARACTER, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		$gf = new GenericFunction();

		foreach($entities as $entity)
		{
			$row = array();
			
			$img = $gf->adaptImageSize(Biography::PATH_FILE.$entity['biography_photo']);

			$show = $this->generateUrl('app_indexquotus_fictionalcharacter', array('id' => $entity['biography_id'], 'slug' => $entity['biography_slug']));
			$row[] = "<img src='".$img."' alt='".$entity['biography_photo']."'>";
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity['biography_title'].'</a>';

			$row[] = '<span class="badge badge-secondary">'.$entity['number_by_biography'].'</span>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/fictionalcharacter/{id}/{slug}", defaults={"slug": null})
     */
	public function fictionalCharacterAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Biography::class)->find($id);

		return $this->render('IndexQuotus/fictionalcharacter.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/fictionalcharacter_datatables/{biographyId}")
     */
	public function fictionalCharacterDatatablesAction(Request $request, TranslatorInterface $translator, $biographyId)
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

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Quote::class)->getQuoteByBiographyDatatables(Biography::FICTIONAL_CHARACTER, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $biographyId);
		$iTotal = $entityManager->getRepository(Quote::class)->getQuoteByBiographyDatatables(Biography::FICTIONAL_CHARACTER, $iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $biographyId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity["quote_text"];
			$row[] = !empty($entity["source_id"]) ? '<u><a href="'.$this->generateUrl("app_indexquotus_source", ['id' => $entity["source_id"], 'slug' => $entity["source_slug"]]).'">'.$entity["source_text"].'</a></u>' : "-";
			$show = $this->generateUrl('app_indexquotus_read', array('id' => $entity["quote_id"], 'slug' => $entity["quote_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans("fictionalCharacter.table.Read").'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/byusers")
     */
	public function byUsersAction(Request $request)
    {
        return $this->render('IndexQuotus/byuser.html.twig');
    }

    /**
     * @Route("/byusers_datatables")
     */
	public function byUsersDatatablesAction(Request $request)
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

		$entities = $entityManager->getRepository(Quote::class)->findQuoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Quote::class)->findQuoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

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

				$show = $this->generateUrl('app_indexquotus_read', array('id' => $entity['id'], 'slug' => $entity['slug']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['text'].'</a>';

				$show = $this->generateUrl('app_user_show', array('username' => $entity['username']));
				$row[] = '<a href="'.$show.'" alt="Show">'.$entity['username'].'</a>';

				$output['aaData'][] = $row;
			}
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/download_image/{fileName}")
     */
	public function downloadImageAction($fileName)
	{
		$response = new BinaryFileResponse(Quote::PATH_FILE.$fileName);
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
		return $response;
	}

	public function lastAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Quote::class)->getLastEntries($request->getLocale());

		return $this->render('IndexQuotus/last.html.twig', array('entities' => $entities));
    }

	public function statAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$statistics = $entityManager->getRepository(Quote::class)->getStat($request->getLocale());

		return $this->render('IndexQuotus/stat.html.twig', array('statistics' => $statistics));
    }

    /**
     * @Route("/quoteuser/new")
     */
	public function quoteUserNewAction(Request $request)
	{
		$form = $this->createForm(QuoteUserType::class, null);

		return $this->render("IndexQuotus/quoteUserNew.html.twig", array("form" => $form->createView()));
	}

    /**
     * @Route("/quoteuser/create")
     */
	public function quoteUserCreateAction(Request $request, TokenStorageInterface $tokenStorage)
	{
		$entity = new Quote();
		$form = $this->createForm(QuoteUserType::class, $entity);
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

			$entityManager = $this->getDoctrine()->getManager();
			$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));
			$entity->setText(nl2br($entity->getText()));

			$entityManager->persist($entity);
			$entityManager->flush();

			return $this->redirect($this->generateUrl('app_user_show', array('id' => $user->getId())));
		}
		
		return $this->render('IndexQuotus/quoteUserNew.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/quoteuser/edit/{id}")
     */
	public function quoteUserEditAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		$entity->setText(strip_tags($entity->getText()));
		
		$form = $this->createForm(QuoteUserType::class, $entity);

		return $this->render("IndexQuotus/quoteUserEdit.html.twig", ["form" => $form->createView(), "entity" => $entity]);
	}

    /**
     * @Route("/quoteuser/update/{id}")
     */
	public function quoteUserUpdateAction(Request $request, TokenStorageInterface $tokenStorage, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id, true);
		$form = $this->createForm(QuoteUserType::class, $entity);
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
			
			$language = $entityManager->getRepository(Language::class)->findOneBy(['abbreviation' => $request->getLocale()]);

			$entity->setLanguage($language->getId());
			
			$entityManager->persist($entity);
			$entityManager->flush();

			return $this->redirect($this->generateUrl('app_user_show', array('id' => $user->getId())));
		}
		
		return $this->render('IndexQuotus/quoteUserEdit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/quoteuser/delete")
     */
	public function quoteUserDeleteAction(Request $request, TokenStorageInterface $tokenStorage)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$id = $request->query->get("id");
		
		$entity = $entityManager->getRepository(Quote::class)->find($id, false);
		$entity->setState(2);
		
		$entity->setText(nl2br($entity->getText()));
		$user = $tokenStorage->getToken()->getUser();

		$entity->setUser($user);

		$entityManager->persist($entity);
		$entityManager->flush();
		
		return new Response();
	}

	private function createFormIndexSearch($locale, $entity)
	{
		return $this->createForm(IndexQuotusSearchType::class, null, ["locale" => $locale]);
	}
}