<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Repository\CvRepository;
use App\State\CvItemProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            outputFormats: [
                'jsonld' => ['application/ld+json'],
                'json' => ['application/json'],
                'html' => ['text/html'],
            ],
            provider: CvItemProvider::class,
            extraProperties: ['_api_disable_swagger_provider' => true],
        ),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['cv:read']],
    denormalizationContext: ['groups' => ['cv:write']],
    graphQlOperations: [
        new Query(),
        new QueryCollection(),
        new Mutation(name: 'create', security: "is_granted('ROLE_ADMIN')"),
        new Mutation(name: 'update', security: "is_granted('ROLE_ADMIN')"),
        new DeleteMutation(name: 'delete', security: "is_granted('ROLE_ADMIN')"),
    ],
)]
#[ORM\Entity(repositoryClass: CvRepository::class)]
class Cv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cv:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 2, options: ['default' => 'fr'])]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\Choice(choices: ['fr', 'en'])]
    private string $locale = 'fr';

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column]
    #[Groups(['cv:read', 'cv:write'])]
    private array $skills = [];

    #[ORM\Column]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    private array $languages = [];

    #[ORM\Column]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    private array $education = [];

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\Url(protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    private ?string $github = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\Url(protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    private ?string $linkedin = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['cv:read', 'cv:write'])]
    #[Assert\Url(protocols: ['https', 'http'])]
    #[Assert\Length(max: 2048)]
    private ?string $portfolio = null;

    #[ORM\Column]
    #[Groups(['cv:read', 'cv:write'])]
    private array $experiences = [];

    #[ORM\Column]
    #[Groups(['cv:read'])]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getSkills(): array
    {
        return $this->skills;
    }

    public function setSkills(array $skills): static
    {
        $this->skills = $skills;

        return $this;
    }

    public function getExperiences(): array
    {
        return $this->experiences;
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    public function setLanguages(array $languages): static
    {
        $this->languages = $languages;

        return $this;
    }

    public function getEducation(): array
    {
        return $this->education;
    }

    public function setEducation(array $education): static
    {
        $this->education = $education;

        return $this;
    }

    public function getGithub(): ?string
    {
        return $this->github;
    }

    public function setGithub(?string $github): static
    {
        $this->github = $github;

        return $this;
    }

    public function getLinkedin(): ?string
    {
        return $this->linkedin;
    }

    public function setLinkedin(?string $linkedin): static
    {
        $this->linkedin = $linkedin;

        return $this;
    }

    public function getPortfolio(): ?string
    {
        return $this->portfolio;
    }

    public function setPortfolio(?string $portfolio): static
    {
        $this->portfolio = $portfolio;

        return $this;
    }

    public function setExperiences(array $experiences): static
    {
        $this->experiences = $experiences;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
