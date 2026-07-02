<?php

namespace Articulate\Symfony\Tests\Fixtures\Entity;

use Articulate\Attributes\Entity;
use Articulate\Attributes\Indexes\PrimaryKey;
use Articulate\Attributes\Property;
use Articulate\Symfony\Tests\Fixtures\Repository\BookRepository;

#[Entity(repositoryClass: BookRepository::class)]
final class Book
{
    #[PrimaryKey]
    public ?int $id = null;

    #[Property]
    public string $title;
}
