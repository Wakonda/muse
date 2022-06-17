<?php

namespace App\Controller;

use App\Entity\Proverb;
use App\Entity\ProverbImage;
use App\Entity\Country;
use App\Entity\Language;
use App\Entity\Tag;
use App\Service\GenericFunction;
use App\Service\ImageGenerator;
use App\Form\Type\ProverbType;
use App\Form\Type\ProverbFastMultipleType;
use App\Form\Type\ProverbEditMultipleType;
use App\Form\Type\ImageGeneratorType;
use App\Service\PHPImage;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

use Abraham\TwitterOAuth\TwitterOAuth;
use seregazhuk\PinterestBot\Factories\PinterestBot;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Facebook;

require_once __DIR__.'/../../vendor/simple_html_dom.php';

/**
 * @Route("/admin/proverb")
 */
class ProverbAdminController extends AbstractController
{
	private $formName = "proverb";
	private $authorizedURLs = ['d3d3LmxpbnRlcm5hdXRlLmNvbQ==', 'Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy', 'ZGljb2NpdGF0aW9ucy5sZW1vbmRlLmZy', 'd3d3LnByb3ZlcmJlcy1mcmFuY2Fpcy5mcg==', 'Y3JlYXRpdmVwcm92ZXJicy5jb20=', 'd3d3LnNwZWNpYWwtZGljdGlvbmFyeS5jb20='];

    /**
     * @Route("/")
     */
	public function indexAction(Request $request)
	{
		return $this->render('Proverb/index.html.twig');
	}

    /**
     * @Route("/datatables")
     */
	public function indexDatatablesAction(Request $request, TranslatorInterface $translator)
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
		$entities = $entityManager->getRepository(Proverb::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $entityManager->getRepository(Proverb::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => []
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$row["DT_RowId"] = $entity->getId();
			$row[] = $entity->getId();
			$row[] = $entity->getText();
			$row[] = $entity->getLanguage()->getTitle();
			
			$show = $this->generateUrl('app_proverbadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('app_proverbadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a> - <a href="'.$edit.'" alt="Edit">'.$translator->trans('admin.index.Update').'</a>';

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/new/{countryId}", defaults={"countryId": null}, requirements={"countryId"="\d+"})
     */
    public function newAction(Request $request, $countryId)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Proverb();
		
		$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));
		
		if(!empty($countryId))
			$entity->setCountry($entityManager->getRepository(Country::class)->find($countryId));
		
		$form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Proverb/new.html.twig', array('form' => $form->createView()));
    }

    /**
     * @Route("/create")
     */
	public function createAction(Request $request, TranslatorInterface $translator)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Proverb();
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($translator, $entity, $form);

		if($form->isValid())
		{
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('app_proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Proverb/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/show/{id}")
     */
	public function showAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
		$imageGeneratorForm = $this->createForm(ImageGeneratorType::class);
	
		return $this->render('Proverb/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}

    /**
     * @Route("/edit/{id}")
     */
	public function editAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);
	
		return $this->render('Proverb/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update/{id}")
     */
	public function updateAction(Request $request, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);
		
		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($translator, $entity, $form);
		
		if($form->isValid())
		{
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('app_proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Proverb/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/edit_multiple")
     */
	public function editMultipleAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$ids = json_decode($request->query->get("ids"));
		$locale = $entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		$form = $this->createForm(ProverbEditMultipleType::class, null, array("locale" => $locale->getId()));

		return $this->render('Proverb/editMultiple.html.twig', array('form' => $form->createView(), 'ids' => $ids));
	}

    /**
     * @Route("/update_multiple/{ids}")
     */
	public function updateMultipleAction(Request $request, SessionInterface $session, TranslatorInterface $translator, $ids)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$ids = json_decode($ids);
		$locale = $entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]);
		$form = $this->createForm(ProverbEditMultipleType::class, null, array("locale" => $locale->getId()));
		$form->handleRequest($request);

		$req = $request->request->get($form->getName());

		foreach($ids as $id)
		{
			$entity = $entityManager->getRepository(Proverb::class)->find($id);
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

		return $this->redirect($this->generateUrl('app_proverbadmin_index'));
	}

    /**
     * @Route("/new_fast_multiple")
     */
	public function newFastMultipleAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$datas = $request->query->all();
		$datas = !empty($datas) ? json_decode($datas["datas"], true) : null;
		$entity = new Proverb();

		$url = null;
		$ipProxy = null;

		if(!empty($datas)) {
			$entity->setLanguage($entityManager->getRepository(Language::class)->find($datas["language"]));

			$url = $datas["url"];
			$ipProxy = $datas["ipProxy"];
		} else
			$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));

		$form = $this->createForm(ProverbFastMultipleType::class, $entity, ["locale" => $request->getLocale(), "url" => $url, "ipProxy" => $ipProxy]);

		return $this->render('Proverb/fastMultiple.html.twig', array('form' => $form->createView(), "authorizedURLs" => $this->authorizedURLs));
	}

    /**
     * @Route("/add_fast_multiple")
     */
	public function addFastMultipleAction(Request $request, SessionInterface $session, TranslatorInterface $translator)
	{
		$entity = new Proverb();
		
		$form = $this->createForm(ProverbFastMultipleType::class, $entity, ["locale" => $request->getLocale()]);
		
		$form->handleRequest($request);
		$req = $request->request->get($form->getName());

		if(!empty($req["url"]) and filter_var($req["url"], FILTER_VALIDATE_URL))
		{
			$url = $req["url"];
			$url_array = parse_url($url);

			if(!in_array(base64_encode($url_array['host']), $this->authorizedURLs))
				$form->get("url")->addError(new FormError($translator->trans("admin.error.UnknownURL")));
		}

		if($form->isValid())
		{
			$gf = new GenericFunction();

			if(!empty($ipProxy = $form->get('ipProxy')->getData()))
				$html = $gf->getContentURL($url, $ipProxy);
			else
				$html = $gf->getContentURL($url);

			$proverbsArray = [];

			$dom = new \simple_html_dom();
			$dom->load($html);

			switch(base64_encode($url_array['host']))
			{
				case 'd3d3LmxpbnRlcm5hdXRlLmNvbQ==':
					foreach($dom->find('td.libelle_proverbe strong') as $pb)
					{					
						$entityProverb = clone $entity;
						$text = str_replace("\r\n", "", trim($pb->plaintext));
						
						$entityProverb->setText($text);

						$proverbsArray[] = $entityProverb;
					}
					break;
				case 'Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy':
					foreach($dom->find('div#citation_citationSearchList q') as $pb)
					{					
						$entityProverb = clone $entity;
						$text = str_replace("\r\n", "", trim($pb->plaintext));
						
						$entityProverb->setText($text);
						
						$proverbsArray[] = $entityProverb;
					}
					break;
				case 'ZGljb2NpdGF0aW9ucy5sZW1vbmRlLmZy':
					foreach($dom->find('div#content blockquote') as $pb)
					{
						$entityProverb = clone $entity;
						$text = str_replace("\r\n", "", trim(utf8_encode($pb->plaintext)));

						$entityProverb->setText($text);

						$proverbsArray[] = $entityProverb;
					}
					break;
				case 'd3d3LnByb3ZlcmJlcy1mcmFuY2Fpcy5mcg==':
					foreach($dom->find("div.post q") as $pb)
					{
						$entityProverb = clone $entity;
						$entityProverb->setText($pb->plaintext);

						$proverbsArray[] = $entityProverb;
					}
					break;
				case 'Y3JlYXRpdmVwcm92ZXJicy5jb20=':
					foreach($dom->find('center table tr td center table[CELLPADDING=10] tr td') as $pb) {
						if(!empty($pb->plaintext)) {
							$entityProverb = clone $entity;
							$entityProverb->setText($pb->plaintext);

							$proverbsArray[] = $entityProverb;
						}
					}
				break;
				case 'd3d3LnNwZWNpYWwtZGljdGlvbmFyeS5jb20=':
					foreach($dom->find('.quotes li') as $quote)
					{
						$entityProverb = clone $entity;
						$content = $quote->innertext;
						$content = preg_replace('/<span[^>]*>.*?<\/span>/i', '', $content);
						
						$entityProverb->setText(strip_tags($content));
						
						$proverbsArray[] = $entityProverb;
					}
				break;
			}

			$numberAdded = 0;
			$numberDoubloons = 0;

			$entityManager = $this->getDoctrine()->getManager();

			foreach($proverbsArray as $proverb)
			{
				if($entityManager->getRepository(Proverb::class)->checkForDoubloon($proverb) > 0)
					$numberDoubloons++;
				else
				{
					$entityManager->persist($proverb);
					$entityManager->flush();
					$numberAdded++;
				}
			}

			$session->getFlashBag()->add('message', $translator->trans("admin.index.AddedSuccessfully", ["%numberAdded%" => $numberAdded, "%numberDoubloons%" => $numberDoubloons]));

			unset($req["_token"]);

			return $this->redirect($this->generateUrl('app_proverbadmin_newfastmultiple', ["datas" => json_encode($req)]));
		}
		
		return $this->render('Proverb/fastMultiple.html.twig', array('form' => $form->createView(), "authorizedURLs" => $this->authorizedURLs));
	}

    /**
     * @Route("/twitter/{id}")
     */
	public function twitterAction(Request $request, SessionInterface $session, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);

		$locale = strtoupper($entity->getLanguage()->getAbbreviation());
		
		$consumer_key = $_ENV["TWITTER_CONSUMER_KEY_".$locale];
		$consumer_secret = $_ENV["TWITTER_CONSUMER_SECRET_".$locale];
		$access_token = $_ENV["TWITTER_ACCESS_TOKEN_".$locale];
		$access_token_secret = $_ENV["TWITTER_ACCESS_TOKEN_SECRET_".$locale];

		$connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
		$parameters = [];
		$parameters["status"] = $request->request->get("twitter_area")." ".$this->generateUrl("app_indexproverbius_read", array("id" => $id, 'slug' => $entity->getSlug()), UrlGeneratorInterface::ABSOLUTE_URL);
		$imageId = $request->request->get('image_id_tweet');
		
		$proverbImage = null;

		if(!empty($imageId)) {
			$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($imageId);
			$media = $connection->upload('media/upload', array('media' => Proverb::PATH_FILE.$proverbImage->getImage()));
			$parameters['media_ids'] = implode(',', array($media->media_id_string));
		}

		$statues = $connection->post("statuses/update", $parameters);
		
		if(isset($statues->errors) and !empty($statues->errors))
			$session->getFlashBag()->add('message', "Twitter - ".$translator->trans("admin.index.SentError"));
		else {
			if(!empty($proverbImage)) {
				$proverbImage->addSocialNetwork("Twitter");
				$entityManager->persist($proverbImage);
				$entityManager->flush();
			}
		
			$session->getFlashBag()->add('message', "Twitter - ".$translator->trans("admin.index.SentSuccessfully"));
		}
	
		return $this->redirect($this->generateUrl("app_proverbadmin_show", array("id" => $id)));
	}

    /**
     * @Route("/pinterest/{id}")
     */
	public function pinterestAction(Request $request, SessionInterface $session, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
		$mail = $_ENV["PINTEREST_MAIL"];
		$pwd = $_ENV["PINTEREST_PASSWORD"];
		$username = $_ENV["PINTEREST_USERNAME"];

		$pinterestBoards = [
			"Proverbes" => "fr",
			"Proverbs" => "en"
		];

		$bot = PinterestBot::create();
		$bot->auth->login($mail, $pwd);
		
		$boards = $bot->boards->forUser($username);
		$i = 0;

		foreach($boards as $board) {
			if(!isset($pinterestBoards[$board["name"]]) or $pinterestBoards[$board["name"]] == $entity->getLanguage()->getAbbreviation()) {
				break;
			}
			$i++;
		}

		$imageId = $request->request->get('image_id_pinterest');
		$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($imageId);
		
		if(empty($proverbImage)) {
			$session->getFlashBag()->add('message', $translator->trans("admin.index.YouMustSelectAnImage"));
			return $this->redirect($this->generateUrl("app_proverbadmin_show", array("id" => $id)));
		}
			
		$bot->pins->create($request->getUriForPath('/'.Proverb::PATH_FILE.$proverbImage->getImage()), $boards[$i]['id'], $request->request->get("pinterest_area"), $this->generateUrl("app_indexproverbius_read", ["id" => $entity->getId(), "slug" => $entity->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));
		
		if(empty($bot->getLastError())) {
			$session->getFlashBag()->add('message', "Pinterest - ".$translator->trans("admin.index.SentSuccessfully"));

			$proverbImage->addSocialNetwork("Pinterest");
			$entityManager->persist($proverbImage);
			$entityManager->flush();
		}
		else
			$session->getFlashBag()->add('message', $bot->getLastError());
	
		return $this->redirect($this->generateUrl("app_proverbadmin_show", array("id" => $id)));
	}

    /**
     * @Route("/facebook/{id}")
     */
	public function facebookAction(Request $request, TranslatorInterface $translator, Facebook $facebook, SessionInterface $session, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		
		$proverbImage = null;
		
		$proverb = $entityManager->getRepository(Proverb::class)->find($id);

		if(!empty($request->request->get("image_id_facebook"))) {
			$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($request->request->get("image_id_facebook"));
			$url = $this->generateUrl("app_indexproverbius_read", ["id" => $id, "slug" => $proverbImage->getProverb()->getSlug(), "idImage" => $proverbImage->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
		} else {
			$url = $this->generateUrl("app_indexproverbius_read", ["id" => $id, "slug" => $proverb->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
		}
		
		$res = json_decode($facebook->postMessage($url, $request->request->get("facebook_area"), $proverb->getLanguage()->getAbbreviation()));
		
		if(property_exists($res, "error")) {
			$session->getFlashBag()->add('message', "Facebook - ".$translator->trans("admin.index.SentError")." (".$res->error->message.")");
		} else {
			if(!empty($proverbImage)) {
				$proverbImage->addSocialNetwork("Facebook");
				$entityManager->persist($proverbImage);
				$entityManager->flush();	
			}

			$session->getFlashBag()->add('message', "Facebook - ".$translator->trans("admin.index.SentSuccessfully"));
		}

		return $this->redirect($this->generateUrl("app_proverbadmin_show", ["id" => $id]));
	}

    /**
     * @Route("/save_image/{id}")
     */
	public function saveImageAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
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
				$imageGenerator->setCopyright(["x" => $copyright_x, "y" => $copyright_y, "text" => "proverbius.wakonda.guru"]);

				$imageGenerator->generate($start_x, $start_y, $widthText);

				imagepng($image, "/".Proverb::PATH_FILE.$fileName);
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
				$image->textBox("“".html_entity_decode($text)."”", array(
					'width' => $image->getWidth() - $gutter * 2,
					'height' => $image->getHeight() - $gutter * 2,
					'fontSize' => $data["font_size"],
					'x' => $gutter,
					'y' => $gutter
				));

				imagepng($image->getResource(), Proverb::PATH_FILE.$fileName);
				imagedestroy($image->getResource());
				fclose($tmp);
			}

			$entity->addImage(new ProverbImage($fileName));
			
			$entityManager->persist($entity);
			$entityManager->flush();
			
			$redirect = $this->generateUrl('app_proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}

        return $this->render('Proverb/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}

    /**
     * @Route("/remove_image/{id}/{proverbImageId}")
     */
	public function removeImageAction(Request $request, $id, $proverbImageId)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($proverbImageId);
		
		$fileName = $proverbImage->getImage();
		
		$entity->removeImage($proverbImage);
		
		$entityManager->persist($entity);
		$entityManager->flush();
		
		$filesystem = new Filesystem();
		$filesystem->remove(Proverb::PATH_FILE.$fileName);
		
		$redirect = $this->generateUrl('app_proverbadmin_show', array('id' => $entity->getId()));

		return $this->redirect($redirect);
	}
	
	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(ProverbType::class, $entity, array('locale' => $locale));
	}
	
	private function checkForDoubloon(TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getText() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(Proverb::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("text")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}