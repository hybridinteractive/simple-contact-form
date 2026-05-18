<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Models;

use Craft;
use craft\base\Model;
use craft\helpers\Json;
use hybridinteractive\SimpleContactForm\Support\FormOverrides;

class Settings extends Model
{
    // Basic Contact Form Settings
    /**
     * @var string|string[]|null
     */
    public string|array|null $toEmail = null;

    public ?string $prependSender = null;

    public ?string $prependSubject = null;

    public bool $allowAttachments = false;

    public ?string $successFlashMessage = null;

    /**
     * @var string[]|null List of allowed `message` sub-keys on the send action URL (besides `body`).
     *
     * @since 2.5.0
     */
    public ?array $allowedMessageFields = null;

    /**
     * Per-form overrides keyed by handle matching `message[formName]` (default `contact`).
     * Defined only via CP JSON field or config/simple-contact-form.php — never trusted from POST.
     *
     * @var array<string, array<mixed>>|null
     */
    public ?array $formOverrides = null;

    /**
     * When non-empty, `message[formName]` must be one of these handles (after normalization).
     *
     * @var string[]|null
     */
    public ?array $allowedPublicFormNames = null;

    /**
     * When true, register `actions/contact-form/send` as an alias for Simple Contact Form's send action so
     * templates that still use Contact Form URLs keep working during migration.
     *
     * Ignored (skipped with a Craft log warning) while the official `contact-form` plugin is enabled. Disable or
     * uninstall Contact Form before relying on this alias.
     *
     * Recommended to set via `config/simple-contact-form.php`.
     */
    public bool $enableLegacyContactFormRoutes = false;

    /** @var bool Set when CP JSON textarea could not be decoded */
    private bool $_formOverridesJsonHadError = false;

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        $this->_formOverridesJsonHadError = false;

        if (isset($values['allowedMessageFields']) && is_string($values['allowedMessageFields'])) {
            $values['allowedMessageFields'] = array_filter(array_map('trim', explode(',', $values['allowedMessageFields']))) ?: null;
        }
        if (isset($values['allowedPublicFormNames'])) {
            if (is_string($values['allowedPublicFormNames'])) {
                $values['allowedPublicFormNames'] = array_values(array_filter(array_map('trim', explode(',', $values['allowedPublicFormNames'])))) ?: null;
            } elseif ($values['allowedPublicFormNames'] === '') {
                $values['allowedPublicFormNames'] = null;
            }
        }
        if (isset($values['formOverrides'])) {
            if (is_string($values['formOverrides'])) {
                $trimmed = trim($values['formOverrides']);
                if ($trimmed === '') {
                    $values['formOverrides'] = null;
                } else {
                    try {
                        $values['formOverrides'] = Json::decode($trimmed);
                    } catch (\Throwable $e) {
                        $values['formOverrides'] = null;
                        $this->_formOverridesJsonHadError = true;
                    }
                }
            }
        }
        parent::setAttributes($values, $safeOnly);
    }

    // Enhanced Features Settings
    /**
     * @var bool
     */
    public $enableDatabase = true;

    /**
     * @var bool Whether to save spam submissions to the spam table.
     */
    public $enableSpamCapture = true;

    /**
     * @var bool
     */
    public $enableTemplateOverwrite = true;

    /**
     * @var bool
     */
    public $enableConfirmationEmail = true;

    /**
     * @var string|null
     */
    public $notificationTemplate = '';

    /**
     * @var string|null
     */
    public $confirmationTemplate = '';

    /**
     * @var string|null
     */
    public $confirmationSubject = '';

    // reCAPTCHA Settings
    /**
     * @var bool
     */
    public $recaptcha = false;

    /**
     * @var bool
     */
    public $enableRecaptchaOverride = false;

    /**
     * @var string|null
     */
    public $recaptchaUrl = '';

    /**
     * @var string|null
     */
    public $recaptchaVerificationUrl = '';

    /**
     * @var string|null
     */
    public $recaptchaVersion = '';

    /**
     * @var string|null
     */
    public $recaptchaSiteKey = '';

    /**
     * @var string|null
     */
    public $recaptchaSecretKey = '';

    /**
     * @var bool
     */
    public $recaptchaHideBadge = false;

    /**
     * @var string
     */
    public $recaptchaDataBadge = 'bottomright';

    /**
     * @var int
     */
    public $recaptchaTimeout = 5;

    /**
     * @var bool
     */
    public $recaptchaDebug = false;

    /**
     * @var float
     */
    public $recaptchaThreshold = 0.5;

    public function __construct(array $config = [])
    {
        $craft = Craft::$app;

        if ($this->prependSender === null) {
            $this->prependSender = 'On behalf of';
        }

        if ($this->prependSubject === null) {
            $this->prependSubject = sprintf('New message from %s', $craft->getSites()->getCurrentSite()->name);
        }

        if ($this->successFlashMessage === null) {
            $this->successFlashMessage = 'Your message has been sent.';
        }

        if ($this->confirmationSubject === null) {
            $this->confirmationSubject = 'Thank you for your message';
        }

        parent::__construct($config);
    }

    /**
     * @return string
     */
    public function getConfirmationSubject(): string
    {
        if ($this->confirmationSubject === null) {
            return '';
        }

        // Handle array (multi-site) configuration
        $subject = $this->confirmationSubject;
        if (is_string($subject)) {
            return $subject;
        }

        // Array case for multi-site
        return $subject[Craft::$app->sites->currentSite->handle] ?? '';
    }

    /**
     * Returns sanitized overrides for a form handle, or empty array.
     *
     * @return array<string, mixed>
     */
    public function getMergedFormOverridesForHandle(string $formHandle): array
    {
        $map = $this->formOverrides ?? [];
        $row = $map[$formHandle] ?? [];
        if (!is_array($row)) {
            return [];
        }

        return FormOverrides::sanitizeRow($row);
    }

    /**
     * @param  mixed  $message  Submission message (array|string|null)
     */
    public static function resolveFormHandle(mixed $message): string
    {
        $default = 'contact';
        if (!is_array($message) || !isset($message['formName'])) {
            return $default;
        }
        $handle = trim((string) $message['formName']);
        if ($handle !== '' && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $handle)) {
            return $handle;
        }

        return $default;
    }

    /**
     * Validates formOverrides shape (CP JSON textarea or config.php).
     *
     * @param mixed $_params
     * @param mixed $_validator
     * @param mixed $_current
     */
    public function validateFormOverrides(string $attribute, mixed $_params = null, mixed $_validator = null, mixed $_current = null): void
    {
        if ($this->_formOverridesJsonHadError) {
            $this->addError($attribute, Craft::t('simple-contact-form', 'Form overrides must be valid JSON.'));
            return;
        }

        if ($this->formOverrides === null || $this->formOverrides === []) {
            return;
        }

        foreach ($this->formOverrides as $handle => $row) {
            if (!is_string($handle) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $handle)) {
                $this->addError($attribute, Craft::t('simple-contact-form', 'Each form override key must be a handle (letters, numbers, underscores, hyphens).'));
                return;
            }
            if (!is_array($row)) {
                $this->addError($attribute, Craft::t('simple-contact-form', 'Overrides for "{handle}" must be a JSON object.', ['handle' => $handle]));
                return;
            }
            foreach ($row as $k => $_) {
                if (!in_array($k, FormOverrides::OVERRIDE_KEYS, true)) {
                    $this->addError($attribute, Craft::t('simple-contact-form', 'Unknown override key "{key}".', ['key' => (string) $k]));
                    return;
                }
            }
        }
    }

    /**
     * @param mixed $_params
     * @param mixed $_validator
     * @param mixed $_current
     */
    public function validateAllowedPublicFormNames(string $attribute, mixed $_params = null, mixed $_validator = null, mixed $_current = null): void
    {
        foreach ($this->allowedPublicFormNames ?? [] as $handle) {
            if (!is_string($handle) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $handle)) {
                $this->addError($attribute, Craft::t('simple-contact-form', 'Each allowed form handle must use only letters, numbers, underscores, and hyphens.'));
                return;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getRules(): array
    {
        return [
            // Basic contact form rules
            'toEmail' => ['required', 'string'],
            'successFlashMessage' => ['required', 'string'],
            'prependSender' => ['nullable', 'string'],
            'prependSubject' => ['nullable', 'string'],
            'allowAttachments' => ['boolean'],
            'allowedMessageFields' => ['nullable', 'array'],
            ['formOverrides', 'validateFormOverrides'],
            ['allowedPublicFormNames', 'validateAllowedPublicFormNames'],
            'enableLegacyContactFormRoutes' => ['boolean'],

            // Enhanced features rules
            'enableDatabase' => ['boolean'],
            'enableSpamCapture' => ['boolean'],
            'enableTemplateOverwrite' => ['boolean'],
            'enableConfirmationEmail' => ['boolean'],
            'notificationTemplate' => ['nullable', 'string'],
            'confirmationTemplate' => ['nullable', 'string'],
            'confirmationSubject' => ['nullable', 'string'],

            // reCAPTCHA rules
            'recaptcha' => ['boolean'],
            'enableRecaptchaOverride' => ['boolean'],
            'recaptchaUrl' => ['nullable', 'string'],
            'recaptchaVerificationUrl' => ['nullable', 'string'],
            'recaptchaVersion' => ['nullable', 'string'],
            'recaptchaSiteKey' => ['nullable', 'string'],
            'recaptchaSecretKey' => ['nullable', 'string'],
            'recaptchaHideBadge' => ['boolean'],
            'recaptchaDataBadge' => ['nullable', 'string'],
            'recaptchaTimeout' => ['integer'],
            'recaptchaDebug' => ['boolean'],
            'recaptchaThreshold' => ['numeric', 'min' => 0, 'max' => 1],

            // Conditional rules
            [['confirmationTemplate', 'confirmationSubject'], 'required', 'when' => static function ($model) {
                return $model->enableConfirmationEmail == true;
            }],

            ['notificationTemplate', 'required', 'when' => static function ($model) {
                return $model->enableTemplateOverwrite == true;
            }],

            [['recaptchaSiteKey', 'recaptchaSecretKey'], 'required', 'when' => static function ($model) {
                return $model->recaptcha == true;
            }],

            [['recaptchaUrl', 'recaptchaVerificationUrl'], 'required', 'when' => static function ($model) {
                return $model->enableRecaptchaOverride == true;
            }],
        ];
    }
}
