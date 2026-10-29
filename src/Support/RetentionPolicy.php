<?php

namespace Nawasara\Audit\Support;

/**
 * Validate the configured retention before anything is deleted.
 *
 * Audit rows are the evidence of who changed what. A typo in the env
 * (NAWASARA_AUDIT_RETENTION_DAYS=0, =9 meant as 90, an empty string) must
 * not turn the nightly prune into "delete everything", so anything below
 * MINIMUM_DAYS or not a whole number is refused instead of applied.
 */
final class RetentionPolicy
{
    public const MINIMUM_DAYS = 30;

    /**
     * @return int|null days to keep, or null when the configured value must
     *                  not be acted on
     */
    public static function days(mixed $configured): ?int
    {
        if (is_string($configured)) {
            $configured = trim($configured);

            if ($configured === '' || ! ctype_digit($configured)) {
                return null;
            }

            $configured = (int) $configured;
        }

        if (! is_int($configured) || $configured < self::MINIMUM_DAYS) {
            return null;
        }

        return $configured;
    }
}
