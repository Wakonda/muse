<?php

namespace App\Controller;

use App\Entity\Poem;
use App\Entity\PoemImage;
use App\Entity\User;
use App\Entity\Language;
use App\Entity\Biography;
use App\Entity\Source;
use App\Entity\PoeticForm;
use App\Entity\Tag;
use App\Form\Type\PoemType;
use App\Form\Type\PoemFastType;
use App\Form\Type\ImagePoemGeneratorType;
use App\Form\Type\PoemFastMultipleType;
use App\Form\Type\PoemEditMultipleType;
use App\Service\GenericFunction;
use App\Service\ImageGenerator;
use App\Service\PHPImage;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Routing\Annotation\Route;
use App\Service\Twitter;

require_once __DIR__.'/../../vendor/simple_html_dom.php';

/**
 * @Route("/admin/poem")
 */
class PoemAdminController extends AbstractController
{
	private $formName = "poem";
	
	private $authorizedURLs = ['cG9lc2llLndlYm5ldC5mcg==', 'd3d3LnBvZXNpZS1mcmFuY2Fpc2UuZnI=', 'd3d3LnBvZXRpY2EuZnI=', 'd3d3LnRvdXRlbGFwb2VzaWUuY29t', 'd3d3LnVuaGFpa3UuY29t', 'd3d3LmNpdGFkb3IucHQ='];
	private $authorizedURLMultiples = ['d3d3LnBvZXNpZS1mcmFuY2Fpc2UuZnI=', 'd3d3LnBlbnNpZXJpcGFyb2xlLml0'];

    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Poem/index.html.twig');
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
		
		$entities = $em->getRepository(Poem::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $em->getRepository(Poem::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$row["DT_RowId"] = $entity->getId();
			$row[] = $entity->getId();
			$row[] = $entity->getTitle();
			$row[] = $entity->getLanguage()->getTitle();
			
			$show = $this->generateUrl('app_poemadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_poemadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a> - <a href="'.$edit.'" alt="Edit">'.$translator->trans('admin.index.Update').'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/new/{biographyId}/{collectionId}", defaults={"biographyId": null, "collectionId": null}, requirements={"biographyId"="\d+", "collectionId"="\d+"})
     */
    public function newAction(EntityManagerInterface $em, Request $request, $biographyId, $collectionId)
    {
		$entity = new Poem();
		$language = $em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		
		$entity->setLanguage($language);

		if(!empty($biographyId))
			$entity->setBiography($em->getRepository(Biography::class)->find($biographyId));
		
		if(!empty($collectionId))
			$entity->setCollection($em->getRepository(Source::class)->find($collectionId));

        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Poem/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator)
	{
		$entity = new Poem();
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);

		$this->checkForDoubloon($em, $translator, $entity, $form);

		$poeticForm = $entity->getPoeticForm();
		
		if(!empty($poeticForm) and $poeticForm->getTypeContentPoem() == PoeticForm::IMAGETYPE) {
			if(empty($entity->getFileManagement()) or $entity->getFileManagement()->getPhoto() == null) {
				$form->get("fileManagement")->get("id")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
			}
		}
		else {
			if($entity->getText() == null)
				$form->get("text")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
		}
		
		$userForms = $em->getRepository(User::class)->findAllForChoice($request->getLocale());

		if(($entity->isBiography() and $entity->getBiography() == null) or ($entity->isUser() and $entity->getUser() == null))
			$form->get($entity->getAuthorType())->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if($form->isValid())
		{
			$entity->setState(0);
			$entity->setCountry($em->getRepository(Biography::class)->find($entity->getBiography())->getCountry());
			$em->persist($entity);
			$em->flush();

			$redirect = $this->generateUrl('app_poemadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Poem/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Poem::class)->find($id);
		$imageGeneratorForm = $this->createForm(ImagePoemGeneratorType::class);
	
		return $this->render('Poem/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Poem::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);

		return $this->render('Poem/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Poem::class)->find($id);
		
		$locale = $request->request->get($this->formName)["language"];
		$language = $em->getRepository(Language::class)->find($locale);
		
		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($em, $translator, $entity, $form);

		$poeticForm = $entity->getPoeticForm();

		if(!empty($poeticForm) and $poeticForm->getTypeContentPoem() == PoeticForm::IMAGETYPE) {
			if(empty($entity->getFileManagement()) or $entity->getFileManagement()->getPhoto() == null) {
				$form->get("fileManagement")->get("id")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
			}
		}
		else {
			if($entity->getText() == null)
				$form->get("text")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
		}

		if(($entity->isBiography() and $entity->getBiography() == null) or ($entity->isUser() and $entity->getUser() == null))
			$form->get($entity->getAuthorType())->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
		
		if($form->isValid())
		{
			$entity->setCountry( $em->getRepository(Biography::class)->find($entity->getBiography())->getCountry());
			$em->persist($entity);
			$em->flush();

			return $this->redirect($this->generateUrl('app_poemadmin_show', array('id' => $entity->getId())));
		}
	
		return $this->render('Poem/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/edit_multiple")
     */
	public function editMultipleAction(EntityManagerInterface $em, Request $request)
	{
		$ids = json_decode($request->query->get("ids"));
		$locale = $em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		$form = $this->createForm(PoemEditMultipleType::class, null, array("locale" => $locale->getId()));

		return $this->render('Poem/editMultiple.html.twig', array('form' => $form->createView(), 'ids' => $ids));
	}

    /**
     * @Route("/update_multiple/{ids}")
     */
	public function updateMultipleAction(EntityManagerInterface $em, Request $request, SessionInterface $session, TranslatorInterface $translator, $ids)
	{
		$ids = json_decode($ids);
		$locale = $em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		$form = $this->createForm(PoemEditMultipleType::class, null, array("locale" => $locale->getId()));
		$form->handleRequest($request);

		$req = $request->request->get($form->getName());

		foreach($ids as $id)
		{
			$entity = $em->getRepository(Poem::class)->find($id);
			$tagsId = $req["tags"];

			foreach($tagsId as $tagId)
			{
				$tag = $em->getRepository(Tag::class)->find($tagId);
				$realTag = $em->getRepository(Tag::class)->findOneBy(["internationalName" => $tag->getInternationalName(), "language" => $entity->getLanguage()]);
				
				if(!empty($realTag))
				{
					if(!$entity->isTagExisted($realTag))
					{
						$entity->addTag($realTag);
						$em->persist($entity);
					}
				}
			}

			$em->flush();
		}

		$session->getFlashBag()->add('message', $translator->trans("admin.index.ChangesMadeSuccessfully"));

		return $this->redirect($this->generateUrl('app_poemadmin_index'));
	}

    /**
     * @Route("/new_fast/{biographyId}/{collectionId}", defaults={"biographyId": null, "collectionId": null}, requirements={"biographyId"="\d+", "collectionId"="\d+"})
     */
	public function newFastAction(EntityManagerInterface $em, Request $request, $biographyId, $collectionId)
	{
		$entity = new Poem();

		$datas = $request->query->all();
		$datas = !empty($datas) ? json_decode($datas["datas"], true) : null;

		$url = null;
		$ipProxy = null;

		if(!empty($datas)) {
			$entity->setLanguage($em->getRepository(Language::class)->find($datas["language"]));
			$entity->setBiography($em->getRepository(Biography::class)->find($datas["biography"]));
			
			if(!empty($datas["collection"]))
				$entity->setCollection($em->getRepository(Source::class)->find($datas["collection"]));

			if(!empty($datas["poetic_form"]))
				$entity->setPoeticForm($em->getRepository(PoeticForm::class)->find($datas["poetic_form"]));
			
			$url = $datas["url"];
			$ipProxy = $datas["ipProxy"];
		} else {
			$entity->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

			if(!empty($biographyId))
			{
				$entity->setBiography($em->getRepository(Biography::class)->find($biographyId));
				$entity->setLanguage($em->getRepository(Language::class)->find($entity->getBiography()->getLanguage()->getId()));
			}
			if(!empty($collectionId))
				$entity->setCollection($em->getRepository(Source::class)->find($collectionId));
		}
		$form = $this->createForm(PoemFastType::class, $entity, array("locale" => $request->getLocale(), "url" => $url, "ipProxy" => $ipProxy));
	
		return $this->render('Poem/fast.html.twig', array('form' => $form->createView(), 'entity' => $entity, 'authorizedURLs' => $this->authorizedURLs));
	}

    /**
     * @Route("/add_fast")
     */
	public function addFastAction(EntityManagerInterface $em, Request $request, TranslatorInterface $translator, SessionInterface $session)
	{
		$entity = new Poem();
		
		$form = $this->createForm(PoemFastType::class, $entity, array("locale" => $request->getLocale()));
		$form->handleRequest($request);
		
		$req = $request->request->get($form->getName());

		if(!empty($req["url"]) and filter_var($req["url"], FILTER_VALIDATE_URL))
		{
			$url = $req["url"];
			$url_array = parse_url($url);

			$gf = new GenericFunction();
			
			if(!empty($ipProxy = $form->get('ipProxy')->getData()))
				$html = $gf->getContentURL($url, $ipProxy);
			else
				$html = $gf->getContentURL($url);

			$dom = new \simple_html_dom();
			$dom->load($html);

			$entity->setAuthorType("biography");
			$entity->setCountry( $em->getRepository(Biography::class)->find($entity->getBiography())->getCountry());
			$poemArray = array();

			switch(base64_encode($url_array['host']))
			{
				case 'cG9lc2llLndlYm5ldC5mcg==':
					$title = $dom->find('h3[class=poem__title]'); 
					$text = $dom->find('div[class=poem__content]'); 

					$title = html_entity_decode($title[0]->plaintext);
					$title = (preg_match('!!u', $title)) ? $title : utf8_encode($title);

					$subPoemArray = array();
					$subPoemArray['title'] = $title;
					$subPoemArray['text'] = $text[0]->outertext;
					$poemArray[] = $subPoemArray;
					break;
				case 'd3d3LnBvZXNpZS1mcmFuY2Fpc2UuZnI=':
					$title_node = $dom->find('article h1');
					$title_str = $title_node[0]->plaintext;
					$title_array = explode(":", $title_str);
					$title = trim($title_array[1]);

					$text_node = $dom->find('div.postpoetique p');
					$text_init = strip_tags($text_node[0]->plaintext, "<br><br /><br/>");
					$text_array = explode("\n", $text_init);
					$text = "";
					
					foreach($text_array as $line) {
						$text = $text."<br>".trim($line);
					}
					$text = preg_replace('/^(<br>)+/', '', $text);
					
					$subPoemArray = array();
					$subPoemArray['title'] = $title;
					$subPoemArray['text'] = $text;
					$poemArray[] = $subPoemArray;
					break;
				case 'd3d3LnBvZXRpY2EuZnI=':
					$title = current($dom->find("h1.entry-title"))->innertext;
					
					$text = $dom->find("main article div.entry-content");
					$text = $text[0]->innertext;
					
					$text = str_replace("<p>", "", $text);
					$text = str_replace("<br />", "<br>", $text);
					$text = trim($text);

					$text = explode("</p>", $text);
					array_pop($text);
					array_pop($text);
					$text = implode("<br><br>", $text);
					
					$subPoemArray = array();
					$subPoemArray['title'] = $title;
					$subPoemArray['text'] = $text;
					$poemArray[] = $subPoemArray;
					break;
				case 'd3d3LnRvdXRlbGFwb2VzaWUuY29t':
					$title = trim(current(explode("<br>", current($dom->find('h1.ipsType_pagetitle'))->innertext)));			
					$text = current($dom->find('div.poemeanthologie'))->innertext;
					$text =preg_replace('#</?span[^>]*>#is', '', $text);

					$subPoemArray = array();
					$subPoemArray['title'] = utf8_encode($title);
					$subPoemArray['text'] = utf8_encode($text);
					$poemArray[] = $subPoemArray;
					break;
				case 'd3d3LnVuaGFpa3UuY29t':
					foreach($dom->find('ul#chunkLast > li') as $li)
					{
						$text = current($li->find("div#texte"));
						
						if(!empty($text))
						{
							$titleArray = preg_split(":(<br ?/?>):", $text->innertext);
							
							$subPoemArray = array();
							$subPoemArray['title'] = $titleArray[0];
							$subPoemArray['text'] = $text->innertext;
							$poemArray[] = $subPoemArray;
						}
					}
					break;
				case 'd3d3LmNpdGFkb3IucHQ=':
					$dom = new \DOMDocument();
					libxml_use_internal_errors(true); 
					$dom->loadHTML($html);
					libxml_clear_errors();

					$xpath = new \DOMXpath($dom);

					$div = $xpath->query("//div[@class='panel panel-default']/div[@class='panel-body']/div")->item(0);
					
					$subPoemArray = [];
					$subPoemArray['title'] = $xpath->query("//div[@class='panel panel-default']/div[@class='panel-body']/h2")->item(0)->textContent;

					$html="";
					foreach($div->childNodes as $node) {
						$html .= str_replace("&nbsp;", '', $dom->saveHTML($node));
					}

					$htmlArray = preg_split('/<i[^>]*>([\s\S]*?)<\/i[^>]*>/', $html);

					array_pop($htmlArray);
					$content = $htmlArray[0];

					$content = preg_replace('/<font[^>]*>([\s\S]*?)<\/font[^>]*>/', '', $content);

					// Remove <br> at the end of string
					$content = preg_replace('[^([\n\r\s]*<br( \/)?>[\n\r\s]*)*|([\n\r\s]*<br( \/)?>[\n\r\s]*)*$]', '', $content);

					$content = str_replace(chr(150), "-", utf8_decode($content));// Replace "en dash" by simple "dash"
					$content = str_replace(chr(151), '-', $content);// Replace "em dash" by simple "dash"
					$content = str_replace("\xc2\xa0", '', utf8_encode($content));// Remove nbsp
				
					$subPoemArray['text'] = $content;
				
					/*$html = file_get_html($url);
					
					$divPanelDefault = $html->find("div.panel-default", 0);
					$div = $divPanelDefault->find("div.panel-body", 0);
					
					
					$subPoemArray['title'] = $div->find("h2", 0)->plaintext;
					$content = $div->find("div", 0)->innertext;

					$content = preg_replace('/<font[^>]*>([\s\S]*?)<\/font[^>]*>/', '', $content);
					$content = preg_replace('/<i[^>]*>([\s\S]*?)<\/i[^>]*>/', '', $content);
					
					// Remove <br> at the end of string
					$content = preg_replace('[^([\n\r\s]*<br( \/)?>[\n\r\s]*)*|([\n\r\s]*<br( \/)?>[\n\r\s]*)*$]', '', $content);

					$content = str_replace(chr(150), "-", $content);// Replace "en dash" by simple "dash"
					$content = str_replace(chr(151), '-', $content);// Replace "em dash" by simple "dash"
					$content = utf8_encode($content); 

					$subPoemArray['text'] = $content;*/
					
					$poemArray[] = $subPoemArray;
					break;
			}
		}
		
		$numberDoubloons = 0;
		$numberAdded = 0;

		if($form->isValid())
		{
			foreach($poemArray as $poem)
			{
				$entityPoem = clone $entity;
				$entityPoem->setTitle($poem['title']);
				$entityPoem->setText($poem['text']);
				$entityPoem->setState(0);

				if($em->getRepository(Poem::class)->checkForDoubloon($entityPoem) >= 1)
					$numberDoubloons++;
				else
				{
					$em->persist($entityPoem);
					$em->flush();
					$id = $entity->getId();
					$numberAdded++;
				}
			}

			$session->getFlashBag()->add('message', $translator->trans("admin.index.AddedSuccessfully", ["%numberAdded%" => $numberAdded, "%numberDoubloons%" => $numberDoubloons]));

			unset($req["_token"]);

			return $this->redirect($this->generateUrl('app_poemadmin_newfast', ["datas" => json_encode($req)]));
		}
	
		return $this->render('Poem/fast.html.twig', array('form' => $form->createView(), 'entity' => $entity, 'authorizedURLs' => $this->authorizedURLs));
	}

    /**
     * @Route("/new_fast_multiple")
     */
	public function newFastMultipleAction(EntityManagerInterface $em, Request $request)
	{
		$entity = new Poem();
		$datas = $request->query->all();
		$datas = !empty($datas) ? json_decode($datas["datas"], true) : null;

		$url = null;
		$ipProxy = null;

		if(!empty($datas)) {
			$entity->setLanguage($em->getRepository(Language::class)->find($datas["language"]));
			$entity->setBiography($em->getRepository(Biography::class)->find($datas["biography"]));
			$entity->setReleasedDate($datas["releasedDate"]);
			
			if(!empty($datas["collection"]))
				$entity->setCollection($em->getRepository(Source::class)->find($datas["collection"]));

			if(!empty($datas["poetic_form"]))
				$entity->setPoeticForm($em->getRepository(PoeticForm::class)->find($datas["poetic_form"]));

			$url = $datas["url"];
			$ipProxy = $datas["ipProxy"];
		} else
			$entity->setLanguage($em->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

		$form = $this->createForm(PoemFastMultipleType::class, $entity, array("locale" => $request->getLocale(), "url" => $url, "ipProxy" => $ipProxy));

		return $this->render('Poem/fastMultiple.html.twig', array('form' => $form->createView(), 'language' => $request->getLocale(), 'authorizedURLMultiples' => $this->authorizedURLMultiples));
	}

    /**
     * @Route("/add_fast_multiple")
     */
	public function addFastMultipleAction(EntityManagerInterface $em, Request $request, SessionInterface $session, TranslatorInterface $translator)
	{
		$entity = new Poem();
		
		$form = $this->createForm(PoemFastMultipleType::class, $entity, array("locale" => $request->getLocale()));
		
		$form->handleRequest($request);
		$req = $request->request->get($form->getName());
			
		if(!empty($req["url"]) and filter_var($req["url"], FILTER_VALIDATE_URL))
		{
			$url = $req["url"];
			$url_array = parse_url($url);

			if(!in_array(base64_encode($url_array['host']), $this->authorizedURLMultiples))
				$form->get("url")->addError(new FormError($translator->trans("admin.error.UnknownURL")));
		}

		if($form->isValid())
		{
			$numberDoubloons = 0;
			$entity->setAuthorType("biography");
			$entity->setCountry( $em->getRepository(Biography::class)->find($entity->getBiography())->getCountry());
			$number = $req['number'];
			$i = 0;
			$gf = new GenericFunction();
			
			if(!empty($ipProxy = $form->get('ipProxy')->getData()))
				$html = $gf->getContentURL($url, $ipProxy);
			else
				$html = $gf->getContentURL($url);

			$dom = new \simple_html_dom();
			$dom->load($html);

			switch(base64_encode($url_array['host']))
			{
				case 'd3d3LnBvZXNpZS1mcmFuY2Fpc2UuZnI=':
					foreach($dom->find('div.poemes-auteurs') as $div)
					{					
						$entityPoem = clone $entity;
						$a = current($div->find("a"));
						$content = $gf->getContentDom($a->href);
						$title_node = $content->find('article h1');
						$title_str = $title_node[0]->plaintext;
						$title_array = explode(":", $title_str);
						$title = trim($title_array[1]);

						$text_node = $content->find('div.postpoetique p');
						$text_init = strip_tags($text_node[0]->plaintext, "<br><br /><br/>");
						$text_array = explode("\n", $text_init);
						$text = "";
						
						foreach($text_array as $line) {
							$text = $text."<br>".trim($line);
						}
						$text = preg_replace('/^(<br>)+/', '', $text);
						
						$entityPoem->setTitle($title);
						$entityPoem->setText($text);
						$entityPoem->setState(0);
						$entityPoem->setLanguage($em->getRepository(Language::class)->findOneByAbbreviation('fr'));
					
						if($em->getRepository(Poem::class)->checkForDoubloon($entityPoem) >= 1) {
							$numberDoubloons++;
							continue;
						}
						
						if($number == $i)
							break;
	
						$i++;

						$em->persist($entityPoem);
						$em->flush();
						$id = $entity->getId();
					}
					break;
				case 'd3d3LnBlbnNpZXJpcGFyb2xlLml0':
					foreach($dom->find('article') as $article)
					{
						if(empty($article) || empty($article->find("h2", 0)))
							continue;
						$title = $article->find("h2", 0)->plaintext;
						$blockquote = $article->find('blockquote', 0);
						
						$content = $blockquote->plaintext;
						$content = utf8_encode(str_replace(chr(150), '-', $content)); // Replace "en dash" by simple "dash"
						$content = str_replace("\n", "<br>", $content);
						$entityPoem = clone $entity;
						$entityPoem->setTitle($title);
						$entityPoem->setText($content);
						$entityPoem->setState(0);
						
						$entityPoem->setLanguage($em->getRepository(Language::class)->findOneByAbbreviation('it'));
						
						if($em->getRepository(Poem::class)->checkForDoubloon($entityPoem) >= 1) {
							$numberDoubloons++;
							continue;
						}
						
						if($number == $i)
							break;
	
						$i++;

						$em->persist($entityPoem);
						$em->flush();
						$id = $entity->getId();
					}
				break;
			}
			
			$session->getFlashBag()->add('message', $translator->trans("admin.index.AddedSuccessfully", ["%numberAdded%" => $i, "%numberDoubloons%" => $numberDoubloons]));

			unset($req["_token"]);

			return $this->redirect($this->generateUrl('app_poemadmin_newfastmultiple', ["datas" => json_encode($req)]));
		}
		
		return $this->render('Poem/fastMultiple.html.twig', array('form' => $form->createView(), 'language' => $request->getLocale(), 'authorizedURLMultiples' => $this->authorizedURLMultiples));
	}

    /**
     * @Route("/select_biographies")
     */
	public function listSelectedBiographyAction(EntityManagerInterface $em, Request $request)
	{
		$id = $request->request->get("id");

		if($id != "")
		{
			$entity =  $em->getRepository(Biography::class)->find($id);

			$collections = $em->getRepository(Source::class)->findAllByAuthor($id);
			$collectionArray = array();
			
			foreach($collections as $collection)
			{
				$collectionArray[] = array("id" => $collection->getId(), "title" => $collection->getTitle(), "releaseDate" => $collection->getReleasedDate());
			}

			$country = $entity->getCountry();

			$countryText = (empty($country)) ? null : array('title' => $country->getTitle(), 'flag' => $country->getFlag());
				
			$finalArray = array("collections" => $collectionArray, "country" => $countryText);
		}
		else
			$finalArray = array("collections" => "", "country" => "");

		return new JsonResponse($finalArray);
	}

    /**
     * @Route("/select_collections")
     */
	public function listSelectedCollectionAction(EntityManagerInterface $em, Request $request)
	{
		$id = $request->request->get("id");
		
		if($id != "")
		{
			$entity = $em->getRepository(Source::class)->find($id);
			$finalArray = array("releasedDate" => $entity->getReleasedDate());
		}
		else
			$finalArray = array("releasedDate" => null);

		return new JsonResponse($finalArray);
	}

    /**
     * @Route("/select_poeticforms")
     */
	public function selectPoeticFormAction(EntityManagerInterface $em, Request $request)
	{
		$id = $request->request->get("id");
		
		if($id != "")
		{
			$entity = $em->getRepository(PoeticForm::class)->find($id);
			$finalArray = array("typeContentPoem" => $entity->getTypeContentPoem());
		}
		else
			$finalArray = array("typeContentPoem" => "");

		return new JsonResponse($finalArray);
	}

    /**
     * @Route("/twitter/{id}")
     */
	public function twitterAction(EntityManagerInterface $em, Request $request, SessionInterface $session, TranslatorInterface $translator, $id)
	{
		$entity = $em->getRepository(Poem::class)->find($id);
		$locale = $entity->getLanguage()->getAbbreviation();

		$message = $request->request->get("twitter_area")." ".$this->generateUrl("app_indexpoeticus_read", array("id" => $id, 'slug' => $entity->getSlug()), UrlGeneratorInterface::ABSOLUTE_URL);
		$imageId = $request->request->get('image_id_tweet');

		$poemImage = null;
		$image = null;

		if(!empty($imageId)) {
			$poemImage = $em->getRepository(PoemImage::class)->find($imageId);
			$image = Poem::PATH_FILE.$poemImage->getImage();
		}
		
		$statues = $twitter->sendTweet($message, $image, $locale);
	
		if(isset($statues->errors) and !empty($statues->errors))
			$session->getFlashBag()->add('message', "Twitter - ".$translator->trans("admin.index.SentError").json_encode($statues->errors));
		else {
			if(!empty($poemImage)) {
				$poemImage->addSocialNetwork("Twitter");
				$em->persist($poemImage);
				$em->flush();
			}
		
			$session->getFlashBag()->add('message', "Twitter - ".$translator->trans("admin.index.SentSuccessfully"));
		}
	
		return $this->redirect($this->generateUrl("app_poemadmin_show", array("id" => $id)));
	}

    /**
     * @Route("/save_image/{id}")
     */
	public function saveImageAction(EntityManagerInterface $em, Request $request, $id)
	{
		$entity = $em->getRepository(Poem::class)->find($id);
		
        $imageGeneratorForm = $this->createForm(ImagePoemGeneratorType::class);
        $imageGeneratorForm->handleRequest($request);
		$data = $imageGeneratorForm->getData();
		
		if(empty($data["image"]["title"]) and empty($data["image"]["content"]))
			$imageGeneratorForm->get("image")["name"]->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if ($imageGeneratorForm->isSubmitted() && $imageGeneratorForm->isValid())
		{
			$file = $data['image']["content"];
            $fileName = md5(uniqid()).'_'.$data["image"]["title"];
			$text = html_entity_decode($data['text'], ENT_QUOTES);

			$font = realpath(__DIR__."/../../assets").DIRECTORY_SEPARATOR.'font'.DIRECTORY_SEPARATOR.'source-serif-pro'.DIRECTORY_SEPARATOR.'SourceSerifPro-Regular.otf';
			
			if($data["version"] == "v1")
			{
				$image = imagecreatefromstring($file);
				
				ob_start();
				imagepng($image);
				$png = ob_get_clean();

				$image_size = getimagesizefromstring($png);

				$widthText = $image_size[0] * 0.9;
				$start_x = $image_size[0] * 0.1;
				$start_y = $image_size[1] * 0.35;

				$copyright_x = $image_size[0] * 0.03;
				$copyright_y = $image_size[1] - $image_size[1] * 0.03;

				if($data['invert_colors'])
				{
					$white = imagecolorallocate($image, 0, 0, 0);
					$black = imagecolorallocate($image, 255, 255, 255);
				}
				else
				{
					$black = imagecolorallocate($image, 0, 0, 0);
					$white = imagecolorallocate($image, 255, 255, 255);
				}

				$imageGenerator = new ImageGenerator();
				$imageGenerator->setFontColor($black);
				$imageGenerator->setStrokeColor($white);
				$imageGenerator->setStroke(true);
				$imageGenerator->setBlur(true);
				$imageGenerator->setFont($font);
				$imageGenerator->setFontSize($data['font_size']);
				$imageGenerator->setImage($image);
				
				$imageGenerator->setText($text);
				$imageGenerator->setCopyright(["x" => $copyright_x, "y" => $copyright_y, "text" => "poeticus.wakonda.guru"]);

				$imageGenerator->generate($start_x, $start_y, $widthText);

				imagepng($image, Poem::PATH_FILE.$fileName);
				imagedestroy($image);
			}
			else
			{
				$textColor = [0, 0, 0];
				$strokeColor = [255, 255, 255];
				$rectangleColor = [255, 255, 255];
				
				if($data["invert_colors"]) {
					$textColor = [255, 255, 255];
					$strokeColor = [0, 0, 0];
					$rectangleColor = [0, 0, 0];
				}

				$tmp = tmpfile();
				fwrite($tmp, $file);
				$bg = stream_get_meta_data($tmp)['uri'];

				$image = new PHPImage();
				$image->setDimensionsFromImage($bg);
				$image->draw($bg);
				$image->setAlignHorizontal('center');
				$image->setAlignVertical('center');
				$image->setFont($font);
				$image->setTextColor($textColor);
				$image->setStrokeWidth(1);
				$image->setStrokeColor($strokeColor);
				$gutter = 50;
				$image->rectangle($gutter, $gutter, $image->getWidth() - $gutter * 2, $image->getHeight() - $gutter * 2, $rectangleColor, 0.5);
				$image->textBox("“".html_entity_decode($text)."”\n___\n".str_replace("\xe2\x80\x8b", '', $entity->getAuthor()->getTitle()), array(
					'width' => $image->getWidth() - $gutter * 2,
					'height' => $image->getHeight() - $gutter * 2,
					'fontSize' => $data["font_size"],
					'x' => $gutter,
					'y' => $gutter
				));

				imagepng($image->getResource(), Poem::PATH_FILE.$fileName);
				imagedestroy($image->getResource());
				fclose($tmp);
			}

			$entity->addImage(new PoemImage($fileName));
			
			$em->persist($entity);
			$em->flush();
			
			$redirect = $this->generateUrl('app_poemadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}

        return $this->render('Poem/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}

    /**
     * @Route("/remove_image/{id}/{poemImageId}")
     */
	public function removeImageAction(EntityManagerInterface $em, Request $request, $id, $poemImageId)
	{
		$entity = $em->getRepository(Poem::class)->find($id);
		$poemImage = $em->getRepository(PoemImage::class)->find($poemImageId);
		
		$fileName = $poemImage->getImage();
		
		$entity->removeImage($poemImage);
		
		$em->persist($entity);
		$em->flush();
		
		$filesystem = new Filesystem();
		$filesystem->remove(Poem::PATH_FILE.$fileName);
		
		$redirect = $this->generateUrl('app_poemadmin_show', array('id' => $entity->getId()));

		return $this->redirect($redirect);
	}

	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(PoemType::class, $entity, array('locale' => $locale));
	}

	private function checkForDoubloon(EntityManagerInterface $em, TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$checkForDoubloon = $em->getRepository(Poem::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}