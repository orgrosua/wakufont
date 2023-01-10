<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: FileRepository::class)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['file:read'])]
    #[ORM\Column(length: 255)]
    private ?string $style = null;

    #[Groups(['file:read'])]
    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $weight = null;

    #[Groups(['file:read'])]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $url = null;

    #[Groups(['file:read'])]
    #[ORM\Column(length: 255)]
    private ?string $format = null;

    #[Groups(['file:read'])]
    #[ORM\Column(type: Types::ARRAY)]
    private array $subsets = [];

    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Font $font = null;

    #[Groups(['file:read'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $unicodeRange = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function setStyle(string $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getSubsets(): array
    {
        return $this->subsets;
    }

    public function setSubsets(array $subsets): self
    {
        $this->subsets = $subsets;

        return $this;
    }

    public function getFont(): ?Font
    {
        return $this->font;
    }

    public function setFont(?Font $font): self
    {
        $this->font = $font;

        return $this;
    }

    public function getUnicodeRange(): ?string
    {
        return $this->unicodeRange;
    }

    public function setUnicodeRange(?string $unicodeRange): self
    {
        $this->unicodeRange = $unicodeRange;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
