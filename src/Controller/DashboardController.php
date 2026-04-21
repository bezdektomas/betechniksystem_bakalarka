<?php

namespace App\Controller;

use App\Repository\StatusRepository;
use App\Repository\ZakazkaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private ZakazkaRepository $zakazkaRepository,
        private StatusRepository $statusRepository,
    ) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $statusy = $this->statusRepository->findAllOrdered();
        
        // Najít status IDs podle názvů
        $statusNovy = null;
        $statusCekaNaSchvaleni = null;
        $statusVRealizaci = null;
        $statusDokonceno = null;
        
        foreach ($statusy as $status) {
            match ($status->getName()) {
                'Nový' => $statusNovy = $status,
                'Čeká na schválení' => $statusCekaNaSchvaleni = $status,
                'V realizaci' => $statusVRealizaci = $status,
                'Dokončeno' => $statusDokonceno = $status,
                default => null,
            };
        }

        // Nezpracované zakázky (Nové + Čeká na schválení)
        $zakazkyNezpracovane = [];
        if ($statusNovy) {
            $zakazkyNezpracovane = array_merge($zakazkyNezpracovane, $this->zakazkaRepository->findByStatus($statusNovy));
        }
        if ($statusCekaNaSchvaleni) {
            $zakazkyNezpracovane = array_merge($zakazkyNezpracovane, $this->zakazkaRepository->findByStatus($statusCekaNaSchvaleni));
        }
        // Seřadit podle data vytvoření
        usort($zakazkyNezpracovane, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $zakazkyNezpracovane = array_slice($zakazkyNezpracovane, 0, 5);

        // Zakázky v realizaci
        $zakazkyVRealizaci = [];
        if ($statusVRealizaci) {
            $zakazkyVRealizaci = $this->zakazkaRepository->findByStatus($statusVRealizaci);
            usort($zakazkyVRealizaci, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
            $zakazkyVRealizaci = array_slice($zakazkyVRealizaci, 0, 5);
        }

        // Statistiky
        $countNezpracovane = 0;
        if ($statusNovy) {
            $countNezpracovane += $this->zakazkaRepository->countByStatus($statusNovy);
        }
        if ($statusCekaNaSchvaleni) {
            $countNezpracovane += $this->zakazkaRepository->countByStatus($statusCekaNaSchvaleni);
        }

        $countVRealizaci = 0;
        if ($statusVRealizaci) {
            $countVRealizaci = $this->zakazkaRepository->countByStatus($statusVRealizaci);
        }

        // Měsíční obrat (cena zakázek dokončených tento měsíc)
        $mesicniObrat = 0;
        $dokoncenoMesic = 0;
        if ($statusDokonceno) {
            $startOfMonth = new \DateTime('first day of this month midnight');
            $endOfMonth = new \DateTime('last day of this month 23:59:59');
            
            $dokonceneZakazky = $this->zakazkaRepository->findByStatusAndDateRange($statusDokonceno, $startOfMonth, $endOfMonth);
            $dokoncenoMesic = count($dokonceneZakazky);
            
            foreach ($dokonceneZakazky as $zakazka) {
                $mesicniObrat += $zakazka->getPrice() ?? 0;
            }
        }

        // Celkový obrat v realizaci (pokud nejsou dokončené zakázky, ukážeme hodnotu zakázek v realizaci)
        if ($mesicniObrat === 0 && $statusVRealizaci) {
            $mesicniObrat = (int) $this->zakazkaRepository->sumPriceByStatus($statusVRealizaci);
        }

        $stats = [
            'nezpracovane' => $countNezpracovane,
            'v_realizaci' => $countVRealizaci,
            'mesicni_obrat' => $mesicniObrat,
            'dokonceno_mesic' => $dokoncenoMesic,
        ];

        return $this->render('dashboard/index.html.twig', [
            'zakazkyNezpracovane' => $zakazkyNezpracovane,
            'zakazkyVRealizaci' => $zakazkyVRealizaci,
            'stats' => $stats,
        ]);
    }

    #[Route('/offline', name: 'app_offline')]
    public function offline(): Response
    {
        return $this->render('offline.html.twig');
    }
}
