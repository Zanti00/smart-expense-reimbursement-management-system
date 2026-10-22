<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Format a numeric amount in Philippine Pesos (e.g. ₱4,500.10).
     */
    public static function formatPeso(float|int|string|null $amount): string
    {
        return '₱' . number_format((float) ($amount ?? 0), 2, '.', ',');
    }

    /**
     * Query all admin users (Accounting department or explicit admin/finance roles).
     */
    public static function getAdminUsers(?int $excludeUserId = null): Collection
    {
        $query = User::query()->where(function ($q) {
            $q->whereRaw('LOWER(TRIM(COALESCE(department, ""))) = ?', ['accounting'])
              ->orWhereIn('role', ['admin', 'superadmin', 'finance', 'accounting']);
        });

        if ($excludeUserId !== null) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->get();
    }

    /**
     * Create a single unread notification for a target user.
     */
    public static function notifyUser(
        int $userId,
        string $type,
        string $module,
        string $title,
        string $message,
        string $recordLink,
        ?string $recordType = null,
        ?int $recordId = null,
        array $metadata = []
    ): Notification {
        try {
            return Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'module' => $module,
                'title' => $title,
                'message' => $message,
                'record_type' => $recordType,
                'record_id' => $recordId,
                'record_link' => $recordLink,
                'is_read' => false,
                'metadata' => !empty($metadata) ? $metadata : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationService::notifyUser failed', [
                'user_id' => $userId,
                'type' => $type,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create a single unread notification only if an identical (user_id, type, record_type, record_id)
     * notification does not already exist. Used by daily deadline/overdue checks (G2-071).
     */
    public static function notifyOnce(
        int $userId,
        string $type,
        string $module,
        string $title,
        string $message,
        string $recordLink,
        string $recordType,
        int $recordId,
        array $metadata = []
    ): ?Notification {
        $exists = Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('record_type', $recordType)
            ->where('record_id', $recordId)
            ->exists();

        if ($exists) {
            return null;
        }

        return self::notifyUser(
            userId: $userId,
            type: $type,
            module: $module,
            title: $title,
            message: $message,
            recordLink: $recordLink,
            recordType: $recordType,
            recordId: $recordId,
            metadata: $metadata
        );
    }

    /**
     * Notify all admin users.
     */
    public static function notifyAdmins(
        string $type,
        string $module,
        string $title,
        string $message,
        string $recordLink,
        ?string $recordType = null,
        ?int $recordId = null,
        array $metadata = [],
        ?int $excludeUserId = null
    ): Collection {
        $admins = self::getAdminUsers($excludeUserId);
        $created = collect();

        foreach ($admins as $admin) {
            $created->push(self::notifyUser(
                userId: (int) $admin->id,
                type: $type,
                module: $module,
                title: $title,
                message: $message,
                recordLink: $recordLink,
                recordType: $recordType,
                recordId: $recordId,
                metadata: $metadata
            ));
        }

        return $created;
    }

    /**
     * Notify all admin users once per (type, record_type, record_id).
     */
    public static function notifyAdminsOnce(
        string $type,
        string $module,
        string $title,
        string $message,
        string $recordLink,
        string $recordType,
        int $recordId,
        array $metadata = [],
        ?int $excludeUserId = null
    ): Collection {
        $admins = self::getAdminUsers($excludeUserId);
        $created = collect();

        foreach ($admins as $admin) {
            $notif = self::notifyOnce(
                userId: (int) $admin->id,
                type: $type,
                module: $module,
                title: $title,
                message: $message,
                recordLink: $recordLink,
                recordType: $recordType,
                recordId: $recordId,
                metadata: $metadata
            );
            if ($notif) {
                $created->push($notif);
            }
        }

        return $created;
    }
}
