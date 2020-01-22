<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Form\Type;

use App\Form\Type\NamedEmailType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class NamedEmailTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }

    public function testRendersAsTextInput(): void
    {
        $view = $this->factory->create(NamedEmailType::class)->createView();

        $this->assertSame('text', $view->vars['type']);
    }

    public function testParentIsEmailType(): void
    {
        $prefixes = $this->factory->create(NamedEmailType::class)->createView()->vars['block_prefixes'];

        $this->assertContains('email', $prefixes);
    }

    /**
     * @dataProvider validEmailProvider
     */
    public function testAcceptsValidFormats(string $value): void
    {
        $form = $this->factory->create(NamedEmailType::class);
        $form->submit($value);

        $this->assertTrue($form->isValid(), sprintf('Expected "%s" to be valid', $value));
    }

    public static function validEmailProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'plain email' => ['user@example.com'];
        yield 'named email' => ['Alice <alice@example.com>'];
        yield 'quoted display name' => ['"Bob Smith" <bob@example.org>'];
        yield 'subdomain email' => ['user@mail.example.com'];
        yield 'named subdomain email' => ['Support <support@mail.example.com>'];
    }

    /**
     * @dataProvider invalidEmailProvider
     */
    public function testRejectsInvalidFormats(string $value): void
    {
        $form = $this->factory->create(NamedEmailType::class);
        $form->submit($value);

        $this->assertFalse($form->isValid(), sprintf('Expected "%s" to be invalid', $value));
    }

    public static function invalidEmailProvider(): iterable
    {
        yield 'no at sign' => ['notanemail'];
        yield 'angle brackets without email' => ['<notanemail>'];
        yield 'name with empty brackets' => ['Name <>'];
        yield 'at sign only' => ['@'];
    }
}
