<?php

namespace App\Messenger;

class CommentMessage
{
    public function __construct(
        public readonly string $id,
        public readonly array $context = [],
    )
    {
    }
}