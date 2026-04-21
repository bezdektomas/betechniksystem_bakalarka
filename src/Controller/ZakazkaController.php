<?php

namespace App\Controller;

use App\Entity\Status;
use App\Entity\User;
use App\Entity\Zakazka;
use App\Form\ZakazkaType;
use App\Repository\ChatKonverzaceRepository;
use App\Repository\StatusRepository;
use App\Repository\UserRepository;
use App\Repository\ZakazkaRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/zakazky')]
class ZakazkaController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ZakazkaRepository $zakazkaRepository,
        private StatusRepository $statusRepository,
        private UserRepository $userRepository,
        private ChatKonverzaceRepository $chatKonverzaceRepository,
        private NotificationService $notificationService,
    ) {}

    /**
     * Seznam všech zakázek
     */
    #[Route('', name: 'app_zakazka_list', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $statusFilter = $request->query->get('status');
        $search = $request->query->get('q');
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('dir', 'desc');
        $onlyMine = $request->query->getBoolean('mine');

        // Validace směru řazení
        $direction = in_array(strtolower($direction), ['asc', 'desc']) ? strtolower($direction) : 'desc';

        // Validace sloupce při řazení
        $allowedSorts = ['createdAt', 'price', 'name', 'realizace'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'createdAt';

        // Paginator - řazení je aplikováno v repository, paginátoru zakážeme vlastní řazení
        $queryBuilder = $this->zakazkaRepository->getFilteredQueryBuilder($statusFilter, $search, $sort, $direction, $onlyMine, $this->getUser());

        $zakazky = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20,
            ['sortFieldParameterName' => '_sort', 'sortDirectionParameterName' => '_dir']
        );

        $statusy = $this->statusRepository->findAllOrdered();

        return $this->render('zakazka/index.html.twig', [
            'zakazky' => $zakazky,
            'statusy' => $statusy,
            'currentStatus' => $statusFilter,
            'search' => $search,
            'currentSort' => $sort,
            'currentDirection' => $direction,
            'onlyMine' => $onlyMine,
        ]);
    }

    /**
     * Vytvoření nové zakázky
     */
    #[Route('/nova', name: 'app_zakazka_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $zakazka = new Zakazka();
        
        // Nastavit výchozí status (první v pořadí - Nový)
        $defaultStatus = $this->statusRepository->findOneBy([], ['sortOrder' => 'ASC']);
        if ($defaultStatus) {
            $zakazka->setStatus($defaultStatus);
        }

        $form = $this->createForm(ZakazkaType::class, $zakazka);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $zakazka->setCreatedBy($user);

            $this->entityManager->persist($zakazka);
            $this->entityManager->flush();

            $this->notificationService->notify(
                recipients: $this->userRepository->findActiveUsers(),
                title: 'Nová zakázka',
                body: $zakazka->getName(),
                link: $this->generateUrl('app_zakazka_detail', ['id' => $zakazka->getId()]),
                resourceType: 'zakazka',
                resourceId: $zakazka->getId(),
                collapseKey: 'zakazka_new_' . $zakazka->getId(),
                excludeUser: $user,
            );

            $this->addFlash('success', 'Zakázka byla úspěšně vytvořena.');
            return $this->redirectToRoute('app_zakazka_detail', ['id' => $zakazka->getId()]);
        }

        return $this->render('zakazka/new.html.twig', [
            'form' => $form,
            'zakazka' => $zakazka,
            'users' => $this->userRepository->findBy(['isActive' => true], ['name' => 'ASC']),
        ]);
    }

    /**
     * Detail zakázky
     */
    #[Route('/{id}', name: 'app_zakazka_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(Zakazka $zakazka): Response
    {
        $statusy = $this->statusRepository->findAllOrdered();

        return $this->render('zakazka/detail.html.twig', [
            'zakazka' => $zakazka,
            'statusy' => $statusy,
        ]);
    }

    /**
     * Editace zakázky
     */
    #[Route('/{id}/upravit', name: 'app_zakazka_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Zakazka $zakazka): Response
    {
        $form = $this->createForm(ZakazkaType::class, $zakazka);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->chatKonverzaceRepository->syncZakazkaMembers($zakazka);

            $this->addFlash('success', 'Zakázka byla úspěšně upravena.');
            return $this->redirectToRoute('app_zakazka_detail', ['id' => $zakazka->getId()]);
        }

        return $this->render('zakazka/edit.html.twig', [
            'form' => $form,
            'zakazka' => $zakazka,
            'users' => $this->userRepository->findBy(['isActive' => true], ['name' => 'ASC']),
        ]);
    }

    /**
     * Smazání zakázky
     */
    #[Route('/{id}/smazat', name: 'app_zakazka_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Zakazka $zakazka): Response
    {
        if ($this->isCsrfTokenValid('delete' . $zakazka->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($zakazka);
            $this->entityManager->flush();

            $this->addFlash('success', 'Zakázka byla úspěšně smazána.');
        }

        return $this->redirectToRoute('app_zakazka_list');
    }

    /**
     * Rychlá změna statusu (AJAX)
     */
    #[Route('/{id}/status', name: 'app_zakazka_change_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changeStatus(Request $request, Zakazka $zakazka): Response
    {
        $statusId = $request->request->get('status');
        
        if ($statusId && $this->isCsrfTokenValid('status' . $zakazka->getId(), $request->request->get('_token'))) {
            $status = $this->statusRepository->find($statusId);
            if ($status) {
                $zakazka->setStatus($status);
                $this->entityManager->flush();

                $participants = array_values(array_unique([
                    ...$zakazka->getAssignedUsers()->toArray(),
                    $zakazka->getCreatedBy(),
                ], SORT_REGULAR));
                $this->notificationService->notify(
                    recipients: $participants,
                    title: 'Změna statusu zakázky',
                    body: $zakazka->getName() . ' → ' . $status->getName(),
                    link: $this->generateUrl('app_zakazka_detail', ['id' => $zakazka->getId()]),
                    resourceType: 'zakazka',
                    resourceId: $zakazka->getId(),
                    collapseKey: 'zakazka_status_' . $zakazka->getId(),
                    excludeUser: $this->getUser(),
                );

                $this->addFlash('success', 'Status zakázky byl změněn.');
            }
        }

        // Pokud AJAX request, vrátit JSON
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('app_zakazka_detail', ['id' => $zakazka->getId()]);
    }
}
