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

class RecaptchaV3
{
    public $siteKey;
    public $secretKey;
    public $recaptchaUrl;
    public $recaptchaVerificationUrl;
    public $threshold;
    public $timeout;
    public $hideBadge;

    public function __construct($siteKey, $secretKey, $recaptchaUrl, $recaptchaVerificationUrl, $threshold, $timeout, $hideBadge)
    {
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
        $this->recaptchaUrl = $recaptchaUrl;
        $this->recaptchaVerificationUrl = $recaptchaVerificationUrl;
        $this->threshold = $threshold;
        $this->timeout = $timeout;
        $this->hideBadge = $hideBadge;
    }

    /**
     * Render reCAPTCHA V3
     *
     * @param string|null $action
     * @return string
     */
    public function render(string $action = null): string
    {
        $action = $action ?: 'contactform';
        
        $html = '<script src="' . $this->recaptchaUrl . '?render=' . $this->siteKey . '"></script>';
        $html .= '<script>';
        $html .= 'grecaptcha.ready(function() {';
        $html .= 'grecaptcha.execute("' . $this->siteKey . '", {action: "' . $action . '"}).then(function(token) {';
        $html .= 'document.getElementById("g-recaptcha-response").value = token;';
        $html .= '});';
        $html .= '});';
        $html .= '</script>';
        $html .= '<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">';

        if ($this->hideBadge) {
            $html .= '<style>.grecaptcha-badge { visibility: hidden; }</style>';
        }

        return $html;
    }

    /**
     * Verify reCAPTCHA V3 response
     *
     * @param string $response
     * @param string $remoteIp
     * @return bool
     */
    public function verifyResponse(string $response, string $remoteIp): bool
    {
        if (empty($response)) {
            return false;
        }

        try {
            $client = new Client(['timeout' => $this->timeout]);
            $result = $client->post($this->recaptchaVerificationUrl, [
                'form_params' => [
                    'secret' => $this->secretKey,
                    'response' => $response,
                    'remoteip' => $remoteIp,
                ],
            ]);

            $body = json_decode($result->getBody()->getContents(), true);

            if (!$body['success']) {
                Craft::warning("reCAPTCHA verification failed: " . implode(', ', $body['error-codes'] ?? []), __METHOD__);
                return false;
            }

            if ($body['score'] < $this->threshold) {
                Craft::warning("reCAPTCHA score too low: " . $body['score'], __METHOD__);
                return false;
            }

            return true;
        } catch (RequestException $e) {
            Craft::error("reCAPTCHA verification request failed: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
