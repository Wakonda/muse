<?php

namespace App\Controller;

use App\Entity\Quote;
use App\Entity\QuoteImage;
use App\Entity\User;
use App\Entity\Language;
use App\Entity\Biography;
use App\Entity\Source;
use App\Entity\Tag;
use App\Form\Type\QuoteType;
use App\Form\Type\ImageGeneratorType;
use App\Form\Type\QuoteFastMultipleType;
use App\Form\Type\QuoteEditMultipleType;
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

use seregazhuk\PinterestBot\Factories\PinterestBot;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Facebook;
use App\Service\Twitter;

require_once __DIR__.'/../../vendor/simple_html_dom.php';

/**
 * @Route("/admin/quote")
 */
class QuoteAdminController extends AbstractController
{
	private $formName = "quote";
	
	private $authorizedURLs = ['Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy', 'ZXZlbmUubGVmaWdhcm8uZnI='];

    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Quote/index.html.twig');
	}

    /**
     * @Route("/datatables")
     */
	public function indexDatatablesAction(Request $request, TranslatorInterface $translator)
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
		
		$entities = $entityManager->getRepository(Quote::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $entityManager->getRepository(Quote::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			$row[] = $entity->getText();
			$row[] = $entity->getLanguage()->getTitle();
			
			$show = $this->generateUrl('app_quoteadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_quoteadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a> - <a href="'.$edit.'" alt="Edit">'.$translator->trans('admin.index.Update').'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/new/{biographyId}/{sourceId}", defaults={"biographyId": null, "sourceId": null}, requirements={"biographyId"="\d+", "sourceId"="\d+"})
     */
    public function newAction(Request $request, $biographyId, $sourceId)
    {
		$entity = new Quote();
		
		$entityManager = $this->getDoctrine()->getManager();
		$language = $entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		
		$entity->setLanguage($language);
		
		if(!empty($biographyId))
			$entity->setBiography($entityManager->getRepository(Biography::class)->find($biographyId));
		
		if(!empty($sourceId))
			$entity->setSource($entityManager->getRepository(Source::class)->find($sourceId));

        $form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Quote/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(Request $request, TranslatorInterface $translator)
	{
		$entity = new Quote();
		$entityManager = $this->getDoctrine()->getManager();
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);

		$this->checkForDoubloon($translator, $entity, $form);

		if($form->isValid())
		{
			$entity->setState(0);
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('app_quoteadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Quote/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);

		$imageGeneratorForm = $this->createForm(ImageGeneratorType::class);
	
		return $this->render('Quote/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);

		return $this->render('Quote/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(Request $request, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);
		
		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($translator, $entity, $form);
		
		if(($entity->isBiography() and $entity->getBiography() == null) or ($entity->isUser() and $entity->getUser() == null))
			$form->get($entity->getAuthorType())->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));
		
		if($form->isValid())
		{
			$entityManager->persist($entity);
			$entityManager->flush();

			return $this->redirect($this->generateUrl('app_quoteadmin_show', array('id' => $entity->getId())));
		}
	
		return $this->render('Quote/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/edit_multiple")
     */
	public function editMultipleAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$ids = json_decode($request->query->get("ids"));
		$locale = $entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		$form = $this->createForm(QuoteEditMultipleType::class, null, array("locale" => $locale->getId()));

		return $this->render('Quote/editMultiple.html.twig', array('form' => $form->createView(), 'ids' => $ids));
	}

    /**
     * @Route("/update_multiple/{ids}")
     */
	public function updateMultipleAction(Request $request, SessionInterface $session, TranslatorInterface $translator, $ids)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$ids = json_decode($ids);
		$locale = $entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		$form = $this->createForm(QuoteEditMultipleType::class, null, array("locale" => $locale->getId()));
		$form->handleRequest($request);

		$req = $request->request->get($form->getName());

		foreach($ids as $id)
		{
			$entity = $entityManager->getRepository(Quote::class)->find($id);
			$tagsId = $req["tags"];

			foreach($tagsId as $tagId)
			{
				$tag = $entityManager->getRepository(Tag::class)->find($tagId);
				$realTag = $entityManager->getRepository(Tag::class)->findOneBy(["internationalName" => $tag->getInternationalName(), "language" => $entity->getLanguage()]);
				
				if(!empty($realTag))
				{
					if(!$entity->isTagExisted($realTag))
					{
						$entity->addTag($realTag);
						$entityManager->persist($entity);
					}
				}
			}

			$entityManager->flush();
		}
		
		$session->getFlashBag()->add('message', $translator->trans("admin.index.ChangesMadeSuccessfully"));

		return $this->redirect($this->generateUrl('app_quoteadmin_index'));
	}

    /**
     * @Route("/new_fast_multiple")
     */
	public function newFastMultipleAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$datas = $request->query->all();
		$datas = !empty($datas) ? json_decode($datas["datas"], true) : null;
		$entity = new Quote();
		
		$url = null;
		$ipProxy = null;

		if(!empty($datas)) {
			$entity->setLanguage($entityManager->getRepository(Language::class)->find($datas["language"]));
			$entity->setBiography($entityManager->getRepository(Biography::class)->find($datas["biography"]));
			
			if(!empty($datas["source"]))
				$entity->setSource($entityManager->getRepository(Source::class)->find($datas["source"]));
			
			$url = $datas["url"];
			$ipProxy = $datas["ipProxy"];
		} else
			$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

		$form = $this->createForm(QuoteFastMultipleType::class, $entity, array("locale" => $request->getLocale(), "url" => $url, "ipProxy" => $ipProxy));

		return $this->render('Quote/fastMultiple.html.twig', array('form' => $form->createView(), 'language' => $request->getLocale(), 'authorizedURLs' => $this->authorizedURLs));
	}

    /**
     * @Route("/add_fast_multiple")
     */
	public function addFastMultipleAction(Request $request, SessionInterface $session, TranslatorInterface $translator)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Quote();
		
		$form = $this->createForm(QuoteFastMultipleType::class, $entity, array("locale" => $request->getLocale()));
		
		$form->handleRequest($request);
		$req = $request->request->get($form->getName());

		if(!empty($req["url"]) and filter_var($req["url"], FILTER_VALIDATE_URL))
		{
			$url = $req["url"];
			$url_array = parse_url($url);

			if(!in_array(base64_encode($url_array['host']), $this->authorizedURLs))
				$form->get("url")->addError(new FormError($translator->trans("admin.error.UnknownURL")));
			else {
				switch(base64_encode($url_array['host']))
				{
					case 'ZXZlbmUubGVmaWdhcm8uZnI=':
					case 'Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy':
						if(!isset($req["biography"]) or empty($req["biography"]))
							$form->get("biography")->addError(new FormError($translator->trans((new Assert\NotBlank())->message, [], 'validators')));
						break;
				}
			}
		}

		if($form->isValid())
		{
			$i = 0;
			$gf = new GenericFunction();
			
			if(!empty($ipProxy = $form->get('ipProxy')->getData()))
				$html = $gf->getContentURL($url, $ipProxy);
			else
				$html = $gf->getContentURL($url);
			
			$entitiesArray = [];

			$dom = new \simple_html_dom();
			$dom->load($html);
die("ok");
			switch(base64_encode($url_array['host']))
			{
				case 'Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy':
					$urlArray = parse_url($url, PHP_URL_PATH);
					
					$type = array_filter(explode("/", $urlArray))[1];
					
					foreach($dom->find('.citation') as $pb)
					{
						$save = true;
						$entityNew = clone $entity;
						$q = current($pb->find("q"));

						$text = html_entity_decode($q->plaintext, ENT_QUOTES);
						
						if($type == "personnage") {
							
							$sourceTitle = html_entity_decode(current($pb->find(".auteurLien"))->plaintext, ENT_QUOTES);
							$source = $entityManager->getRepository(Source::class)->getSourceByAuthorsAndTitle($entity->getBiography(), $sourceTitle);
							
							if(!empty($source))
								$entityNew->setSource($source);
							else
								if(empty($sourceTitle))
									$save = false;
						}
						
						$entityNew->setText($text);
						
						if($save)
							$entitiesArray[] = $entityNew;
					}
					break;
				case 'ZXZlbmUubGVmaWdhcm8uZnI=':
					foreach($dom->find('.figsco__selection__list__evene__list__item') as $pb)
					{
						$save = true;
						$entityNew = clone $entity;
						
						$a = current($pb->find("a"));
						$text = html_entity_decode($a->plaintext);
						$entityNew->setText(trim(trim($text, "“"), "”"));
						
						$div = $pb->find(".figsco__quote__from .figsco__fake__col-9");		
						$div = preg_replace('#<div class="figsco__note__users">(.*?)</div>#', '', current($div)->innertext);

						$div = explode("/", strip_tags($div));
						$source = null;
						
						$entityNew->setSource(null);

						if(isset($div[1])) {
							$source = $entityManager->getRepository(Source::class)->getSourceByAuthorsAndTitle($entity->getBiography(), trim($div[1]));
							
							if(!empty($source))
								$entityNew->setSource($source);
							else
								$save = false;
						}
							
						
						if($save)
							$entitiesArray[] = $entityNew;
					}
					break;
			}

			$numberAdded = 0;
			$numberDoubloons = 0;

			$entityManager = $this->getDoctrine()->getManager();

			foreach($entitiesArray as $entity)
			{
				if($entityManager->getRepository(Quote::class)->checkForDoubloon($entity) > 0)
					$numberDoubloons++;
				else
				{
					$entityManager->persist($entity);
					$entityManager->flush();
					$numberAdded++;
				}
			}

			$session->getFlashBag()->add('message', $translator->trans("admin.index.AddedSuccessfully", ["%numberAdded%" => $numberAdded, "%numberDoubloons%" => $numberDoubloons]));
	
			unset($req["_token"]);

			return $this->redirect($this->generateUrl('app_quoteadmin_newfastmultiple', ["datas" => json_encode($req)]));
		}
		
		return $this->render('Quote/fastMultiple.html.twig', array('form' => $form->createView(), 'language' => $request->getLocale(), 'authorizedURLs' => $this->authorizedURLs));
	}

    /**
     * @Route("/twitter/{id}")
     */
	public function twitterAction(Request $request, SessionInterface $session, TranslatorInterface $translator, Twitter $twitter, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);

		$locale = $entity->getLanguage()->getAbbreviation();
// print_r($locale);die;
		// $consumer_key = $_ENV["TWITTER_CONSUMER_KEY_".$locale];
		// $consumer_secret = $_ENV["TWITTER_CONSUMER_SECRET_".$locale];
		// $access_token = $_ENV["TWITTER_ACCESS_TOKEN_".$locale];
		// $access_token_secret = $_ENV["TWITTER_ACCESS_TOKEN_SECRET_".$locale];

		// $connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
		
		$message = $request->request->get("twitter_area")." ".$this->generateUrl("app_indexquotus_read", array("id" => $id, 'slug' => $entity->getSlug()), UrlGeneratorInterface::ABSOLUTE_URL);

		// $parameters = [];
		// $parameters["status"] = $message;
		$imageId = $request->request->get('image_id_tweet');

		$quoteImage = null;
		$image = null;

		if(!empty($imageId)) {
			$quoteImage = $entityManager->getRepository(QuoteImage::class)->find($imageId);
			$image = Quote::PATH_FILE.$quoteImage->getImage();
			
			// $media = $connection->upload('media/upload', array('media' => Quote::PATH_FILE.$quoteImage->getImage()));
			// $parameters['media_ids'] = implode(',', array($media->media_id_string));
		}
		
		$statues = $twitter->sendTweet($message, $image, $locale);

		// $statues = $connection->post("statuses/update", $parameters);
	
		if(isset($statues->errors) and !empty($statues->errors))
			$session->getFlashBag()->add('message', "Twitter - ".$translator->trans("admin.index.SentError").json_encode($statues->errors));
		else {
			if(!empty($quoteImage)) {
				$quoteImage->addSocialNetwork("Twitter");
				$entityManager->persist($quoteImage);
				$entityManager->flush();
			}
		
			$session->getFlashBag()->add('message', "Twitter - ".$translator->trans("admin.index.SentSuccessfully"));
		}
	
		return $this->redirect($this->generateUrl("app_quoteadmin_show", array("id" => $id)));
	}

    /**
     * @Route("/pinterest/{id}")
     */
	public function pinterestAction(Request $request, SessionInterface $session, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		
		$mail = $_ENV["PINTEREST_MAIL"];
		$pwd = $_ENV["PINTEREST_PASSWORD"];
		$username = $_ENV["PINTEREST_USERNAME"];

		$bot = PinterestBot::create();
		$bot->auth->login($mail, $pwd);
		
		$boards = $bot->boards->forUser($username);
		
		$i = 0;

		foreach($boards as $board) {
			if($pinterestBoards[$board["name"]] == $entity->getLanguage()->getAbbreviation())
				break;

			$i++;
		}
		
		$imageId = $request->request->get('image_id_pinterest');
		$quoteImage = $entityManager->getRepository(QuoteImage::class)->find($imageId);
		
		if(empty($quoteImage)) {
			$session->getFlashBag()->add('message', "Pinterest - ".$translator->trans("admin.index.YouMustSelectAnImage"));
			return $this->redirect($this->generateUrl("app_quoteadmin_show", array("id" => $id)));
		}

		$bot->pins->create($request->getUriForPath('/'.Quote::PATH_FILE.$quoteImage->getImage()), $boards[0]['id'], $request->request->get("pinterest_area"), $this->generateUrl("read", ["id" => $entity->getId(), "slug" => $entity->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));
		
		if(empty($bot->getLastError())) {
			$session->getFlashBag()->add('message', "Pinterest - ".$translator->trans("admin.index.SentSuccessfully"));
			
			$quoteImage->addSocialNetwork("Pinterest");
			$entityManager->persist($quoteImage);
			$entityManager->flush();
		}
		else
			$session->getFlashBag()->add('message', $bot->getLastError());
	
		return $this->redirect($this->generateUrl("app_quoteadmin_show", array("id" => $id)));
	}

    /**
     * @Route("/facebook/{id}")
     */
	public function facebookAction(Request $request, TranslatorInterface $translator, Facebook $facebook, SessionInterface $session, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		
		$quoteImage = null;
		
		$quote = $entityManager->getRepository(Quote::class)->find($id);

		if(!empty($request->request->get("image_id_facebook"))) {
			$quoteImage = $entityManager->getRepository(QuoteImage::class)->find($request->request->get("image_id_facebook"));
			$url = $this->generateUrl("app_indexquotus_read", ["id" => $id, "slug" => $quoteImage->getQuote()->getSlug(), "idImage" => $quoteImage->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
		} else {
			$url = $this->generateUrl("app_indexquotus_read", ["id" => $id, "slug" => $quote->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
		}

		$res = json_decode($facebook->postMessage($url, $request->request->get("facebook_area"), $quote->getLanguage()->getAbbreviation()));
		
		if(property_exists($res, "error")) {
			$session->getFlashBag()->add('message', "Facebook - ".$translator->trans("admin.index.SentError")." (".$res->error->message.")");
		} else {
			if(!empty($quoteImage)) {
				$quoteImage->addSocialNetwork("Facebook");
				$entityManager->persist($quoteImage);
				$entityManager->flush();
			}

			$session->getFlashBag()->add('message', "Facebook - ".$translator->trans("admin.index.SentSuccessfully"));
		}

		return $this->redirect($this->generateUrl("app_quoteadmin_show", ["id" => $id]));
	}

    /**
     * @Route("/save_image/{id}")
     */
	public function saveImageAction(Request $request, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		
        $imageGeneratorForm = $this->createForm(ImageGeneratorType::class);
        $imageGeneratorForm->handleRequest($request);
		$data = $imageGeneratorForm->getData();
		
		if(empty($data["image"]["title"]) and empty($data["image"]["content"]))
			$imageGeneratorForm->get("image")["name"]->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if ($imageGeneratorForm->isSubmitted() && $imageGeneratorForm->isValid())
		{
			$file = $data['image']["content"];
            $fileName = md5(uniqid()).'_'.$data["image"]["title"];
			$text = $entity->getText();
			
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
				
				$text = html_entity_decode($entity->getText(), ENT_QUOTES);
				
				$imageGenerator->setText($text);
				$imageGenerator->setCopyright(["x" => $copyright_x, "y" => $copyright_y, "text" => "quotus.wakonda.guru"]);

				$imageGenerator->generate($start_x, $start_y, $widthText);

				imagepng($image, Quote::PATH_FILE.$fileName);
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
				$fontSizeAuthor = 20;
				$image->rectangle($gutter, $gutter, $image->getWidth() - $gutter * 2, $image->getHeight() - $gutter * 2 + $fontSizeAuthor, $rectangleColor, 0.5);
				$image->textBox("“".html_entity_decode($text)."”", array(
					'width' => $image->getWidth() - $gutter * 2,
					'height' => $image->getHeight() - $gutter * 2,
					'fontSize' => $data["font_size"],
					'x' => $gutter,
					'y' => $gutter
				));
				
				$image->textBox($entity->getAuthor(), array('width' => $image->getWidth() - $gutter * 2, 'fontSize' => $fontSizeAuthor, 'x' => $gutter, 'y' => ($image->getHeight() - $gutter * 2) + $fontSizeAuthor * 2));

				imagepng($image->getResource(), Quote::PATH_FILE.$fileName);
				imagedestroy($image->getResource());
				fclose($tmp);
			}

			$entity->addImage(new QuoteImage($fileName));
			
			$entityManager->persist($entity);
			$entityManager->flush();
			
			$redirect = $this->generateUrl('app_quoteadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}

        return $this->render('Quote/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}

    /**
     * @Route("/remove_image/{id}/{quoteImageId}")
     */
	public function removeImageAction(Request $request, $id, $quoteImageId)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Quote::class)->find($id);
		
		$quoteImage = $entityManager->getRepository(QuoteImage::class)->find($quoteImageId);
		
		$fileName = $quoteImage->getImage();
		
		$entity->removeQuoteImage($quoteImage);
		
		$entityManager->persist($entity);
		$entityManager->flush();
		
		$filesystem = new Filesystem();
		$filesystem->remove(Quote::PATH_FILE.$fileName);
		
		$redirect = $this->generateUrl('app_quoteadmin_show', array('id' => $entity->getId()));

		return $this->redirect($redirect);
	}

	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(QuoteType::class, $entity, array('locale' => $locale));
	}

	private function checkForDoubloon(TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getText() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(Quote::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}