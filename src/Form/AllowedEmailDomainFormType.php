<?php

namespace App\Form;

use App\Entity\AllowedEmailDomain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class AllowedEmailDomainFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('domain', TextType::class, [
                'label' => 'Domaine email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'exemple.com',
                ],
                'help' => 'Entrez le domaine sans le @ (ex: gmail.com, exemple.org)',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un domaine',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)*$/i',
                        'message' => 'Le format du domaine n\'est pas valide',
                    ]),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'Désactivez ce domaine pour empêcher temporairement les inscriptions',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AllowedEmailDomain::class,
        ]);
    }
}
