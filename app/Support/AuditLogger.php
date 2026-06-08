<?php

namespace App\Support;

use App\Models\AuditLog;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class AuditLogger
{
    private static bool $registered = false;

    private static ?bool $auditTableExists = null;

    /**
     * Register global Eloquent event listeners for audit logging.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        foreach (['created', 'updated', 'deleted', 'restored', 'forceDeleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $payload) use ($event): void {
                $model = $payload[0] ?? null;
                self::record($event, $model);
            });
        }
    }

    /**
     * Persist an audit record for the model event.
     */
    public static function record(string $event, mixed $model): void
    {
        if (! config('audit.enabled', true) || ! $model instanceof Model) {
            return;
        }

        if (! str_starts_with($model::class, 'App\\Models\\')) {
            return;
        }

        if (! self::auditTableIsAvailable()) {
            return;
        }

        if (in_array($model::class, config('audit.excluded_models', []), true)) {
            return;
        }

        [$oldValues, $newValues, $changedFields] = self::extractChangeSet($event, $model);
        if ($event === 'updated' && empty($changedFields)) {
            return;
        }

        $request = request();
        [$actorGuard, $actor] = self::resolveActor();
        $actorName = $actor?->full_name ?? $actor?->name ?? $actor?->email ?? null;

        AuditLog::withoutEvents(function () use (
            $event,
            $model,
            $oldValues,
            $newValues,
            $changedFields,
            $request,
            $actor,
            $actorGuard,
            $actorName
        ): void {
            AuditLog::query()->create([
                'event' => $event,
                'auditable_type' => $model::class,
                'auditable_id' => (string) $model->getKey(),
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'changed_fields' => $changedFields ?: null,
                'actor_type' => $actor ? $actor::class : null,
                'actor_id' => $actor ? (string) $actor->getKey() : null,
                'actor_name' => $actorName,
                'actor_guard' => $actorGuard,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'http_method' => $request?->method(),
                'metadata' => [
                    'route_name' => $request?->route()?->getName(),
                    'connection' => $model->getConnectionName(),
                    'table' => $model->getTable(),
                    'transaction_level' => DB::transactionLevel(),
                ],
            ]);
        });
    }

    /**
     * Derive old/new values and changed field names from an Eloquent event.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<int, string>}
     */
    private static function extractChangeSet(string $event, Model $model): array
    {
        $oldValues = [];
        $newValues = [];
        $changedFields = [];

        if ($event === 'created') {
            $newValues = self::sanitizeAttributes($model->getAttributes());
            $changedFields = array_keys($newValues);

            return [$oldValues, $newValues, $changedFields];
        }

        if ($event === 'updated') {
            $changes = self::sanitizeAttributes($model->getChanges());

            foreach ($changes as $field => $newValue) {
                $oldValues[$field] = self::normalizeValue($model->getOriginal($field));
                $newValues[$field] = $newValue;
                $changedFields[] = $field;
            }

            return [$oldValues, $newValues, $changedFields];
        }

        if (in_array($event, ['deleted', 'forceDeleted'], true)) {
            $oldValues = self::sanitizeAttributes($model->getOriginal());
            $changedFields = array_keys($oldValues);

            return [$oldValues, $newValues, $changedFields];
        }

        if ($event === 'restored') {
            $newValues = self::sanitizeAttributes($model->getAttributes());
            $changedFields = array_keys($newValues);
        }

        return [$oldValues, $newValues, $changedFields];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private static function sanitizeAttributes(array $attributes): array
    {
        $excluded = config('audit.excluded_fields', []);

        $clean = [];
        foreach ($attributes as $field => $value) {
            if (in_array($field, $excluded, true)) {
                continue;
            }
            $clean[$field] = self::normalizeValue($value);
        }

        return $clean;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            return Arr::map($value, fn (mixed $item): mixed => self::normalizeValue($item));
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                /** @var array<int|string, mixed> $arr */
                $arr = $value->toArray();
                return self::normalizeValue($arr);
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            try {
                /** @var mixed $jsonValue */
                $jsonValue = json_decode(json_encode($value), true);
                return $jsonValue;
            } catch (\Throwable) {
                return sprintf('[unserializable:%s]', $value::class);
            }
        }

        return $value;
    }

    /**
     * Resolve authenticated actor with guard context.
     *
     * @return array{0: string|null, 1: \Illuminate\Contracts\Auth\Authenticatable|null}
     */
    private static function resolveActor(): array
    {
        $configuredGuards = array_keys(config('auth.guards', []));
        $preferredGuards = ['admin', 'customer', 'web', 'sanctum'];

        /** @var array<int, string> $guards */
        $guards = array_values(array_unique(array_merge(
            array_values(array_intersect($preferredGuards, $configuredGuards)),
            $configuredGuards
        )));

        foreach ($guards as $guard) {
            $guardInstance = Auth::guard($guard);
            if ($guardInstance->check()) {
                return [$guard, $guardInstance->user()];
            }
        }

        return [null, Auth::user()];
    }

    private static function auditTableIsAvailable(): bool
    {
        if (self::$auditTableExists !== null) {
            return self::$auditTableExists;
        }

        try {
            self::$auditTableExists = Schema::hasTable('audit_logs');
        } catch (\Throwable) {
            self::$auditTableExists = false;
        }

        return self::$auditTableExists;
    }
}
