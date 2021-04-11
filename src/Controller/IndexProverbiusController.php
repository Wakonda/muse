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
use Symfony\Contracts\Translation\TranslatorInterface;

use App\Form\Type\IndexProverbiusSearchType;

use App\Entity\Proverb;
use App\Entity\ProverbImage;
use App\Entity\Country;
use App\Entity\Store;
use App\Entity\Language;
use App\Entity\Biography;
use App\Entity\Tag;

use Spipu\Html2Pdf\Html2Pdf;
use MatthiasMullie\Minify;

use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(
 *     host="proverbius.wakonda.{domain}",
 *     defaults={"domain": "%domain_name%"}
 * )
 */
class IndexProverbiusController extends AbstractController
{
    /**
     * @Route("/", priority=10)
     */
    public function indexAction(Request $request)
    {
		$form = $this->createFormIndexSearch($request->getLocale(), null);
		$entityManager = $this->getDoctrine()->getManager();
		$random = $entityManager->getRepository(Proverb::class)->getRandomProverb($request->getLocale());

        return $this->render('IndexProverbius/index.html.twig', array('form' => $form->createView(), 'random' => $random));
    }

    /**
     * @Route("/random")
     */
    public function randomAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$random = $entityManager->getRepository(Proverb::class)->getRandomProverb($request->getLocale());

        return $this->render('IndexProverbius/random.html.twig', array('random' => $random));
    }

    /**
     * @Route("/change_language/{locale}")
     */
	public function changeLanguageAction(Request $request, $locale)
	{
		$request->getSession()->set('_locale', $locale);
		return $this->redirect($this->generateUrl('app_indexproverbius_index'));
	}

    /**
     * @Route("/search")
     */
	public function searchAction(Request $request, TranslatorInterface $translator)
	{
		$search = $request->request->get("index_search", []);
		$entityManager = $this->getDoctrine()->getManager();
		$search['country'] = (empty($search['country'])) ? null : $search['country'];
		
		unset($search["_token"]);
		
		$criteria = $search;
		$criteria['country'] = (empty($search['country'])) ? null : $entityManager->getRepository(Country::class)->find($search['country'])->getTitle();
		$criteria = array_filter(array_values($criteria));
		$criteria = empty($criteria) ? $translator->trans("search.result.None") : $criteria;

		return $this->render('IndexProverbius/resultIndexSearch.html.twig', array('search' => base64_encode(json_encode($search)), 'criteria' => $criteria));
	}

    /**
     * @Route("/result_search/{search}")
     */
	public function searchDatatablesAction(Request $request, $search)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');

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
		$sSearch = json_decode(base64_decode($search));

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Proverb::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Proverb::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();

			$show = $this->generateUrl('app_indexproverbius_read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$country = $entity->getCountry();
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getText().'</a>';
			$row[] = '<img src="'.$request->getBaseUrl().'/'.Country::PATH_FILE.$country->getFlag().'" class="flag">';

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
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$image = (!empty($idImage)) ? $entityManager->getRepository(ProverbImage::class)->find($idImage) : null;
		
		$browsingProverbs = $entityManager->getRepository(Proverb::class)->browsingProverbShow($id);

		return $this->render('IndexProverbius/read.html.twig', array('entity' => $entity, 'browsingProverbs' => $browsingProverbs, 'image' => $image));
	}

    /**
     * @Route("/tag/{id}/{slug}", defaults={"slug": null})
     */
	public function tagAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Tag::class)->find($id);

		return $this->render('IndexProverbius/tag.html.twig', array('entity' => $entity));
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
		$entities = $entityManager->getRepository(Proverb::class)->getEntityByTagDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $tagId);
		$iTotal = $entityManager->getRepository(Proverb::class)->getEntityByTagDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $tagId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();

			$show = $this->generateUrl('app_indexproverbius_read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$country = $entity->getCountry();
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getText().'</a>';
			$row[] = '<img src="'.$request->getBaseUrl().'/'.Country::PATH_FILE.$country->getFlag().'" class="flag">';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/byimages", defaults={"page": 1})
     */
	public function byImagesAction(Request $request, PaginatorInterface $paginator, $page)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$query = $entityManager->getRepository(ProverbImage::class)->getPaginator($request->getLocale());

		$pagination = $paginator->paginate(
			$query, /* query NOT result */
			$page, /*page number*/
			10 /*limit per page*/
		);
		
		$pagination->setCustomParameters(['align' => 'center']);
		
		return $this->render('IndexProverbius/byimage.html.twig', ['pagination' => $pagination]);
	}

    /**
     * @Route("/download_image/{fileName}")
     */
	public function downloadImageAction($fileName)
	{
		$response = new BinaryFileResponse(Proverb::PATH_FILE.$fileName);
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
		return $response;
	}

    /**
     * @Route("/read_pdf/{id}/{slug}", defaults={"slug": null})
     */
	public function readPDFAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$content = $this->renderView('IndexProverbius/pdf.html.twig', array('entity' => $entity));

		$html2pdf = new Html2Pdf('P','A4','fr');
		$html2pdf->WriteHTML($content);

		$file = $html2pdf->Output('proverb.pdf');
		$response = new Response($file);
		$response->headers->set('Content-Type', 'application/pdf');

		return $response;
	}

	public function lastAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Proverb::class)->getLastEntries($request->getLocale());

		return $this->render('IndexProverbius/last.html.twig', array('entities' => $entities));
    }
	
	public function statAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$statistics = $entityManager->getRepository(Proverb::class)->getStat($request->getLocale());

		return $this->render('IndexProverbius/stat.html.twig', array('statistics' => $statistics));
    }

    /**
     * @Route("/country/{id}/{slug}", defaults={"slug": null})
     */
	public function countryAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Country::class)->find($id);

		return $this->render('IndexProverbius/country.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/country_datatables/{countryId}")
     */
	public function countryDatatablesAction(Request $request, TranslatorInterface $translator, $countryId)
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
		$entities = $entityManager->getRepository(Proverb::class)->getProverbByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId);
		$iTotal = $entityManager->getRepository(Proverb::class)->getProverbByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity["proverb_text"];
			$show = $this->generateUrl('app_indexproverbius_read', array('id' => $entity["proverb_id"], 'slug' => $entity["proverb_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans("country.table.Read").'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/bycountries")
     */
	public function byCountriesAction(Request $request)
    {
        return $this->render('IndexProverbius/bycountry.html.twig');
    }

    /**
     * @Route("/bycountries_datatables")
     */
	public function byCountriesDatatablesAction(Request $request)
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
		$entities = $entityManager->getRepository(Proverb::class)->findProverbByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Proverb::class)->findProverbByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			if(!empty($entity['country_id']))
			{
				$row = array();

				$show = $this->generateUrl('app_indexproverbius_country', array('id' => $entity['country_id'], 'slug' => $entity['country_slug']));
				$row[] = '<a href="'.$show.'" alt="Show"><img src="'.$request->getBaseUrl().'/'.Country::PATH_FILE.$entity['flag'].'" class="flag" /> '.$entity['country_title'].'</a>';

				$row[] = '<span class="badge badge-secondary">'.$entity['number_proverbs_by_country'].'</span>';

				$output['aaData'][] = $row;
			}
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/letter/{letter}")
     */
	public function letterAction(Request $request, $letter)
	{
		return $this->render('IndexProverbius/letter.html.twig', array('letter' => $letter));
	}

    /**
     * @Route("/letter_datatables/{letter}")
     */
	public function letterDatatablesAction(Request $request, TranslatorInterface $translator, $letter)
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
		$entities = $entityManager->getRepository(Proverb::class)->getProverbByLetterDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $letter, $request->getLocale());
		$iTotal = $entityManager->getRepository(Proverb::class)->getProverbByLetterDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $letter, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity["proverb_text"];
			$show = $this->generateUrl('app_indexproverbius_read', array('id' => $entity["proverb_id"], 'slug' => $entity["proverb_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans("alphabetBook.table.Read").'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/byletters")
     */
	public function byLettersAction(Request $request)
    {
        return $this->render('IndexProverbius/byletter.html.twig');
    }

    /**
     * @Route("/byletters_datatables")
     */
	public function byLettersDatatablesAction(Request $request)
	{
		$results = [];
		$entityManager = $this->getDoctrine()->getManager();
		
		foreach(range('A', 'Z') as $letter)
		{
			$subArray = [];
			
			$subArray["letter"] = $letter;
			
			$resQuery = $entityManager->getRepository(Proverb::class)->findProverbByLetter($letter, $request->getLocale());
			$subArray["link"] = $resQuery["number_letter"];
			$results[] = $subArray;
		}
		
		return $this->render('IndexProverbius/byletterDatatable.html.twig', array('results' => $results));
	}

    /**
     * @Route("/store/{page}", defaults={"page": 1})
     */
    public function storeAction(Request $request, PaginatorInterface $paginator, $page)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$querySearch = $request->request->get("query", null);
		$query = $entityManager->getRepository(Store::class)->getProducts($querySearch, $request->getLocale());

		$pagination = $paginator->paginate(
			$query, /* query NOT result */
			$page, /*page number*/
			10 /*limit per page*/
		);

		$pagination->setCustomParameters(['align' => 'center']);
		
		return $this->render('IndexProverbius/store.html.twig', ['pagination' => $pagination, "query" => $querySearch]);
    }

    /**
     * @Route("/read_store/{id}/{slug}", defaults={"slug": null})
     */
	public function readStoreAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$entity = $em->getRepository(Store::class)->find($id);
		
		return $this->render('IndexProverbius/readStore.html.twig', [
			'entity' => $entity
		]);
	}

    /**
     * @Route("/generate_widget")
     */
	public function generateWidgetAction()
	{
		return $this->render('IndexProverbius/generate_widget.html.twig');
	}

    /**
     * @Route("/author/{id}/{slug}", defaults={"slug": null})
     */
	public function authorAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Biography::class)->find($id);
		$stores = $entityManager->getRepository(Store::class)->findBy(["biography" => $entity]);

		return $this->render('IndexProverbius/author.html.twig', array('entity' => $entity, "stores" => $stores));
	}

    /**
     * @Route("/widget/{locale}", defaults={"locale": "en"})
     */
	public function widgetAction(Request $request, $locale)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$proverb = $entityManager->getRepository(Proverb::class)->getRandomProverb($locale);

		return $this->render('IndexProverbius/Widget/randomProverbWidget.html.twig', ['proverb' => $proverb]);
	}

	private function createFormIndexSearch($locale, $entity)
	{
		return $this->createForm(IndexProverbiusSearchType::class, null, ["locale" => $locale]);
	}
}