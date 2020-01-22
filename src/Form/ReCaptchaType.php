<?php
/*
 * Copyright (c) 2024 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\SubmitButtonTypeInterface;

class ReCaptchaType extends AbstractType implements SubmitButtonTypeInterface
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['class'] = $view->vars['attr']['class'] ?? 'btn-primary g-recaptcha';
        $view->vars['attr']['data-sitekey'] = $view->vars['attr']['data-sitekey'] ?? '';
        $view->vars['attr']['data-callback'] = $view->vars['attr']['data-callback'] ?? 'onSubmit';
        $view->vars['attr']['data-action'] = $view->vars['attr']['data-action'] ?? 'submit';
    }

    public function getParent(): string
    {
        return SubmitType::class;
    }
}
