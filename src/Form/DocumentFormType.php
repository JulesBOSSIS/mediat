<?php

namespace App\Form;

use App\Entity\Document;
use App\Entity\Folder;
use App\Service\VideoService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class DocumentFormType extends AbstractType
{
    private VideoService $videoService;

    public function __construct(VideoService $videoService)
    {
        $this->videoService = $videoService;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du document',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: FPM - Gestion des indus.pdf'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre est requis',
                    ]),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères',
                        'max' => 255,
                        'maxMessage' => 'Le titre ne doit pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('documentType', ChoiceType::class, [
                'label' => 'Type de document',
                'choices' => [
                    'Fichier' => Document::TYPE_FILE,
                    'Lien externe' => Document::TYPE_LINK,
                    'Autre' => Document::TYPE_OTHER,
                ],
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier à télécharger',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'help' => 'Formats acceptés: PDF, DOCX, images, vidéos... (max 50MB)',
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'image/*',
                            'video/*',
                            'text/plain',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier valide',
                    ])
                ],
            ])
            ->add('externalUrl', UrlType::class, [
                'label' => 'URL externe',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://exemple.com'
                ],
                'help' => 'À remplir si le type est "Lien externe"',
                'constraints' => [
                    new Url([
                        'message' => 'L\'URL n\'est pas valide',
                    ]),
                ],
            ])
            ->add('externalVideoUrl', ChoiceType::class, [
                'label' => 'Vidéo de didacticiel',
                'required' => false,
                'placeholder' => 'Sélectionnez une vidéo...',
                'choices' => $this->videoService->getAvailableVideos(),
                'attr' => ['class' => 'form-control'],
                'help' => 'Optionnel: Sélectionnez une vidéo de tutoriel à afficher avec ce document',
            ])
            ->add('folder', EntityType::class, [
                'class' => Folder::class,
                'choice_label' => 'name',
                'label' => 'Dossier parent',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner un dossier',
                    ]),
                ],
            ])
            ->add('position', \Symfony\Component\Form\Extension\Core\Type\IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => [
                    'class' => 'form-control',
                    'min' => '0'
                ],
                'required' => false,
                'help' => 'Plus le numéro est petit, plus haut il apparaîtra'
            ])
        ;

        // Événement pour adapter les champs selon le type de document
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $document = $event->getData();
            $form = $event->getForm();

            if (!$document || !$document->getId()) {
                // Création
                return;
            }

            // Édition : adapter les champs selon le type
            if ($document->getDocumentType() === Document::TYPE_LINK) {
                $form->get('externalUrl')->setData($document->getExternalUrl());
            }
        });

        // Événement POST_SUBMIT pour valider les champs conditionnels
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $document = $event->getData();
            $form = $event->getForm();
            
            if ($document->getDocumentType() === Document::TYPE_FILE) {
                // Vérifier si un fichier a été uploadé
                $uploadedFile = $form->get('file')->getData();
                if (!$uploadedFile) {
                    // Lors de l'édition, c'est OK si pas de nouveau fichier
                    if (!$document->getId() || !$document->getPath()) {
                        $form->get('file')->addError(
                            new \Symfony\Component\Form\FormError('Un fichier est requis pour un document de type "Fichier"')
                        );
                    }
                }
            } elseif ($document->getDocumentType() === Document::TYPE_LINK) {
                if (!$document->getExternalUrl()) {
                    $form->get('externalUrl')->addError(
                        new \Symfony\Component\Form\FormError('Une URL est requise pour un document de type "Lien externe"')
                    );
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}
