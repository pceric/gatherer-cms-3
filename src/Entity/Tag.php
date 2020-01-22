<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;

    #[Gedmo\Slug(fields: ['name'])]
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private ?string $slug;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private ?string $name;

    #[ORM\ManyToMany(targetEntity: Blog::class, mappedBy: 'tags', fetch: 'EXTRA_LAZY')]
    #[OrderBy(['pubdate' => 'DESC'])]
    private Collection $blogs;

    #[ORM\ManyToMany(targetEntity: Reader::class, mappedBy: 'tags', fetch: 'EXTRA_LAZY')]
    #[OrderBy(['pubdate' => 'DESC'])]
    private Collection $readers;

    public function __construct()
    {
        $this->blogs = new ArrayCollection();
        $this->readers = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|Blog[]
     */
    public function getBlogs($onlyPublished = false): Collection
    {
        $criteria = Criteria::create(true);
        if ($onlyPublished) {
            $criteria->where(Criteria::expr()->eq('published', true));
        }
        $criteria->orderBy(['pubdate' => Order::Descending]);
        return $this->blogs->matching($criteria);
    }

    public function addBlog(self $blog): static
    {
        if (!$this->blogs->contains($blog)) {
            $this->blogs[] = $blog;
        }

        return $this;
    }

    public function removeBlog(self $blog): static
    {
        $this->blogs->removeElement($blog);

        return $this;
    }

    /**
     * @return Collection|Reader[]
     */
    public function getReaders(): Collection
    {
        $criteria = Criteria::create(true);
        $criteria->orderBy(['pubdate' => Order::Descending]);
        return $this->readers->matching($criteria);
    }

    public function addReader(self $reader): static
    {
        if (!$this->readers->contains($reader)) {
            $this->readers[] = $reader;
        }

        return $this;
    }

    public function removeReader(self $reader): static
    {
        $this->readers->removeElement($reader);

        return $this;
    }
}
