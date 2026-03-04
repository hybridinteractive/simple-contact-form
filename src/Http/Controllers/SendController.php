<?php

namespace hybridinteractive\SimpleContactForm\Http\Controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use craft\web\UploadedFile;
use hybridinteractive\SimpleContactForm\Models\Submission;
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

        // Get and prepare the message data
        $message = $request->getBodyParam('message');
        if (is_array($message)) {
            $message = array_filter($message, function ($value) {
                return $value !== '' && $value !== null;
            });
        }

        // Create and populate the submission model
        $submission = new Submission();
        $submission->fromEmail = $request->getBodyParam('fromEmail');
        $submission->fromName = $request->getBodyParam('fromName');
        $submission->subject = $request->getBodyParam('subject');
        $submission->message = $message;

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
}
