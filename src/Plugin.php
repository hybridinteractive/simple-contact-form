<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm;

use Craft;
use craft\base\Plugin as CraftPlugin;
use hybridinteractive\SimpleContactForm\Models\Settings;
use hybridinteractive\SimpleContactForm\Services\SimpleContactFormService;
use hybridinteractive\SimpleContactForm\Events\MessageSending;
use hybridinteractive\SimpleContactForm\Events\MessageSent;
use hybridinteractive\SimpleContactForm\Mailer;
use craft\events\TemplateEvent;
use craft\helpers\App;
use craft\mail\Message;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use hybridinteractive\SimpleContactForm\Variables\SimpleContactFormVariable;
use yii\base\Event;

/**
 * Class Plugin
 *
 * @property Settings $settings
 * @property Mailer $mailer
 * @property SimpleContactFormService $simpleContactFormService
 *
 * @method Settings getSettings()
 */
class Plugin extends CraftPlugin
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Set the controller namespace for actions
        $this->controllerNamespace = 'hybridinteractive\SimpleContactForm\Http\Controllers';

        Craft::info(
            sprintf(
                '%s plugin loaded',
                static::getInstance()->name
            ),
            __METHOD__
        );

        self::$plugin = $this;

        // Set the mailer component
        $this->setComponents([
            'mailer' => Mailer::class,
            'simpleContactFormService' => SimpleContactFormService::class,
        ]);

        $this->_registerVariable();
        $this->_registerContactFormEventListeners();
        $this->_registerSettings();
        $this->_registerSiteRoutes();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
        }
    }
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Plugin::$plugin.
     *
     * @var Plugin
     */
    public static $plugin;

    public string $schemaVersion = '1.1.0';

    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    protected array $scripts = [];
    protected array $styles = [];
    protected array $publishables = [];


    /**
     * {@inheritdoc}
     */
    public function getCpNavItem(): ?array
    {
        if (!$this->settings->enableDatabase) {
            return null;
        }

        $nav = parent::getCpNavItem();

        $nav['label'] = Craft::t('simple-contact-form', 'Form Submissions');
        $nav['fontIcon'] = 'envelope';
        $nav['subnav'] = [
            'submissions' => [
                'label' => Craft::t('simple-contact-form', 'Submissions'),
                'url' => 'simple-contact-form',
            ],
            'tools' => [
                'label' => Craft::t('simple-contact-form', 'Tools'),
                'url' => 'simple-contact-form/tools',
            ],
        ];

        return $nav;
    }

    /**
     * {@inheritdoc}
     */
    protected function createSettingsModel(): ?Settings
    {
        return new Settings;
    }

    /**
     * {@inheritdoc}
     */
    protected function settingsHtml(): ?string
    {
        // Get and pre-validate the settings
        $settings = $this->getSettings();
        $settings->validate();

        // Get the settings that are being defined by the config file
        $overrides = Craft::$app->config->getConfigFromFile(strtolower($this->handle));

        return Craft::$app->view->renderTemplate('simple-contact-form/_settings.twig', [
            'settings' => $settings,
            'overrides' => array_keys($overrides),
        ]);
    }

    // Private Methods
    // =========================================================================

    private function _registerSettings(): void
    {
        // Settings are now managed in the template file
    }

    private function _registerVariable(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function (Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('simpleContactForm', SimpleContactFormVariable::class);
        });
    }

    private function _registerContactFormEventListeners(): void
    {
        // Capture Before Send Event from Mailer
        Event::on(Mailer::class, Mailer::EVENT_BEFORE_SEND, function (MessageSending $e) {
            if ($e->isSpam) {
                $e->spamReason = $e->spamReason ?? 'external';

                return;
            }

            // Disable Recaptcha
            $disableRecaptcha = false;
            if (is_array($e->submission->message) && array_key_exists('disableRecaptcha', $e->submission->message)) {
                $disableRecaptcha = filter_var($e->submission->message['disableRecaptcha'], FILTER_VALIDATE_BOOLEAN);
            }

            if (Plugin::getInstance()->settings->recaptcha && $disableRecaptcha != true) {
                $recaptcha = Plugin::getInstance()->simpleContactFormService->getRecaptcha();
                $captchaResponse = Craft::$app->request->getParam('g-recaptcha-response');

                if (!$recaptcha->verifyResponse($captchaResponse, $_SERVER['REMOTE_ADDR'])) {
                    $e->isSpam = true;
                    $e->spamReason = 'recaptcha';

                    return;
                }
            }

            // Disable Saving Submission to DB
            $disableSaveSubmission = false;
            if (is_array($e->submission->message) && array_key_exists('disableSaveSubmission', $e->submission->message)) {
                $disableSaveSubmission = filter_var($e->submission->message['disableSaveSubmission'], FILTER_VALIDATE_BOOLEAN);
            }

            $submission = $e->submission;
            if (Plugin::getInstance()->settings->enableDatabase && $disableSaveSubmission != true) {
                Plugin::getInstance()->simpleContactFormService->saveSubmission($submission);
            }

            // Override toEmail setting
            if (is_array($e->submission->message) && array_key_exists('toEmail', $e->submission->message)) {
                $email = $e->submission->message['toEmail'];
                $e->toEmails = explode(',', $email);
            }

            // Notification Template and overrides
            if (Plugin::getInstance()->settings->enableTemplateOverwrite) {
                // First set the template mode to the Site templates
                Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

                // Check if template is overridden in form
                if (is_array($e->submission->message) && array_key_exists('notificationTemplate', $e->submission->message)) {
                    $template = '_emails/'.$e->submission->message['notificationTemplate'];
                } else {
                    // Render the set template
                    $template = App::parseEnv(Plugin::getInstance()->settings->notificationTemplate);
                }

                // Render the set template
                $html = Craft::$app->view->renderTemplate(
                    $template,
                    ['submission' => $e->submission]
                );

                // Update the message body
                $e->message->setHtmlBody($html);

                // Set the template mode back to Control Panel
                if (Craft::$app->request->isCpRequest) {
                    Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_CP);
                }
            }
        });

        // Capture After Send Event from Mailer
        Event::on(Mailer::class, Mailer::EVENT_AFTER_SEND, function (MessageSent $e) {
            // Disable confirmation
            $disableConfirmation = false;
            if (is_array($e->submission->message) && array_key_exists('disableConfirmation', $e->submission->message)) {
                $disableConfirmation = filter_var($e->submission->message['disableConfirmation'], FILTER_VALIDATE_BOOLEAN);
            }

            // Confirmation Template and overrides
            if (Plugin::getInstance()->settings->enableConfirmationEmail && $disableConfirmation != true) {
                // First set the template mode to the Site templates
                Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

                // Check if template is overridden in form
                $template = null;
                if (is_array($e->submission->message) && array_key_exists('confirmationTemplate', $e->submission->message)) {
                    $template = '_emails/'.$e->submission->message['confirmationTemplate'];
                } else {
                    // Render the set template
                    $template = App::parseEnv(Plugin::getInstance()->settings->confirmationTemplate);
                }

                $html = Craft::$app->view->renderTemplate(
                    $template,
                    ['submission' => $e->submission]
                );

                // Check fromEmail
                $message = new Message();
                $message->setTo($e->submission->fromEmail);

                if (isset(App::mailSettings()->fromEmail)) {
                    $message->setFrom([Craft::parseEnv(App::mailSettings()->fromEmail) => Craft::parseEnv(App::mailSettings()->fromName)]);
                } else {
                    $message->setFrom($e->message->getTo());
                }
                $message->setHtmlBody($html);

                // Check for subject override
                $confirmationSubject = null;
                if (is_array($e->submission->message) && array_key_exists('confirmationSubject', $e->submission->message)) {
                    $confirmationSubject = $e->submission->message['confirmationSubject'];
                } else {
                    $confirmationSubject = App::parseEnv(Plugin::getInstance()->settings->getConfirmationSubject());
                }
                $message->setSubject($confirmationSubject);

                // Send the mail
                Craft::$app->mailer->send($message);

                // Set the template mode back to Control Panel
                if (Craft::$app->request->isCpRequest) {
                    Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_CP);
                }
            }
        });
    }

    private function _registerCpRoutes(): void
    {
        // Register CP routes for form submissions management
        Event::on(\craft\web\UrlManager::class, \craft\web\UrlManager::EVENT_REGISTER_CP_URL_RULES, function (\craft\events\RegisterUrlRulesEvent $event) {
            $event->rules['simple-contact-form'] = ['template' => 'simple-contact-form/index'];
            $event->rules['simple-contact-form/submissions/<id:\d+>'] = 'simple-contact-form/submissions/show';
            $event->rules['simple-contact-form/tools'] = 'simple-contact-form/tools/index';
        });
    }

    private function _registerSiteRoutes(): void
    {
        // Register site/action routes for form submission
        Event::on(\craft\web\UrlManager::class, \craft\web\UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (\craft\events\RegisterUrlRulesEvent $event) {
            $event->rules['actions/simple-contact-form/send'] = 'simple-contact-form/http/send/index';
        });
    }
}
