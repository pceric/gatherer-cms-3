<?php
/*
 * Copyright (c) 2024 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Form;

use App\Entity\Tag;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class TagTransformer implements DataTransformerInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @inheritDoc
     */
    public function transform($value): string
    {
        $tag_array = [];
        foreach ($value as $t) {
            $tag_array[] = $t->getName();
        }
        return implode(',', $tag_array);
    }

    /**
     * @inheritDoc
     */
    public function reverseTransform($value): ArrayCollection
    {
        $tagCollection = new ArrayCollection();
        foreach (explode(',', $value) as $name) {
            if (empty($name)) {
                continue;
            }

            if (mb_strlen($name) > 32) {
                throw new TransformationFailedException('Tag cannot be longer than 32 chars');
            }

            $t = $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => trim($name)]);
            if ($t == null) {
                $t = new Tag();
                $t->setName(trim($name));
            }
            $tagCollection->add($t);
        }
        return $tagCollection;
    }
}