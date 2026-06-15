<?php

namespace App\Constants;

/**
 * Seller-related constants
 */
class SellerConstants
{
    /**
     * List of usernames allowed to have unlimited debt (bypass max_debit check)
     * Case-sensitive! Username must match exactly.
     * 
     * These sellers can place orders even when their balance goes negative beyond max_debit.
     * When they add funds later, it will automatically reduce their debt.
     */
    public const UNLIMITED_DEBT_USERNAMES = [
        'felineez',
        'HuloTeam',
        'bugteam',
        'DNT',
        'NHmedia',
        'Wecat',
        'seller_hung'
    ];

    /**
     * Check if a username is allowed unlimited debt
     * 
     * @param string $username
     * @return bool
     */
    public static function canHaveUnlimitedDebt(string $username): bool
    {
        return in_array($username, self::UNLIMITED_DEBT_USERNAMES, true);
    }
}
