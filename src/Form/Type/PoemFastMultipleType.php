<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Repository\PoeticFormRepository;
use App\Repository\LanguageRepository;
use App\Repository\CollectionRepository;

use App\Entity\Language;
use App\Entity\Collection;
use App\Entity\PoeticForm;

class PoemFastMultipleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$locale = $options["locale"];

        $builder
			->add('ipProxy', TextType::class, array(
                'label' => 'admin.poem.ProxyAddress', 'required' => false, 'mapped' => false, 'constraints' => [new Assert\Regex("#^[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}:[0-9]{2,4}$#")], "data" => $options["ipProxy"]
            ))

			->add('url', TextType::class, array(
                'constraints' => [new Assert\NotBlank(), new Assert\Url()], 'label' => 'URL', 'mapped' => false, "data" => $options["url"]
            ))

			->add('releasedDate', IntegerType::class, array(
                'label' => 'admin.poem.PublicationDate'
            ))
			
			->add('unknownReleasedDate', CheckboxType::class, array(
                'mapped' => false, 'label' => 'admin.poem.UnknownDate'
            ))
            ->add('biography', BiographySelectorType::class, array(
                'label' => 'admin.poem.Biography',
				'constraints' => new Assert\NotBlank()
            ))
			->add('collection', EntityType::class, array(
				'label' => 'admin.poem.Collection',
				'class' => Collection::class,
				'query_builder' => function (CollectionRepository $er) use ($locale) {
					return $er->findAllForChoice($locale);
				},
				'multiple' => false,
				'required' => false,
				'expanded' => false,
				'placeholder' => 'main.field.ChooseAnOption'
			))
			->add('number', IntegerType::class, array(
				'label' => 'admin.poem.Number',
				'required' => true,
				'mapped' => false
			))
            ->add('poetic_form', EntityType::class, array(
				'label' => 'admin.poem.PoeticForm', 
				'class' => PoeticForm::class,
				'query_builder' => function (PoeticFormRepository $er) use ($locale) {
					return $er->findAllForChoice($locale);
				},
				'multiple' => false,
				'required' => false,
				'expanded' => false,
				'placeholder' => 'main.field.ChooseAnOption'
			))
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
            ->add('save', SubmitType::class, array('label' => 'admin.index.Add', 'attr' => array('class' => 'btn btn-success')));
    }

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			"locale" => null,
			"url" => null,
			"ipProxy" => null
		));
	}

    public function getName()
    {
        return 'poemfastmultiple';
    }
}