<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class PoemUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, array(
                'constraints' => new Assert\NotBlank(), 'label' => 'yourPoems.field.Title'
            ))
			->add('text', TextareaType::class, array(
                'constraints' => new Assert\NotBlank(), 'attr' => array('class' => 'redactor'), 'label' => 'yourPoems.field.Text'
            ))
			
            ->add('save', SubmitType::class, array('label' => 'yourPoems.field.Save', 'attr' => array('class' => 'btn btn-success')))
            ->add('draft', SubmitType::class, array('label' => 'yourPoems.field.Draft', 'attr' => array('class' => 'btn btn-primary')));
    }

    public function getName()
    {
        return 'poemuser';
    }
}