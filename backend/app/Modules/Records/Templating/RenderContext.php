<?php

declare(strict_types=1);

namespace App\Modules\Records\Templating;

use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;

/** One rendering of a template (an email, a document) for its viewers, with its memoized values. */
final class RenderContext
{
    /** @var array<string, string> placeholder => text */
    public array $cache = [];

    /** @var array<int, array<string, string>> field access per viewer */
    public array $levels = [];

    public function __construct(
        public readonly ?FormRuntime $rt,
        public readonly ?int $recordId,
        /** @var list<User> */
        public readonly array $viewers,
        public readonly string $locale,
        public readonly ?string $recipientName,
        /** Who produced it (the person who asked for the document or ran the action), for `{{ @user }}`. */
        public readonly ?string $actorName = null,
    ) {}
}
