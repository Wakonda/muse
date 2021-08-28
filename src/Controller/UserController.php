<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Quote;
use App\Entity\Poem;
use App\Entity\Proverb;
use App\Entity\QuoteComment;
use App\Entity\PoemComment;
use App\Entity\ProverbComment;
use App\Entity\QuoteVote;
use App\Entity\PoemVote;
use App\Entity\ProverbVote;
use App\Form\Type\UserType;
use App\Form\Type\UpdatePasswordType;
use App\Form\Type\ForgottenPasswordType;
use App\Form\Type\LoginType;

use App\Service\Mailer;
use App\Service\PasswordHash;
use App\Service\GenericFunction;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/user")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/login")
     */
    public function loginAction(AuthenticationUtils $authenticationUtils)
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

		return $this->render('User/login.html.twig', array(
				'error'         => $error,
				'last_username' => $lastUsername
		));
    }

    /**
     * @Route("/logout", methods={"GET"})
     */
    public function logout()
    {
        // controller can be blank: it will never be executed!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }

    /**
     * @Route("/list")
     */
	public function listAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(User::class)->findAll();

		return $this->render('User/list.html.twig', array('entities' => $entities));
	}

    /**
     * @Route("/show", defaults={"username": null})
     */
	public function showAction(TokenStorageInterface $tokenStorage, $username)
	{
		$entityManager = $this->getDoctrine()->getManager();
		if(!empty($username))
			$entity = $entityManager->getRepository(User::class)->findOneBy(["username" => $username]);
		else
			$entity = $tokenStorage->getToken()->getUser();

		return $this->render('User/show.html.twig', array('entity' => $entity));
	}

    /**
     * @Route("/new")
     */
	public function newAction(Request $request)
	{
		$entity = new User();
        $form = $this->createFormUser($entity, false);

		return $this->render('User/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/create")
     */
	public function createAction(Request $request, SessionInterface $session, \Swift_Mailer $mailer, TranslatorInterface $translator)
	{
		$entity = new User();
        $form = $this->createFormUser($entity, false);
		$form->handleRequest($request);
		
		$params = $request->request->get("user");

		if($params["captcha"] != "" and $session->get("captcha_word") != $params["captcha"])
			$form->get("captcha")->addError(new FormError($translator->trans('user.createAccount.TheWordMustMatchThePicture')));

		$this->checkForDoubloon($translator, $entity, $form);

		if($form->isValid())
		{
			if(!is_null($entity->getAvatar()))
			{
				$image = uniqid()."_avatar.png";
				$entity->getAvatar()->move(User::PATH_FILE, $image);
				$entity->setAvatar($image);
			}

			$ph = new PasswordHash();
			$salt = $ph->create_hash($entity->getPassword());
			
			$encoder = new MessageDigestPasswordEncoder();
			$entity->setPassword($encoder->encodePassword($entity->getPassword(), $salt));
			
			$expiredAt = new \Datetime();
			$entity->setExpiredAt($expiredAt->modify("+1 day"));
			$entity->setToken(md5(uniqid(mt_rand(), true).$entity->getUsername()));
			$entity->setEnabled(false);
			$entity->setSalt($salt);

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();

			// Send email
			$body = $this->renderView('User/confirmationInscription_mail.html.twig', array("entity" => $entity));

			$message = (new \Swift_Message('Quotus - '.$translator->trans('user.createAccount.Registration')))
				->setFrom('quotus@wakonda.guru', "Quotus")
				->setTo($entity->getEmail())
				->setBody($body, 'text/html');
		
			$mailer->send($message);

			return $this->render('User/confirmationInscription.html.twig', array('entity' => $entity));
		}

		return $this->render('User/new.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/edit", defaults={"id": null})
     */
	public function editAction(Request $request, TokenStorageInterface $tokenStorage, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		if(!empty($id))
			$entity = $entityManager->getRepository(User::class)->find($id);
		else
		{
			$this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY', null, $translator->trans('user.createAccount.UnableToAccessThisPage'));
			$entity = $tokenStorage->getToken()->getUser();
		}

		$form = $this->createFormUser($entity, true);
	
		return $this->render('User/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/update", defaults={"id": null})
     */
	public function updateAction(Request $request, TokenStorageInterface $tokenStorage, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		if(empty($id))
			$entity = $tokenStorage->getToken()->getUser();
		else
			$entity = $entityManager->getRepository(User::class)->find($id);
		
		$current_avatar = $entity->getAvatar();

		$form = $this->createFormUser($entity, true);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($translator, $entity, $form);

		if($form->isValid())
		{
			if(!is_null($entity->getAvatar()))
			{
				unlink(User::PATH_FILE.$current_avatar);
				$image = uniqid()."_avatar.png";
				$entity->getAvatar()->move(User::PATH_FILE, $image);
				$entity->setAvatar($image);
			}
			else
				$entity->setAvatar($current_avatar);

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();
			
			return $this->redirect($this->generateUrl('user_show', array('id' => $entity->getId())));
		}
	
		return $this->render('User/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/updatepassword")
     */
	public function updatePasswordAction(Request $request, TokenStorageInterface $tokenStorage)
	{
		$entity = $tokenStorage->getToken()->getUser();
		$form = $this->createForm(UpdatePasswordType::class, $entity);
		
		return $this->render('User/updatepassword.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

    /**
     * @Route("/updatepasswordsave")
     */
	public function updatePasswordSaveAction(Request $request, SessionInterface $session, TokenStorageInterface $tokenStorage, TranslatorInterface $translator)
	{
		$entity = $tokenStorage->getToken()->getUser();
        $form = $this->createForm(UpdatePasswordType::class, $entity);
		$form->handleRequest($request);

		if($form->isValid())
		{
			$ph = new PasswordHash();
			$salt = $ph->create_hash($entity->getPassword());
			
			$encoder = new MessageDigestPasswordEncoder();
			$entity->setSalt($salt);
			$entity->setPassword($encoder->encodePassword($entity->getPassword(), $salt));
			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();

			$session->getFlashBag()->add('new_password', $translator->trans('forgottenPassword.confirmation.YourPasswordHasBeenChanged'));

			return $this->redirect($this->generateUrl('user_show', array('id' => $id)));
		}
		
		return $this->render('User/updatepassword.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/forgottenpassword")
     */
	public function forgottenPasswordAction(Request $request)
	{
		$form = $this->createForm(ForgottenPasswordType::class, null);
	
		return $this->render('User/forgottenpassword.html.twig', array('form' => $form->createView()));
	}

    /**
     * @Route("/forgottenpasswordsend")
     */
	public function forgottenPasswordSendAction(Request $request, \Swift_Mailer $mailer, TranslatorInterface $translator)
	{
        $form = $this->createForm(ForgottenPasswordType::class, null);
		$form->handleRequest($request);
	
		$params = $request->request->get("forgotten_password");

		if($params["captcha"] != "" and $request->getSession()->get("captcha_word") != $params["captcha"])
			$form->get("captcha")->addError(new FormError($translator->trans('forgottenPassword.field.TheWordMustMatchThePicture')));

		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(User::class)->findByUsernameOrEmail($params["emailUsername"]);

		if(empty($entity))
			$form->get("emailUsername")->addError(new FormError($translator->trans('forgottenPassword.field.UsernameOrEmailAddressDoesNotExist')));

		if(!$form->isValid())
		{
			return $this->render('User/forgottenpassword.html.twig', array('form' => $form->createView()));
		}
		
		$temporaryPassword = $this->randomPassword();
		$ph = new PasswordHash();
		$salt = $ph->create_hash($temporaryPassword);

		$encoder = new MessageDigestPasswordEncoder();
		$entity->setSalt($salt);
		$entity->setPassword($encoder->encodePassword($temporaryPassword, $salt));
		$entityManager->persist($entity);
        $entityManager->flush();
		
		// Send email
		$body = $this->renderView('User/forgottenpassword_mail.html.twig', array("entity" => $entity, "temporaryPassword" => $temporaryPassword));

		$message = (new \Swift_Message("Quotus - ".$translator->trans('forgottenPassword.index.ForgotYourPassword')))
			->setFrom('quotus@wakonda.guru', "Quotus")
			->setTo($entity->getEmail())
			->setBody($body, 'text/html');
	
		$mailer->send($message);
		
		return $this->render('User/forgottenpasswordsend.html.twig');
	}

	private function randomPassword($length = 8)
	{
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789&+-$";
		
		if($length >= strlen($chars))
			$length = 8;
		
		return substr(str_shuffle($chars), 0, $length);
	}

	private function createFormUser($entity, $ifEdit)
	{
		return $this->createForm(UserType::class, $entity, array('edit' => $ifEdit));
	}

	private function checkForDoubloon(TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getUsername() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(User::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("username")->addError(new FormError($translator->trans('user.createAccount.UserSameUsernameEmailExists')));
		}
	}
	
	private function createTemporaryPassword($email)
	{
		$key = strlen(uniqid());
		
		if(strlen($key) < strlen($email))
			$key = str_pad($key, strlen($email), $key, STR_PAD_RIGHT);
		elseif(strlen($key) > strlen($email))
		{
			$diff = strlen($key) - strlen($email);
			$key = substr($key, 0, -$diff);
		}
		
		return $email ^ $key;
	}

	private function testStrongestPassword(TranslatorInterface $translator, $form, $password)
	{
		$min_length = 5;
		
		$letter = array();
		$number = array();
		
		for($i = 0; $i < strlen($password); $i++)
		{
			$current = $password[$i];
			
			if(($current >= 'a' and $current <= 'z') or ($current >= 'A' and $current <= 'Z'))
				$letter[] = $current;
			if($current >= '0' and $current <= '9')
				$number[] = $current;
		}
		
		if(strlen($password) > 0)
		{
			if(strlen($password) < $min_length)
				$form->get("password")->addError(new FormError($translator->trans('user.createAccount.PasswordMustContainAtLeast', ["%minLength%" => $min_length])));
			else
			{
				if(count($letter) == 0)
					$form->get('password')->addError(new FormError($translator->trans('user.createAccount.PasswordOneLetter')));
				if(count($number) == 0)
					$form->get('password')->addError(new FormError($translator->trans('user.createAccount.PasswordOneNumber')));
			}
		}
	}

    /**
     * @Route("/quote_user_datatables/{username}")
     */
	public function quotesUserDatatablesAction(Request $request, TokenStorageInterface $tokenStorage, TranslatorInterface $translator, AuthorizationCheckerInterface $authChecker, $username)
	{
		list($voteClassName, $commentClassName, $entityClass, $editRoute) = $this->selectEntity();
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

		$entities = $entityManager->getRepository($entityClass)->findByUserAndAuhorType($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $tokenStorage->getToken()->getUser(), 'user');
		$iTotal = $entityManager->getRepository($entityClass)->findByUserAndAuhorType($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, $tokenStorage->getToken()->getUser(), 'user', true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = [];

			$show = $this->generateUrl($entity->getShowRoute(), array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getText().'</a>';

			if ($authChecker->isGranted('IS_AUTHENTICATED_REMEMBERED') and $tokenStorage->getToken()->getUser()->getUsername() == $username) {
				$row[] = '<div class="state_entity '.$entity->getStateRealName().'">'.$translator->trans($entity->getStateString()).'</div>';
				$row[] = '<a href="'.$this->generateUrl($editRoute, ["id" => $entity->getId()]).'" class="btn btn-primary btn-sm" alt="" title="'.$translator->trans('user.myProfile.Edit').'"><i class="fas fa-pencil-alt fa-fw"></i></a> <a href="#" class="btn btn-danger btn-sm" alt="" data-id="'.$entity->getId().'" class="delete_poem" title="'.$translator->trans('user.myProfile.Delete').'"><i class="fas fa-times fa-fw"></i></a>';
			}
			
			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/vote_datatables/{username}")
     */
	public function votesUserDatatablesAction(Request $request, $username)
	{
		list($voteClassName, $commentClassName, $entityClass, $editRoute) = $this->selectEntity();
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
		$entities = $entityManager->getRepository($voteClassName)->findVoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username);
		$iTotal = $entityManager->getRepository($voteClassName)->findVoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => []
		);

		foreach($entities as $entity)
		{
			$row = [];

			$show = $this->generateUrl($entity->getEntity()->getShowRoute(), array('id' => $entity->getEntity()->getId(), 'slug' => $entity->getEntity()->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getEntity()->getAbstract().'</a>';
			
			list($icon, $color) = (($entity->getVote() == -1) ? array("fa-arrow-down", "red") : array("fa-arrow-up", "green"));
			$row[] = "<i class='fas ".$icon."' aria-hidden='true' style='color: ".$color.";'></i>";

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}

    /**
     * @Route("/comment_datatables/{username}")
     */
	public function commentsUserDatatablesAction(Request $request, $username)
	{
		list($voteClassName, $commentClassName, $entityClass, $editRoute) = $this->selectEntity();
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
		$entities = $entityManager->getRepository($commentClassName)->findCommentByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username);
		$iTotal = $entityManager->getRepository($commentClassName)->findCommentByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => []
		);

		foreach($entities as $entity)
		{
			$row = [];

			$show = $this->generateUrl($entity->getEntity()->getShowRoute(), array('id' => $entity->getEntity()->getId(), 'slug' => $entity->getEntity()->getSlug()));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getEntity()->getAbstract().'</a>';
			$row[] = "le ".$entity->getCreatedAt()->format("d/m/Y à H:i:s");

			$output['aaData'][] = $row;
		}

		return new JsonResponse($output);
	}
	
	private function selectEntity()
	{
		switch((new GenericFunction())->getSubDomain()) {
			case "poeticus":
				return [PoemVote::class, PoemComment::class, Poem::class, "app_indexpoeticus_poemuseredit"];
			case "quotus":
				return [QuoteVote::class, QuoteComment::class, Quote::class, "app_indexquotus_quoteuseredit"];
			case "proverbius":
				return [ProverbVote::class, ProverbComment::class, Proverb::class, null];
		}
		
		return [];
	}
}