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
     * @var string[]|null List of allowed `message` sub-keys that can be posted to `contact-form/send` (besides `body`).
     *
     * @since 2.5.0
     */
    public ?array $allowedMessageFields = null;

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
