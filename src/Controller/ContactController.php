<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\Type\ContactType;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @Route("/contact")
 */
class ContactController extends AbstractController
{
    /**
     * @Route("/")
     */
    public function indexAction(Request $request)
    {
		$form = $this->createForm(ContactType::class, null);

        return $this->render('Index/contact.html.twig', array('form' => $form->createView()));
    }
	
    /**
     * @Route("/send")
     */
	public function sendAction(EntityManagerInterface $em, Request $request, SessionInterface $session, TranslatorInterface $translator)
	{
		$entity = new Contact();
        $form = $this->createForm(ContactType::class, $entity);
		$form->handleRequest($request);

		if($form->isValid())
		{
			$em->persist($entity);
			$em->flush();
			$session->getFlashBag()->add('message', $translator->trans("contact.field.YourMessageHasBeenSentSuccessfully"));

			return $this->redirect($this->generateUrl('index'));
		}
		
		return $this->render('Index/contact.html.twig', array('form' => $form->createView()));
	}
}
