<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case ShopOwner = 'shop_owner';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::ShopOwner => 'Shop Owner',
            self::Admin => 'Administrator',
        };
    }
}