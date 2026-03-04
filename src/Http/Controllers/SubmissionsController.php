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

        // Decode the JSON message for display in the template
        $messageObject = json_decode($submission->message, true) ?? [];

        return $this->renderTemplate('simple-contact-form/submissions/_show', [
            'submission' => $submission,
            'messageObject' => $messageObject,
        ]);
    }
}
