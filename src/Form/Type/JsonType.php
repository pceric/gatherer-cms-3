<?php
/*
 * Copyright (c) 2024 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class JsonType extends AbstractType
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['fields'] as $f) {
            foreach($f as $k => $v) {
                $fieldOptions = ['label' => $v['label'], 'help' => isset($v['help']) ? $this->translator->trans($v['help']) : '', 'required' => false];
                if ($v['type'] === 'UrlType') {
                    $fieldOptions['default_protocol'] = null;
                }
                $appType = "App\\Form\\Type\\" . $v['type'];
                $type = class_exists($appType)
                    ? $appType
                    : "Symfony\\Component\\Form\\Extension\\Core\\Type\\" . $v['type'];
                $builder->add(
                    $k,
                    $type,
                    $fieldOptions
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define("fields");
        $resolver->addAllowedTypes("fields", "array");
    }

    /**
     * @inheritDoc
     */
    public function getBlockPrefix(): string
    {
        return 'json';
    }
}
