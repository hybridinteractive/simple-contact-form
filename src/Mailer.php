<?php

namespace hybridinteractive\SimpleContactForm;

use Craft;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\mail\Message;
use hybridinteractive\SimpleContactForm\Support\Arr;
use hybridinteractive\SimpleContactForm\Support\Env;
use hybridinteractive\SimpleContactForm\Events\MessageSending;
use hybridinteractive\SimpleContactForm\Events\MessageSent;
use hybridinteractive\SimpleContactForm\Models\Submission;
use hybridinteractive\SimpleContactForm\Plugin;
use yii\base\Component;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Markdown;

/**
 * Mailer service
 */
class Mailer extends Component
{
    /**
     * @event SubmissionEvent The event that is triggered before a message is sent
     */
    public const EVENT_BEFORE_SEND = 'beforeSend';

    /**
     * @event SubmissionEvent The event that is triggered after a message is sent
     */
    public const EVENT_AFTER_SEND = 'afterSend';

    /**
     * Sends an email submitted through a contact form.
     *
     * @param  bool  $runValidation  Whether the section should be validated
     *
     * @throws InvalidConfigException if the plugin settings don't validate
     */
    public function send(Submission $submission, bool $runValidation = true): bool
    {
        // Get the plugin settings and make sure they validate before doing anything
        $settings = Plugin::getInstance()->getSettings();
        if (! $settings->validate()) {
            throw new InvalidConfigException("The Simple Contact Form settings don't validate.");
        }

        if ($runValidation && ! $submission->validate()) {
            Craft::info("Contact form submission not saved due to validation error.", __METHOD__);

            return false;
        }

        $mailer = Craft::$app->getMailer();

        // Prep the message
        $fromEmail = $this->getFromEmail($mailer->from);
        $fromName = $this->compileFromName($submission->fromName);
        $subject = $this->compileSubject($submission->subject);
        $textBody = $this->compileTextBody($submission);
        $htmlBody = $this->compileHtmlBody($textBody);

        // Flag for file attachment validation.
        $validAttachments = true;

        $message = (new Message)
            ->setFrom([$fromEmail => $fromName])
            ->setReplyTo([$submission->fromEmail => (string) $submission->fromName])
            ->setSubject($subject)
            ->setTextBody($textBody)
            ->setHtmlBody($htmlBody);

        if ($submission->attachment !== null) {
            $allowedFileTypes = Craft::$app->getConfig()->getGeneral()->allowedFileExtensions;

            if (! is_array($submission->attachment)) {
                $submission->attachment = [$submission->attachment];
            }

            foreach ($submission->attachment as $attachment) {
                if (! $attachment) {
                    continue;
                }

                // Validate that the file is safe to send by e-mail
                $extension = pathinfo($attachment->name, PATHINFO_EXTENSION);

                if (! in_array(strtolower($extension), $allowedFileTypes)) {
                    $validAttachments = false;
                }

                $message->attach($attachment->tempName, [
                    'fileName' => $attachment->name,
                    'contentType' => FileHelper::getMimeType($attachment->tempName),
                ]);
            }
        }

        // Grab any "to" emails set in the plugin settings.
        $toEmails = Env::parse($settings->toEmail);
        $toEmails = is_string($toEmails) ? StringHelper::split($toEmails) : $toEmails;

        // Fire a message sending event (formerly 'beforeSend')
        /** @var MessageSending $event */
        $event = new MessageSending([]);
        $event->submission = $submission;
        $event->message = $message;
        $event->toEmails = $toEmails;
        $event->isSpam = false;

        $this->trigger(self::EVENT_BEFORE_SEND, $event);

        // Check if spam flag was set by event handlers
        if ($event->isSpam !== false) {
            Craft::warning("Contact form submission suspected to be spam.", __METHOD__);
            Plugin::getInstance()->simpleContactFormService->saveSpamSubmission($event->submission, $event->spamReason);

            return true;
        }

        if ($validAttachments !== true) {
            Craft::error("Contact form submission contains a disallowed filetype.", __METHOD__);

            return false;
        }

        foreach ($event->toEmails as $toEmail) {
            $message->setTo($toEmail);
            $mailer->send($message);
        }

        // Fire a message sent event (formerly 'afterSend')
        if ($this->hasEventHandlers(self::EVENT_AFTER_SEND)) {
            /** @var MessageSent $afterSendEvent */
            $afterSendEvent = new MessageSent([]);
            $afterSendEvent->submission = $submission;
            $afterSendEvent->message = $message;
            $afterSendEvent->toEmails = $event->toEmails;

            $this->trigger(self::EVENT_AFTER_SEND, $afterSendEvent);
        }

        return true;
    }

    /**
     * Returns the "From" email value on the given mailer $from property object.
     *
     * @param  string|array|User|User[]|null  $from
     *
     * @throws InvalidConfigException if it can't be determined
     */
    public function getFromEmail($from): string
    {
        if (is_string($from)) {
            return $from;
        }
        if ($from instanceof User) {
            return $from->email;
        }
        if (is_array($from)) {
            $first = reset($from);
            $key = key($from);
            if (is_numeric($key)) {
                return $this->getFromEmail($first);
            }

            return $key;
        }
        throw new InvalidConfigException('Can\'t determine "From" email from email config settings.');
    }

    /**
     * Compiles the "From" name value from the submitted name.
     */
    public function compileFromName(?string $fromName = null): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $prependSender = App::parseEnv($settings->prependSender);

        return $prependSender.($prependSender && $fromName ? ' ' : '').$fromName;
    }

    /**
     * Compiles the real email subject from the submitted subject.
     */
    public function compileSubject(?string $subject = null): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $prependSubject = App::parseEnv($settings->prependSubject);

        return $prependSubject.($prependSubject && $subject ? ' - ' : '').$subject;
    }

    /**
     * Compiles the real email textual body from the submitted message.
     */
    public function compileTextBody(Submission $submission): string
    {
        $fields = [];

        if ($submission->fromName) {
            $fields[Craft::t('simple-contact-form', 'Name')] = $submission->fromName;
        }

        $fields[Craft::t('simple-contact-form', 'Email')] = $submission->fromEmail;

        if (is_array($submission->message)) {
            $settings = Plugin::getInstance()->getSettings();
            $messageFields = array_merge($submission->message);
            $body = Arr::pull($messageFields, 'body', '');
            foreach ($messageFields as $key => $value) {
                if ($settings->allowedMessageFields === null || in_array($key, $settings->allowedMessageFields)) {
                    $label = Craft::t('site', $key);
                    $fields[$label] = $value;
                }
            }
        } else {
            $body = (string) $submission->message;
        }

        $text = '';

        foreach ($fields as $key => $value) {
            $text .= ($text ? "\n" : '')."- **{$key}:** ";
            if (is_array($value)) {
                $text .= implode(', ', $value);
            } else {
                $text .= $value;
            }
        }

        if ($body !== '') {
            $body = preg_replace('/\R/u', "\n", $body);
            $text .= "\n\n".$body;
        }

        return $text;
    }

    /**
     * Compiles the real email HTML body from the compiled textual body.
     */
    public function compileHtmlBody(string $textBody): string
    {
        $html = Html::encode($textBody);
        $html = Markdown::process($html);

        return $html;
    }
}
