<?php

namespace hybridinteractive\SimpleContactForm\Support;

use craft\helpers\StringHelper;

/**
 * Sanitizes per-form overrides that may only originate from CP / config.php (never POST).
 *
 * Override keys mirror the legacy form fields now blocked from untrusted requests.
 */
class FormOverrides
{
    /** @see Models\Submission::getReservedMessageKeys() */
    public const OVERRIDE_KEYS = [
        'toEmail',
        'disableRecaptcha',
        'disableSaveSubmission',
        'disableConfirmation',
        'notificationTemplate',
        'confirmationTemplate',
        'confirmationSubject',
    ];

    /**
     * @param  array<mixed>|null  $row  Raw row from Settings::formOverrides[handle]
     * @return array<string, mixed> Sanitized overrides only (empty if none valid)
     */
    public static function sanitizeRow(?array $row): array
    {
        if ($row === null || $row === []) {
            return [];
        }

        $out = [];

        if (isset($row['toEmail']) && $row['toEmail'] !== '') {
            $clean = self::sanitizeToEmailField((string) $row['toEmail']);
            if ($clean !== null) {
                $out['toEmail'] = $clean;
            }
        }

        foreach (['notificationTemplate', 'confirmationTemplate'] as $tplKey) {
            if (!empty($row[$tplKey]) && is_string($row[$tplKey])) {
                $base = self::sanitizeTemplateBasename($row[$tplKey]);
                if ($base !== null) {
                    $out[$tplKey] = $base;
                }
            }
        }

        if (isset($row['confirmationSubject']) && $row['confirmationSubject'] !== '' && is_string($row['confirmationSubject'])) {
            $subj = StringHelper::trim(preg_replace('/[\r\n\x00-\x1f\x7f]/u', '', $row['confirmationSubject']));
            if ($subj !== '') {
                $out['confirmationSubject'] = mb_substr($subj, 0, 1000);
            }
        }

        foreach (['disableRecaptcha', 'disableSaveSubmission', 'disableConfirmation'] as $flag) {
            if (array_key_exists($flag, $row)) {
                $out[$flag] = filter_var($row[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $out;
    }

    /**
     * Comma-separated list of RFC-like emails; strips invalid segments.
     */
    public static function sanitizeToEmailField(string $raw): ?string
    {
        $raw = preg_replace('/[\r\n\x00-\x1f\x7f]/u', '', $raw);
        $parts = StringHelper::split($raw, ',');
        $valid = [];
        foreach ($parts as $part) {
            $part = StringHelper::trim($part);
            if ($part === '') {
                continue;
            }
            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $part;
            }
        }

        return $valid === [] ? null : implode(',', $valid);
    }

    public static function sanitizeTemplateBasename(string $filename): ?string
    {
        $filename = str_replace('\\', '/', $filename);
        if ($filename === '') {
            return null;
        }
        // Reject traversal before basename (basename alone is not sufficient)
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return null;
        }
        $base = basename($filename);
        if ($base === '' || $base === '.' || $base === '..') {
            return null;
        }
        // Safe template stem under _emails/
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $base)) {
            return null;
        }

        return $base;
    }
}
