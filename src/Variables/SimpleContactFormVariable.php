<?php

/**
 * @link https://craftcms.com/
 *
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace hybridinteractive\SimpleContactForm\Variables;

use Craft;
use craft\elements\db\ElementQueryInterface;
use hybridinteractive\SimpleContactForm\Plugin;
use hybridinteractive\SimpleContactForm\Elements\Submission;

class SimpleContactFormVariable
{
    public function name()
    {
        return Plugin::$plugin->name;
    }

    /**
     * Render reCAPTCHA widget
     *
     * @param string|null $localeOrAction
     * @return string
     */
    public function recaptcha(string $localeOrAction = null)
    {
        if (Plugin::$plugin->settings->recaptcha) {
            return Plugin::$plugin->simpleContactFormService->getRecaptcha()->render($localeOrAction);
        }

        return '';
    }

    /**
     * Get form submissions
     *
     * @param array|null $criteria
     * @return ElementQueryInterface
     */
    public function submissions($criteria = null): ElementQueryInterface
    {
        $query = Submission::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    /**
     * Get plugin settings
     *
     * @return \hybridinteractive\SimpleContactForm\Models\Settings
     */
    public function settings()
    {
        return Plugin::$plugin->getSettings();
    }
}
