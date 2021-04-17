<?php

namespace App\Twig;

use App\Service\Captcha;
use App\Service\Gravatar;
use App\Service\GenericFunction;

use Doctrine\ORM\EntityManagerInterface;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class MuseExtension extends AbstractExtension
{
	private $em;
	
	public function __construct(EntityManagerInterface $em)
	{
		$this->em = $em;
	}

    public function getFilters() {
        return array(
			new TwigFilter('html_entity_decode', array($this, 'htmlEntityDecodeFilter')),
			new TwigFilter('toString', array($this, 'toStringFilter')),
			new TwigFilter('max_size_image', array($this, 'maxSizeImageFilter'), array('is_safe' => array('html'))),
			new TwigFilter('remove_control_characters', array($this, 'removeControlCharactersFilter')),
			new TwigFilter('base64_decode', array($this, 'base64DecodeFilter'))
        );
    }
	
	public function getFunctions() {
		return array(
			new TwigFunction('sub_domain', array($this, 'getSubDomain')),
			new TwigFunction('captcha', array($this, 'generateCaptcha')),
			new TwigFunction('gravatar', array($this, 'generateGravatar')),
			new TwigFunction('number_version', array($this, 'getCurrentVersion')),
			new TwigFunction('minify_file', array($this, 'minifyFile')),
			new TwigFunction('count_unread_messages', array($this, 'countUnreadMessagesFunction')),
			new TwigFunction('code_by_language', array($this, 'getCodeByLanguage')),
			new TwigFunction('random_image', array($this, 'randomImage')),
			new TwigFunction('text_month', array($this, 'textMonth')),
			new TwigFunction('date_biography_letter', array($this, 'dateBiographyLetter'), array('is_safe' => array('html'))),
			new TwigFunction('date_letter', array($this, 'dateLetter'), array('is_safe' => array('html'))),
			new TwigFunction('display_file', array($this, 'displayFileManagement'), array('is_safe' => array('html')))
		);
	}

    public function getStringObject($arraySubEntity, $element) {
		if(!is_null($arraySubEntity) and array_key_exists ($element, $arraySubEntity))
			return $arraySubEntity[$element];

        return "";
    }

    public function htmlEntityDecodeFilter($str) {
        return html_entity_decode($str);
    }
	
	public function textMonth($year, $month, $locale)
	{
		list($arrayBCYear, $arrayMonth) = $this->formatDateByLocale();
		return $arrayMonth[$locale]["months"][intval($month) - 1].(!empty($year) ? $arrayMonth[$locale]["separator"].($year < 0 ? abs($year)." ".$arrayBCYear[$locale] : $year) : "");
	}
	
	public function maxSizeImageFilter($img, array $options = [], $isPDF = false)
	{
		$basePath = ($isPDF) ? '' : '/';

		if(!file_exists($img) or !is_file($img))
			return '<img src="'.$basePath.'photo/640px-Starry_Night_Over_the_Rhone.jpg" alt="" style="max-width: 400px" />';
		
		$imageSize = getimagesize($img);

		$width = $imageSize[0];
		$height = $imageSize[1];
		
		$max_width = 500;
				
		if($width > $max_width)
		{
			$height = ($max_width * $height) / $width;
			$width = $max_width;
		}

		return '<img src="'.$basePath.$img.'" alt="" style="max-width: '.$width.'px;" />';
	}
	
	public function dateBiographyLetter($year, $month, $day, $locale)
	{
		list($arrayBCYear, $arrayMonth) = $this->formatDateByLocale();
		$month = $arrayMonth[$locale]["months"][$month - 1];
		
		$day = ($day == 1) ? $day.((!empty($arrayMonth[$locale]["sup"])) ? "<sup>".$arrayMonth[$locale]["sup"]."</sup>" : "") : $day;
		
		return ltrim($day, "0")." ".$month." ".($year < 0 ? abs($year)." ".$arrayBCYear[$locale] : $year);
	}

	public function dateLetter(\DateTime $date, $locale)
	{
		list($arrayBCYear, $arrayMonth) = $this->formatDateByLocale();
		$month = $arrayMonth[$locale]["months"][$date->format("m") - 1];
		$day = $date->format("d");
		$year = $date->format("Y");
		
		$day = ($day == 1) ? $day.((!empty($arrayMonth[$locale]["sup"])) ? "<sup>".$arrayMonth[$locale]["sup"]."</sup>" : "") : $day;
		
		return ltrim($day, "0")." ".$month." ".($year < 0 ? abs($year)." ".$arrayBCYear[$locale] : $year);
	}
	
	public function removeControlCharactersFilter($string)
	{
		return preg_replace("/[^a-zA-Z0-9 .\-_;!:?äÄöÖüÜß<>='\"]/", "", $string);
	}
	
	public function base64DecodeFilter($string)
	{
		return base64_decode($string);
	}
	
	public function generateCaptcha($request)
	{
		$captcha = new Captcha($request->getSession());

		$wordOrNumberRand = rand(1, 2);
		$length = rand(3, 7);

		if($wordOrNumberRand == 1)
			$word = $captcha->wordRandom($length);
		else
			$word = $captcha->numberRandom($length);
		
		return $captcha->generate($word);
	}

	public function generateGravatar()
	{
		$gr = new Gravatar();

		return $gr->getURLGravatar();
	}

	public function countUnreadMessagesFunction()
	{
		return $this->em->getRepository("App\Entity\Contact")->countUnreadMessages();
	}
	
	public function minifyFile($file)
	{
		$mn = new \App\Service\MinifyFile($file);
		return $mn->save();
	}
	
	public function randomImage($entity) {
		$imageArray = [];

		if(empty($imageArray))
			return $entity->getImages();

		foreach($entity->getImages() as $image)
			$imageArray[] = $image->getImage();
		
		return $imageArray[array_rand($imageArray)];
	}
	
	public function getCodeByLanguage($locale): String
	{
		switch($locale)
		{
			case "it":
				return "it";
			case "pt":
				return "pt_PT";
			case "en":
				return "en_GB";
			default:
				return "fr_FR";
		}
	}
	
	public function displayFileManagement($entity, $caption = true, $isPDF = false) {
		$basePath = ($isPDF) ? '' : '/';
		
		$class = get_class($entity);
		$img = null;

		if(method_exists($entity, "getFileManagement") and !empty($entity->getFileManagement())) {
			$img = $class::PATH_FILE.$entity->getFileManagement()->getPhoto();
		}

		if(empty($img) or !file_exists($img) or !is_file($img))
			return '<img src="'.$basePath.'photo/640px-Starry_Night_Over_the_Rhone.jpg" alt="" style="max-width: 400px" class="img-responsive mx-auto d-block" />';
		
		$imageSize = getimagesize($img);

		$width = $imageSize[0];
		$height = $imageSize[1];
		
		$max_width = 500;
				
		if($width > $max_width)
		{
			$height = ($max_width * $height) / $width;
			$width = $max_width;
		}
		
		$strImg = '<img src="'.$basePath.$img.'" alt="" style="max-width: '.$width.'px;" class="img-responsive mx-auto d-block" />';

		if(!$caption or empty($entity->getFileManagement()->getDescription()))
			return $strImg;
		
		return '<figure class="image">'.$strImg.'<figcaption>'.$entity->getFileManagement()->getDescription().'</figcaption></figure>';
	}
	
	public function getSubDomain(): String {
		return (new GenericFunction())->getSubDomain();
	}
	
	public function getCurrentVersion()
	{
		return $this->em->getRepository("App\Entity\Version")->getCurrentVersion();
	}

	private function formatDateByLocale()
	{
		$arrayMonth = array();
		$arrayMonth['fr'] = array("sup" => "er", "separator" => " ", "months" => array("janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre"));
		$arrayMonth['it'] = array("sup" => "°", "separator" => " ", "months" => array("gennaio", "febbraio", "marzo", "aprile", "maggio", "guigno", "luglio", "agosto", "settembre", "ottobre", "novembre", "dicembre"));
		$arrayMonth['pt'] = array("sup" => null, "separator" => " de ", "months" => array("janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"));
		$arrayMonth['en'] = array("sup" => "st", "separator" => " ", "months" => array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"));
	
		$arrayBCYear = [];
		$arrayBCYear["fr"] = "av. J.-C.";
		$arrayBCYear["it"] = "a.C.";
		$arrayBCYear["pt"] = "a.C.";
		$arrayBCYear["en"] = "BC";

		return [$arrayBCYear, $arrayMonth];
	}
}