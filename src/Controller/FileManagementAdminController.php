<?php

namespace App\Controller;

use App\Entity\FileManagement;
use App\Form\Type\FileManagementType;
use App\Service\GenericFunction;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Form\FormError;

/**
 * @Route("/admin/filemanagement")
 */
class FileManagementAdminController extends AbstractController
{
    /**
     * @Route("/media/{idForm}/{folder}/{id}", defaults={"id": null})
     */
	public function mediaAction(Request $request, $idForm, $folder, $id)
	{
		if(empty($id))
			$entity = new FileManagement();
		else {
			$entityManager = $this->getDoctrine()->getManager();
			$entity = $entityManager->getRepository(FileManagement::class)->find($id);
		}

		$entity->setFolder($folder);
		$form = $this->createForm(FileManagementType::class, $entity);
		
		return $this->render('FileManagement/media.html.twig', ["entity" => $entity, "form" => $form->createView(), "idForm" => $idForm, "folder" => $folder]);
	}

    /**
     * @Route("/upload/{idForm}/{folder}/{id}", defaults={"id": null})
     */
	public function uploadAction(Request $request, TranslatorInterface $translator, $idForm, $folder, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		if(empty($id))
			$entity = new FileManagement();
		else
			$entity = $entityManager->getRepository(FileManagement::class)->find($id);

		$form = $this->createForm(FileManagementType::class, $entity);
		$form->handleRequest($request);
		
		$title = null;
		
		if($form->get('photo')->isRequired()) {
			if(isset($request->request->get($form->getName())["photo"]) and isset($request->request->get($form->getName())["photo"]["name"]) and !empty($request->request->get($form->getName())["photo"]["name"]))
				$title = $request->request->get($form->getName())["photo"]["name"];
			else {
				if(!empty($title = $form->get('photo')->getData()["title"]) and !empty($content = $form->get('photo')->getData()["content"]))
					$title = $form->get('photo')->getData()["title"];
			}
		}

		if(empty($title))
			$form->get('photo')->get("name")->addError(new FormError($translator->trans("This value should not be blank.", array(), "validators")));

		if($form->isValid())
		{
			$entityManager = $this->getDoctrine()->getManager();
			
			if(isset($request->request->get($form->getName())["photo"]) and isset($request->request->get($form->getName())["photo"]["name"]) and !empty($request->request->get($form->getName())["photo"]["name"]))
			{
				$gf = new GenericFunction();
				$data = $gf->getContentURL(urldecode($request->request->get($form->getName())["photo"]["name"]));
				$title = urldecode(basename($request->request->get($form->getName())["photo"]["name"]));
				file_put_contents("photo/".$entity->getFolder()."/".$title, $data);
			} else {
				if(!empty($title = $form->get('photo')->getData()["title"]) and !empty($content = $form->get('photo')->getData()["content"]))
					file_put_contents("photo/".$entity->getFolder()."/".$title, $content);
			}

			$entity->setPhoto($title);
			
			$entityManager->persist($entity);
			$entityManager->flush();

			return new JsonResponse(["state" => "success", "id" => $entity->getId(), "filename" => $entity->getPhoto()]);
		}

		return new JsonResponse(["state" => "error", "content" => $this->render('FileManagement/media.html.twig', ["form" => $form->createView(), "idForm" => $idForm, "folder" => $entity->getFolder(), "entity" => $entity])->getContent()]);
	}

    /**
     * @Route("/load/{folder}")
     */
	public function loadAction(Request $request, $folder)
	{
		$page = $request->request->get("page");
		
		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(FileManagement::class)->loadAjax($folder, $page, 10);
		$total = $entityManager->getRepository(FileManagement::class)->count([]);
		
		return $this->render('FileManagement/loadMedia.html.twig', ["entities" => $entities, "page" => $page, "total" => $total, "folder" => $folder]);
	}
}