<?php

declare(strict_types=1);

namespace App\Domain\AI\Guards;

use App\Domain\AI\Assistant\TextNormalizer;

/**
 * AI spec 8.1: questions that try to change the assistant's instructions get the scope reminder and no model call. The prompt
 * already ignores such text, so this is a cheap first line, not the only one. Patterns run on normalised text.
 */
final class InjectionHeuristics
{
    private const array PATTERNS = [
        '/\b(ignore|disregard|forget|override|bypass)\b.{0,30}\b(instructions?|rules?|prompts?|guidelines?|restrictions?|previous|above)\b/u',
        '/\b(reveal|show|print|repeat|leak|tell me)\b.{0,25}\b(system prompt|your prompt|your instructions|your rules|hidden prompt)\b/u',
        '/\bsystem prompt\b/u',
        '/\byou are now\b/u',
        '/\bpretend (to be|you are|you re)\b/u',
        '/\bact as (if you|a different|another|an? (ai|assistant|bot|teacher|expert|hacker|human|dan|unrestricted)|my|dan)\b/u',
        '/\b(developer|god|dan) mode\b|\bjailbreak\b/u',
        '/(تجاهل|تجاهلي|انس|انسي|اهمل|تخطي).{0,30}(التعليمات|القواعد|الاوامر|ما سبق|السابق|تعليماتك)/u',
        '/(اكشف|اظهر|اعرض|اطبع|كرر).{0,25}(التعليمات|تعليماتك|قواعدك)/u',
        '/(انت الان|تصرف ك|تظاهر ب|تظاهر انك|اعتبر نفسك)/u',
        '/وضع المطور/u',
    ];

    public function detects(string $text): bool
    {
        $normalized = TextNormalizer::normalize($text);
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
