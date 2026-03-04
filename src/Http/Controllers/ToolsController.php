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

class ToolsController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;

    /**
     * Shows the tools page.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('accessPlugin-simple-contact-form');

        $formNames = $this->getFormNames();
        $formOptions = array_map(
            fn(string $form) => ['label' => ucfirst($form), 'value' => $form],
            $formNames
        );
        array_unshift($formOptions, [
            'label' => Craft::t('simple-contact-form', 'All forms'),
            'value' => 'all',
        ]);

        return $this->renderTemplate('simple-contact-form/tools/index', [
            'formOptions' => $formOptions,
        ]);
    }

    /**
     * Clears submissions by form name (or all if formName is 'all').
     *
     * @return Response
     */
    public function actionClearSubmissions(): Response
    {
        $this->requirePermission('accessPlugin-simple-contact-form');
        $this->requirePostRequest();

        $formName = Craft::$app->getRequest()->getBodyParam('formName');

        if (empty($formName)) {
            Craft::$app->getSession()->setError(Craft::t('simple-contact-form', 'Please select a form'));

            return $this->redirectToPostedUrl();
        }

        $query = Submission::find();
        if ($formName !== 'all') {
            $query->form($formName);
        }
        $submissions = $query->all();

        $count = count($submissions);

        foreach ($submissions as $submission) {
            Craft::$app->getElements()->deleteElement($submission);
        }

        Craft::$app->getSession()->setNotice(Craft::t('simple-contact-form', '{count} submission(s) deleted.', [
            'count' => $count,
        ]));

        return $this->redirectToPostedUrl();
    }

    /**
     * Returns distinct form names from submissions.
     *
     * @return array<string>
     */
    private function getFormNames(): array
    {
        $forms = Craft::$app->db->createCommand(
            'SELECT DISTINCT s.[[form]] FROM {{%simplecontactform_submissions}} s 
             INNER JOIN {{%elements}} e ON e.[[id]] = s.[[id]] 
             WHERE e.[[type]] = :type AND e.[[dateDeleted]] IS NULL',
            ['type' => Submission::class]
        )->queryColumn();

        return array_filter($forms);
    }
}
