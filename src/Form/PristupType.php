<?php

namespace App\Form;

use App\Entity\Pristup;
use App\Entity\Zakazka;
use App\Repository\ZakazkaRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PristupType extends AbstractType
{
    public function __construct(
        private Security $security,
        private ZakazkaRepository $zakazkaRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $this->security->getUser();
        $isAdmin = $user && in_array('ROLE_ADMIN', $user->getRoles());

        $builder
            ->add('popis', TextareaType::class, [
                'label' => 'Popis',
                'required' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors',
                    'rows' => 3,
                    'placeholder' => 'Např. Admin přístup do WordPress',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-slate-700 mb-1',
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors',
                    'placeholder' => 'https://example.com/wp-admin',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-slate-700 mb-1',
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'Uživatelské jméno',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors',
                    'placeholder' => 'admin',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-slate-700 mb-1',
                ],
            ])
            ->add('password', TextType::class, [
                'label' => 'Heslo',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors',
                    'placeholder' => '••••••••',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-slate-700 mb-1',
                ],
            ]);

        // Zakázka selector jen pokud není předvyplněna
        if (!$options['zakazka']) {
            $builder->add('zakazka', EntityType::class, [
                'class' => Zakazka::class,
                'choice_label' => 'name',
                'label' => 'Zakázka',
                'placeholder' => 'Vyberte zakázku (nepovinné)',
                'required' => false,
                'query_builder' => function (ZakazkaRepository $repository) use ($user, $isAdmin) {
                    $qb = $repository->createQueryBuilder('z')
                        ->orderBy('z.name', 'ASC');
                    
                    if (!$isAdmin) {
                        $qb->leftJoin('z.assignedUsers', 'au')
                           ->where('z.createdBy = :user OR au = :user')
                           ->setParameter('user', $user);
                    }
                    
                    return $qb;
                },
                'attr' => [
                    'class' => 'tom-select w-full',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-medium text-slate-700 mb-1',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Pristup::class,
            'zakazka' => null, // Pre-set zakazka
        ]);
    }
}
