<?php

namespace App\Support;

final class BusinessDocumentTypes
{
    public const BANK_STATEMENT = 'bank_statement';

    public const SUMUP_REPORT = 'sumup_report';

    public const BARION_REPORT = 'barion_report';

    public const MARKET_RECEIPT = 'market_receipt';

    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::BANK_STATEMENT,
            self::SUMUP_REPORT,
            self::BARION_REPORT,
            self::MARKET_RECEIPT,
            self::OTHER,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
