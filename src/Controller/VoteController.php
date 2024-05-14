<?php

namespace App\Controller;

use App\Entity\Poem;
use App\Entity\Proverb;
use App\Entity\Quote;
use App\Entity\PoemVote;
use App\Entity\ProverbVote;
use App\Entity\QuoteVote;
use App\Entity\Vote;
use App\Entity\User;
use App\Service\GenericFunction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @Route("/vote")
 */
class VoteController extends AbstractController
{
    /**
     * @Route("/{id}")
     */
	public function voteAction(EntityManagerInterface $em, Request $request, TokenStorageInterface $tokenStorage, TranslatorInterface $translator, $id)
	{
		list($entity, $className, $mainEntity) = $this->selectEntity();
		
		$vote = $request->query->get('vote');
		$state = "";

		if(!empty($vote))
		{
			$user = $tokenStorage->getToken()->getUser();
			
			if(is_object($user))
			{
				$vote = ($vote == "up") ? 1 : -1;
				
				$entity->setVote($vote);
				$entity->setEntity($em->getRepository($mainEntity)->find($id));

				$userDb = $em->getRepository(User::class)->findByUsernameOrEmail($user->getUsername());
				$entity->setUser($userDb);
			
				$numberOfDoubloons = $em->getRepository($className)->checkIfUserAlreadyVote($id, $userDb->getId());
				
				if($numberOfDoubloons >= 1)
					$state = $translator->trans("vote.field.YouHaveAlreadyVotedForThis");
				else
				{
					$em->persist($entity);
					$em->flush();
				}
			}
			else
				$state = $translator->trans("vote.field.YouMustBeLoggedInToVote");
		}

		$up_values = $em->getRepository($className)->countVoteBy($id, 1);
		$down_values = $em->getRepository($className)->countVoteBy($id, -1);
		$total = $up_values + $down_values;
		$value = ($total == 0) ? 50 : round(((100 * $up_values) / $total), 1);

		return new JsonResponse(["up" => $up_values, "down" => $down_values, "value" => $value, "alreadyVoted" => $state]);
	}
	
	private function selectEntity()
	{
		switch((new GenericFunction())->getSubDomain()) {
			case "poeticus":
				return [new PoemVote(), PoemVote::class, Poem::class];
			case "quotus":
				return [new QuoteVote(), QuoteVote::class, Quote::class];
			case "proverbius":
				return [new ProverbVote(), ProverbVote::class, Proverb::class];
		}

		return [];
	}
}