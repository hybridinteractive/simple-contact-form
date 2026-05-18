# Migrating to Simple Contact Form

This plugin can replace **[Contact Form](https://plugins.craftcms.com/contact-form)** (official) plus typical “extensions” setups (database submissions, confirmation mail, reCAPTCHA) with one package. Payload shape is largely the same; URLs, Twig variables, and PHP events differ.

---

## 1. Before you switch

1. **Identify every place** forms post (Twig templates, Vue builds, modules).
2. Note any **PHP code** listening to Contact Form mailer events (`craft\contactform\Mailer`).
3. Decide whether you **need old submission rows** in the database long term (same plugin ≠ same tables; see §7).

---

## 2. Install Simple Contact Form

Install and enable Simple Contact Form, then migrate settings (`toEmail`, prepend strings, attachments, confirmation templates, etc.) using the Craft CP or **`config/simple-contact-form.php`** (recommended for production parity with your previous `contact-form.php` overrides).

Copy keys from **`config/simple-contact-form.php`** in this repo as a checklist.

---

## 3. Legacy action URL alias (optional bridge)

Older sites use **`/actions/contact-form/send`** (`{{ actionUrl('contact-form/send') }}`).

To keep that route working **without editing every template immediately**:

1. **Disable or uninstall** the official **Contact Form** plugin (`contact-form`).  
   Simple Contact Form will **not** register the alias while Contact Form remains **installed and enabled**, and will emit a Craft **log warning**.
2. In **`craft/config/simple-contact-form.php`** set:

```php
return [
    // …other settings…
    'enableLegacyContactFormRoutes' => true,
];
```

3. Optionally update templates later to **`/actions/simple-contact-form/send`** (`{{ actionUrl('simple-contact-form/send') }}`), then remove this flag once done.

Both URLs hit the **same controller** (`SendController::actionIndex`).

---

## 4. Twig cheat sheet

| Before (Contact Form + typical extensions) | After (Simple Contact Form) |
|-------------------------------------------|----------------------------|
| `{{ actionUrl('contact-form/send') }}` *(with legacy flag)* | `{{ actionUrl('simple-contact-form/send') }}` |
| Extensions: `craft.contactFormExtensions.recaptcha \| raw` *(handle may vary)* | `{{ craft.simpleContactForm.recaptcha() \| raw }}` |
| `craft.contactFormExtensions.submissions()` | `craft.simpleContactForm.submissions()` |

**Unchanged POST fields**

- `fromEmail`, `fromName`, `subject`
- `message[…]` *(e.g. `message[body]`, optional `message[formName]` for multi-form routing)*
- `attachment` (when attachments are allowed)

**Security change (required reading)**

Dangerous overrides must **no longer** come from `<input type="hidden">`: `toEmail`, `notificationTemplate`, `confirmationTemplate`, `confirmationSubject`, `disableRecaptcha`, `disableSaveSubmission`, `disableConfirmation`.

Recreate allowed behaviour via **Control Panel JSON** (“Per-form overrides”) or **`formOverrides`** in `config/simple-contact-form.php**, keyed by the same **`message[formName]`** handle each form posts.

---

## 5. PHP / modules

Rewrite listeners from:

```php
use craft\contactform\Mailer as ContactMailer;
use craft\contactform\events\SendEvent;

\Event::on(ContactMailer::class, ContactMailer::EVENT_BEFORE_SEND, function (SendEvent $e) {
    // …
});
```

to:

```php
use hybridinteractive\SimpleContactForm\Mailer;
use hybridinteractive\SimpleContactForm\Events\MessageSending;

\Event::on(Mailer::class, Mailer::EVENT_BEFORE_SEND, function (MessageSending $e) {
    // Same general properties: submission, message, toEmails; isSpam; plus spamReason
});
```

Similarly, **`EVENT_AFTER_SEND`** uses **`hybridinteractive\SimpleContactForm\Events\MessageSent`**.

Namespaces for **submission models / elements** are different; grep your codebase for `craft\contactform` and **`contactformextensions`**.

---

## 6. Config file mapping

| Typical `contact-form.php` keys | Simple Contact Form |
|--------------------------------|---------------------|
| `toEmail`, `prependSender`, `prependSubject`, `allowAttachments`, `successFlashMessage` | Same concept in plugin settings |
| Composer “allowed body fields” behaviour | **`allowedMessageFields`** |
| Behaviour you used to tuck in POST | **`formOverrides`** + **`allowedPublicFormNames`** |

---

## 7. Existing submission records

Historical rows from **Contact Form Extensions** live in **that plugin’s tables/elements**.

Simple Contact Form uses its **own** element storage. There is **no automatic importer** bundled.

Options:

- **Export → import** manually (CSV, Feed Me if you expose a compatible feed).
- Leave old data archived and attach read-only snapshots for auditors.
- Build a **one-off console command** tailored to your DB if you must merge history.

Plan this **before** you drop the old plugin tables.

---

## 8. Removing old plugins

When traffic is migrated and backups exist:

1. Remove legacy hidden POST overrides from templates (use `formOverrides` instead).
2. Turn off **`enableLegacyContactFormRoutes`** if you standardized on **`simple-contact-form/send`**.
3. Uninstall **Contact Form Extensions** and **Contact Form** (order per project; ensure no Twig/PHP references remain).

---

## Support

Hybrid Interactive maintains this fork; vendor-specific SLA is up to your distribution/licensing. Plugin Store installs should follow whichever support channel that listing documents.
