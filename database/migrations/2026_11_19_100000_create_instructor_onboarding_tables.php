<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * T-P9-01 (YG-GAP-INS-001..003): independent-instructor applications (private ID document), versioned agreements with
 * acceptance records (version, time, IP) and one encrypted payout profile per instructor.
 * `pending_user_id` is set while an application is pending and cleared on a decision, so the unique index allows one
 * pending application per account. The payout `details` column holds one encrypted payload (no plaintext fragment is kept).
 * Also grants the new payout permissions to an already-seeded finance role; fresh installs get them from the role seeder.
 */
return new class extends Migration
{
    private const array FINANCE_PERMISSIONS = ['payout_profile.view', 'payout_profile.reveal'];

    public function up(): void
    {
        Schema::create('instructor_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pending_user_id')->nullable()->unique();
            $table->string('full_name', 120);
            $table->string('phone', 32)->nullable();
            $table->string('discipline', 120);
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->text('bio');
            $table->json('portfolio_links');
            $table->string('locale', 5)->default('en');
            $table->string('id_document_path');
            $table->string('id_document_mime', 64);
            $table->unsignedInteger('id_document_size');
            $table->string('id_document_sha256', 64);
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('instructor_agreements', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 64)->unique();
            $table->json('title');
            $table->json('body');
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('instructor_agreement_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained('instructor_agreements')->restrictOnDelete();
            $table->string('version', 64);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->unique(['user_id', 'agreement_id']);
        });

        Schema::create('instructor_payout_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('method', 16);
            $table->text('details');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->grantFinance();
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_payout_profiles');
        Schema::dropIfExists('instructor_agreement_acceptances');
        Schema::dropIfExists('instructor_agreements');
        Schema::dropIfExists('instructor_applications');
    }

    private function grantFinance(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $guard = (string) config('permissions.guard', 'web');
        foreach (self::FINANCE_PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
        }

        $finance = DB::table('roles')->where('name', 'finance')->where('guard_name', $guard)->value('id');
        if ($finance !== null) {
            foreach (self::FINANCE_PERMISSIONS as $name) {
                $permission = DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id');
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $finance]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
