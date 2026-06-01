<?php

namespace App\Form;

use App\Entity\Folder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class FolderFormType extends AbstractType
{
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
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Partenaire' => 'ROLE_PARTNER',
                    'Utilisateur' => 'ROLE_USER',
                ],
                'help' => 'Sélectionnez les rôles autorisés à accéder à ce dossier (laissez vide pour public)',
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
