<?php

/**
 * @link https://hybridinteractive.io/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @copyright Copyright (c) Hybrid Interactive
 * @author Hybrid Interactive
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
use craft\helpers\StringHelper;
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

            $simpleSettings = Plugin::getInstance()->settings;
            $formHandle = Settings::resolveFormHandle($e->submission->message);
            $formOverrides = $simpleSettings->getMergedFormOverridesForHandle($formHandle);

            // Disable Recaptcha (server-defined only per formOverrides / config.php)
            $disableRecaptcha = !empty($formOverrides['disableRecaptcha']);

            if ($simpleSettings->recaptcha && $disableRecaptcha !== true) {
                $recaptcha = Plugin::getInstance()->simpleContactFormService->getRecaptcha();
                $captchaResponse = Craft::$app->request->getParam('g-recaptcha-response');

                if (!$recaptcha->verifyResponse($captchaResponse, $_SERVER['REMOTE_ADDR'])) {
                    $e->isSpam = true;
                    $e->spamReason = 'recaptcha';

                    return;
                }
            }

            // Disable Saving Submission to DB
            $disableSaveSubmission = !empty($formOverrides['disableSaveSubmission']);

            $submission = $e->submission;
            if ($simpleSettings->enableDatabase && $disableSaveSubmission !== true) {
                Plugin::getInstance()->simpleContactFormService->saveSubmission($submission);
            }

            // Override toEmails from server-defined formOverrides
            if (!empty($formOverrides['toEmail']) && is_string($formOverrides['toEmail'])) {
                $e->toEmails = StringHelper::split($formOverrides['toEmail']);
            }

            // Notification Template and overrides
            if ($simpleSettings->enableTemplateOverwrite) {
                // First set the template mode to the Site templates
                Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

                if (!empty($formOverrides['notificationTemplate'])) {
                    $template = '_emails/'.$formOverrides['notificationTemplate'];
                } else {
                    $template = App::parseEnv($simpleSettings->notificationTemplate);
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
            $simpleSettings = Plugin::getInstance()->settings;
            $formHandle = Settings::resolveFormHandle($e->submission->message);
            $formOverrides = $simpleSettings->getMergedFormOverridesForHandle($formHandle);

            // Disable confirmation
            $disableConfirmation = !empty($formOverrides['disableConfirmation']);

            // Confirmation Template and overrides
            if ($simpleSettings->enableConfirmationEmail && $disableConfirmation !== true) {
                // First set the template mode to the Site templates
                Craft::$app->view->setTemplateMode(View::TEMPLATE_MODE_SITE);

                if (!empty($formOverrides['confirmationTemplate'])) {
                    $template = '_emails/'.$formOverrides['confirmationTemplate'];
                } else {
                    $template = App::parseEnv($simpleSettings->confirmationTemplate);
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

                $confirmationSubject = null;
                if (!empty($formOverrides['confirmationSubject']) && is_string($formOverrides['confirmationSubject'])) {
                    $confirmationSubject = $formOverrides['confirmationSubject'];
                } else {
                    $confirmationSubject = App::parseEnv($simpleSettings->getConfirmationSubject());
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
