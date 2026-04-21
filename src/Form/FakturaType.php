<?php

namespace App\Form;

use App\Entity\Faktura;
use App\Entity\StatusFaktura;
use App\Entity\Zakazka;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class FakturaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresa', TextareaType::class, [
                'label' => 'Adresa / Popis',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Adresa nebo popis faktury...',
                ],
            ])
            ->add('cenaBezDph', TextType::class, [
                'label' => 'Částka bez DPH (Kč)',
                'required' => false,
                'attr' => [
                    'placeholder' => '0',
                    'inputmode' => 'decimal',
                ],
                'help' => 'Částka s DPH a bez daně z příjmu se dopočítá automaticky',
            ])
            ->add('datum', DateType::class, [
                'label' => 'Datum faktury',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('poznamka', TextareaType::class, [
                'label' => 'Poznámka',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Interní poznámka...',
                ],
            ])
            ->add('status', EntityType::class, [
                'label' => 'Status',
                'class' => StatusFaktura::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Vyberte status...',
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('s')
                        ->orderBy('s.sortOrder', 'ASC');
                },
            ])
            ->add('zakazka', EntityType::class, [
                'label' => 'Zakázka',
                'class' => Zakazka::class,
                'choice_label' => 'name',
                'required' => true,
                'placeholder' => 'Vyberte zakázku...',
                'query_builder' => function ($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('z')
                        ->orderBy('z.createdAt', 'DESC');
                    
                    // Pokud je definován uživatel a není admin, filtruj zakázky
                    if (isset($options['user']) && $options['user'] && !in_array('ROLE_ADMIN', $options['user']->getRoles())) {
                        $qb->leftJoin('z.assignedUsers', 'au')
                           ->andWhere('z.createdBy = :user OR au = :user')
                           ->setParameter('user', $options['user']);
                    }
                    
                    return $qb;
                },
            ])
            ->add('file', FileType::class, [
                'label' => 'Soubor faktury (PDF)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Nahrajte prosím platný PDF soubor nebo obrázek',
                    ])
                ],
                'attr' => [
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif',
                ],
                'help' => 'Max. velikost: 10 MB. Povolené formáty: PDF, JPG, PNG, GIF',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Faktura::class,
            'user' => null, // Pro filtrování zakázek podle uživatele
        ]);
    }
}
