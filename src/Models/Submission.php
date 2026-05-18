<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Models;

use craft\base\Model;
use craft\web\UploadedFile;

/**
 * Class Submission
 */
class Submission extends Model
{
    /** @var int Maximum length for string fields to prevent abuse */
    private const MAX_STRING_LENGTH = 1000;

    public ?string $fromName = null;

    public ?string $fromEmail = null;

    public ?string $subject = null;

    /**
     * @var string|string[]|string[][]|null
     *
     * @phpstan-var string|array<string|string[]>|null
     */
    public string|array|null $message = null;

    /**
     * @var UploadedFile|UploadedFile[]|null[]|null
     *
     * @phpstan-var UploadedFile|array<UploadedFile|null>|null
     */
    public UploadedFile|array|null $attachment = null;

    /**
     * Message keys that must never be accepted from user input (security-sensitive overrides).
     */
    public static function getReservedMessageKeys(): array
    {
        return [
            'toEmail',
            'disableRecaptcha',
            'disableSaveSubmission',
            'disableConfirmation',
            'notificationTemplate',
            'confirmationTemplate',
            'confirmationSubject',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'fromName' => \Craft::t('simple-contact-form', 'Your Name'),
            'fromEmail' => \Craft::t('simple-contact-form', 'Your Email'),
            'message' => \Craft::t('simple-contact-form', 'Message'),
            'subject' => \Craft::t('simple-contact-form', 'Subject'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getRules(): array
    {
        return [
            [['fromEmail', 'message'], 'required'],
            ['fromEmail', 'email'],
            [['fromName', 'subject'], 'string', 'max' => self::MAX_STRING_LENGTH],
            ['fromName', 'trim'],
            ['subject', 'trim'],
            ['message', 'validateMessage'],
        ];
    }

    /**
     * Validates that message is a string or array of strings, and strips reserved keys.
     */
    public function validateMessage(string $attribute): void
    {
        $value = $this->$attribute;
        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            if (strlen($value) > self::MAX_STRING_LENGTH) {
                $this->addError($attribute, \Craft::t('simple-contact-form', 'Message is too long.'));
            }

            return;
        }

        if (!is_array($value)) {
            $this->addError($attribute, \Craft::t('simple-contact-form', 'Message must be text or an array of fields.'));

            return;
        }

        foreach ($value as $key => $val) {
            if (in_array($key, self::getReservedMessageKeys(), true)) {
                continue;
            }
            if (is_array($val)) {
                foreach ($val as $subVal) {
                    if (is_string($subVal) && strlen($subVal) > self::MAX_STRING_LENGTH) {
                        $this->addError($attribute, \Craft::t('simple-contact-form', 'Message field "{key}" is too long.', ['key' => $key]));
                        return;
                    }
                }
            } elseif (is_string($val) && strlen($val) > self::MAX_STRING_LENGTH) {
                $this->addError($attribute, \Craft::t('simple-contact-form', 'Message field "{key}" is too long.', ['key' => $key]));
            }
        }
    }
}
