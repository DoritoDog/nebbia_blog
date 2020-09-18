<?php

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class)
            ->add('perex', TextType::class)
            ->add('introduction_image', FileType::class, [
                'label' => 'Introduction image',
                'mapped' => false,
                'required' => false
            ])
            ->add('body', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Publikovať']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
