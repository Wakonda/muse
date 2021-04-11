<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Source;
use App\Entity\Language;
use App\Entity\Country;
use App\Entity\Biography;
use App\Entity\Tag;
use App\Entity\FileManagement;
use App\Entity\PoeticForm;
use App\Entity\Poem;
use App\Entity\PoemImage;
use App\Entity\PoemVote;
use App\Entity\User;
use App\Entity\Store;
use App\Entity\Quote;
use App\Entity\QuoteImage;
use App\Entity\Proverb;
use App\Entity\ProverbImage;
use App\Entity\Page;

class DatasCommand extends Command
{
    protected static $defaultName = 'app:datas';

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
		parent::__construct();
        $this->em = $em;
    }
	
    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$conn = $this->em->getConnection();
		$conn->exec("DELETE FROM source_author");
		
		// Poeticus
		$dbh = new \PDO("mysql:dbname=poeticus_prod;host=127.0.0.1;charset=UTF8", "root", "");

		// -- Language
		foreach($dbh->query("SELECT * FROM Language") as $data) {
			$newEntity = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			
			if(empty($newEntity))
				$newEntity = new Language();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setAbbreviation($this->encodingString($data["abbreviation"]));
			$newEntity->setLogo($this->encodingString($data["logo"]));
			$newEntity->setDirection($this->encodingString($data["direction"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Page
		foreach($dbh->query("SELECT p.*, l.abbreviation FROM Page p
					LEFT JOIN language l ON p.language_id = l.id") as $data) {
			
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$newEntity = $this->em->getRepository(Page::class)->findOneBy(["internationalName" => $this->encodingString($data["international_name"]), "language" => $language]);
			
			if(empty($newEntity))
				$newEntity = new Page();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setInternationalName($this->encodingString($data["international_name"])."Poeticus");
			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setLanguage($language);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Country
		foreach($dbh->query("SELECT c.*, l.abbreviation FROM Country c JOIN Language l ON c.language_id = l.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$newEntity = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Country();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setInternationalName($this->encodingString($data["international_name"]));
			$newEntity->setFlag($this->encodingString($data["flag"]));
			$newEntity->setLanguage($language);
			$newEntity->setSlug($this->encodingString($data["slug"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Filemanagement
		foreach($dbh->query("SELECT * FROM file_management") as $data) {
			$newEntity = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photo"])]);

			if(empty($newEntity))
				$newEntity = new FileManagement();

			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setDescription($this->encodingString($data["description"]));
			
			if($data["folder"] == "collection")
				$data["folder"] = "source";
			
			$newEntity->setFolder($this->encodingString($data["folder"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();

		// -- Biography
		foreach($dbh->query("
					SELECT b.*, l.abbreviation, c.title AS titleCountry, fm.photo AS photoFilemanagement
					FROM Biography b 
					JOIN Language l ON b.language_id = l.id
					JOIN Country c ON b.country_id = c.id
					JOIN File_Management fm ON b.file_management_id = fm.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$country = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["titleCountry"]), "language" => $language]);
			$fileManagement = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photoFilemanagement"])]);
			
			$newEntity = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Biography();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setCountry($country);
			$newEntity->setLanguage($language);
			$newEntity->setFileManagement($fileManagement);
			$newEntity->setDayBirth($data["day_birth"]);
			$newEntity->setMonthBirth($data["month_birth"]);
			$newEntity->setYearBirth($data["year_birth"]);
			$newEntity->setDayDeath($data["day_death"]);
			$newEntity->setMonthDeath($data["month_death"]);
			$newEntity->setYearDeath($data["year_death"]);
			$newEntity->setType(Biography::AUTHOR);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();

		// -- Collection
		foreach($dbh->query("
					SELECT c.*, l.abbreviation, fm.photo AS photoFilemanagement
					FROM Collection c 
					JOIN Language l ON c.language_id = l.id
					JOIN File_Management fm ON c.file_management_id = fm.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$fileManagement = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photoFilemanagement"])]);
			
			$newEntity = $this->em->getRepository(Source::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Source();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setReleasedDate($data["released_date"]);
			$newEntity->setType(Source::BOOK);
			$newEntity->setLanguage($language);
			$newEntity->setFileManagement($fileManagement);
			$newEntity->setWidgetProduct($this->encodingString($data["widget_product"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		$source_author = [];

		foreach($dbh->query("
					SELECT c.*, l.abbreviation, b.title AS titleBiography
					FROM Collection c 
					JOIN Language l ON c.language_id = l.id
					JOIN biography b ON c.biography_id = b.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$biography = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleBiography"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(Source::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);
			
			if(!empty($newEntity)) {
				$source_author[] = $newEntity->getId()."#".$biography->getId();
			}
		}
		
		// -- PoeticForm
		foreach($dbh->query("
					SELECT p.*, l.abbreviation, fm.photo AS photoFilemanagement
					FROM Poetic_Form p
					LEFT JOIN Language l ON p.language_id = l.id
					LEFT JOIN file_management fm ON p.file_management_id = fm.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$fileManagement = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photoFilemanagement"])]);

			$newEntity = $this->em->getRepository(PoeticForm::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new PoeticForm();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setTypeContentPoem($data["type_content_poem"]);
			
			$newEntity->setLanguage($language);
			$newEntity->setFileManagement($fileManagement);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Poem
		foreach($dbh->query("
					SELECT p.*, l.abbreviation, fm.photo AS photoFilemanagement, c.title AS titleCountry, b.title AS titleBiography, co.title AS titleCollection, pf.title AS titlePoeticForm
					FROM Poem p
					LEFT JOIN Language l ON p.language_id = l.id
					LEFT JOIN poetic_form pf ON p.poetic_form_id = pf.id
					LEFT JOIN biography b ON p.biography_id = b.id
					LEFT JOIN country c ON p.country_id = c.id
					LEFT JOIN collection co ON p.collection_id = co.id
					LEFT JOIN file_management fm ON p.file_management_id = fm.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$fileManagement = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photoFilemanagement"])]);
			$country = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["titleCountry"]), "language" => $language]);
			$biography = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleBiography"]), "language" => $language]);
			$source = $this->em->getRepository(Source::class)->findOneBy(["title" => $this->encodingString($data["titleCollection"]), "language" => $language]);
			$poeticForm = $this->em->getRepository(PoeticForm::class)->findOneBy(["title" => $this->encodingString($data["titlePoeticForm"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(Poem::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Poem();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setReleasedDate($data["released_date"]);
			$newEntity->setAuthorType($this->encodingString($data["author_type"]));
			$newEntity->setState($data["state"]);
			
			$newEntity->setLanguage($language);
			$newEntity->setFileManagement($fileManagement);
			$newEntity->setPoeticForm($poeticForm);
			$newEntity->setBiography($biography);
			$newEntity->setCountry($country);
			$newEntity->setCollection($source);
			$newEntity->setUser(null);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- PoemImage
		foreach($dbh->query("
					SELECT pi.*, p.id AS poemId, p.title AS titlePoem, l.abbreviation
					FROM Poem_Image pi
					LEFT JOIN Poem p ON p.id = pi.poem_id
					LEFT JOIN language l ON p.language_id = l.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$poem = $this->em->getRepository(Poem::class)->findOneBy(["title" => $this->encodingString($data["titlePoem"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(PoemImage::class)->findOneBy(["image" => $this->encodingString($data["image"])]);

			if(empty($newEntity))
				$newEntity = new PoemImage();

			$newEntity->setPoem($poem);
			$newEntity->setImage($this->encodingString($data["image"]));
			$newEntity->setSocialNetwork($this->encodingString($data["social_network"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- PoemVote
		foreach($dbh->query("
					SELECT pi.*, p.id AS poemId, p.title AS titlePoem, u.username
					FROM Poem_Vote pi
					LEFT JOIN Poem p ON p.id = pi.poem_id
					LEFT JOIN User u ON pi.user_id = u.id") as $data) {
						
			$user = $this->em->getRepository(User::class)->findOneBy(["username" => $this->encodingString($data["username"])]);
			$poem = $this->em->getRepository(Poem::class)->findOneBy(["title" => $this->encodingString($data["titlePoem"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(PoemVote::class)->findOneBy(["entity" => $poem, "user" => $user]);

			if(empty($newEntity))
				$newEntity = new PoemVote();

			$newEntity->setEntity($poem);
			$newEntity->setuser($user);
			$newEntity->setVote($this->encodingString($data["vote"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Store
		foreach($dbh->query("
					SELECT s.*, l.abbreviation, b.title AS titleBiography
					FROM store s
					LEFT JOIN language l ON s.language_id = l.id
					LEFT JOIN biography b ON s.biography_id = b.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$biography = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleBiography"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(Store::class)->findOneBy(["title" => $this->encodingString($data["title"]), "biography" => $biography, "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Store();

			$newEntity->setLanguage($language);
			$newEntity->setBiography($biography);
			
			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setAmazonCode($this->encodingString($data["amazon_code"]));
			$newEntity->setEmbedCode($this->encodingString($data["embed_code"]));
			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// Quotus
		$dbh = new \PDO("mysql:dbname=quotus_prod;host=127.0.0.1;charset=UTF8", "root", "");
		
		// -- Language
		foreach($dbh->query("SELECT * FROM Language") as $data) {
			$newEntity = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			
			if(empty($newEntity))
				$newEntity = new Language();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setAbbreviation($this->encodingString($data["abbreviation"]));
			$newEntity->setLogo($this->encodingString($data["logo"]));
			$newEntity->setDirection($this->encodingString($data["direction"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Page
		foreach($dbh->query("SELECT p.*, l.abbreviation FROM Page p
					LEFT JOIN language l ON p.language_id = l.id") as $data) {
			
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$newEntity = $this->em->getRepository(Page::class)->findOneBy(["internationalName" => $this->encodingString($data["international_name"]), "language" => $language]);
			
			if(empty($newEntity))
				$newEntity = new Page();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setInternationalName($this->encodingString($data["international_name"])."Quotus");
			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setLanguage($language);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Filemanagement
		foreach($dbh->query("SELECT * FROM file_management") as $data) {
			$newEntity = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photo"])]);

			if(empty($newEntity))
				$newEntity = new FileManagement();

			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setDescription($this->encodingString($data["description"]));
			
			if($data["folder"] == "collection")
				$data["folder"] = "source";
			
			$newEntity->setFolder(utf8_encode($data["folder"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Country
		foreach($dbh->query("SELECT c.*, l.abbreviation FROM Country c JOIN Language l ON c.language_id = l.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$newEntity = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Country();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setInternationalName($this->encodingString($data["international_name"]));
			$newEntity->setFlag($this->encodingString($data["flag"]));
			$newEntity->setLanguage($language);
			$newEntity->setSlug($this->encodingString($data["slug"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Biography
		foreach($dbh->query("
					SELECT b.*, l.abbreviation, c.title AS titleCountry, fm.photo AS photoFilemanagement
					FROM Biography b 
					LEFT JOIN Language l ON b.language_id = l.id
					LEFT JOIN Country c ON b.country_id = c.id
					LEFT JOIN File_Management fm ON b.file_management_id = fm.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$country = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["titleCountry"]), "language" => $language]);
			$fileManagement = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photoFilemanagement"])]);
			
			$newEntity = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Biography();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setCountry($country);
			$newEntity->setLanguage($language);
			$newEntity->setFileManagement($fileManagement);
			$newEntity->setDayBirth($data["day_birth"]);
			$newEntity->setMonthBirth($data["month_birth"]);
			$newEntity->setYearBirth($data["year_birth"]);
			$newEntity->setDayDeath($data["day_death"]);
			$newEntity->setMonthDeath($data["month_death"]);
			$newEntity->setYearDeath($data["year_death"]);
			$newEntity->setType($this->encodingString($data["type"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Source
		foreach($dbh->query("
					SELECT c.*, l.abbreviation, fm.photo AS photoFilemanagement
					FROM Source c 
					LEFT JOIN Language l ON c.language_id = l.id
					LEFT JOIN File_Management fm ON c.file_management_id = fm.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$fileManagement = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photoFilemanagement"])]);
			
			$newEntity = $this->em->getRepository(Source::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Source();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setType($this->encodingString($data["type"]));
			$newEntity->setLanguage($language);
			$newEntity->setFileManagement($fileManagement);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();

		// -- SourceAuthor
		foreach($dbh->query("
					SELECT c.*, ls.abbreviation, s.title AS titleSource, a.title AS titleAuthor
					FROM source_author c 
					LEFT JOIN Source s ON s.id = c.source_id
					LEFT JOIN Language ls ON ls.id = s.language_id
					LEFT JOIN Biography a ON a.id = c.biography_id
					LEFT JOIN Language la ON la.id = a.language_id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$source = $this->em->getRepository(Source::class)->findOneBy(["title" => $this->encodingString($data["titleSource"]), "language" => $language]);
			$author = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleAuthor"]), "language" => $language]);

			$source_author[] = $source->getId()."#".$author->getId();
		}
		
		foreach(array_unique($source_author) as $sa) {
			list($sourceId, $biographyId) = explode("#", $sa);
			$conn->exec("INSERT INTO source_author VALUES (".$sourceId.", ".$biographyId.")");
		}
		
		// -- artwork_fictionalcharacter
		$conn->exec("DELETE FROM artwork_fictionalcharacter");
		
		foreach($dbh->query("
					SELECT c.*, ls.abbreviation, s.title AS titleSource, a.title AS titleAuthor
					FROM artwork_fictionalcharacter c 
					LEFT JOIN Source s ON s.id = c.source_id
					LEFT JOIN Language ls ON ls.id = s.language_id
					LEFT JOIN Biography a ON a.id = c.biography_id
					LEFT JOIN Language la ON la.id = a.language_id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$source = $this->em->getRepository(Source::class)->findOneBy(["title" => $this->encodingString($data["titleSource"]), "language" => $language]);
			$author = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleAuthor"]), "language" => $language]);
			
			$conn->exec("INSERT INTO artwork_fictionalcharacter VALUES (".$source->getId().", ".$author->getId().")");
		}
		
		// -- Quote
		foreach($dbh->query("
					SELECT p.*, l.abbreviation, c.title AS titleCountry, b.title AS titleBiography, co.title AS titleCollection
					FROM Quote p
					LEFT JOIN Language l ON p.language_id = l.id
					LEFT JOIN biography b ON p.biography_id = b.id
					LEFT JOIN country c ON p.country_id = c.id
					LEFT JOIN source co ON p.source_id = co.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$country = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["titleCountry"]), "language" => $language]);
			$biography = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleBiography"]), "language" => $language]);
			$source = $this->em->getRepository(Source::class)->findOneBy(["title" => $this->encodingString($data["titleCollection"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(Quote::class)->findOneBy(["text" => $this->encodingString($data["text"]), "biography" => $biography]);

			if(empty($newEntity))
				$newEntity = new Quote();

			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setAuthorType($this->encodingString($data["author_type"]));
			$newEntity->setState($data["state"]);
			
			$newEntity->setLanguage($language);
			$newEntity->setBiography($biography);
			$newEntity->setSource($source);
			$newEntity->setUser(null);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- QuoteImage
		foreach($dbh->query("
					SELECT pi.*, p.text AS textQuote, l.abbreviation
					FROM quote_image pi
					LEFT JOIN Quote p ON p.id = pi.quote_id
					LEFT JOIN language l ON p.language_id = l.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$quote = $this->em->getRepository(Quote::class)->findOneBy(["text" => $this->encodingString($data["textQuote"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(QuoteImage::class)->findOneBy(["image" => $this->encodingString($data["image"])]);

			if(empty($newEntity))
				$newEntity = new QuoteImage();

			$newEntity->setQuote($quote);
			$newEntity->setImage($this->encodingString($data["image"]));
			$newEntity->setSocialNetwork($this->encodingString($data["social_network"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Store
		foreach($dbh->query("
					SELECT s.*, l.abbreviation, b.title AS titleBiography
					FROM store s
					LEFT JOIN language l ON s.language_id = l.id
					LEFT JOIN biography b ON s.biography_id = b.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$biography = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleBiography"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(Store::class)->findOneBy(["title" => $this->encodingString($data["title"]), "biography" => $biography, "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Store();

			$newEntity->setLanguage($language);
			$newEntity->setBiography($biography);
			
			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setAmazonCode($this->encodingString($data["amazon_code"]));
			$newEntity->setEmbedCode($this->encodingString($data["embed_code"]));
			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// Proverbius
		$dbh = new \PDO("mysql:dbname=proverbius_prod;host=127.0.0.1;charset=UTF8", "root", "");
		
		// -- Language
		foreach($dbh->query("SELECT * FROM Language") as $data) {
			$newEntity = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			
			if(empty($newEntity))
				$newEntity = new Language();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setAbbreviation($this->encodingString($data["abbreviation"]));
			$newEntity->setLogo($this->encodingString($data["logo"]));
			$newEntity->setDirection($this->encodingString($data["direction"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Page
		foreach($dbh->query("SELECT p.*, l.abbreviation FROM Page p
					LEFT JOIN language l ON p.language_id = l.id") as $data) {
			
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$newEntity = $this->em->getRepository(Page::class)->findOneBy(["internationalName" => $this->encodingString($data["international_name"]), "language" => $language]);
			
			if(empty($newEntity))
				$newEntity = new Page();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setInternationalName($this->encodingString($data["international_name"])."Proverbius");
			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setLanguage($language);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Filemanagement
		foreach($dbh->query("SELECT * FROM file_management") as $data) {
			$newEntity = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photo"])]);

			if(empty($newEntity))
				$newEntity = new FileManagement();

			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setDescription($this->encodingString($data["description"]));
			
			if($data["folder"] == "collection")
				$data["folder"] = "source";
			
			$newEntity->setFolder($this->encodingString($data["folder"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();

		// -- Biography
		foreach($dbh->query("
					SELECT b.*, l.abbreviation, c.title AS titleCountry, fm.photo AS photoFilemanagement
					FROM Biography b 
					JOIN Language l ON b.language_id = l.id
					JOIN Country c ON b.country_id = c.id
					JOIN File_Management fm ON b.file_management_id = fm.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$country = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["titleCountry"]), "language" => $language]);
			$fileManagement = $this->em->getRepository(FileManagement::class)->findOneBy(["photo" => $this->encodingString($data["photoFilemanagement"])]);
			
			$newEntity = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Biography();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setCountry($country);
			$newEntity->setLanguage($language);
			$newEntity->setFileManagement($fileManagement);
			$newEntity->setDayBirth($data["day_birth"]);
			$newEntity->setMonthBirth($data["month_birth"]);
			$newEntity->setYearBirth($data["year_birth"]);
			$newEntity->setDayDeath($data["day_death"]);
			$newEntity->setMonthDeath($data["month_death"]);
			$newEntity->setYearDeath($data["year_death"]);
			$newEntity->setType(Biography::AUTHOR);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Country
		foreach($dbh->query("SELECT c.*, l.abbreviation FROM Country c JOIN Language l ON c.language_id = l.id") as $data) {
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$newEntity = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["title"]), "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Country();

			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setInternationalName($this->encodingString($data["international_name"]));
			$newEntity->setFlag($this->encodingString($data["flag"]));
			$newEntity->setLanguage($language);
			$newEntity->setSlug($this->encodingString($data["slug"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Proverb
		foreach($dbh->query("
					SELECT p.*, l.abbreviation, c.title AS titleCountry
					FROM Proverb p
					LEFT JOIN Language l ON p.language_id = l.id
					LEFT JOIN country c ON p.country_id = c.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$country = $this->em->getRepository(Country::class)->findOneBy(["title" => $this->encodingString($data["titleCountry"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(Proverb::class)->findOneBy(["text" => $this->encodingString($data["text"]), "country" => $country]);

			if(empty($newEntity))
				$newEntity = new Proverb();

			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			$newEntity->setImages($this->encodingString($data["images"]));
			$newEntity->setCountry($country);
			$newEntity->setLanguage($language);
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- ProverbImage
		foreach($dbh->query("
					SELECT pi.*, p.text AS textProverb, l.abbreviation
					FROM proverb_image pi
					LEFT JOIN Proverb p ON p.id = pi.proverb_id
					LEFT JOIN language l ON p.language_id = l.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$proverb = $this->em->getRepository(Proverb::class)->findOneBy(["text" => $this->encodingString($data["textProverb"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(ProverbImage::class)->findOneBy(["image" => $this->encodingString($data["image"])]);

			if(empty($newEntity))
				$newEntity = new ProverbImage();

			$newEntity->setProverb($proverb);
			$newEntity->setImage($this->encodingString($data["image"]));
			$newEntity->setSocialNetwork($this->encodingString($data["social_network"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();
		
		// -- Store
		foreach($dbh->query("
					SELECT s.*, l.abbreviation, b.title AS titleBiography
					FROM store s
					LEFT JOIN language l ON s.language_id = l.id
					LEFT JOIN biography b ON s.biography_id = b.id") as $data) {
						
			$language = $this->em->getRepository(Language::class)->findOneBy(["abbreviation" => $this->encodingString($data["abbreviation"])]);
			$biography = $this->em->getRepository(Biography::class)->findOneBy(["title" => $this->encodingString($data["titleBiography"]), "language" => $language]);
			
			$newEntity = $this->em->getRepository(Store::class)->findOneBy(["title" => $this->encodingString($data["title"]), "biography" => $biography, "language" => $language]);

			if(empty($newEntity))
				$newEntity = new Store();

			$newEntity->setLanguage($language);
			$newEntity->setBiography($biography);
			
			$newEntity->setTitle($this->encodingString($data["title"]));
			$newEntity->setText($this->encodingString($data["text"]));
			$newEntity->setAmazonCode($this->encodingString($data["amazon_code"]));
			$newEntity->setEmbedCode($this->encodingString($data["embed_code"]));
			$newEntity->setPhoto($this->encodingString($data["photo"]));
			$newEntity->setSlug($this->encodingString($data["slug"]));
			
			$this->em->persist($newEntity);
		}

		$this->em->flush();

		return 0;
    }
	
	private function encodingString(?String $string): ?String {
		if(empty($string))
			return null;

		return $string;
	}
}