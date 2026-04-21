<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('linkify', [$this, 'linkify'], ['is_safe' => ['html']]),
        ];
    }

    public function linkify(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $escaped = preg_replace_callback(
            '~(https?://[^\s<>"\']+)~i',
            static fn($m) => '<a href="' . $m[1] . '" target="_blank" rel="noopener noreferrer"'
                . ' class="underline opacity-80 hover:opacity-100 break-all">' . $m[1] . '</a>',
            $escaped
        );

        return nl2br($escaped);
    }
}
