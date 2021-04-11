<?php

namespace App\Controller;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Comment;
use App\Entity\PoemComment;
use App\Entity\ProverbComment;
use App\Entity\QuoteComment;
use App\Entity\User;
use App\Entity\Poem;
use App\Entity\Proverb;
use App\Entity\Quote;
use App\Form\Type\CommentType;
use App\Service\GenericFunction;

/**
 * @Route("/comment")
 */
class CommentController extends AbstractController
{
    /**
     * @Route("/{id}")
     */
    public function indexAction(Request $request, $id)
    {
		$entity = new Comment();
        $form = $this->createForm(CommentType::class, $entity);

        return $this->render('Comment/index.html.twig', array('id' => $id, 'form' => $form->createView()));
    }

    /**
     * @Route("/create/{id}")
     */
	public function createAction(Request $request, TokenStorageInterface $tokenStorage, TranslatorInterface $translator, $id)
	{
		list($entity, $className, $mainEntity) = $this->selectEntity();
		$newEntity = clone $entity;
		$entityManager = $this->getDoctrine()->getManager();
        $form = $this->createForm(CommentType::class, $entity);
		$form->handleRequest($request);

		$user = $tokenStorage->getToken()->getUser();
		
		if(!empty($user) and is_object($user))
			$user = $entityManager->getRepository(User::class)->findByUsernameOrEmail($user->getUsername());
		else
			$form->get("text")->addError(new FormError($translator->trans("comment.field.YouMustBeLoggedInToWriteAComment")));

		if($form->isValid())
		{
			// dump($entityManager->getRepository($mainEntity)->find($id));die;
			$entity->setUser($user);
			$entity->setEntity($entityManager->getRepository($mainEntity)->find($id));

			$entityManager->persist($entity);
			$entityManager->flush();
			
			$entities = $entityManager->getRepository($className)->findAll();

			$form = $this->createForm(CommentType::class, $newEntity);
		}

		$params = $this->getParametersComment($request, $id);

		return $this->render('Comment/form.html.twig', array("form" => $form->createView(), "id" => $id));
	}

    /**
     * @Route("/load/{id}")
     */
	public function loadAction(Request $request, $id)
	{
		return $this->render('Comment/list.html.twig', $this->getParametersComment($request, $id));
	}
	
	private function selectEntity()
	{
		switch((new GenericFunction())->getSubDomain()) {
			case "poeticus":
				return [new PoemComment(), PoemComment::class, Poem::class];
			case "quotus":
				return [new QuoteComment(), QuoteComment::class, Quote::class];
			case "proverbius":
				return [new ProverbComment(), ProverbComment::class, Proverb::class];
		}
		
		return [];
	}
	
	private function getParametersComment($request, $id)
	{
		list($entity, $className, $mainEntity) = $this->selectEntity();

		$entityManager = $this->getDoctrine()->getManager();
		$max_comment_by_page = 7;
		$page = $request->query->get("page");
		$totalComments = $entityManager->getRepository($className)->countAllComments($id);
		$number_pages = ceil($totalComments / $max_comment_by_page);
		$first_message_to_display = ($page - 1) * $max_comment_by_page;
		
		$entities = $entityManager->getRepository($className)->displayComments($id, $max_comment_by_page, $first_message_to_display);
		
		return array("entities" => $entities, "page" => $page, "number_pages" => $number_pages);
	}
}
