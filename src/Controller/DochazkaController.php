<?php

namespace App\Controller;

use App\Entity\Dochazka;
use App\Entity\DochazkaTimer;
use App\Entity\Zakazka;
use App\Form\DochazkaType;
use App\Repository\DochazkaRepository;
use App\Repository\DochazkaTimerRepository;
use App\Repository\ZakazkaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dochazka')]
#[IsGranted('ROLE_USER')]
class DochazkaController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DochazkaRepository $dochazkaRepository,
        private DochazkaTimerRepository $timerRepository,
        private ZakazkaRepository $zakazkaRepository,
        private PaginatorInterface $paginator,
    ) {}

    #[Route('', name: 'app_dochazka_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        // Aktivní timer
        $activeTimer = $this->timerRepository->findActiveForUser($user);
        
        // Dnešní docházka
        $todayRecords = $this->dochazkaRepository->getTodayForUser($user);
        $todayMinutes = $this->dochazkaRepository->getTotalMinutesForUserAndDate($user, new \DateTime());
        
        // Tento týden
        $weekRecords = $this->dochazkaRepository->getThisWeekForUser($user);
        $monday = (new \DateTime())->modify('monday this week');
        $sunday = (new \DateTime())->modify('sunday this week');
        $weekMinutes = $this->dochazkaRepository->getTotalMinutesForUserInPeriod($user, $monday, $sunday);
        
        // Souhrn po zakázkách
        $zakazkySummary = $this->dochazkaRepository->getSummaryByZakazkaForUser($user);
        
        // Seznam všech záznamů s paginatorem
        $qb = $this->dochazkaRepository->createQueryBuilderForUser($user);
        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );
        
        // Zakázky pro timer dropdown
        $zakazky = $this->zakazkaRepository->findAll();
        
        return $this->render('dochazka/index.html.twig', [
            'activeTimer' => $activeTimer,
            'todayRecords' => $todayRecords,
            'todayMinutes' => $todayMinutes,
            'weekRecords' => $weekRecords,
            'weekMinutes' => $weekMinutes,
            'zakazkySummary' => $zakazkySummary,
            'pagination' => $pagination,
            'zakazky' => $zakazky,
        ]);
    }

    /**
     * Admin přehled
     */
    #[Route('/admin', name: 'app_dochazka_admin', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(Request $request): Response
    {
        // Souhrn po uživatelích
        $usersSummary = $this->dochazkaRepository->getSummaryByUser();
        
        // Všechny záznamy s paginatorem
        $qb = $this->dochazkaRepository->createQueryBuilderAll();
        
        // Filtr podle uživatele
        $filterUserId = $request->query->get('user');
        if ($filterUserId) {
            $qb->andWhere('d.user = :userId')
               ->setParameter('userId', $filterUserId);
        }
        
        // Filtr podle zakázky
        $filterZakazkaId = $request->query->get('zakazka');
        if ($filterZakazkaId) {
            $qb->andWhere('d.zakazka = :zakazkaId')
               ->setParameter('zakazkaId', $filterZakazkaId);
        }
        
        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );
        
        return $this->render('dochazka/admin.html.twig', [
            'usersSummary' => $usersSummary,
            'pagination' => $pagination,
            'filterUserId' => $filterUserId,
            'filterZakazkaId' => $filterZakazkaId,
        ]);
    }

    /**
     * Docházka k zakázce
     */
    #[Route('/zakazka/{id}', name: 'app_dochazka_zakazka', methods: ['GET'])]
    public function zakazka(Zakazka $zakazka, Request $request): Response
    {
        // Kontrola přístupu k zakázce
        if (!$zakazka->hasUserAccess($this->getUser())) {
            throw $this->createAccessDeniedException('Nemáte přístup k této zakázce.');
        }
        
        $qb = $this->dochazkaRepository->createQueryBuilderForZakazka($zakazka);
        
        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );
        
        return $this->render('dochazka/zakazka.html.twig', [
            'zakazka' => $zakazka,
            'pagination' => $pagination,
            'totalMinutes' => $zakazka->getTotalWorkedMinutes(),
        ]);
    }

    /**
     * Nový záznam docházky
     */
    #[Route('/new', name: 'app_dochazka_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ZakazkaRepository $zakazkaRepository): Response
    {
        $dochazka = new Dochazka();
        $dochazka->setUser($this->getUser());
        $dochazka->setDatum(new \DateTime());
        
        // Předvyplnění zakázky z URL parametru
        $zakazkaId = $request->query->get('zakazka');
        if ($zakazkaId) {
            $zakazka = $zakazkaRepository->find($zakazkaId);
            if ($zakazka && $zakazka->hasUserAccess($this->getUser())) {
                $dochazka->setZakazka($zakazka);
            }
        }
        
        $form = $this->createForm(DochazkaType::class, $dochazka);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Celkové minuty
            $hodiny = (int) $form->get('hodiny')->getData();
            $minuty = (int) $form->get('minutyInput')->getData();
            $totalMinuty = ($hodiny * 60) + $minuty;
            
            if ($totalMinuty <= 0) {
                $this->addFlash('error', 'Zadejte odpracovaný čas.');
                return $this->render('dochazka/new.html.twig', [
                    'form' => $form,
                ]);
            }
            
            $dochazka->setMinuty($totalMinuty);
            
            $this->em->persist($dochazka);
            $this->em->flush();
            
            $this->addFlash('success', 'Záznam docházky byl vytvořen.');
            
            return $this->redirectToRoute('app_dochazka_index');
        }
        
        return $this->render('dochazka/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Úprava záznamu docházky
     */
    #[Route('/{id}/edit', name: 'app_dochazka_edit', methods: ['GET', 'POST'])]
    public function edit(Dochazka $dochazka, Request $request): Response
    {
        // Kontrola práva na úpravu (vlastník záznamu nebo admin)
        if ($dochazka->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Nemáte oprávnění upravit tento záznam.');
        }
        
        // Převod minut na hodiny a minuty pro formulář
        $currentMinuty = $dochazka->getMinuty();
        $hodiny = intdiv($currentMinuty, 60);
        $minuty = $currentMinuty % 60;
        
        $form = $this->createForm(DochazkaType::class, $dochazka, [
            'hodiny' => $hodiny,
            'minuty_input' => $minuty,
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Spočítáme minuty z hodiny + minuty
            $hodiny = (int) $form->get('hodiny')->getData();
            $minuty = (int) $form->get('minutyInput')->getData();
            $totalMinuty = ($hodiny * 60) + $minuty;
            
            if ($totalMinuty <= 0) {
                $this->addFlash('error', 'Zadejte odpracovaný čas.');
                return $this->render('dochazka/edit.html.twig', [
                    'form' => $form,
                    'dochazka' => $dochazka,
                ]);
            }
            
            $dochazka->setMinuty($totalMinuty);
            
            $this->em->flush();
            
            $this->addFlash('success', 'Záznam docházky byl upraven.');
            
            return $this->redirectToRoute('app_dochazka_index');
        }
        
        return $this->render('dochazka/edit.html.twig', [
            'form' => $form,
            'dochazka' => $dochazka,
        ]);
    }

    /**
     * Smazání záznamu docházky
     */
    #[Route('/{id}/delete', name: 'app_dochazka_delete', methods: ['POST'])]
    public function delete(Dochazka $dochazka, Request $request): Response
    {
        // Kontrola vlastnictví
        if ($dochazka->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Nemáte oprávnění smazat tento záznam.');
        }
        
        if ($this->isCsrfTokenValid('delete' . $dochazka->getId(), $request->request->get('_token'))) {
            $this->em->remove($dochazka);
            $this->em->flush();
            $this->addFlash('success', 'Záznam docházky byl smazán.');
        }
        
        return $this->redirectToRoute('app_dochazka_index');
    }

    /**
     * Spuštění timeru
     */
    #[Route('/timer/start', name: 'app_dochazka_timer_start', methods: ['POST'])]
    public function timerStart(Request $request, ZakazkaRepository $zakazkaRepository): JsonResponse
    {
        $user = $this->getUser();
        
        // Zkontrolujeme, jestli už nemá aktivní timer
        $existingTimer = $this->timerRepository->findActiveForUser($user);
        if ($existingTimer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Již máte aktivní timer. Nejprve ho ukončete.',
            ], 400);
        }
        
        // Vytvoříme nový timer
        $timer = new DochazkaTimer();
        $timer->setUser($user);
        $timer->setStartedAt(new \DateTimeImmutable());
        $timer->setAccumulatedMinutes(0);
        
        // Volitelně přiřadíme zakázku
        $zakazkaId = $request->request->get('zakazka_id');
        if ($zakazkaId) {
            $zakazka = $zakazkaRepository->find($zakazkaId);
            if ($zakazka && $zakazka->hasUserAccess($user)) {
                $timer->setZakazka($zakazka);
            }
        }
        
        $this->em->persist($timer);
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'timer' => [
                'id' => $timer->getId(),
                'startedAt' => $timer->getStartedAt()->format('c'),
                'isRunning' => $timer->isRunning(),
                'totalMinutes' => $timer->getTotalMinutes(),
                'formattedTime' => $timer->getFormattedTime(),
                'zakazkaId' => $timer->getZakazka()?->getId(),
                'zakazkaName' => $timer->getZakazka()?->getName(),
            ],
        ]);
    }

    /**
     * Pozastavení timeru
     */
    #[Route('/timer/pause', name: 'app_dochazka_timer_pause', methods: ['POST'])]
    public function timerPause(): JsonResponse
    {
        $user = $this->getUser();
        $timer = $this->timerRepository->findActiveForUser($user);
        
        if (!$timer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nemáte aktivní timer.',
            ], 400);
        }
        
        $timer->pause();
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'timer' => [
                'id' => $timer->getId(),
                'isRunning' => $timer->isRunning(),
                'isPaused' => $timer->isPaused(),
                'totalMinutes' => $timer->getTotalMinutes(),
                'formattedTime' => $timer->getFormattedTime(),
            ],
        ]);
    }

    /**
     * Obnoví timeru z pauzy
     */
    #[Route('/timer/resume', name: 'app_dochazka_timer_resume', methods: ['POST'])]
    public function timerResume(): JsonResponse
    {
        $user = $this->getUser();
        $timer = $this->timerRepository->findActiveForUser($user);
        
        if (!$timer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nemáte aktivní timer.',
            ], 400);
        }
        
        $timer->resume();
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'timer' => [
                'id' => $timer->getId(),
                'startedAt' => $timer->getStartedAt()->format('c'),
                'isRunning' => $timer->isRunning(),
                'isPaused' => $timer->isPaused(),
                'totalMinutes' => $timer->getTotalMinutes(),
                'formattedTime' => $timer->getFormattedTime(),
            ],
        ]);
    }

    /**
     * Ukončení timeru a uložení docházky
     */
    #[Route('/timer/stop', name: 'app_dochazka_timer_stop', methods: ['POST'])]
    public function timerStop(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $timer = $this->timerRepository->findActiveForUser($user);
        
        if (!$timer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nemáte aktivní timer.',
            ], 400);
        }
        
        // Celkový čas
        $totalMinutes = $timer->getTotalMinutes();
        
        if ($totalMinutes < 1) {
            // Méně než minuta - nezapíšeme
            $this->em->remove($timer);
            $this->em->flush();
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Timer ukončen. Čas byl příliš krátký pro záznam.',
                'recorded' => false,
            ]);
        }
        
        // Vytvoříme záznam docházky
        $dochazka = new Dochazka();
        $dochazka->setUser($user);
        $dochazka->setZakazka($timer->getZakazka());
        $dochazka->setDatum(new \DateTime());
        $dochazka->setMinuty($totalMinutes);
        $dochazka->setPopis($request->request->get('popis'));
        
        $this->em->persist($dochazka);
        $this->em->remove($timer);
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Docházka byla zaznamenána.',
            'recorded' => true,
            'dochazka' => [
                'id' => $dochazka->getId(),
                'minuty' => $dochazka->getMinuty(),
                'formattedTime' => $dochazka->getFormattedTime(),
            ],
        ]);
    }

    /**
     * Zrušení timeru
     */
    #[Route('/timer/cancel', name: 'app_dochazka_timer_cancel', methods: ['POST'])]
    public function timerCancel(): JsonResponse
    {
        $user = $this->getUser();
        $timer = $this->timerRepository->findActiveForUser($user);
        
        if (!$timer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nemáte aktivní timer.',
            ], 400);
        }
        
        $this->em->remove($timer);
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Timer byl zrušen.',
        ]);
    }

}
