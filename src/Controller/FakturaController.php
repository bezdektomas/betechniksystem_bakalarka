<?php

namespace App\Controller;

use App\Entity\Faktura;
use App\Entity\Zakazka;
use App\Form\FakturaType;
use App\Repository\FakturaRepository;
use App\Repository\StatusFakturaRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/faktura')]
#[IsGranted('ROLE_USER')]
class FakturaController extends AbstractController
{
    private string $fakturaDirectory;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/faktury')] string $fakturaDirectory,
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
    ) {
        $this->fakturaDirectory = $fakturaDirectory;
    }

    #[Route('', name: 'app_faktura_index', methods: ['GET'])]
    public function index(Request $request, FakturaRepository $fakturaRepository, StatusFakturaRepository $statusRepository, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();
        $search = $request->query->get('search');
        $statusFilter = $request->query->get('status');

        // Paginator
        $queryBuilder = $fakturaRepository->getAccessibleByUserQueryBuilder($user, $search, $statusFilter);
        
        $faktury = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20
        );
        
        $statusy = $statusRepository->findAllOrdered();

        return $this->render('faktura/index.html.twig', [
            'faktury' => $faktury,
            'statusy' => $statusy,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    #[Route('/nova', name: 'app_faktura_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        SluggerInterface $slugger,
        StatusFakturaRepository $statusRepository
    ): Response {
        $faktura = new Faktura();
        
        // Nastavíme výchozí status
        $defaultStatus = $statusRepository->findDefaultStatus();
        if ($defaultStatus) {
            $faktura->setStatus($defaultStatus);
        }

        // Pokud přicházíme ze zakázky předvyplníme ji
        $zakazkaId = $request->query->get('zakazka');
        if ($zakazkaId) {
            $zakazka = $entityManager->getRepository(Zakazka::class)->find($zakazkaId);
            if ($zakazka && $zakazka->hasUserAccess($this->getUser())) {
                $faktura->setZakazka($zakazka);
            }
        }

        $form = $this->createForm(FakturaType::class, $faktura, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Kontrola přístupu k zakázce
            $zakazka = $faktura->getZakazka();
            if ($zakazka && !$zakazka->hasUserAccess($this->getUser())) {
                throw new AccessDeniedHttpException('Nemáte přístup k této zakázce.');
            }

            // Zpracování přílohy
            $file = $form->get('file')->getData();
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    // Vytvoříme adresář, pokud neexistuje
                    if (!is_dir($this->fakturaDirectory)) {
                        mkdir($this->fakturaDirectory, 0755, true);
                    }

                    $file->move($this->fakturaDirectory, $newFilename);
                    $faktura->setFile($newFilename);
                    $faktura->setOriginalFilename($file->getClientOriginalName());
                } catch (FileException $e) {
                    $this->addFlash('error', 'Nepodařilo se nahrát soubor: ' . $e->getMessage());
                }
            }

            // Automatické dopočítání cen
            $faktura->calculatePrices();
            
            $faktura->setCreatedBy($this->getUser());
            $entityManager->persist($faktura);
            $entityManager->flush();

            $this->notificationService->notify(
                recipients: $this->userRepository->findAdmins(),
                title: 'Nová faktura',
                body: $faktura->getOriginalFilename() ?? ('Faktura #' . $faktura->getId()),
                link: $this->generateUrl('app_faktura_show', ['id' => $faktura->getId()]),
                resourceType: 'faktura',
                resourceId: $faktura->getId(),
                collapseKey: 'faktura_new_' . $faktura->getId(),
                excludeUser: $this->getUser(),
            );

            $this->addFlash('success', 'Faktura byla úspěšně vytvořena.');

            return $this->redirectToRoute('app_faktura_show', ['id' => $faktura->getId()]);
        }

        return $this->render('faktura/new.html.twig', [
            'faktura' => $faktura,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_faktura_show', methods: ['GET'])]
    public function show(Faktura $faktura, StatusFakturaRepository $statusRepository): Response
    {
        // Kontrola přístupu - uživatel musí spolupracovat na zakázce nebo být admin
        $this->checkAccess($faktura);

        return $this->render('faktura/show.html.twig', [
            'faktura' => $faktura,
            'statusy' => $statusRepository->findAllOrdered(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_faktura_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Faktura $faktura, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        // Kontrola přístupu
        $this->checkAccess($faktura);

        $oldStatus = $faktura->getStatus();

        $form = $this->createForm(FakturaType::class, $faktura, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Kontrola přístupu k nové zakázce (pokud byla změněna)
            $zakazka = $faktura->getZakazka();
            if ($zakazka && !$zakazka->hasUserAccess($this->getUser())) {
                throw new AccessDeniedHttpException('Nemáte přístup k této zakázce.');
            }

            // Zpracování nahraného souboru
            $file = $form->get('file')->getData();
            if ($file) {
                // Smažeme starý soubor
                $oldFile = $faktura->getFile();
                if ($oldFile) {
                    $oldFilePath = $this->fakturaDirectory . '/' . $oldFile;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    if (!is_dir($this->fakturaDirectory)) {
                        mkdir($this->fakturaDirectory, 0755, true);
                    }

                    $file->move($this->fakturaDirectory, $newFilename);
                    $faktura->setFile($newFilename);
                    $faktura->setOriginalFilename($file->getClientOriginalName());
                } catch (FileException $e) {
                    $this->addFlash('error', 'Nepodařilo se nahrát soubor: ' . $e->getMessage());
                }
            }

            // Automatické dopočítání cen
            $faktura->calculatePrices();

            $newStatus = $faktura->getStatus();
            $entityManager->flush();

            if ($newStatus !== $oldStatus) {
                $this->notificationService->notify(
                    recipients: $this->userRepository->findAdmins(),
                    title: 'Změna statusu faktury',
                    body: ($faktura->getOriginalFilename() ?? ('Faktura #' . $faktura->getId())) . ' → ' . $newStatus->getName(),
                    link: $this->generateUrl('app_faktura_show', ['id' => $faktura->getId()]),
                    resourceType: 'faktura',
                    resourceId: $faktura->getId(),
                    collapseKey: 'faktura_status_' . $faktura->getId(),
                    excludeUser: $this->getUser(),
                );
            }

            $this->addFlash('success', 'Faktura byla úspěšně aktualizována.');

            return $this->redirectToRoute('app_faktura_show', ['id' => $faktura->getId()]);
        }

        return $this->render('faktura/edit.html.twig', [
            'faktura' => $faktura,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_faktura_delete', methods: ['POST'])]
    public function delete(Request $request, Faktura $faktura, EntityManagerInterface $entityManager): Response
    {
        // Kontrola přístupu
        $this->checkAccess($faktura);

        if ($this->isCsrfTokenValid('delete' . $faktura->getId(), $request->getPayload()->getString('_token'))) {
            // Smažeme soubor
            $file = $faktura->getFile();
            if ($file) {
                $filePath = $this->fakturaDirectory . '/' . $file;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $entityManager->remove($faktura);
            $entityManager->flush();

            $this->addFlash('success', 'Faktura byla úspěšně smazána.');
        }

        return $this->redirectToRoute('app_faktura_index');
    }

    /**
     * Zabezpečené stažení faktury
     */
    #[Route('/{id}/file', name: 'app_faktura_file', methods: ['GET'])]
    public function downloadFile(Faktura $faktura, Request $request): Response
    {
        $this->checkAccess($faktura);

        $filename = $faktura->getFile();
        if (!$filename) {
            throw new NotFoundHttpException('Faktura nemá přiložený soubor.');
        }

        $filePath = $this->fakturaDirectory . '/' . $filename;
        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Soubor nebyl nalezen.');
        }

        $response = new BinaryFileResponse($filePath);
        
        $mimeType = mime_content_type($filePath);
        $response->headers->set('Content-Type', $mimeType);

        // Pokud je to PDF nebo obrázek, zobrazíme v appce, jinak stáhneme
        $disposition = in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'])
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        $originalFilename = $faktura->getOriginalFilename() ?? $filename;
        $response->setContentDisposition($disposition, $originalFilename);

        return $response;
    }

    /**
     * Rychlá změna statusu faktury (AJAX)
     */
    #[Route('/{id}/status', name: 'app_faktura_change_status', methods: ['POST'])]
    public function changeStatus(
        Request $request, 
        Faktura $faktura, 
        EntityManagerInterface $entityManager,
        StatusFakturaRepository $statusRepository
    ): Response {
        $this->checkAccess($faktura);

        $statusId = $request->request->get('status');
        if ($statusId && $this->isCsrfTokenValid('status' . $faktura->getId(), $request->request->get('_token'))) {
            $status = $statusRepository->find($statusId);
            if ($status) {
                $faktura->setStatus($status);
                $entityManager->flush();

                $this->notificationService->notify(
                    recipients: $this->userRepository->findAdmins(),
                    title: 'Změna statusu faktury',
                    body: ($faktura->getOriginalFilename() ?? ('Faktura #' . $faktura->getId())) . ' → ' . $status->getName(),
                    link: $this->generateUrl('app_faktura_show', ['id' => $faktura->getId()]),
                    resourceType: 'faktura',
                    resourceId: $faktura->getId(),
                    collapseKey: 'faktura_status_' . $faktura->getId(),
                    excludeUser: $this->getUser(),
                );

                $this->addFlash('success', 'Status faktury byl změněn.');
            }
        }

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_faktura_show', ['id' => $faktura->getId()]);
    }

    /**
     * Kontrola přístupu k faktuře
     * Uživatel musí být admin nebo mít přístup k související zakázce
     */
    private function checkAccess(Faktura $faktura): void
    {
        $user = $this->getUser();
        
        // Admin má přístup ke všemu
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return;
        }

        // Zkontrolujeme přístup přes zakázku
        $zakazka = $faktura->getZakazka();
        if (!$zakazka || !$zakazka->hasUserAccess($user)) {
            throw new AccessDeniedHttpException('Nemáte přístup k této faktuře.');
        }
    }
}
