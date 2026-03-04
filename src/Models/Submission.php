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
}
