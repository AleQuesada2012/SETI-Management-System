<?php

namespace App\Providers;

use App\Enums\AuditChannel;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetComponent;
use App\Models\AssetCondition;
use App\Models\AssetEvent;
use App\Models\AssetMovement;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Building;
use App\Models\File;
use App\Models\MaintenanceRecord;
use App\Models\Permission;
use App\Models\ReportGeneration;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Support\Audit\AuditContext;
use App\Support\Audit\Auditor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditContext::class);
        $this->app->singleton(Auditor::class);
        $this->app->singleton(ReportRegistry::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAuthorization();
        $this->configureAuditChannelForQueuedJobs();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Outside production, lazy loading in loops, silently discarded attributes and reads of
        // attributes that were never selected throw, so these bugs surface while developing.
        Model::shouldBeStrict(! app()->isProduction());

        // At least 12 characters and no composition rules (NIST 800-63B); in production the
        // password is also checked against known breaches (haveibeenpwned, k-anonymity).
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(12),
        );
    }

    /**
     * Morph aliases are stored in model_has_roles.model_type, notifications.notifiable_type and
     * audit_logs.entity_type. The database CHECKs require 'user' (Agent-DB.md §15). Every
     * Auditable model needs an alias here.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'permission' => Permission::class,
            'space' => Space::class,
            'asset' => Asset::class,
            'asset_component' => AssetComponent::class,
            'asset_movement' => AssetMovement::class,
            'asset_event' => AssetEvent::class,
            'maintenance_record' => MaintenanceRecord::class,
            'reservation' => Reservation::class,
            'report_generation' => ReportGeneration::class,
            'file' => File::class,
            'building' => Building::class,
            'space_type' => SpaceType::class,
            'space_status' => SpaceStatus::class,
            'asset_category' => AssetCategory::class,
            'asset_type' => AssetType::class,
            'asset_condition' => AssetCondition::class,
            'brand' => Brand::class,
            'supplier' => Supplier::class,
        ]);
    }

    /**
     * The Superusuario can do everything (CONTEXT.md). Invariants that even a Superusuario may not
     * break (the last active Superusuario, protected system roles…) live in Actions, not policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isSuperuser() ? true : null);
    }

    /** Audit entries written while a queued job runs have channel "system" (ADR-0002). */
    protected function configureAuditChannelForQueuedJobs(): void
    {
        Queue::before(fn () => Context::addHidden(AuditContext::CHANNEL_OVERRIDE, AuditChannel::System->value));
        Queue::after(fn () => Context::forgetHidden(AuditContext::CHANNEL_OVERRIDE));
        Queue::failing(fn () => Context::forgetHidden(AuditContext::CHANNEL_OVERRIDE));
    }
}
