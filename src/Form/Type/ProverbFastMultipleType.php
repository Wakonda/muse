<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\Proverb;
use App\Entity\Country;
use App\Entity\Language;
use App\Repository\CountryRepository;
use App\Repository\LanguageRepository;

class ProverbFastMultipleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$locale = $options["locale"];

        $builder
			->add('url', TextType::class, array(
                'constraints' => [new Assert\NotBlank(), new Assert\Url()], 'label' => 'URL', 'mapped' => false, "data" => $options["url"]
            ))
			->add('ipProxy', TextType::class, array(
                'label' => 'admin.proverb.ProxyAddress', 'required' => false, 'mapped' => false, 'constraints' => [new Assert\Regex("#^[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}:[0-9]{2,4}$#")], "data" => $options["ipProxy"]
            ))
			->add('language', EntityType::class, array(
				'label' => 'admin.form.Language',
				'class' => Language::class,
				'query_builder' => function (LanguageRepository $er) use ($locale) {
					return $er->findAllForChoice($locale);
				},
				'multiple' => false,
				'required' => true,
				'expanded' => false,
				'placeholder' => 'main.field.ChooseAnOption',
				'constraints' => new Assert\NotBlank()
			))
			->add('country', EntityType::class, array(
				'label' => 'admin.proverb.Country',
				'class' => Country::class,
				'query_builder' => function (CountryRepository $er) use ($locale) {
					return $er->findAllForChoice($locale);
				},
				'multiple' => false, 
				'expanded' => false,
				'constraints' => array(new Assert\NotBlank()),
				'placeholder' => 'main.field.ChooseAnOption'
			))
            ->add('save', SubmitType::class, array('label' => 'admin.main.Save', 'attr' => array('class' => 'btn btn-success')));
    }

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			"data_class" => Proverb::class,
			"locale" => null,
			"url" => null,
			"ipProxy" => null
		));
	}

    public function getName()
    {
        return 'proverbfastmultiple';
    }
}