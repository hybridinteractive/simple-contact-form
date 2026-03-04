<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use hybridinteractive\SimpleContactForm\Plugin;
use hybridinteractive\SimpleContactForm\Elements\Submission;
use hybridinteractive\SimpleContactForm\Models\Submission as SubmissionModel;
use hybridinteractive\SimpleContactForm\Models\RecaptchaV2;
use hybridinteractive\SimpleContactForm\Models\RecaptchaV3;
use yii\base\Exception;

class SimpleContactFormService extends Component
{
    /**
     * Save a contact form submission to the database
     *
     * @param SubmissionModel $submission
     *
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     *
     * @return mixed
     */
    public function saveSubmission(SubmissionModel $submission)
    {
        $contactFormSubmission = new Submission();
        $contactFormSubmission->form = $submission->message['formName'] ?? 'contact';
        $contactFormSubmission->fromName = $submission->fromName;
        $contactFormSubmission->fromEmail = $submission->fromEmail;
        $contactFormSubmission->subject = $submission->subject;

        if (!is_array($submission->message)) {
            $submission->message = ['message' => $this->utf8Value($submission->message)];
        }

        $message = $this->utf8AllTheThings($submission->message);
        $contactFormSubmission->message = json_encode($message);

        if (Craft::$app->elements->saveElement($contactFormSubmission)) {
            return $contactFormSubmission;
        }

        throw new Exception(json_encode($contactFormSubmission->errors));
    }

    /**
     * Save a spam submission to the spam table
     *
     * @param SubmissionModel $submission
     * @param string|null $reason Optional reason the submission was marked as spam (e.g. 'recaptcha', 'honeypot')
     *
     * @return bool Whether the spam was saved successfully
     */
    public function saveSpamSubmission(SubmissionModel $submission, ?string $reason = null): bool
    {
        if (!Plugin::$plugin->settings->enableDatabase || !Plugin::$plugin->settings->enableSpamCapture) {
            return false;
        }

        if (!is_array($submission->message)) {
            $submission->message = ['message' => $this->utf8Value($submission->message)];
        }

        $message = $this->utf8AllTheThings($submission->message);
        $messageJson = json_encode($message);

        try {
            Craft::$app->db->createCommand()
                ->insert('{{%simplecontactform_spam}}', [
                    'form' => $submission->message['formName'] ?? 'contact',
                    'fromName' => $submission->fromName,
                    'fromEmail' => $submission->fromEmail,
                    'subject' => $submission->subject,
                    'message' => $messageJson,
                    'reason' => $reason,
                    'dateCreated' => date('Y-m-d H:i:s'),
                    'dateUpdated' => date('Y-m-d H:i:s'),
                    'uid' => StringHelper::UUID(),
                ])
                ->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning("Failed to save spam submission: {$e->getMessage()}", __METHOD__);

            return false;
        }
    }

    /**
     * Get reCAPTCHA instance based on plugin settings
     *
     * @return RecaptchaV2|RecaptchaV3
     */
    public function getRecaptcha()
    {
        $siteKey = Craft::parseEnv(Plugin::$plugin->settings->recaptchaSiteKey);
        $secretKey = Craft::parseEnv(Plugin::$plugin->settings->recaptchaSecretKey);

        $recaptchaUrl = 'https://www.google.com/recaptcha/api.js';
        $recaptchaVerificationUrl = 'https://www.google.com/recaptcha/api/siteverify';

        if (Plugin::$plugin->settings->enableRecaptchaOverride === true) {
            $recaptchaUrl = Craft::parseEnv(Plugin::$plugin->settings->recaptchaUrl);
            $recaptchaVerificationUrl = Craft::parseEnv(Plugin::$plugin->settings->recaptchaVerificationUrl);
        }

        if (Plugin::$plugin->settings->recaptchaVersion === '3') {
            $recaptcha = new RecaptchaV3(
                $siteKey,
                $secretKey,
                $recaptchaUrl,
                $recaptchaVerificationUrl,
                Plugin::$plugin->settings->recaptchaThreshold,
                Plugin::$plugin->settings->recaptchaTimeout,
                Plugin::$plugin->settings->recaptchaHideBadge
            );

            return $recaptcha;
        }

        return new RecaptchaV2(
            $siteKey,
            $secretKey,
            $recaptchaUrl,
            $recaptchaVerificationUrl,
            Plugin::$plugin->settings->recaptchaHideBadge,
            Plugin::$plugin->settings->recaptchaDataBadge,
            Plugin::$plugin->settings->recaptchaTimeout,
            Plugin::$plugin->settings->recaptchaDebug
        );
    }

    /**
     * Convert all values in an array to UTF-8
     *
     * @param array $things
     *
     * @return array
     */
    public function utf8AllTheThings(array $things): array
    {
        foreach ($things as $key => $value) {
            $things[$key] = $this->utf8Value($value);
        }

        return $things;
    }

    /**
     * Convert a value to UTF-8
     *
     * @param array|string $value
     *
     * @return array|string
     */
    public function utf8Value($value)
    {
        if (is_array($value)) {
            return $this->utf8AllTheThings($value);
        }

        return StringHelper::convertToUtf8($value);
    }
}
