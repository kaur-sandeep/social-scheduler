<?php

namespace App\Enums;

enum SocialProvider: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case Twitter = 'twitter';
    case TikTok = 'tiktok';
    case Pinterest = 'pinterest';
    case YouTube = 'youtube';
    case Threads = 'threads';

    public function color(): string
    {
        return match ($this) {
            self::Facebook => '#1877F2',
            self::Instagram => '#833AB4',
            self::LinkedIn => '#0A66C2',
            self::Twitter => '#1F2937',
            self::TikTok => '#111111',
            self::Pinterest => '#E60023',
            self::YouTube => '#FF0000',
            self::Threads => '#000000',
        };
    }
}
