<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
   /**
     * @Route("/")
     */
    public function indexAction()
    {
        return $this->render('Admin/index.html.twig');
    }
}
