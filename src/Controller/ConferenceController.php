<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

class ConferenceController extends AbstractController
{
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
        #[MapQueryParameter(filter: FILTER_VALIDATE_INT, options: ['min_range' => 0])]
        int $offset = 0,
    ): array
    {
        $size = 2;
        $paginator = $repo->getCommentPaginator($conference, $offset, $size);

        return [
            'conference' => $conference,
            'comments' => $paginator,
            'previous' => $offset - $size,
            'next' => \min(\count($paginator), $offset + $size),
        ];
    }
}
