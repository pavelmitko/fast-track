<?php

namespace App\EntityListener;

use App\Entity\Conference;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsEntityListener(event: Events::prePersist, entity: Conference::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Conference::class)]
class ConferenceEntityListener
{
    public function __construct(
        private readonly SluggerInterface $slugger,
    )
    {
    }

    public function prePersist(Conference $conference, LifecycleEventArgs $event): void
    {
        $this->computeSlug($conference);
    }

    public function preUpdate(Conference $conference, LifecycleEventArgs $event): void
    {
        $this->computeSlug($conference);
    }

    private function computeSlug(Conference $conference): void
    {
        if (!$conference->getSlug() || '-' === $conference->getSlug()) {
            $conference->setSlug((string)$this->slugger->slug((string)$conference)->lower());
        }
    }
}