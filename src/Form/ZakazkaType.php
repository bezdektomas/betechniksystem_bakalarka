<?php

namespace App\Form;

use App\Entity\Status;
use App\Entity\User;
use App\Entity\Zakazka;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ZakazkaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Název zakázky',
                'attr' => [
                    'placeholder' => 'Např. Rekonstrukce kuchyně - Novák',
                    'class' => 'block w-full px-4 py-3 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Zadejte název zakázky.']),
                    new Length(['min' => 3, 'max' => 255, 'minMessage' => 'Název musí mít alespoň {{ limit }} znaky.']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Popis',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Podrobný popis zakázky...',
                    'rows' => 4,
                    'class' => 'block w-full px-4 py-3 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Předpokládaná cena (Kč)',
                'required' => false,
                'html5' => true,
                'attr' => [
                    'placeholder' => '0',
                    'min' => 0,
                    'step' => '0.01',
                ],
            ])
            ->add('status', EntityType::class, [
                'label' => 'Status',
                'class' => Status::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')->orderBy('s.sortOrder', 'ASC');
                },
                'attr' => [
                    'class' => 'block w-full px-4 py-3 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all bg-white',
                ],
            ])
            ->add('realizace', DateType::class, [
                'label' => 'Datum realizace',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'block w-full px-4 py-3 border border-slate-300 rounded-xl text-slate-900 focus:outline-none focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Odkaz (URL)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://...',
                    'class' => 'block w-full px-4 py-3 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Poznámky',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Interní poznámky...',
                    'rows' => 3,
                    'class' => 'block w-full px-4 py-3 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                ],
            ])
            ->add('assignedUsers', EntityType::class, [
                'label' => 'Přiřazení uživatelé',
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getName() ?? $user->getEmail();
                },
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('u.name', 'ASC');
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'attr' => [
                    'class' => 'space-y-2',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Zakazka::class,
        ]);
    }
}
