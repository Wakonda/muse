<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ImagePoemGeneratorType extends ImageGeneratorType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		parent::buildForm($builder, $options);
        
		$builder
			->add('text', TextareaType::class, ["label" => "admin.imageGenerator.Text", "required" => false]);
    }

    public function getName()
    {
        return 'image_generator';
    }
}