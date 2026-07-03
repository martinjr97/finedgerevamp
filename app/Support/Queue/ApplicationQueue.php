<?php

namespace App\Support\Queue;

class ApplicationQueue
{
    public static function connection(): string
    {
        return (string) config('queues.connections.default', 'redis');
    }

    public static function notifications(): string
    {
        return (string) config('queues.names.notifications', 'notifications');
    }

    public static function reports(): string
    {
        return (string) config('queues.names.reports', 'reports');
    }

    public static function maintenance(): string
    {
        return (string) config('queues.names.maintenance', 'maintenance');
    }

    public static function default(): string
    {
        return (string) config('queue.connections.redis.queue', 'default');
    }
}
