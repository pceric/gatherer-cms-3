<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Email form type that accepts both plain addresses and RFC 5322 display-name format.
 *
 * Accepts:
 *   - user@example.com
 *   - Display Name <user@example.com>
 *
 * Renders as <input type="text"> so browsers do not reject the display-name syntax,
 * while inheriting all other behaviour from EmailType.
 */
class NamedEmailType extends AbstractType
{
    /**
     * Overrides the HTML input type to "text" so browsers accept "Name <email>" values.
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['type'] = 'text';
    }

    /**
     * Validates that the submitted value is either empty, a plain email address,
     * or an address with a display name in angle brackets.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Regex(
                    pattern: '/^$|^.+<[^@\s]+@[^@\s]+(\.[^@\s]+)*>$|^[^@\s]+@[^@\s]+(\.[^@\s]+)*$/',
                    message: 'Please enter a valid email address.',
                ),
            ],
        ]);
    }

    /** @return class-string */
    public function getParent(): string
    {
        return EmailType::class;
    }
}
