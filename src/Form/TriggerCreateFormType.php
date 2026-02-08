<?php

namespace App\Form;

use App\Entity\Book;
use App\Entity\Trigger;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TriggerCreateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('books', EntityType::class, [
                'class' => Book::class,
                'choice_value' => 'uuid',
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('phones', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => "Un numéro par ligne\n+33 6 12 34 56 78\n+33 6 98 76 54 32",
                ],
            ])
            ->add('saveAsBook', CheckboxType::class, [
                'required' => false,
            ])
            ->add('bookName', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du répertoire',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'SMS' => Trigger::TYPE_SMS,
                    'Appel' => Trigger::TYPE_CALL,
                ],
                'expanded' => true,
                'data' => Trigger::TYPE_SMS,
            ])
            ->add('content', TextareaType::class, [
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Votre message…',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
