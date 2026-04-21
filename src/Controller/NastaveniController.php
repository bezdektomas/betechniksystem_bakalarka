<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Repository\ZakazkaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/nastaveni')]
#[IsGranted('ROLE_USER')]
class NastaveniController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private ZakazkaRepository $zakazkaRepository,
        private PaginatorInterface $paginator,
        private SluggerInterface $slugger,
    ) {}

    /**
     * Hlavní stránka nastavení - osobní profil
     */
    #[Route('', name: 'app_nastaveni_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('nastaveni/profil.html.twig');
    }

    /**
     * Nahrání profilové fotografie
     */
    #[Route('/fotografie', name: 'app_nastaveni_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request): Response
    {
        $user = $this->getUser();
        
        $photoFile = $request->files->get('photo');
        
        if (!$photoFile) {
            $this->addFlash('error', 'Nebyl vybrán žádný soubor.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($photoFile->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', 'Povolené formáty jsou: JPG, PNG, GIF, WebP.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        if ($photoFile->getSize() > 5 * 1024 * 1024) {
            $this->addFlash('error', 'Maximální velikost souboru je 5 MB.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        // Smazání staré fotografie
        $oldPhoto = $user->getProfilePicture();
        if ($oldPhoto) {
            $oldPhotoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $oldPhoto;
            if (file_exists($oldPhotoPath)) {
                unlink($oldPhotoPath);
            }
        }
        
        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();
        
        try {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $photoFile->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'Nepodařilo se nahrát fotografii. Zkuste to znovu.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        $user->setProfilePicture($newFilename);
        $this->em->flush();
        
        $this->addFlash('success', 'Profilová fotografie byla úspěšně nahrána.');
        return $this->redirectToRoute('app_nastaveni_index');
    }

    /**
     * Smazání profilové fotografie
     */
    #[Route('/fotografie/smazat', name: 'app_nastaveni_photo_delete', methods: ['POST'])]
    public function deletePhoto(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$this->isCsrfTokenValid('delete-photo', $request->request->get('_token'))) {
            $this->addFlash('error', 'Neplatný CSRF token.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        $photo = $user->getProfilePicture();
        if ($photo) {
            $photoPath = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles/' . $photo;
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
            
            $user->setProfilePicture(null);
            $this->em->flush();
            
            $this->addFlash('success', 'Profilová fotografie byla smazána.');
        }
        
        return $this->redirectToRoute('app_nastaveni_index');
    }

    /**
     * Změna jména
     */
    #[Route('/jmeno', name: 'app_nastaveni_name', methods: ['POST'])]
    public function updateName(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $name = trim($request->request->get('name', ''));
        
        $user->setName($name ?: null);
        $this->em->flush();
        
        $this->addFlash('success', 'Jméno bylo úspěšně změněno.');
        return $this->redirectToRoute('app_nastaveni_index');
    }

    /**
     * Změna hesla
     */
    #[Route('/heslo', name: 'app_nastaveni_password', methods: ['POST'])]
    public function updatePassword(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');
        
        // Validace aktuálního hesla
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Aktuální heslo není správné.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        // Validace nového hesla
        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'Nové heslo musí mít alespoň 6 znaků.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Nová hesla se neshodují.');
            return $this->redirectToRoute('app_nastaveni_index');
        }
        
        // Nastavení nového hesla
        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $this->em->flush();
        
        $this->addFlash('success', 'Heslo bylo úspěšně změněno.');
        return $this->redirectToRoute('app_nastaveni_index');
    }

    /**
     * Seznam uživatelů
     */
    #[Route('/admin/uzivatele', name: 'app_nastaveni_admin_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUsers(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $roleFilter = $request->query->get('role', '');
        $statusFilter = $request->query->get('status', '');

        $qb = $this->userRepository->createFilteredQueryBuilder($search, $roleFilter, $statusFilter);

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('nastaveni/admin/users/index.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'totalUsers' => $this->userRepository->count([]),
            'activeUsers' => $this->userRepository->count(['isActive' => true]),
            'adminUsers' => $this->userRepository->countAdmins(),
        ]);
    }

    /**
     * Detail uživatele
     */
    #[Route('/admin/uzivatele/{id}', name: 'app_nastaveni_admin_user_detail', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserDetail(User $user): Response
    {
        $allZakazky = $this->zakazkaRepository->findBy([], ['name' => 'ASC']);
        
        // Zakázky, ke kterým má uživatel přístup
        $assignedZakazky = $user->getAssignedZakazkas();
        
        // Statistiky docházky
        $totalMinutes = 0;
        foreach ($user->getDochazky() as $d) {
            $totalMinutes += $d->getMinuty();
        }
        
        return $this->render('nastaveni/admin/users/detail.html.twig', [
            'user' => $user,
            'allZakazky' => $allZakazky,
            'assignedZakazky' => $assignedZakazky,
            'totalMinutes' => $totalMinutes,
            'totalDochazky' => count($user->getDochazky()),
        ]);
    }

    /**
     * Vytvoření nového uživatele
     */
    #[Route('/admin/uzivatele/novy', name: 'app_nastaveni_admin_user_new', methods: ['GET', 'POST'], priority: 10)]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserNew(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $user->setIsActive(true);
        
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Kontrola duplicity emailu
            if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('error', 'Uživatel s tímto emailem již existuje.');
                return $this->render('nastaveni/admin/users/new.html.twig', [
                    'form' => $form,
                ]);
            }
            
            // Nastavení hesla
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            
            $this->em->persist($user);
            $this->em->flush();
            
            $this->addFlash('success', 'Uživatel byl vytvořen.');
            return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
        }
        
        return $this->render('nastaveni/admin/users/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Úprava uživatele
     */
    #[Route('/admin/uzivatele/{id}/upravit', name: 'app_nastaveni_admin_user_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserEdit(User $user, Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $originalEmail = $user->getEmail();
        $isCurrentUser = $user === $this->getUser();
        
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Kontrola duplicity emailu (pokud se změnil)
            if ($user->getEmail() !== $originalEmail) {
                if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
                    $this->addFlash('error', 'Uživatel s tímto emailem již existuje.');
                    return $this->render('nastaveni/admin/users/edit.html.twig', [
                        'form' => $form,
                        'user' => $user,
                        'isCurrentUser' => $isCurrentUser,
                    ]);
                }
            }
            
            // admin si nemůže odebrat práva ani se deaktivovat
            if ($isCurrentUser) {
                if (!in_array('ROLE_ADMIN', $user->getRoles())) {
                    $user->setRoles(array_merge($user->getRoles(), ['ROLE_ADMIN']));
                }
                $user->setIsActive(true);
            }
            
            // Heslo pouze pokud bylo zadáno
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }
            
            $this->em->flush();
            
            $this->addFlash('success', 'Uživatel byl upraven.');
            return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
        }
        
        return $this->render('nastaveni/admin/users/edit.html.twig', [
            'form' => $form,
            'user' => $user,
            'isCurrentUser' => $isCurrentUser,
        ]);
    }

    /**
     * Smazání uživatele
     */
    #[Route('/admin/uzivatele/{id}/smazat', name: 'app_nastaveni_admin_user_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserDelete(User $user, Request $request): Response
    {
        // Nelze smazat sám sebe
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Nemůžete smazat sám sebe.');
            return $this->redirectToRoute('app_nastaveni_admin_users');
        }
        
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'Uživatel byl smazán.');
        }
        
        return $this->redirectToRoute('app_nastaveni_admin_users');
    }

    /**
     * Přepnutí aktivního stavu uživatele
     */
    #[Route('/admin/uzivatele/{id}/toggle-active', name: 'app_nastaveni_admin_user_toggle_active', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserToggleActive(User $user, Request $request): JsonResponse
    {
        // Nelze deaktivovat sám sebe
        if ($user === $this->getUser()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Nemůžete deaktivovat sám sebe.',
            ], 400);
        }
        
        $user->setIsActive(!$user->isActive());
        $this->em->flush();
        
        return new JsonResponse([
            'success' => true,
            'isActive' => $user->isActive(),
            'message' => $user->isActive() ? 'Uživatel byl aktivován.' : 'Uživatel byl deaktivován.',
        ]);
    }

    /**
     * Přidání zakázky uživateli
     */
    #[Route('/admin/uzivatele/{id}/zakazka/pridat', name: 'app_nastaveni_admin_user_add_zakazka', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserAddZakazka(User $user, Request $request): Response
    {
        $zakazkaId = $request->request->get('zakazka_id');
        
        if (!$zakazkaId) {
            $this->addFlash('error', 'Vyberte zakázku.');
            return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
        }
        
        $zakazka = $this->zakazkaRepository->find($zakazkaId);
        
        if (!$zakazka) {
            $this->addFlash('error', 'Zakázka nebyla nalezena.');
            return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
        }
        
        // Přidání uživatele do zakázky
        if (!$zakazka->getAssignedUsers()->contains($user)) {
            $zakazka->addAssignedUser($user);
            $this->em->flush();
            $this->addFlash('success', 'Uživatel byl přidán k zakázce "' . $zakazka->getName() . '".');
        } else {
            $this->addFlash('info', 'Uživatel již má přístup k této zakázce.');
        }
        
        return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
    }

    /**
     * Odebrání zakázky uživateli
     */
    #[Route('/admin/uzivatele/{id}/zakazka/{zakazkaId}/odebrat', name: 'app_nastaveni_admin_user_remove_zakazka', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserRemoveZakazka(User $user, int $zakazkaId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('remove-zakazka' . $zakazkaId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Neplatný CSRF token.');
            return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
        }
        
        $zakazka = $this->zakazkaRepository->find($zakazkaId);
        
        if (!$zakazka) {
            $this->addFlash('error', 'Zakázka nebyla nalezena.');
            return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
        }
        
        // Odebrání uživatele ze zakázky
        if ($zakazka->getAssignedUsers()->contains($user)) {
            $zakazka->removeAssignedUser($user);
            $this->em->flush();
            $this->addFlash('success', 'Uživatel byl odebrán ze zakázky "' . $zakazka->getName() . '".');
        }
        
        return $this->redirectToRoute('app_nastaveni_admin_user_detail', ['id' => $user->getId()]);
    }

}
