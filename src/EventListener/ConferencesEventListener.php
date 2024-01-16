<?php

namespace App\EventListener;

use App\Repository\ConferenceRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Twig\Environment;

#[AsEventListener(event: 'kernel.controller', method: 'populateConferences')]
final class ConferencesEventListener
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ConferenceRepository $repo,
    )
    {
    }

    public function populateConferences(): void
    {
        $this->twig->addGlobal('conferences', $this->repo->findAll());
    }
}