<?php

namespace App\Messenger;

use App\Repository\CommentRepository;
use App\Services\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class CommentMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SpamChecker $spamChecker,
        private readonly MessageBusInterface $bus,
        #[Target(name: 'commentStateMachine')]
        private readonly WorkflowInterface $workflow,
        private readonly CommentRepository $repo,
    )
    {
    }

    public function __invoke(CommentMessage $message): void
    {
        $comment = $this->repo->find($message->id);
        if (!$comment) {
            return;
        }

        if (($publish = $this->workflow->can($comment, 'publish'))
            || $this->workflow->can($comment, 'publish_ham')
        ) {
            $this->workflow->apply($comment, $publish ? 'publish' : 'publish_ham');
            $this->em->flush();

            return;
        }

        if (!$this->workflow->can($comment, 'accept')) {
            return;
        }

        $score = $this->spamChecker->getSpamScore($comment, $message->context);
        $transition = match ($score) {
            2 => 'reject_spam',
            1 => 'might_be_spam',
            default => 'accept',
        };
        $this->workflow->apply($comment, $transition);
        $this->em->flush();
        $this->bus->dispatch($message);
    }
}