<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentType;
use App\Messenger\CommentMessage;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class ConferenceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    )
    {

    }

    #[Route('/', name: 'home')]
    #[Template('conference/index.html.twig')]
    public function index(
        ConferenceRepository $repo,
    ): array
    {
        return ['conferences' => $repo->findAll()];
    }

    #[Route('/conference/{slug}', name: 'conference')]
    #[Template('conference/show.html.twig')]
    public function show(
        Conference $conference,
        CommentRepository $repo,
        Request $request,
        #[MapQueryParameter(filter: FILTER_VALIDATE_INT, options: ['min_range' => 0])]
        int $offset = 0,
    ): array|RedirectResponse
    {
        $size = 2;
        $paginator = $repo->getCommentPaginator($conference, $offset, $size);

        $comment = new Comment();
        $comment->setConference($conference);
        $form = $this->createForm(CommentType::class, $comment);

        $form->add('Comment', SubmitType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($comment);
            $this->em->flush();

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referer'),
                'permalink' => $request->getUri(),
            ];
            $this->bus->dispatch(new CommentMessage($comment->getId(), $context));

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }

        return [
            'conference' => $conference,
            'comments' => $paginator,
            'previous' => $offset - $size,
            'next' => \min(\count($paginator), $offset + $size),
            'form' => $form,
        ];
    }
}
