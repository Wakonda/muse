<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Annotation\Route;

use Ifsnop\Mysqldump as IMysqldump;

/**
 * @Route("/admin/backup")
 */
class BackupAdminController extends AbstractController
{
	private $parameterBag;

	public function __construct(ParameterBagInterface $parameterBag)
	{
		$this->parameterBag = $parameterBag;
	}

    /**
     * @Route("/")
     */
    public function indexAction()
    {
		$files = [];
		
		if(is_dir($this->getPath())) {
			if ($handle = opendir($this->getPath())) {
				while (false !== ($entry = readdir($handle))) {
					if ($entry != "." && $entry != "..") {
						$files[] = $entry;
					}
				}

				closedir($handle);
			}
		}

        return $this->render('Backup/index.html.twig', ["files" => $files]);
    }

    /**
     * @Route("/download/{filename}")
     */
	public function downloadAction($filename)
	{
		$response = new Response(file_get_contents($this->getPath().DIRECTORY_SEPARATOR.$filename));

		$disposition = HeaderUtils::makeDisposition(
			HeaderUtils::DISPOSITION_ATTACHMENT,
			$filename
		);

		$response->headers->set('Content-Disposition', $disposition);

		return $response;
	}

    /**
     * @Route("/delete/{filename}")
     */
	public function deleteAction(SessionInterface $session, TranslatorInterface $translator, $filename)
	{
		unlink($this->getPath().DIRECTORY_SEPARATOR.$filename);
		
		$session->getFlashBag()->add('message', $translator->trans("backup.index.FileDeleted"));
		
		return $this->redirect($this->generateUrl("app_backupadmin_index"));
	}

    /**
     * @Route("/generate")
     */
	public function generateAction(SessionInterface $session, TranslatorInterface $translator)
	{
		try {
			if(!is_dir($this->getPath())) {
				mkdir($this->getPath());
			}
			
			$filename = "backup_" . date("Y_m_d_H_i_s") . ".sql";

			$dump = new IMysqldump\Mysqldump($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
			$dump->start($this->getPath().DIRECTORY_SEPARATOR.$filename);
			$session->getFlashBag()->add('message', $translator->trans("backup.index.FileGenerated"));
		} catch (\Exception $e) {
			$session->getFlashBag()->add('message', 'mysqldump-php error: ' . $e->getMessage());
		}

		return $this->redirect($this->generateUrl("app_backupadmin_index"));
	}

	public function countAction()
	{
		$count = 0;
		
		if(is_dir($this->getPath())) {
			$fi = new \FilesystemIterator($this->getPath(), \FilesystemIterator::SKIP_DOTS);
			$count = iterator_count($fi);
		}
		
		return new Response($count);
	}

	private function getPath()
	{
		return $this->parameterBag->get('kernel.project_dir').DIRECTORY_SEPARATOR."var".DIRECTORY_SEPARATOR."backup";
	}
}