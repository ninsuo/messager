<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;

class PhoneFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('phone', TelType::class, [
            'label' => false,
            'required' => true,
            'attr' => [
                'class' => 'form-control',
                'placeholder' => '+33 6 12 34 56 78',
                'autocomplete' => 'tel',
                'autofocus' => true,
            ],
        ]);
    }
}
