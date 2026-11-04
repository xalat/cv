<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Cv;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/** @implements ProviderInterface<Cv|Response> */
final class CvItemProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private readonly ProviderInterface $itemProvider,
        private readonly Environment $twig,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Cv|Response|null
    {
        $cv = $this->itemProvider->provide($operation, $uriVariables, $context);
        if (!$cv instanceof Cv || ($context['request'] ?? null)?->getRequestFormat() !== 'html') {
            return $cv;
        }

        return new Response($this->twig->render('cv/show.html.twig', ['cv' => $cv]), headers: [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Vary' => 'Accept',
        ]);
    }
}
