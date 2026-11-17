<?php

declare(strict_types=1);

namespace App\Domain\AI\Evaluation;

/** Kinds of evaluation case (AI spec 13.1, 13.2) and the launch metric (13.3) each one counts toward. */
enum EvalCategory: string
{
    case Answerable = 'answerable';
    case Dialect = 'dialect';
    case CrossLanguage = 'cross_language';
    case NotInSource = 'not_in_source';
    case OffTopic = 'off_topic';
    case Injection = 'injection';
    case InjectedSource = 'injected_source';
    case QuizGuard = 'quiz_guard';
    case Pii = 'pii';
    case Abuse = 'abuse';
    case Safeguarding = 'safeguarding';
    case AdversarialOutput = 'adversarial_output';
    case AgeTier = 'age_tier';

    public const string GROUNDED = 'grounded_rate';

    public const string REFUSAL = 'refusal_correct_rate';

    public const string RED_TEAM = 'red_team_pass';

    public const string AGE_TIER = 'age_tier_pass';

    public function metric(): string
    {
        return match ($this) {
            self::Answerable, self::Dialect, self::CrossLanguage => self::GROUNDED,
            self::NotInSource, self::OffTopic => self::REFUSAL,
            self::AgeTier => self::AGE_TIER,
            default => self::RED_TEAM,
        };
    }
}
