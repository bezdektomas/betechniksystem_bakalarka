<?php

namespace App\Form;

use App\Entity\Dochazka;
use App\Entity\Zakazka;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class DochazkaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('datum', DateType::class, [
                'label' => 'Datum',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-xl text-slate-700 text-sm focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors duration-200',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Zadejte datum.']),
                ],
            ])
            ->add('zakazka', EntityType::class, [
                'label' => 'Zakázka',
                'class' => Zakazka::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Bez zakázky (obecná práce)',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('z')
                        ->orderBy('z.name', 'ASC');
                },
                'attr' => [
                    'class' => 'tom-select-zakazka w-full px-4 py-2.5 border border-slate-300 rounded-xl text-slate-700 text-sm focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors duration-200',
                ],
            ])
            ->add('hodiny', IntegerType::class, [
                'label' => 'Hodiny',
                'mapped' => false,
                'required' => false,
                'data' => $options['hodiny'] ?? 0,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-xl text-slate-700 text-sm focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors duration-200',
                    'min' => 0,
                    'max' => 24,
                    'placeholder' => '0',
                ],
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0, 'message' => 'Hodiny nemohou být záporné.']),
                    new LessThanOrEqual(['value' => 24, 'message' => 'Maximálně 24 hodin.']),
                ],
            ])
            ->add('minutyInput', IntegerType::class, [
                'label' => 'Minuty',
                'mapped' => false,
                'required' => false,
                'data' => $options['minuty_input'] ?? 0,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-xl text-slate-700 text-sm focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors duration-200',
                    'min' => 0,
                    'max' => 59,
                    'placeholder' => '0',
                ],
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0, 'message' => 'Minuty nemohou být záporné.']),
                    new LessThanOrEqual(['value' => 59, 'message' => 'Maximálně 59 minut.']),
                ],
            ])
            ->add('popis', TextareaType::class, [
                'label' => 'Popis práce',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-2.5 border border-slate-300 rounded-xl text-slate-700 text-sm focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-[rgb(241,97,1)] transition-colors duration-200 resize-none',
                    'rows' => 3,
                    'placeholder' => 'Co jste dělali...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dochazka::class,
            'hodiny' => 0,
            'minuty_input' => 0,
        ]);
    }
}
