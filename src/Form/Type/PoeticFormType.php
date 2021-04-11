<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Repository\LanguageRepository;
use App\Entity\PoeticForm;
use App\Entity\Language;

class PoeticFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$locale = $options["locale"];

        $builder
            ->add('title', TextType::class, array(
                'constraints' => new Assert\NotBlank(), "label" => "admin.poeticForm.Title"
            ))
			->add('text', TextareaType::class, array(
                'constraints' => new Assert\NotBlank(), "label" => "admin.poeticForm.Text", 'attr' => array('class' => 'redactor')
            ))
			->add('typeContentPoem', ChoiceType::class, array("label" => "admin.poeticForm.KindOfContent", "required" => true, "multiple" => false, "expanded" => false, 'choices' => ['admin.poeticForm.Image' => PoeticForm::IMAGETYPE, 'admin.poeticForm.Text' => PoeticForm::TEXTTYPE]
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
			->add('fileManagement', FileManagementSelectorType::class, ["label" => "admin.poeticForm.Image", "required" => true, "folder" => PoeticForm::FOLDER])
            ->add('save', SubmitType::class, array('label' => 'admin.main.Save', 'attr' => array('class' => 'btn btn-success')))
			;
    }

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			"data_class" => PoeticForm::class,
			"locale" => null
		));
	}

    public function getName()
    {
        return 'poeticform';
    }
}