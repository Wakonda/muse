<?php

namespace App\Controller;

use App\Entity\PoemImage;
use App\Entity\ProverbImage;
use App\Entity\QuoteImage;

use App\Entity\Poem;
use App\Entity\Proverb;
use App\Entity\Quote;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @Route("/admin/image")
 */
class ImageAdminController extends AbstractController
{
    /**
     * @Route("/{domainName}")
     */
	public function indexAction(Request $request, String $domainName)
	{
		return $this->render('Image/index.html.twig', ["domainName" => $domainName]);
	}

    /**
     * @Route("/datatables/{domainName}")
     */
	public function indexDatatablesAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, String $domainName)
	{
		list($imageClass, $path, $method) = $this->selectEntity($domainName);
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = [];
		$sortDirColumn = [];
			
		for($i=0 ; $i<intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entities = $em->getRepository($imageClass)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository($imageClass)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => []
		);
		
		foreach($entities as $entity)
		{
			$row = [];
			$row[] = "<img class='mx-auto d-block' src='/".$path.$entity->getImage()."'>";
			
			$socialNetworkArray = [];

			if(!empty($entity->getSocialNetwork()))
			{
				$socialNetworks = array_unique(json_decode($entity->getSocialNetwork()));
				
				foreach ($socialNetworks as $sn) {
					$socialNetworkArray[] = '<span class="badge badge-secondary"><i class="fab fa-'.strtolower($sn).'" aria-hidden="true"></i></span>';
				}
			}
			
			$row[] = empty($sn = $socialNetworkArray) ? "-" : implode(" ", $socialNetworkArray);
			$show = $this->generateUrl('app_quoteadmin_show', array('id' => $entity->$method()->getId()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a>';
			
			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}
	
	private function selectEntity(String $domainName): ?Array {
		switch($domainName) {
			case "poeticus":
				return [PoemImage::class, Poem::PATH_FILE, "getPoem"];
			case "proverbius":
				return [ProverbImage::class, Proverb::PATH_FILE, "getProverb"];
			case "quotus":
				return [QuoteImage::class, Quote::PATH_FILE, "getQuote"];
		}
		
		return null;
	}
}