<?php

/**
 * This file exists only as a template for the Simple Contact Form settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'simple-contact-form.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    'enableDatabase'          => true,
    'enableConfirmationEmail' => true,
    'enableTemplateOverwrite' => true,
    'notificationTemplate'    => '',
    'confirmationTemplate'    => '',
    'confirmationSubject'     => '',
    'enableSpamCapture'       => true,
    'recaptcha'               => false,
    'enableRecaptchaOverride' => false,
    'recaptchaUrl'            => '',
    'recaptchaVerificationUrl'=> '',
    'recaptchaVersion'        => '',
    'recaptchaSiteKey'        => '',
    'recaptchaSecretKey'      => '',
    'recaptchaHideBadge'      => false,
    'recaptchaDataBadge'      => 'bottomright',
    'recaptchaTimeout'        => 5,
    'recaptchaThreshold'      => .5,
    'recaptchaDebug'          => false,

    // Migration alias for `actions/contact-form/send`; see MIGRATING.md. Default false.
    'enableLegacyContactFormRoutes' => false,

    /*
     * Per-form overrides keyed by handle = message[formName] from the frontend.
     * Set only here or in CP (never from hidden POST fields).
     *
     * 'formOverrides' => [
     *     'contact' => [
     *         // 'toEmail' => 'team@example.com',
     *         // 'disableRecaptcha' => true,
     *         // 'disableSaveSubmission' => false,
     *         // 'disableConfirmation' => false,
     *         // 'notificationTemplate' => 'contact-notification',
     *         // 'confirmationTemplate' => 'contact-confirmation',
     *         // 'confirmationSubject' => 'Thanks for contacting us',
     *     ],
     * ],
     * 'allowedPublicFormNames' => ['contact', 'careers'],
     */
];
