<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class VerifyCodeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => false,
            'required' => true,
            'attr' => [
                'class' => 'form-control form-control-lg text-center',
                'placeholder' => '000 000',
                'autocomplete' => 'one-time-code',
                'inputmode' => 'numeric',
                'maxlength' => '16',
                'autofocus' => true,
            ],
        ]);
    }
}
