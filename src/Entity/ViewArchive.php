<?php
/*
 * Copyright (c) 2024 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'v_archive')]
#[ORM\Entity(repositoryClass: 'App\Repository\ViewArchiveRepository', readOnly: true)]
class ViewArchive
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id;

    #[Gedmo\Slug(fields: ['title'])]
    #[ORM\Column(type: 'string', length: 128, unique: true)]
    private ?string $slug;

    #[ORM\Column(type: 'guid', unique: true)]
    private ?string $guid;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $pubdate;

    #[ORM\Column(type: 'string', length: 32)]
    private ?string $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getGuid(): ?string
    {
        return $this->guid;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getPubdate(): ?\DateTimeInterface
    {
        return $this->pubdate;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
}
