<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Repository\LanguageRepository;

use App\Entity\Language;
use App\Entity\Advertising;

class AdvertisingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$locale = $options["locale"];

        $builder
            ->add('title', TextType::class, array(
                'constraints' => new Assert\NotBlank(), "label" => "admin.advertising.Title"
            ))
			->add('text', TextareaType::class, array(
                'required' => false, "label" => "admin.advertising.Text", 'attr' => array('class' => 'redactor')
            ))
			->add('width', IntegerType::class, array("label" => "admin.advertising.Width", "required" => true, 'constraints' => new Assert\NotBlank()))
			->add('height', IntegerType::class, array("label" => "admin.advertising.Height", "required" => true, 'constraints' => new Assert\NotBlank()))
			->add('language', EntityType::class, array(
				'label' => 'admin.form.Language', 
				'class' => Language::class,
				'query_builder' => function (LanguageRepository $er) use ($locale) {
					return $er->findAllForChoice($locale);
				},
				'multiple' => false,
				'required' => false,
				'expanded' => false,
				'placeholder' => 'main.field.ChooseAnOption'
			))
            ->add('save', SubmitType::class, array('label' => 'admin.main.Save', 'attr' => array('class' => 'btn btn-success')))
			;
    }

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
			"data_class" => Advertising::class,
			"locale" => null
		]);
	}
	
    public function getName()
    {
        return 'advertising';
    }
}