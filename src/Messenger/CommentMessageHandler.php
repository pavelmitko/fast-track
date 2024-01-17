<?php

namespace App\Messenger;

use App\Repository\CommentRepository;
use App\Services\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class CommentMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SpamChecker $spamChecker,
        private readonly MailerInterface $mailer,
        private readonly MessageBusInterface $bus,
        #[Target(name: 'commentStateMachine')]
        private readonly WorkflowInterface $workflow,
        private readonly CommentRepository $repo,
        #[Autowire('%admin_email%')]
        private readonly string $adminEmail,
    )
    {
    }

    public function __invoke(CommentMessage $message): void
    {
        $comment = $this->repo->find($message->id);
        if (!$comment) {
            return;
        }

        if ($this->workflow->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->context);
            $transition = match ($score) {
                2 => 'reject_spam',
                1 => 'might_be_spam',
                default => 'accept',
            };
            $this->workflow->apply($comment, $transition);
            $this->em->flush();
            $this->bus->dispatch($message);

            return;
        }

        if ($this->workflow->can($comment, 'publish')
            || $this->workflow->can($comment, 'publish_ham')
        ) {
            $this->mailer->send(
                (new NotificationEmail())
                    ->subject('New comment posted')
                    ->htmlTemplate('emails/comment_notification.html.twig')
                    ->from($this->adminEmail)
                    ->to($this->adminEmail)
                    ->context(['comment' => $comment]),
            );
        }
    }
}