<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Models;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class RecaptchaV2
{
    public $siteKey;
    public $secretKey;
    public $recaptchaUrl;
    public $recaptchaVerificationUrl;
    public $hideBadge;
    public $dataBadge;
    public $timeout;
    public $debug;

    public function __construct($siteKey, $secretKey, $recaptchaUrl, $recaptchaVerificationUrl, $hideBadge, $dataBadge, $timeout, $debug)
    {
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
        $this->recaptchaUrl = $recaptchaUrl;
        $this->recaptchaVerificationUrl = $recaptchaVerificationUrl;
        $this->hideBadge = $hideBadge;
        $this->dataBadge = $dataBadge;
        $this->timeout = $timeout;
        $this->debug = $debug;
    }

    /**
     * Render reCAPTCHA V2 (Invisible)
     *
     * @param string|null $lang Language code (e.g., 'en', 'es')
     * @return string
     */
    public function render(string $lang = null): string
    {
        $html = $this->renderPolyfill();
        $html .= $this->renderCaptchaHTML();
        $html .= $this->renderFooterJS($lang);
        return $html;
    }

    /**
     * Render the polyfill JS components only.
     *
     * @return string
     */
    protected function renderPolyfill(): string
    {
        return '<script src="https://cdnjs.cloudflare.com/polyfill/v2/polyfill.min.js"></script>' . PHP_EOL;
    }

    /**
     * Render the captcha HTML.
     *
     * @return string
     */
    protected function renderCaptchaHTML(): string
    {
        $html = '<div id="_g-recaptcha"></div>' . PHP_EOL;
        if ($this->hideBadge) {
            $html .= '<style>.grecaptcha-badge{display:none !important;}</style>' . PHP_EOL;
        }

        $html .= '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($this->siteKey) . '" ';
        $html .= 'data-size="invisible" data-callback="_submitForm" data-badge="' . htmlspecialchars($this->dataBadge) . '"></div>';
        return $html;
    }

    /**
     * Render the footer JS necessary for the recaptcha integration.
     *
     * @param string|null $lang
     * @return string
     */
    protected function renderFooterJS(?string $lang = null): string
    {
        $apiUrl = $this->recaptchaUrl;
        if ($lang) {
            $apiUrl .= '?hl=' . htmlspecialchars($lang);
        }

        $html = '<script src="' . htmlspecialchars($apiUrl) . '" async defer></script>' . PHP_EOL;
        $html .= '<script>var _submitForm,_captchaForm,_captchaSubmit,_execute=true,_captchaBadge;</script>';
        $html .= "<script>window.addEventListener('load', _loadCaptcha);" . PHP_EOL;
        $html .= "function _loadCaptcha(){";
        if ($this->hideBadge) {
            $html .= "_captchaBadge=document.querySelector('.grecaptcha-badge');";
            $html .= "if(_captchaBadge){_captchaBadge.style = 'display:none !important;';}" . PHP_EOL;
        }
        $html .= '_captchaForm=document.querySelector("#_g-recaptcha").closest("form");';
        $html .= "_captchaSubmit=_captchaForm.querySelector('[type=submit]');";
        $html .= '_submitForm=function(){if(typeof _submitEvent==="function"){_submitEvent();';
        $html .= 'grecaptcha.reset();}else{_captchaForm.submit();}};';
        $html .= "_captchaForm.addEventListener('submit',";
        $html .= "function(e){e.preventDefault();if(typeof _beforeSubmit==='function'){";
        $html .= "_execute=_beforeSubmit(e);}if(_execute){grecaptcha.execute();}});";
        if ($this->debug) {
            $html .= $this->renderDebug();
        }
        $html .= "}</script>" . PHP_EOL;
        return $html;
    }

    /**
     * Get debug javascript code.
     *
     * @return string
     */
    protected function renderDebug(): string
    {
        $debugElements = ['_submitForm', '_captchaForm', '_captchaSubmit'];
        $html = '';
        foreach ($debugElements as $element) {
            $html .= $this->consoleLog('"Checking element binding of ' . $element . '..."');
            $html .= $this->consoleLog($element . '!==undefined');
        }

        return $html;
    }

    /**
     * Get console.log function for javascript code.
     *
     * @param string $string
     * @return string
     */
    protected function consoleLog(string $string): string
    {
        return "console.log({$string});";
    }

    /**
     * Verify invisible reCAPTCHA response.
     *
     * @param string|null $response
     * @param string|null $clientIp
     * @return bool
     */
    public function verifyResponse(?string $response, ?string $clientIp = null): bool
    {
        if (empty($response)) {
            return false;
        }

        try {
            $client = new Client(['timeout' => $this->timeout]);
            $result = $client->post($this->recaptchaVerificationUrl, [
                'form_params' => [
                    'secret' => $this->secretKey,
                    'remoteip' => $clientIp,
                    'response' => $response,
                ],
            ]);

            $body = json_decode($result->getBody()->getContents(), true);

            if (!isset($body['success']) || $body['success'] !== true) {
                $errorCodes = $body['error-codes'] ?? [];
                Craft::warning("reCAPTCHA verification failed: " . implode(', ', $errorCodes), __METHOD__);
                return false;
            }

            return true;
        } catch (RequestException $e) {
            Craft::error("reCAPTCHA verification request failed: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}

