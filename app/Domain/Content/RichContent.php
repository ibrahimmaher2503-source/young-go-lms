<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Domain\Media\SignedFileUrl;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * The one place rich lesson content is made safe (ED-003, ED-031). Content is stored as editor JSON (Tiptap node
 * format) and is never trusted: clean() rebuilds it from an allowlist of blocks, marks and attributes, and html()
 * renders only that rebuilt tree with every value escaped. Raw HTML, scripts, event handlers, unsafe links and foreign
 * frames cannot survive because there is no code path that passes them through.
 */
final class RichContent
{
    public const int MAX_BYTES = 200000;

    private const int MAX_DEPTH = 8;

    private const int MAX_TEXT = 20000;

    private const array CALLOUT_TONES = ['info', 'tip', 'warning'];

    /**
     * @return array{type: string, content: list<array<string, mixed>>}
     *
     * @throws ValidationException when the document is too large or not a document
     */
    public static function clean(mixed $document): array
    {
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
            throw ValidationException::withMessages(['content' => __('content.invalid')]);
        }
        if (strlen((string) json_encode($document)) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['content' => __('content.too_large')]);
        }

        return ['type' => 'doc', 'content' => self::blocks((array) ($document['content'] ?? []), 0)];
    }

    /** @param  array<string, mixed>|null  $document  already cleaned or raw: it is cleaned again before rendering */
    public static function html(?array $document, ?Lesson $lesson = null, ?User $viewer = null): HtmlString
    {
        if ($document === null || $document === []) {
            return new HtmlString('');
        }

        try {
            $clean = self::clean($document);
        } catch (ValidationException) {
            return new HtmlString('');
        }

        return new HtmlString(implode("\n", array_map(fn (array $block): string => self::render($block, $lesson, $viewer), $clean['content'])));
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function blocks(array $nodes, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $out = [];
        foreach ($nodes as $node) {
            if (is_array($node) && ($clean = self::block($node, $depth)) !== null) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function block(array $node, int $depth): ?array
    {
        $type = (string) ($node['type'] ?? '');
        $children = (array) ($node['content'] ?? []);

        return match ($type) {
            'paragraph' => ['type' => 'paragraph', 'content' => self::inline($children)],
            'heading' => ['type' => 'heading', 'attrs' => ['level' => min(4, max(2, (int) ($node['attrs']['level'] ?? 2)))], 'content' => self::inline($children)],
            'bulletList', 'orderedList' => ['type' => $type, 'content' => array_values(array_filter(self::blocks($children, $depth + 1), static fn (array $b): bool => $b['type'] === 'listItem'))],
            'listItem' => ['type' => 'listItem', 'content' => self::blocks($children, $depth + 1)],
            'blockquote' => ['type' => 'blockquote', 'content' => self::blocks($children, $depth + 1)],
            'callout' => ['type' => 'callout', 'attrs' => ['tone' => in_array($node['attrs']['tone'] ?? null, self::CALLOUT_TONES, true) ? $node['attrs']['tone'] : 'info'], 'content' => self::blocks($children, $depth + 1)],
            'horizontalRule' => ['type' => 'horizontalRule'],
            'table' => ['type' => 'table', 'content' => array_values(array_filter(self::blocks($children, $depth + 1), static fn (array $b): bool => $b['type'] === 'tableRow'))],
            'tableRow' => ['type' => 'tableRow', 'content' => array_values(array_filter(self::blocks($children, $depth + 1), static fn (array $b): bool => in_array($b['type'], ['tableHeader', 'tableCell'], true)))],
            'tableHeader', 'tableCell' => ['type' => $type, 'content' => self::blocks($children, $depth + 1)],
            'image' => ($id = (int) ($node['attrs']['resource_id'] ?? 0)) > 0 ? ['type' => 'image', 'attrs' => ['resource_id' => $id, 'alt' => mb_substr(trim((string) ($node['attrs']['alt'] ?? '')), 0, 200)]] : null,
            'embed' => ($url = self::embedUrl((string) ($node['attrs']['url'] ?? ''))) !== null ? ['type' => 'embed', 'attrs' => ['url' => $url]] : null,
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function inline(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'hardBreak') {
                $out[] = ['type' => 'hardBreak'];
            } elseif (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null) && $node['text'] !== '') {
                $marks = [];
                foreach ((array) ($node['marks'] ?? []) as $mark) {
                    $kind = is_array($mark) ? ($mark['type'] ?? null) : null;
                    if ($kind === 'bold' || $kind === 'italic') {
                        $marks[] = ['type' => $kind];
                    } elseif ($kind === 'link' && ($href = self::linkUrl((string) ($mark['attrs']['href'] ?? ''))) !== null) {
                        $marks[] = ['type' => 'link', 'attrs' => ['href' => $href]];
                    }
                }
                $out[] = ['type' => 'text', 'text' => mb_substr($node['text'], 0, self::MAX_TEXT), 'marks' => $marks];
            }
        }

        return $out;
    }

    /** Only http(s) and mailto links survive; javascript:, data:, vbscript: and relative tricks are dropped. */
    private static function linkUrl(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('/[\x00-\x20\x7f]/', $url) === 1 || strlen($url) > 2000) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return match ($scheme) {
            'http', 'https' => filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null,
            'mailto' => filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false ? $url : null,
            default => null,
        };
    }

    /** Embeds must be https on an approved host (the media iframe allowlist plus the two video hosts). */
    private static function embedUrl(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('/[\x00-\x20\x7f]/', $url) === 1 || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = array_map('strtolower', array_merge((array) config('media.iframe_allowlist', []), ['www.youtube-nocookie.com', 'player.vimeo.com']));

        return in_array($host, $allowed, true) ? $url : null;
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param  array<string, mixed>  $node */
    private static function render(array $node, ?Lesson $lesson, ?User $viewer): string
    {
        $inner = static fn (): string => implode('', array_map(fn (array $child): string => self::render($child, $lesson, $viewer), (array) ($node['content'] ?? [])));

        return match ($node['type']) {
            'text' => self::renderText($node),
            'hardBreak' => '<br>',
            'paragraph' => '<p dir="auto">'.$inner().'</p>',
            'heading' => '<h'.(int) $node['attrs']['level'].' dir="auto">'.$inner().'</h'.(int) $node['attrs']['level'].'>',
            'bulletList' => '<ul dir="auto">'.$inner().'</ul>',
            'orderedList' => '<ol dir="auto">'.$inner().'</ol>',
            'listItem' => '<li>'.$inner().'</li>',
            'blockquote' => '<blockquote dir="auto">'.$inner().'</blockquote>',
            'callout' => '<aside dir="auto" class="rc-callout rc-callout-'.self::e((string) $node['attrs']['tone']).'" role="note">'.$inner().'</aside>',
            'horizontalRule' => '<hr>',
            'table' => '<div class="rc-table"><table dir="auto"><tbody>'.$inner().'</tbody></table></div>',
            'tableRow' => '<tr>'.$inner().'</tr>',
            'tableHeader' => '<th scope="col">'.$inner().'</th>',
            'tableCell' => '<td>'.$inner().'</td>',
            'image' => self::renderImage($node, $lesson, $viewer),
            'embed' => '<div class="rc-embed"><iframe src="'.self::e((string) $node['attrs']['url']).'" title="'.self::e(__('content.embed')).'" loading="lazy" allowfullscreen sandbox="'.self::e((string) config('media.iframe_sandbox', 'allow-scripts allow-same-origin allow-presentation')).'" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
            default => '',
        };
    }

    /** @param  array<string, mixed>  $node */
    private static function renderText(array $node): string
    {
        $html = self::e((string) $node['text']);
        foreach ((array) $node['marks'] as $mark) {
            $html = match ($mark['type']) {
                'bold' => '<strong>'.$html.'</strong>',
                'italic' => '<em>'.$html.'</em>',
                'link' => '<a href="'.self::e((string) $mark['attrs']['href']).'" target="_blank" rel="noopener noreferrer nofollow">'.$html.'</a>',
                default => $html,
            };
        }

        return $html;
    }

    /** Images come only from the lesson's own image materials, as signed links for the viewer; anything else renders nothing. */
    /** @param  array<string, mixed>  $node */
    private static function renderImage(array $node, ?Lesson $lesson, ?User $viewer): string
    {
        if ($lesson === null || $viewer === null) {
            return '';
        }

        $resource = LessonResource::query()->where('lesson_id', $lesson->getKey())->where('type', LessonResource::TYPE_IMAGE)->find((int) $node['attrs']['resource_id']);
        $url = $resource === null ? null : app(SignedFileUrl::class)->for($viewer, $resource);

        return $url === null ? '' : '<figure class="rc-figure"><img src="'.self::e($url).'" alt="'.self::e((string) $node['attrs']['alt']).'" loading="lazy"></figure>';
    }
}
