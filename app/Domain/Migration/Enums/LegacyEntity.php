<?php

declare(strict_types=1);

namespace App\Domain\Migration\Enums;

enum LegacyEntity: string
{
    case Users = 'users';
    case Categories = 'categories';
    case Courses = 'courses';
    case Sections = 'sections';
    case Lessons = 'lessons';
    case Resources = 'resources';
    case Quizzes = 'quizzes';
    case LiveSessions = 'live_sessions';
    case Enrollments = 'enrollments';
    case Progress = 'progress';
    case QuizResults = 'quiz_results';
    case Orders = 'orders';
    case Reviews = 'reviews';
    case Certificates = 'certificates';
    case Redirects = 'redirects';

    /** @return list<self> */
    public static function ordered(): array
    {
        return self::cases();
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
