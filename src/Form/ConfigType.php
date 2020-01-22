<?php
/*
 * Copyright (c) 2024 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Form;

use App\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Maps our Config entity and its various JSONs into a form type
 */
final class ConfigType extends AbstractType implements DataMapperInterface
{
     public function configureOptions(OptionsResolver $resolver): void
     {
         $resolver->setDefaults([
             'data_class' => 'App\Entity\Config',
         ]);
     }

     /**
     * @inheritDoc
     */
    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        if ($viewData === null) {
            return;
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        foreach ($viewData as $config) {
            if (!$config instanceof Config) {
                throw new UnexpectedTypeException($viewData, Config::class);
            }
            $forms[$config->getNamespace()]->setData([$config->getValue()]);
        }
    }

    /**
     * @inheritDoc
     */
    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        foreach ($viewData as $config) {
            $config->setValue($forms[$config->getNamespace()]->getData()[0]);
        }
    }
}