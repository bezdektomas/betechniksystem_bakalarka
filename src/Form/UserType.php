<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'uzivatel@example.com',
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Zadejte email.']),
                    new Email(['message' => 'Zadejte platný email.']),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'Jméno',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Jan Novák',
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                ],
                'help' => 'Pokud není zadáno, použije se část emailu před @',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'Nové heslo' : 'Heslo',
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => [
                    'placeholder' => $isEdit ? 'Ponechte prázdné pro zachování stávajícího' : 'Minimálně 6 znaků',
                    'class' => 'w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-[rgb(241,97,1)] focus:border-transparent transition-all',
                    'autocomplete' => 'new-password',
                ],
                'help' => $isEdit ? 'Vyplňte pouze pokud chcete heslo změnit (min. 6 znaků)' : null,
                'constraints' => $isEdit ? [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Heslo musí mít alespoň {{ limit }} znaků.',
                    ]),
                ] : [
                    new NotBlank(['message' => 'Zadejte heslo.']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Heslo musí mít alespoň {{ limit }} znaků.',
                    ]),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Administrátor' => 'ROLE_ADMIN',
                ],
                'expanded' => true,
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'space-y-3',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Aktivní účet',
                'required' => false,
                'help' => 'Uživatel se může přihlásit do systému',
                'attr' => [
                    'class' => 'w-5 h-5 text-[rgb(241,97,1)] border-slate-300 rounded focus:ring-[rgb(241,97,1)]',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
