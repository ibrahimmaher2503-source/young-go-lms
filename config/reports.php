<?php

declare(strict_types=1);

/**
 * T-P9-06: reporting limits. `max_rows` is the hard cap of one CSV export (the export is refused, never truncated, so a file
 * can not silently miss rows; narrow the filters instead). `preview_rows` is how many rows the report page shows. `chunk` is the
 * page size of the streamed detail queries. `max_range_days` caps the date range of a report.
 */
return [
    'max_rows' => 50000,
    'preview_rows' => 50,
    'chunk' => 500,
    'max_range_days' => 366,
];
