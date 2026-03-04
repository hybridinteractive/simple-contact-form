<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use hybridinteractive\SimpleContactForm\Elements\Db\SubmissionQuery;
use hybridinteractive\SimpleContactForm\exporters\FlatExporter;

class Submission extends Element
{
    // Public Properties
    // =========================================================================

    public ?string $form;
    public ?string $fromName;
    public ?string $fromEmail;
    public ?string $subject;
    public $message;

    // Static Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function displayName(): string
    {
        return Craft::t('simple-contact-form', 'Submission');
    }

    /**
     * @inheritDoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('simple-contact-form', 'Submissions');
    }

    /**
     * @inheritDoc
     */
    public static function refHandle(): ?string
    {
        return 'submission';
    }

    /**
     * @inheritDoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function trackChanges(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function defineTableName(): string
    {
        return '{{%simplecontactform_submissions}}';
    }

    /**
     * @inheritDoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    public function canView($user): bool
    {
        return true;
    }

    public function canDelete($user): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return new SubmissionQuery(static::class);
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['form', 'subject', 'fromName', 'fromEmail'];
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('simple-contact-form/submissions/'.$this->id);
    }

    /**
     * @inheritDoc
     */
    protected static function defineSources(string $context = null): array
    {
        // Use a direct query for distinct forms instead of loading all submissions
        $forms = array_filter(
            Craft::$app->db->createCommand(
                'SELECT DISTINCT s.[[form]] FROM {{%simplecontactform_submissions}} s 
                 INNER JOIN {{%elements}} e ON e.[[id]] = s.[[id]] 
                 WHERE e.[[type]] = :type AND e.[[dateDeleted]] IS NULL',
                ['type' => self::class]
            )->queryColumn()
        );

        $sources = [
            [
                'key'      => '*',
                'label'    => Craft::t('simple-contact-form', 'All submissions'),
                'criteria' => [],
            ],
        ];

        foreach ($forms as $formHandle) {
            $sources[] = [
                'key'      => $formHandle,
                'label'    => ucfirst($formHandle),
                'criteria' => ['form' => $formHandle],
            ];
        }

        return $sources;
    }

    /**
     * @inheritDoc
     */
    protected static function defineActions(string $source = null): array
    {
        $elementsService = Craft::$app->getElements();

        $actions = parent::defineActions($source);

        $actions[] = $elementsService->createAction([
            'type'                => Delete::class,
            'confirmationMessage' => Craft::t('simple-contact-form', 'Are you sure you want to delete the selected submissions?'),
            'successMessage'      => Craft::t('simple-contact-form', 'Submissions deleted.'),
        ]);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'id'          => Craft::t('simple-contact-form', 'ID'),
            'form'        => Craft::t('simple-contact-form', 'Form'),
            'subject'     => Craft::t('simple-contact-form', 'Subject'),
            'fromName'    => Craft::t('simple-contact-form', 'From Name'),
            'fromEmail'   => Craft::t('simple-contact-form', 'From Email'),
            'message'     => Craft::t('simple-contact-form', 'Message'),
            'dateCreated' => Craft::t('simple-contact-form', 'Date Created'),
        ];

        return $attributes;
    }

    /**
     * @inheritDoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'id',
            'form',
            'subject',
            'fromName',
            'fromEmail',
            'message',
            'dateCreated',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTableAttributeHtml(string $attribute): string
    {
        if ($attribute == 'message') {
            $message = (array) json_decode($this->message);
            $html = '<ul>';
            foreach ($message as $key => $value) {
                if (is_string($value) && $key != 'formName' && $key != 'toEmail' && $key != 'confirmationSubject' && $key != 'confirmationTemplate' && $key != 'notificationTemplate' && $key != 'disableRecaptcha' && $key != 'disableConfirmation') {
                    $shortened = trim(substr($value, 0, 30));
                    $html .= "<li><em>{$key}</em>: {$shortened}...</li>";
                }
            }
            $html .= '</ul>';

            return StringHelper::convertToUtf8($html);
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    protected static function defineSortOptions(): array
    {
        $sortOptions = parent::defineSortOptions();

        return $sortOptions;
    }

    /**
     * @inheritDoc
     */
    protected static function defineExporters(string $source): array
    {
        $exporters = parent::defineExporters($source);
        $exporters[] = FlatExporter::class;

        return $exporters;
    }

    /**
     * @param bool $isNew
     *
     * @throws \yii\db\Exception
     */
    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            Craft::$app->db->createCommand()
                ->insert('{{%simplecontactform_submissions}}', [
                    'id'        => $this->id,
                    'form'      => $this->form,
                    'subject'   => $this->subject,
                    'fromName'  => $this->fromName,
                    'fromEmail' => $this->fromEmail,
                    'message'   => $this->message,
                ])
                ->execute();
        } else {
            Craft::$app->db->createCommand()
                ->update('{{%simplecontactform_submissions}}', [
                    'form'      => $this->form,
                    'subject'   => $this->subject,
                    'fromName'  => $this->fromName,
                    'fromEmail' => $this->fromEmail,
                    'message'   => $this->message,
                ], ['id' => $this->id])
                ->execute();
        }

        parent::afterSave($isNew);
    }
}
