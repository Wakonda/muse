<?php

namespace App\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Poem;

/**
 * Poem repository
 */
class PoemRepository extends ServiceEntityRepository implements iRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poem::class);
    }
	
	public function getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.id', 'pf.title', 'la.title', 'pf.id');
		
		$qb->leftjoin("pf.language", "la")
		   ->andWhere("pf.state = 0");
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->where('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
	public function findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $datasObject, $locale, $count = false)
	{
		$aColumns = array( 'pf.title', 'pfb.title', 'pfc.title', 'pf.id');
		$qb = $this->createQueryBuilder("pf");

		$qb->leftjoin("pf.biography", "pfb")
		   ->leftjoin("pf.country", "pfc")
		   ->andWhere("pf.state = 0");

		$this->whereLanguage($qb, 'pf', $locale);

		if(!empty($datasObject->title))
		{
			$value = "%".$datasObject->title."%";
			$qb->andWhere("pf.title LIKE :title")
			   ->setParameter("title", $value);
		}

		if(!empty($datasObject->text))
		{
			$keywords = explode(",", $datasObject->text);
			$i = 0;
			foreach($keywords as $keyword)
			{
				$keyword = "%".$keyword."%";
				$qb->andWhere("pf.text LIKE :keyword".$i)
			       ->setParameter("keyword".$i, $keyword);
				$i++;
			}
		}

		if(!empty($datasObject->author))
		{
			$author = "%".$datasObject->author."%";
			$qb->andWhere("pfb.title LIKE :username")
			   ->setParameter("username", $author);
		}

		if(!empty($datasObject->country))
		{
			$qb->andWhere("pfc.id = :country")
			   ->setParameter("country", $datasObject->country);
		}

		if(!empty($datasObject->collection))
		{
			$collection = "%".$this->findByTable($datasObject->collection, 'collection', 'title')."%";
			$qb->leftjoin("pf.collection", "pfco")
			   ->andWhere("pfco.title LIKE :collection")
			   ->setParameter("collection", $collection);
		}

		if(!empty($datasObject->type))
		{
			$qb->andWhere("pf.authorType = :type")
			   ->setParameter("type", $datasObject->type);
		}

		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
	public function getLastEntries($locale)
	{
		$qb = $this->createQueryBuilder("pf");

		$qb->where("pf.authorType = :biography")
		   ->setMaxResults(7)
		   ->andWhere("pf.state = 0")
		   ->orderBy("pf.id", "DESC")
		   ->setParameter("biography", "biography");
		   
		$this->whereLanguage($qb, "pf", $locale, true);
		   
		return $qb->getQuery()->getResult();
	}
	
	public function getRandomPoem($locale)
	{
		$qb = $this->createQueryBuilder("pf");

		$qb->select("COUNT(pf.id) AS countRow");
		   
		$this->whereLanguage($qb, "pf", $locale);

		$max = max($qb->getQuery()->getSingleScalarResult() - 1, 0);
		$offset = rand(0, $max);

		$qb = $this->createQueryBuilder("pf");

		$qb->andWhere("pf.state = 0")
		   ->andWhere("pf.authorType = :authorType")
		   ->setParameter("authorType", "biography")
		   ->setFirstResult($offset)
		   ->setMaxResults(1);
		   
		$this->whereLanguage($qb, "pf", $locale);

		return $qb->getQuery()->getOneOrNullResult();
	}

	public function whereLanguage($qb, $alias, $locale, $join = true)
	{
		if($join)
			$qb->leftjoin($alias.".language", "la");
		
		$qb->andWhere('la.abbreviation = :locale')
		   ->setParameter("locale", $locale);
		
		return $qb;
	}

	public function getPoemByTagDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $tagId, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.title', 'co.title');
		
		$qb->leftjoin("pf.tags", "bo")
		   ->where("bo.id = :id")
		   ->andWhere("pf.state = 0")
		   ->setParameter("id", $tagId);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(DISTINCT pf.id) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
		{
			$qb->groupBy("pf.id")
			   ->setFirstResult($iDisplayStart)
			   ->setMaxResults($iDisplayLength);
		}

		return $qb->getQuery()->getResult();
	}

	public function getPoemByAuthorDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $authorId, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.title', 'co.title');
		
		$qb->leftjoin("pf.biography", "bo")
		   ->leftjoin("pf.collection", "co")
		   ->where("bo.id = :id")
		   ->andWhere("pf.state = 0")
		   ->setParameter("id", $authorId);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(DISTINCT pf.id) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
		{
			$qb->groupBy("pf.id")
			   ->setFirstResult($iDisplayStart)
			   ->setMaxResults($iDisplayLength);
		}

		return $qb->getQuery()->getResult();
	}
	
    public function findPoemByAuthor($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $locale, $count = false)
    {
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'bp.title', 'COUNT(pf.id)');
		
		$qb->select("bp.id AS id, bp.title AS author, bp.slug AS slug, fm.photo AS photo, COUNT(pf.id) AS number_poems_by_author")
		   ->where("pf.authorType = 'biography'")
		   ->leftjoin("pf.biography", "bp")
		   ->leftjoin("bp.fileManagement", "fm")
		   ->andWhere("pf.state = 0")
		   ->groupBy("bp.id");
		   
		 $this->whereLanguage($qb, "pf", $locale);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('bp.title LIKE :search')
			   ->setParameter("search", $search);
		}
		if($count)
		{
			$params = [];
			
			foreach($qb->getParameters()->getIterator() as $i => $item)
				$params[] = $item->getValue();

			$res = $this->_em->getConnection()->executeQuery("SELECT COUNT(*) AS count FROM (".$qb->getQuery()->getSql().") AS SQ", $params);

			return $res->fetch()["count"];
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
    }
	
    public function findPoemByPoeticForm($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $locale, $count = false)
    {
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'co.title', 'COUNT(pf.id)');
		
		$qb->select("co.id AS poeticform_id, co.title AS poeticform, COUNT(pf.id) AS number_poems_by_poeticform, co.slug AS poeticform_slug")
		   ->where("pf.authorType = 'biography'")
		   ->innerjoin("pf.poeticForm", "co")
		   ->andWhere("pf.state = 0")
		   ->groupBy("co.id");

		$this->whereLanguage($qb, 'pf', $locale);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('co.title LIKE :search')
			   ->setParameter("search", $search);
		}
		if($count)
		{
			$params = [];
			
			foreach($qb->getParameters()->getIterator() as $i => $item)
				$params[] = $item->getValue();

			$res = $this->_em->getConnection()->executeQuery("SELECT COUNT(*) AS count FROM (".$qb->getQuery()->getSql().") AS SQ", $params);

			return $res->fetch()["count"];
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
    }
	
	public function getPoemByPoeticFormDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $collectionId, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.title');
		
		$qb->select("pf.title AS poem_title, pf.id AS poem_id, pf.slug AS slug")
		   ->leftjoin("pf.poeticForm", "pform")
		   ->where("pform.id = :id")
		   ->setParameter("id", $collectionId)
		   ->andWhere("pf.authorType = :authorType")
		   ->andWhere("pf.state = 0")
		   ->setParameter("authorType", "biography");
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
    public function findPoemByCollection($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $locale, $count = false)
    {
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'co.title', 'bp.title', 'COUNT(pf.id)');
		
		$qb->select("bp.id AS author_id, co.id AS collection_id, bp.title AS author, bp.slug AS author_slug, co.title AS collection, co.slug AS collection_slug, COUNT(pf.id) AS number_poems_by_collection")
		   ->leftjoin("pf.biography", "bp")
		   ->innerjoin("pf.collection", "co")
		   ->andWhere("pf.state = 0")
		   ->where("pf.authorType = 'biography'")
		   ->groupBy("co.id")
		   ->addGroupBy("bp.id")
		   ;

		$this->whereLanguage($qb, 'pf', $locale);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('co.title LIKE :search')
			   ->setParameter("search", $search);
		}
		if($count)
		{
			$params = [];
			
			foreach($qb->getParameters()->getIterator() as $i => $item)
				$params[] = $item->getValue();

			$res = $this->_em->getConnection()->executeQuery("SELECT COUNT(*) AS count FROM (".$qb->getQuery()->getSql().") AS SQ", $params);

			return $res->fetch()["count"];
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
    }

	public function getPoemByCollectionDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $collectionId, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.title');
		
		$qb->select("pf.title AS poem_title, pf.id AS poem_id, pf.slug AS slug")
		   ->leftjoin("pf.collection", "co")
		   ->where("co.id = :id")
		   ->setParameter("id", $collectionId)
		   ->andWhere("pf.state = 0")
		   ->andWhere("pf.authorType = :authorType")
		   ->setParameter("authorType", "biography");
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
    public function findPoemByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $locale, $count = false)
    {
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'co.title', 'COUNT(pf.id)');
		
		$qb->select("co.id AS country_id, co.slug AS country_slug, co.title AS country_title, COUNT(pf.id) AS number_poems_by_country, co.flag AS flag")
		   ->where("pf.authorType = 'biography'")
		   ->innerjoin("pf.country", "co")
		   ->andWhere("pf.state = 0")
		   ->groupBy("co.id");
		
		$this->whereLanguage($qb, 'pf', $locale);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('co.title LIKE :search')
			   ->setParameter("search", $search);;
		}
		if($count)
		{
			$params = [];
			
			foreach($qb->getParameters()->getIterator() as $i => $item)
				$params[] = $item->getValue();

			$res = $this->_em->getConnection()->executeQuery("SELECT COUNT(*) AS count FROM (".$qb->getQuery()->getSql().") AS SQ", $params);

			return $res->fetch()["count"];
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
    }
	
    public function findPoemByPoemUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $locale, $count = false)
    {
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.title', 'u.username');
		
		$qb->select("pf.id AS id, pf.title AS title, u.username AS username, u.id AS user_id, pf.slug AS slug")
		   ->where("pf.authorType = 'user'")
		   ->join("pf.user", "u")
		   ->andWhere("pf.state = 0");
		   
		$this->whereLanguage($qb, 'pf', $locale);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.title LIKE :search')
			   ->setParameter("search", $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
    }
	
	public function getPoemByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.id', 'pf.title', 'pf.id');
		
		$qb->select("pf.title AS poem_title, bi.title AS biography_title, bi.slug AS biography_slug, pf.id AS poem_id, bi.id AS biography_id, pf.slug AS poem_slug")
		   ->innerjoin("pf.biography", "bi")
		   ->innerjoin("pf.country", "co")
		   ->where("co.id = :id")
		   ->setParameter("id", $countryId)
		   ->andWhere("pf.state = 0")
		   ->andWhere("pf.authorType = :authorType")
		   ->setParameter("authorType", "biography")
		   ;
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}

	public function checkForDoubloon($entity)
	{
		$qb = $this->createQueryBuilder("pf");

		$qb->select("COUNT(pf) AS number")
		   ->leftjoin("pf.language", "la")
		   ->where("pf.slug = :slug")
		   ->setParameter('slug', $entity->getSlug())
		   ->leftjoin("pf.biography", "bo")
		   ->andWhere("pf.state = 0")
		   ->andWhere("bo.id = :biographyId")
		   ->setParameter("biographyId", $entity->getBiography())
		   ->andWhere("la.id = :idLanguage")
		   ->setParameter("idLanguage", $entity->getLanguage());

		if($entity->getId() != null)
		{
			$qb->andWhere("pf.id != :id")
			   ->setParameter("id", $entity->getId());
		}

		return $qb->getQuery()->getSingleScalarResult();
	}
	
	public function getStat($locale)
	{
		$qbPoem = $this->createQueryBuilder("pf");

		$qbPoem->select("COUNT(pf) AS count_poem");
			   
		$this->whereLanguage($qbPoem, "pf", $locale);

		$resultPoem = $qbPoem->getQuery()->getSingleScalarResult();
		
		$qbBio = $this->_em->createQueryBuilder();

		$qbBio->select("COUNT(bp) AS count_biography")
		      ->from("App\Entity\Biography", "bp");
			  
		$this->whereLanguage($qbBio, "bp", $locale);

		$resultBio = $qbBio->getQuery()->getSingleScalarResult();
		
		$qbCo = $this->_em->createQueryBuilder();

		$qbCo->select("COUNT(co) AS count_collection")
		      ->from("App\Entity\Source", "co");
		
		$this->whereLanguage($qbCo, "co", $locale);
		
		$resultCo = $qbCo->getQuery()->getSingleScalarResult();
		
		return ["count_poem" => $resultPoem, "count_biography" => $resultBio, "count_collection" => $resultCo];
	}

	public function findByUserAndAuhorType($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $currentUser, $authorType, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.id', 'pf.title', 'pf.state', 'pf.id');
		
		$qb->leftjoin("pf.user", "pfu")
		   ->where("pfu.username = :username")
		   ->setParameter("username", $username)
		   ->andWhere("pf.state <> 2")
		   ->andWhere('pf.authorType = :authorType')
		   ->setParameter('authorType', $authorType);

		if($username != $currentUser->getUsername())
		{
			$qb->andWhere("pf.state = 0");
		}
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andhere('pf.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
	
	public function browsingPoemShow($params, $poemId)
	{
		// Previous
		$subqueryPrevious = 'p.id = (SELECT MAX(p2.id) FROM App\Entity\Poem p2 WHERE p2.state = 0 AND p2.id < :poemId AND p2.'.$params["field"].' = :biographyId)';
		$qb_previous = $this->createQueryBuilder("p");
		
		$qb_previous->select("p.id, p.title, p.slug")
		   ->where('p.'.$params["field"].' = :biographyId')
		   ->setParameter('biographyId', $params["author"])
		   ->setParameter('poemId', $poemId)
		   ->andWhere("p.state = 0")
		   ->andWhere($subqueryPrevious);
		   
		$subqueryNext = 'p.id = (SELECT MIN(p2.id) FROM App\Entity\Poem p2 WHERE p2.state = 0 AND p2.id > :poemId AND p2.'.$params["field"].' = :biographyId)';
		$qb_next = $this->createQueryBuilder("p");
		
		$qb_next->select("p.id, p.title, p.slug")
		   ->where('p.'.$params["field"].' = :biographyId')
		   ->setParameter('biographyId', $params["author"])
		   ->setParameter('poemId', $poemId)
		   ->andWhere("p.state = 0")
		   ->andWhere($subqueryNext);

		return array(
			"previous" => $qb_previous->getQuery()->getOneOrNullResult(),
			"next" => $qb_next->getQuery()->getOneOrNullResult()
		);
	}
	
	public function getAllPoemsByCollectionAndAuthorForPdf($id)
	{
		$qb = $this->createQueryBuilder("pf");
		
		$qb->leftjoin('pf.collection', 'co')
		   ->where("co.id = :collectionId")
		   ->setParameter('collectionId', $id);
		   
		return $qb->getQuery()->getResult();
	}
}