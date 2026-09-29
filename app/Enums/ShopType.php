<?php

namespace App\Enums;

enum ShopType: string
{
    case BarberShop = 'barber_shop';
    case BeautyParlor = 'beauty_parlor';

    public function label(): string
    {
        return match ($this) {
            self::BarberShop => 'Barber Shop',
            self::BeautyParlor => 'Beauty Parlor',
        };
    }
}