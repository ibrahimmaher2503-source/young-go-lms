<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\Assistant\TextNormalizer;
use App\Domain\AI\Contracts\ModerationClassifier;
use App\Domain\AI\Guards\ModerationCategory;

/**
 * Local baseline classifier (no network, no cost): whole-phrase matches on normalised Arabic and English text. It is a floor,
 * not the final classifier: the real adapter replaces it when a provider is approved (O-014). Self-harm is checked first.
 */
final class KeywordModerationClassifier implements ModerationClassifier
{
    private const array PHRASES = [
        'self_harm' => ['kill myself', 'suicide', 'end my life', 'want to die', 'hurt myself', 'cut myself', 'self harm', 'انتحر', 'اقتل نفسي', 'اموت نفسي', 'عايز اموت', 'اريد ان اموت', 'ابغى اموت', 'اؤذي نفسي', 'مش عايز اعيش'],
        'sexual' => ['porn', 'pornography', 'nude', 'naked', 'sexting', 'xxx', 'sex chat', 'سكس', 'اباحي', 'اباحية', 'عاري', 'عارية'],
        'violence' => ['kill you', 'kill him', 'kill her', 'hurt someone', 'make a bomb', 'build a bomb', 'stab', 'اقتلك', 'اقتله', 'اقتلها', 'قنبلة'],
        'abuse' => ['fuck', 'shit', 'bitch', 'asshole', 'idiot', 'stupid', 'moron', 'shut up', 'كسمك', 'شرموطة', 'ابن الكلب', 'يا حمار', 'يا غبي', 'اخرس'],
    ];

    public function classify(string $maskedText): ?ModerationCategory
    {
        $haystack = ' '.TextNormalizer::normalize($maskedText).' ';
        foreach (self::PHRASES as $category => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($haystack, ' '.TextNormalizer::normalize($phrase).' ')) {
                    return ModerationCategory::from($category);
                }
            }
        }

        return null;
    }
}
