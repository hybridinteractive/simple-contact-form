<?php

namespace hybridinteractive\SimpleContactForm\Http\Controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use hybridinteractive\SimpleContactForm\Models\Submission;
use hybridinteractive\SimpleContactForm\Models\Settings as PluginSettings;
use hybridinteractive\SimpleContactForm\Plugin;
use yii\web\Response;

/**
 * Send controller
 */
class SendController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = true;

    /**
     * Sends a contact form submission.
     */
    public function actionIndex(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        $request = Craft::$app->getRequest();

        // Get and prepare the message data - strip reserved keys that must never come from user input
        $message = $request->getBodyParam('message');
        if (is_array($message)) {
            foreach (Submission::getReservedMessageKeys() as $key) {
                unset($message[$key]);
            }
            $message = array_filter($message, function ($value) {
                return $value !== '' && $value !== null;
            });
        }

        // Create and populate the submission model with sanitized inputs
        $submission = new Submission();
        $submission->fromEmail = $this->sanitizeEmail($request->getBodyParam('fromEmail'));
        $submission->fromName = $this->sanitizeHeaderValue($request->getBodyParam('fromName'));
        $submission->subject = $this->sanitizeHeaderValue($request->getBodyParam('subject'));
        $submission->message = $message;

        // Optional allowlist for message[formName] (matches server-defined formOverrides keys)
        $allowedHandles = $settings->allowedPublicFormNames ?? [];
        if ($allowedHandles !== []) {
            $handle = PluginSettings::resolveFormHandle($message);
            if (!in_array($handle, $allowedHandles, true)) {
                Craft::warning(sprintf('Rejected contact form submission: form handle "%s" not allowed.', $handle), __METHOD__);
                $submission->addError('message', Craft::t('simple-contact-form', 'This form identifier is not allowed.'));

                return $this->asModelFailure(
                    $submission,
                    Craft::t('simple-contact-form', 'There was a problem with your submission, please check the form and try again!'),
                    'submission',
                );
            }
        }

        // Handle file attachments
        if ($settings->allowAttachments && isset($_FILES['attachment']) && isset($_FILES['attachment']['name'])) {
            if (is_array($_FILES['attachment']['name'])) {
                $submission->attachment = UploadedFile::getInstancesByName('attachment');
            } else {
                $submission->attachment = UploadedFile::getInstanceByName('attachment');
            }
        }

        // Validate the submission
        if (!$submission->validate()) {
            return $this->asModelFailure(
                $submission,
                Craft::t('simple-contact-form', 'There was a problem with your submission, please check the form and try again!'),
                'submission',
            );
        }

        // Send the email via the mailer service
        $mailer = Plugin::getInstance()->mailer;
        if (!$mailer->send($submission)) {
            return $this->asModelFailure(
                $submission,
                Craft::t('simple-contact-form', 'There was a problem with your submission, please check the form and try again!'),
                'submission',
            );
        }

        return $this->asModelSuccess(
            $submission,
            App::parseEnv($settings->successFlashMessage),
            'submission',
        );
    }

    /**
     * Sanitizes an email value - strips newlines and control chars to prevent header injection.
     */
    private function sanitizeEmail(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        $value = preg_replace('/[\r\n\x00-\x1f\x7f]/u', '', $value);

        return StringHelper::trim($value) ?: null;
    }

    /**
     * Sanitizes a value used in email headers - strips newlines and control chars to prevent header injection.
     */
    private function sanitizeHeaderValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        $value = preg_replace('/[\r\n\x00-\x1f\x7f]/u', '', $value);

        return StringHelper::trim($value) ?: null;
    }
}
