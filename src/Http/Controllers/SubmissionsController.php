<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Http\Controllers;

use Craft;
use craft\web\Controller;
use hybridinteractive\SimpleContactForm\Elements\Submission;
use hybridinteractive\SimpleContactForm\Models\Submission as SubmissionPayload;
use hybridinteractive\SimpleContactForm\Support\MessageSynopsis;
use yii\web\Response;

class SubmissionsController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;

    /**
     * Shows the submissions index page.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('accessPlugin-simple-contact-form');

        $submissions = Submission::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        return $this->renderTemplate('simple-contact-form/index', [
            'submissions' => $submissions,
        ]);
    }

    /**
     * Shows a submission detail page.
     *
     * @param int $id
     * @return Response
     */
    public function actionShow(int $id): Response
    {
        $this->requirePermission('accessPlugin-simple-contact-form');

        $submission = Submission::findOne($id);

        if (!$submission) {
            throw new \yii\web\NotFoundHttpException('Submission not found');
        }

        return $this->renderTemplate('simple-contact-form/submissions/_show', [
            'submission' => $submission,
            'submissionMessageFields' => $this->submissionMessageFieldsForDisplay($submission->message),
            'messageSynopsis' => MessageSynopsis::plain($submission->message, 400),
        ]);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    private function submissionMessageFieldsForDisplay(mixed $messageJson): array
    {
        $decoded = json_decode((string) $messageJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $skipKeys = array_merge(SubmissionPayload::getReservedMessageKeys(), ['formName']);
        $fields = [];

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || in_array($key, $skipKeys, true)) {
                continue;
            }

            if (is_string($value)) {
                $fields[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $flat = [];
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $flat[] = (string) $item;
                    } elseif ($item instanceof \Stringable) {
                        $flat[] = (string) $item;
                    }
                }
                if ($flat !== []) {
                    $fields[$key] = $flat;
                }
            }
        }

        return $this->orderSubmissionMessageFields($fields);
    }

    /**
     * @param  array<string, array<int, string>|string>  $fields
     * @return array<string, array<int, string>|string>
     */
    private function orderSubmissionMessageFields(array $fields): array
    {
        if ($fields === []) {
            return [];
        }

        $ordered = [];
        if (array_key_exists('body', $fields)) {
            $ordered['body'] = $fields['body'];
            unset($fields['body']);
        }

        foreach ($fields as $key => $value) {
            $ordered[$key] = $value;
        }

        return $ordered;
    }
}
