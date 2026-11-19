<?php

declare(strict_types=1);

// T-DECISIONS-2026-11-18 | Docs | A1

/** @return array<string, string> O-xxx id => row text, for one section of the decision register ("C" or "D"). */
function decisionsRegisterRows(string $section): array
{
    $register = (string) preg_replace('/\R/', "\n", (string) file_get_contents(base_path('docs/07_DECISION_REGISTER.md')));
    preg_match('/^## '.$section.'\b.*?\n(.*?)(?=^## [A-Z]\b|\z)/ms', $register, $match);
    preg_match_all('/^\s*\|?\s*(O-\d{3})\b(.*)$/m', $match[1] ?? '', $rows, PREG_SET_ORDER);

    return collect($rows)->mapWithKeys(fn (array $row): array => [$row[1] => $row[2]])->all();
}

it('leaves the undecided O-xxx rows in section C unmarked and keeps section D present', function (): void {
    $rows = decisionsRegisterRows('C');
    expect($rows)->not->toBeEmpty();

    foreach (['O-001', 'O-003', 'O-007', 'O-019', 'O-022'] as $id) {
        expect($rows)->toHaveKey($id);
        expect($rows[$id])->not->toContain('DECIDED 2026-11-18');
    }
})->group('T-DECISIONS-2026-11-18', 'A1');

it('lists O-006 and O-013 in section D with credentials or entity facts still to be supplied', function (): void {
    $rows = decisionsRegisterRows('D');

    expect($rows['O-006'])->toContain('Credentials still to be supplied')
        ->and($rows['O-013'])->toContain('legal entity');
})->group('T-DECISIONS-2026-11-18', 'A1');

it('leaves O-026 and O-027 unmarked as the prompt requires', function (): void {
    expect(decisionsRegisterRows('C')['O-026'])->not->toContain('DECIDED 2026-11-18');
})->todo('BUG (docs conflict): docs/07_DECISION_REGISTER.md marks O-026 and O-027 as "DECIDED 2026-11-18 (PO)" in section C, but the 2026-11-18 prompt lists them as not marked. Resolve which is right.')->group('T-DECISIONS-2026-11-18', 'A1');
