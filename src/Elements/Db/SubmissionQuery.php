<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Elements\Db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * ContactFormSubmissionQuery represents a SELECT SQL statement for contact form submissions in a way that is independent of DBMS.
 *
 * @method Submission[]|array all($db = null)
 * @method Submission|array|null one($db = null)
 * @method Submission|array|null nth(int $n, $db = null)
 */
class SubmissionQuery extends ElementQuery
{
    public $form;
    public $fromName;
    public $fromEmail;
    public $subject;
    public $message;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'form':
                $this->form($value);
                break;
            case 'fromName':
                $this->fromName($value);
                break;
            case 'fromEmail':
                $this->fromEmail($value);
                break;
            case 'subject':
                $this->subject($value);
                break;
            case 'message':
                $this->message($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Sets the [[form]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function form($value)
    {
        $this->form = $value;
        return $this;
    }

    /**
     * Sets the [[fromName]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function fromName($value)
    {
        $this->fromName = $value;
        return $this;
    }

    /**
     * Sets the [[fromEmail]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function fromEmail($value)
    {
        $this->fromEmail = $value;
        return $this;
    }

    /**
     * Sets the [[subject]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function subject($value)
    {
        $this->subject = $value;
        return $this;
    }

    /**
     * Sets the [[message]] property.
     *
     * @param string|string[]|null $value The property value
     *
     * @return static self reference
     */
    public function message($value)
    {
        $this->message = $value;
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('simplecontactform_submissions');

        $this->query->select([
            'simplecontactform_submissions.form',
            'simplecontactform_submissions.fromName',
            'simplecontactform_submissions.fromEmail',
            'simplecontactform_submissions.subject',
            'simplecontactform_submissions.message',
        ]);

        if ($this->form) {
            $this->subQuery->andWhere(Db::parseParam('simplecontactform_submissions.form', $this->form));
        }

        if ($this->fromName) {
            $this->subQuery->andWhere(Db::parseParam('simplecontactform_submissions.fromName', $this->fromName));
        }

        if ($this->fromEmail) {
            $this->subQuery->andWhere(Db::parseParam('simplecontactform_submissions.fromEmail', $this->fromEmail));
        }

        if ($this->subject) {
            $this->subQuery->andWhere(Db::parseParam('simplecontactform_submissions.subject', $this->subject));
        }

        if ($this->message) {
            $this->subQuery->andWhere(Db::parseParam('simplecontactform_submissions.message', $this->message));
        }

        return parent::beforePrepare();
    }
}
