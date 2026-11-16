<?php

declare(strict_types=1);

namespace App\Filament\Shared\Actions;

use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportCsvAction
{
    /**
     * @param  array<string, string>  $columns  CSV heading => model attribute path
     */
    public static function make(string $ability, array $columns, string $filename = 'export.csv'): Action
    {
        if (blank($ability)) {
            throw new InvalidArgumentException('A named export ability is required.');
        }

        if ($columns === []) {
            throw new InvalidArgumentException('At least one export column is required.');
        }

        foreach ($columns as $heading => $attribute) {
            if (blank($heading) || blank($attribute)) {
                throw new InvalidArgumentException('Export headings and model attributes must be non-empty.');
            }
        }

        $filename = self::safeFilename($filename);

        return Action::make('exportCsv')
            ->label(__('common.export_csv'))
            ->authorize($ability)
            ->action(static function (HasTable $livewire) use ($ability, $columns, $filename): StreamedResponse {
                Gate::authorize($ability);

                $query = $livewire->getTableQueryForExport();

                return response()->streamDownload(
                    static function () use ($query, $columns): void {
                        $stream = fopen('php://output', 'wb');

                        if ($stream === false) {
                            throw new RuntimeException('Unable to open the CSV output stream.');
                        }

                        fwrite($stream, "\xEF\xBB\xBF");
                        fputcsv($stream, array_map(self::safeCell(...), array_keys($columns)), ',', '"', '', "\r\n");

                        foreach ($query->cursor() as $record) {
                            $values = [];

                            foreach ($columns as $attribute) {
                                $values[] = self::safeCell(data_get($record, $attribute));
                            }

                            fputcsv($stream, $values, ',', '"', '', "\r\n");
                        }

                        fclose($stream);
                    },
                    $filename,
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                );
            });
    }

    private static function safeFilename(string $filename): string
    {
        $basename = basename($filename);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', $basename);

        if (! is_string($safe) || $safe === '' || $safe === '.csv') {
            return 'export.csv';
        }

        return str_ends_with(strtolower($safe), '.csv') ? $safe : $safe.'.csv';
    }

    private static function safeCell(mixed $value): mixed
    {
        $value = match (true) {
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof BackedEnum => $value->value,
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $value instanceof Stringable => (string) $value,
            is_scalar($value), $value === null => $value,
            default => '',
        };

        if (
            is_string($value)
            && preg_match('/^(?:\xEF\xBB\xBF|[\p{Z}\s])*[=+\-@]/u', $value) === 1
        ) {
            return "'".$value;
        }

        return $value;
    }
}
