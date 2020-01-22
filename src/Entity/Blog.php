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
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Repository\BlogRepository')]
class Blog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Gedmo\Slug(fields: ['title'])]
    #[ORM\Column(type: 'string', length: 128, unique: true)]
    private ?string $slug;

    #[ORM\Column(type: Types::GUID, unique: true)]
    private ?string $guid;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title;

    #[ORM\Column(type: 'text')]
    private ?string $content;

    #[ORM\Column(type: 'boolean')]
    private ?bool $sticky;

    #[ORM\Column(type: 'boolean')]
    private ?bool $published;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $pubdate;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $moddate;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'blogs', cascade: ['persist'])]
    private Collection $tags;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'blogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category;

    public function __construct() {
        $this->guid = Uuid::v4();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function getSticky(): ?bool
    {
        return $this->sticky;
    }

    public function setSticky(bool $sticky): static
    {
        $this->sticky = $sticky;

        return $this;
    }

    public function getPublished(): ?bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

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

    public function getAuthor(): string
    {
        return $this->user->getDisplayname() ?? $this->user->getUsername();
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getEntity(): string
    {
        return strtolower(substr(strrchr(static::class, '\\'), 1));
    }
}
