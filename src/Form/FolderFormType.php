<?php

namespace App\Form;

use App\Entity\Folder;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class FolderFormType extends AbstractType
{
    /** @var list<string> */
    private const ACCESS_ROLE_CHOICES = [
        User::ROLE_ADMIN,
        User::ROLE_PARTNER,
        User::ROLE_USER,
        'ROLE_CTIA', // ancien nom (dossiers existants) — affiché comme Partenaire
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du dossier',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Documents, Ressources, Paramètres...'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom du dossier est requis',
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'max' => 255,
                        'maxMessage' => 'Le nom ne doit pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Auto-généré si vide'
                ],
                'help' => 'Identifiant unique pour les URLs (ex: documents-importants)'
            ])
            ->add('parent', EntityType::class, [
                'class' => Folder::class,
                'choice_label' => 'name',
                'label' => 'Dossier parent (optionnel)',
                'required' => false,
                'placeholder' => 'Aucun (dossier racine)',
                'attr' => ['class' => 'form-control'],
                'help' => 'Laissez vide pour créer un dossier à la racine'
            ])
            ->add('requiredRoles', ChoiceType::class, [
                'label' => 'Rôles requis pour l\'accès',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => self::ACCESS_ROLE_CHOICES,
                'choice_label' => static fn (string $role): string => match ($role) {
                    User::ROLE_ADMIN => 'Administrateur',
                    User::ROLE_PARTNER, 'ROLE_CTIA' => 'Partenaire',
                    User::ROLE_USER => 'Utilisateur',
                    default => $role,
                },
                'choice_value' => static fn (?string $role): string => $role ?? '',
                'invalid_message' => 'Le rôle « {{ value }} » n\'est pas valide.',
                'help' => 'Laissez tout décoché pour rendre le dossier accessible à tous les comptes connectés. Cochez un ou plusieurs rôles pour restreindre l\'accès.',
                'constraints' => [
                    new Choice([
                        'choices' => self::ACCESS_ROLE_CHOICES,
                        'multiple' => true,
                        'message' => 'Un ou plusieurs rôles sélectionnés ne sont pas valides.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Folder::class,
        ]);
    }
}
