<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Events;

use craft\base\Event;
use craft\mail\Message;
use hybridinteractive\SimpleContactForm\Models\Submission;

/**
 * MessageSending event class
 */
class MessageSending extends Event
{
    /**
     * @var Submission|null The user submission.
     */
    public ?Submission $submission = null;

    /**
     * @var Message|null The message about to be sent.
     */
    public ?Message $message = null;

    /**
     * @var array The email address(es) the submission will get sent to
     */
    public array $toEmails = [];

    /**
     * @var bool Whether the message appears to be spam, and should not really be sent.
     */
    public bool $isSpam = false;

    /**
     * @var string|null Reason the submission was marked as spam (e.g. 'recaptcha', 'external').
     */
    public ?string $spamReason = null;
}
