<?php

namespace App\Support;

final class ReportMetric
{
    private const NUMERIC_FIELDS = [
        'followers', 'yesterday_net_change', 'yesterday_follows', 'yesterday_unfollows', 'total_posts',
        'yesterday_posts', 'seven_day_posts', 'seven_day_views', 'yesterday_views', 'seven_day_unique_views',
        'yesterday_unique_views', 'seven_day_likes', 'yesterday_likes', 'seven_day_comments', 'yesterday_comments',
        'seven_day_shares', 'yesterday_shares', 'seven_day_saves', 'yesterday_saves',
    ];

    public static function isNumericField(string $field): bool
    {
        return in_array($field, self::NUMERIC_FIELDS, true);
    }

    public static function value(mixed $value): int|float
    {
        return is_numeric($value) ? $value + 0 : 0;
    }

    public static function display(mixed $value): string
    {
        return number_format(self::value($value));
    }
}
