<?php

namespace App\Controller;

use App\Entity\Pristup;
use App\Entity\Zakazka;
use App\Form\PristupType;
use App\Repository\PristupRepository;
use App\Repository\ZakazkaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pristup')]
#[IsGranted('ROLE_USER')]
class PristupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PristupRepository $pristupRepository,
        private ZakazkaRepository $zakazkaRepository,
        private PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'app_pristup_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $search = $request->query->get('search');

        $queryBuilder = $this->pristupRepository->getAccessibleByUserQueryBuilder($user, $search);

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('pristup/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_pristup_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        $pristup = new Pristup();
        
        // Předvyplnění zakázky z URL parametru
        $zakazkaId = $request->query->get('zakazka');
        $zakazka = null;
        
        if ($zakazkaId) {
            $zakazka = $this->zakazkaRepository->find($zakazkaId);
            if ($zakazka && !$zakazka->hasUserAccess($user)) {
                throw $this->createAccessDeniedException('Nemáte přístup k této zakázce.');
            }
            if ($zakazka) {
                $pristup->setZakazka($zakazka);
            }
        }

        $form = $this->createForm(PristupType::class, $pristup, [
            'zakazka' => $zakazka,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $zakazka = $pristup->getZakazka();
            if ($zakazka && !$zakazka->hasUserAccess($user)) {
                throw $this->createAccessDeniedException('Nemáte přístup k této zakázce.');
            }

            $pristup->setCreatedBy($user);

            $this->entityManager->persist($pristup);
            $this->entityManager->flush();

            $this->addFlash('success', 'Přístup byl úspěšně vytvořen.');

            if ($zakazka) {
                return $this->redirectToRoute('app_zakazka_detail', ['id' => $zakazka->getId()]);
            }

            return $this->redirectToRoute('app_pristup_index');
        }

        return $this->render('pristup/new.html.twig', [
            'pristup' => $pristup,
            'form' => $form,
            'zakazka' => $zakazka,
        ]);
    }

    #[Route('/{id}', name: 'app_pristup_show', methods: ['GET'])]
    public function show(Pristup $pristup): Response
    {
        $user = $this->getUser();
        
        $zakazka = $pristup->getZakazka();
        if ($zakazka && !$zakazka->hasUserAccess($user)) {
            throw $this->createAccessDeniedException('Nemáte přístup k tomuto přístupu.');
        }

        return $this->render('pristup/show.html.twig', [
            'pristup' => $pristup,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_pristup_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Pristup $pristup): Response
    {
        $user = $this->getUser();
        
        $zakazka = $pristup->getZakazka();
        if ($zakazka && !$zakazka->hasUserAccess($user)) {
            throw $this->createAccessDeniedException('Nemáte přístup k tomuto přístupu.');
        }

        $form = $this->createForm(PristupType::class, $pristup, [
            'zakazka' => $zakazka,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Přístup byl úspěšně upraven.');

            if ($zakazka) {
                return $this->redirectToRoute('app_zakazka_detail', ['id' => $zakazka->getId()]);
            }

            return $this->redirectToRoute('app_pristup_index');
        }

        return $this->render('pristup/edit.html.twig', [
            'pristup' => $pristup,
            'form' => $form,
            'zakazka' => $zakazka,
        ]);
    }

    #[Route('/{id}', name: 'app_pristup_delete', methods: ['POST'])]
    public function delete(Request $request, Pristup $pristup): Response
    {
        $user = $this->getUser();
        
        $zakazka = $pristup->getZakazka();
        if ($zakazka && !$zakazka->hasUserAccess($user)) {
            throw $this->createAccessDeniedException('Nemáte přístup k tomuto přístupu.');
        }

        $zakazkaId = $zakazka?->getId();

        if ($this->isCsrfTokenValid('delete'.$pristup->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($pristup);
            $this->entityManager->flush();

            $this->addFlash('success', 'Přístup byl úspěšně smazán.');
        }

        if ($zakazkaId) {
            return $this->redirectToRoute('app_zakazka_detail', ['id' => $zakazkaId]);
        }

        return $this->redirectToRoute('app_pristup_index');
    }
}
