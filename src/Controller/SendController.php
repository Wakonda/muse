<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\Type\SendType;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Poem;
use App\Entity\Proverb;
use App\Entity\Quote;
use App\Service\GenericFunction;

/**
 * @Route("/send")
 */
class SendController extends AbstractController
{
    /**
     * @Route("/{id}", requirements={"id"="\d+"})
     */
    public function indexAction(Request $request, $id)
    {
		$form = $this->createForm(SendType::class, null);

        return $this->render('Index/send.html.twig', array('form' => $form->createView(), 'id' => $id));
    }

    /**
     * @Route("/send/{id}", requirements={"id"="\d+"})
     */
	public function sendAction(Request $request, \Swift_Mailer $mailer, $id)
	{
		list($className, $subDomain) = $this->selectEntity();
		parse_str($request->request->get('form'), $form_array);

        $form = $this->createForm(SendType::class, $form_array);
		
		$form->handleRequest($request);

		if($form->isSubmitted() && $form->isValid())
		{
			$data = (object)($request->request->get($form->getName()));
			$entityManager = $this->getDoctrine()->getManager();
			$entity = $entityManager->getRepository($className)->find($id);
		
			$content = $this->renderView('Index/send_message_content.html.twig', array(
				"data" => $data,
				"entity" => $entity
			));

			$message = (new \Swift_Message($data->subject))
				->setFrom($subDomain.'@wakonda.guru', $entity->getSiteName())
				->setTo($data->recipientMail)
				->setBody($content, 'text/html');
		
			$mailer->send($message);

			return new JsonResponse(["result" => "ok"]);
		}

		$res = ["result" => "error"];
		
		$res["content"] = $this->render('Index/send_form.html.twig', array('form' => $form->createView(), 'id' => $id))->getContent();

		return new JsonResponse($res);
	}
	
	private function selectEntity()
	{
		$sd = (new GenericFunction())->getSubDomain();

		switch($sd) {
			case "poeticus":
				return [Poem::class, $sd];
			case "quotus":
				return [Quote::class, $sd];
			case "proverbius":
				return [Proverb::class, $sd];
		}
		
		return [];
	}
}