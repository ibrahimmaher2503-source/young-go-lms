<?php

declare(strict_types=1);

use App\Filament\Shared\Actions\ExportCsvAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

final class CsvExportFixture extends Model
{
    protected $table = 'csv_export_fixtures';

    protected $guarded = [];

    public $timestamps = false;
}

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('csv_export_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
        $table->string('name');
        $table->string('status');
    });
});

it('streams only the authorized filtered table rows with stable safe CSV columns', function () {
    CsvExportFixture::query()->insert([
        ['tenant_id' => 7, 'name' => 'Alice', 'status' => 'active'],
        ['tenant_id' => 7, 'name' => '=2+2', 'status' => 'active'],
        ['tenant_id' => 7, 'name' => 'Hidden by current filter', 'status' => 'inactive'],
        ['tenant_id' => 8, 'name' => 'Outside authorized scope', 'status' => 'active'],
    ]);

    $query = CsvExportFixture::query()
        ->where('tenant_id', 7)
        ->where('status', 'active')
        ->orderBy('id');

    $table = Mockery::mock(HasTable::class);
    $table->shouldReceive('getTableQueryForExport')->once()->andReturn($query);

    $user = User::factory()->create();
    Auth::login($user);
    Gate::define('staff.users.export', static fn (User $actor): bool => $actor->is($user));

    $action = ExportCsvAction::make(
        'staff.users.export',
        ['Name' => 'name', 'Status' => 'status'],
        'users.csv',
    );

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->isAuthorized())->toBeTrue();

    $response = $action->getActionFunction()($table);
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($response->headers->get('Content-Disposition'))->toContain('filename=users.csv')
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv)->toBe("\xEF\xBB\xBFName,Status\r\nAlice,active\r\n'=2+2,active\r\n");
})->group('T-P1-08', 'A3');

it('denies CSV export when the explicitly named permission is missing', function () {
    $allowedUser = User::factory()->create();
    $deniedUser = User::factory()->create();
    Gate::define('staff.users.export', static fn (User $actor): bool => $actor->is($allowedUser));
    Auth::login($deniedUser);

    $action = ExportCsvAction::make(
        'staff.users.export',
        ['Name' => 'name'],
    );

    expect($action->isAuthorized())->toBeFalse();
})->group('T-P1-08', 'A3');
