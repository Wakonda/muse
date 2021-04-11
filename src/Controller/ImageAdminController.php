<?php

namespace App\Controller;

use App\Entity\PoemImage;
use App\Entity\ProverbImage;
use App\Entity\QuoteImage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;

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
	public function indexDatatablesAction(Request $request, TranslatorInterface $translator, String $domainName)
	{
		$imageClass = $this->selectEntity($domainName);
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

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository($imageClass)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $entityManager->getRepository($imageClass)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => []
		);
		
		foreach($entities as $entity)
		{
			$row = [];
			$row[] = "<img class='mx-auto d-block' src='/".Quote::PATH_FILE.$entity->getImage()."'>";
			
			$socialNetworkArray = [];
			
			if(!empty($entity->getSocialNetwork()))
			{
				$ocialNetworks = array_unique($entity->getSocialNetwork());
				
				foreach ($ocialNetworks as $sn) {
					$socialNetworkArray[] = '<span class="badge badge-secondary"><i class="fab fa-'.strtolower($sn).'" aria-hidden="true"></i></span>';
				}
			}
			
			$row[] = empty($sn = $socialNetworkArray) ? "-" : implode(" ", $socialNetworkArray);
			$show = $this->generateUrl('app_quoteadmin_show', array('id' => $entity->getQuote()->getId()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a>';
			
			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}
	
	private function selectEntity(String $domainName): ?String {
		switch($domainName) {
			case "poeticus":
				return PoemImage::class;
			case "proverbius":
				return ProverbImage::class;
			case "quotus":
				return QuoteImage::class;
		}
		
		return null;
	}
}