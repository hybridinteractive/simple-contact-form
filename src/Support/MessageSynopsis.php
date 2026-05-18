<?php

namespace hybridinteractive\SimpleContactForm\Support;

use Craft;
use craft\helpers\StringHelper;
use hybridinteractive\SimpleContactForm\Models\Submission as SubmissionPayload;

/**
 * Builds a short plain-text synopsis of the stored message JSON for CP UI.
 * Output must be encoded (e.g. Html::encode or Twig |e) when rendered as HTML.
 */
final class MessageSynopsis
{
    /**
     * One-line synopsis for lists and preview blocks.
     */
    public static function plain(?string $messageJson, int $maxLength = 260): string
    {
        if ($messageJson === null || trim($messageJson) === '') {
            return '';
        }

        $decoded = json_decode((string) $messageJson, true);
        if (!is_array($decoded)) {
            return StringHelper::truncate(trim((string) $messageJson), $maxLength);
        }

        $skip = array_merge(SubmissionPayload::getReservedMessageKeys(), ['formName']);
        /** @var array<string, string> $fieldStrings */
        $fieldStrings = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || in_array($key, $skip, true)) {
                continue;
            }

            if (is_string($value)) {
                $text = trim($value);
                if ($text !== '') {
                    $fieldStrings[$key] = $text;
                }
                continue;
            }

            if (is_array($value)) {
                $flat = [];
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $t = trim((string) $item);
                        if ($t !== '') {
                            $flat[] = $t;
                        }
                    } elseif ($item instanceof \Stringable) {
                        $t = trim((string) $item);
                        if ($t !== '') {
                            $flat[] = $t;
                        }
                    }
                }
                if ($flat !== []) {
                    $fieldStrings[$key] = implode(', ', $flat);
                }
            }
        }

        if ($fieldStrings === []) {
            return '';
        }

        $orderedKeys = [];
        if (array_key_exists('body', $fieldStrings)) {
            $orderedKeys[] = 'body';
        }
        foreach (array_keys($fieldStrings) as $k) {
            if ($k !== 'body') {
                $orderedKeys[] = $k;
            }
        }

        $total = count($orderedKeys);

        if ($total === 1) {
            return StringHelper::truncate($fieldStrings[$orderedKeys[0]], $maxLength);
        }

        $parts = [];
        foreach ($orderedKeys as $handle) {
            if (count($parts) >= 2) {
                break;
            }
            $text = $fieldStrings[$handle];
            $limit = $handle === 'body' ? 140 : 64;
            $fragment = StringHelper::truncate($text, $limit);
            if ($handle === 'body') {
                $parts[] = $fragment;
            } else {
                $parts[] = sprintf('%s: %s', self::humanFieldLabel($handle), $fragment);
            }
        }

        $synopsis = implode(' · ', array_filter($parts));
        $remaining = $total - count($parts);
        if ($remaining > 0) {
            $synopsis .= ' · '.Craft::t(
                'simple-contact-form',
                '{n, plural, one{# more field} other{# more fields}}',
                ['n' => $remaining]
            );
        }

        return StringHelper::truncate(trim($synopsis), $maxLength);
    }

    private static function humanFieldLabel(string $handle): string
    {
        return Craft::t('site', $handle);
    }
}
