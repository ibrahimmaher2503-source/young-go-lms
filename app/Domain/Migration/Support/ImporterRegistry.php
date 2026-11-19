<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use App\Domain\Migration\Contracts\EntityImporter;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Importers\CategoryImporter;
use App\Domain\Migration\Importers\CertificateImporter;
use App\Domain\Migration\Importers\CourseImporter;
use App\Domain\Migration\Importers\EnrollmentImporter;
use App\Domain\Migration\Importers\LessonImporter;
use App\Domain\Migration\Importers\LiveSessionImporter;
use App\Domain\Migration\Importers\OrderImporter;
use App\Domain\Migration\Importers\ProgressImporter;
use App\Domain\Migration\Importers\QuizImporter;
use App\Domain\Migration\Importers\QuizResultImporter;
use App\Domain\Migration\Importers\RedirectImporter;
use App\Domain\Migration\Importers\ResourceImporter;
use App\Domain\Migration\Importers\ReviewImporter;
use App\Domain\Migration\Importers\SectionImporter;
use App\Domain\Migration\Importers\UserImporter;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class ImporterRegistry
{
    public function __construct(private readonly Container $container) {}

    /** @return list<EntityImporter> */
    public function for(string $entityName): array
    {
        $normalized = strtolower(trim($entityName));
        if ($normalized === 'all') {
            return array_map(fn (LegacyEntity $entity): EntityImporter => $this->make($entity), LegacyEntity::ordered());
        }

        $entity = LegacyEntity::tryFrom($normalized);
        if ($entity === null) {
            throw new InvalidArgumentException(
                "Unknown legacy entity '{$entityName}'. Expected 'all' or one of: ".implode(', ', LegacyEntity::names()),
            );
        }

        return [$this->make($entity)];
    }

    public function make(LegacyEntity $entity): EntityImporter
    {
        $class = match ($entity) {
            LegacyEntity::Users => UserImporter::class,
            LegacyEntity::Categories => CategoryImporter::class,
            LegacyEntity::Courses => CourseImporter::class,
            LegacyEntity::Sections => SectionImporter::class,
            LegacyEntity::Lessons => LessonImporter::class,
            LegacyEntity::Resources => ResourceImporter::class,
            LegacyEntity::Quizzes => QuizImporter::class,
            LegacyEntity::LiveSessions => LiveSessionImporter::class,
            LegacyEntity::Enrollments => EnrollmentImporter::class,
            LegacyEntity::Progress => ProgressImporter::class,
            LegacyEntity::QuizResults => QuizResultImporter::class,
            LegacyEntity::Orders => OrderImporter::class,
            LegacyEntity::Reviews => ReviewImporter::class,
            LegacyEntity::Certificates => CertificateImporter::class,
            LegacyEntity::Redirects => RedirectImporter::class,
        };

        /** @var EntityImporter */
        return $this->container->make($class);
    }
}
