<?php

namespace App\Services;

use Illuminate\Support\HtmlString;

final class SchoolPageDocument
{
    private const CHILDREN = [
        'doc' => ['paragraph', 'heading', 'bulletList', 'orderedList'],
        'paragraph' => ['text', 'hardBreak'],
        'heading' => ['text', 'hardBreak'],
        'bulletList' => ['listItem'],
        'orderedList' => ['listItem'],
        'listItem' => ['paragraph', 'bulletList', 'orderedList'],
    ];

    public static function valid(mixed $document): bool
    {
        return self::validNode($document, ['doc'], 0);
    }

    private static function validNode(mixed $node, array $allowed, int $depth): bool
    {
        if ($depth > 12 || ! is_array($node) || ! in_array($node['type'] ?? null, $allowed, true)) {
            return false;
        }

        $type = $node['type'];
        $children = $node['content'] ?? [];
        if (! is_array($children) || ! array_is_list($children)) {
            return false;
        }

        if ($type === 'text') {
            $marks = $node['marks'] ?? [];
            if ($children !== [] || ! is_string($node['text'] ?? null) || ! is_array($marks) || ! array_is_list($marks) || count($marks) > 3) {
                return false;
            }
            foreach ($marks as $mark) {
                if (! is_array($mark) || ! in_array($mark['type'] ?? null, ['bold', 'italic', 'link'], true)
                    || ($mark['type'] === 'link' && ! self::safeLink($mark['attrs']['href'] ?? null))) {
                    return false;
                }
            }

            return true;
        }

        if ($type === 'heading' && ! in_array($node['attrs']['level'] ?? null, [2, 3], true)) {
            return false;
        }
        if ($type === 'orderedList' && (! is_int($start = $node['attrs']['start'] ?? 1) || $start < 1)) {
            return false;
        }
        foreach ($children as $child) {
            if (! self::validNode($child, self::CHILDREN[$type] ?? [], $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    public static function safeLink(mixed $href): bool
    {
        return is_string($href) && preg_match('~^https?://~i', $href) && filter_var($href, FILTER_VALIDATE_URL) !== false;
    }

    public static function inline(array $children): HtmlString
    {
        $html = '';
        foreach ($children as $node) {
            if ($node['type'] === 'hardBreak') {
                $html .= '<br>';
                continue;
            }

            $text = e($node['text']);
            foreach (array_reverse($node['marks'] ?? []) as $mark) {
                $text = match ($mark['type']) {
                    'bold' => '<strong>'.$text.'</strong>',
                    'italic' => '<em>'.$text.'</em>',
                    'link' => '<a href="'.e($mark['attrs']['href']).'">'.$text.'</a>',
                };
            }
            $html .= $text;
        }

        return new HtmlString($html);
    }

    public static function appearance(?object $site): array
    {
        $color = fn ($value, $fallback) => is_string($value) && preg_match('/^#[a-f0-9]{6}$/iD', $value) ? $value : $fallback;
        $font = $site->font_family ?? null;

        return [
            'primary' => $color($site->primary_color ?? null, '#18332d'),
            'secondary' => $color($site->secondary_color ?? null, '#145b44'),
            'font' => in_array($font, ['Instrument Sans', 'Inter', 'DM Sans', 'Source Sans 3', 'system-ui'], true) ? $font : 'system-ui',
        ];
    }
}
