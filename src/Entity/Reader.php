<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: 'App\Repository\ReaderRepository')]
class Reader
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;

    #[Gedmo\Slug(fields: ['title'])]
    #[ORM\Column(type: 'string', length: 128, unique: true)]
    private ?string $slug;

    #[ORM\Column(type: Types::GUID, unique: true)]
    private ?string $guid;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title;

    #[ORM\Column(type: 'text')]
    private ?string $content;

    #[ORM\Column(type: 'string', length: 2048)]
    private ?string $source;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $annotation;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $pubdate;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $moddate;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'readers', cascade: ['persist'])]
    private Collection $tags;

    public function __construct() {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getGuid(): ?string
    {
        return $this->guid;
    }

    public function setGuid(string $guid): static
    {
        $this->guid = $guid;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getAnnotation(): ?string
    {
        return $this->annotation;
    }

    public function setAnnotation(?string $annotation): static
    {
        $this->annotation = $annotation;

        return $this;
    }

    public function getPubdate(): ?\DateTimeInterface
    {
        return $this->pubdate;
    }

    public function setPubdate(\DateTimeInterface $pubdate): static
    {
        $this->pubdate = $pubdate;

        return $this;
    }

    public function getModdate(): ?\DateTimeInterface
    {
        return $this->moddate;
    }

    public function setModdate(?\DateTimeInterface $moddate): static
    {
        $this->moddate = $moddate;

        return $this;
    }

    /**
     * @return Collection|Tag[]
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getEntity(): string
    {
        return strtolower(substr(strrchr(static::class, '\\'), 1));
    }
}
