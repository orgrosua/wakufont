<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FontRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: FontRepository::class)]
class Font
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['font:read'])]
    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[Groups(['font:read'])]
    #[ORM\Column(length: 255)]
    private ?string $family = null;

    #[Groups(['font:read'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[Groups(['font:read'])]
    #[ORM\Column(length: 255)]
    private ?string $category = null;

    #[Groups(['font:read'])]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $modifiedAt = null;

    #[Groups(['font:read'])]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $addedAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[Groups(['font:read'])]
    #[ORM\Column(type: Types::ARRAY, nullable: true)]
    private array $designers = [];

    #[Groups(['font:read'])]
    #[ORM\Column(type: Types::ARRAY)]
    private array $subsets = [];

    #[Groups(['font:read'])]
    #[ORM\Column(type: Types::ARRAY)]
    private array $variants = [];

    #[Groups(['font:read'])]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $axes = [];

    #[Groups(['font:read'])]
    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $popularity = null;

    #[ORM\OneToMany(mappedBy: 'font', targetEntity: File::class, orphanRemoval: true)]
    private Collection $files;

    #[Groups(['font:read'])]
    #[ORM\Column(length: 12, nullable: true)]
    private ?string $version = null;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getFamily(): ?string
    {
        return $this->family;
    }

    public function setFamily(string $family): self
    {
        $this->family = $family;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getModifiedAt(): ?\DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(\DateTimeImmutable $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getAddedAt(): ?\DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function setAddedAt(\DateTimeImmutable $addedAt): self
    {
        $this->addedAt = $addedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDesigners(): array
    {
        return $this->designers;
    }

    public function setDesigners(?array $designers): self
    {
        $this->designers = $designers;

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

    public function getVariants(): array
    {
        return $this->variants;
    }

    public function setVariants(array $variants): self
    {
        $this->variants = $variants;

        return $this;
    }

    public function getAxes(): array
    {
        return $this->axes;
    }

    public function setAxes(?array $axes): self
    {
        $this->axes = $axes;

        return $this;
    }

    public function getPopularity(): ?int
    {
        return $this->popularity;
    }

    public function setPopularity(int $popularity): self
    {
        $this->popularity = $popularity;

        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (! $this->files->contains($file)) {
            $this->files->add($file);
            $file->setFont($this);
        }

        return $this;
    }

    public function removeFile(File $file): self
    {
        if ($this->files->removeElement($file)) {
            // set the owning side to null (unless already changed)
            if ($file->getFont() === $this) {
                $file->setFont(null);
            }
        }

        return $this;
    }

    #[Groups(['font:read'])]
    public function getIsSupportVariable(): bool
    {
        return ! empty($this->getAxes());
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }
}
