# Simple Contact Form

A comprehensive contact form plugin for Craft CMS that combines the functionality of the official Contact Form plugin with enhanced features like database storage, confirmation emails, template overrides, and reCAPTCHA support.

## Features

### Core Contact Form Features
- Send contact form submissions via email
- Support for file attachments
- Configurable email templates
- Form validation
- Flash message notifications

### Enhanced Features
- **Database Storage**: Save all form submissions to the database for viewing in the Control Panel
- **Confirmation Emails**: Send automatic confirmation emails to form submitters
- **Template Overrides**: Use custom email templates for notifications and confirmations
- **reCAPTCHA Support**: Protect forms from spam with reCAPTCHA v2 (invisible) or v3
- **Form Management**: View and manage all submissions through the Control Panel
- **Multi-form Support**: Support for multiple forms with different names

## Installation

1. Copy this plugin to your `plugins/` directory
2. Install via Composer:
   ```bash
   composer require hybridinteractive/simple-contact-form
   ```
3. Install the plugin in the Craft CMS Control Panel

## Configuration

### Basic Settings
- **To Email**: The email address(es) that form submissions will be sent to
- **Sender Text**: Text prepended to the "From" name in emails
- **Subject Text**: Text prepended to email subjects
- **Allow Attachments**: Enable/disable file upload support
- **Success Flash Message**: Message shown after successful form submission

### Database Settings
- **Save submissions to database**: Enable to store all submissions in the database for viewing in the Control Panel

### Email Settings
- **Enable template overwrite**: Allow custom email templates
- **Notification Template**: Custom template for notification emails
- **Enable confirmation emails**: Send confirmation emails to form submitters
- **Confirmation Template**: Custom template for confirmation emails
- **Confirmation Subject**: Subject line for confirmation emails

### reCAPTCHA Settings
- **Enable reCAPTCHA**: Protect forms from spam
- **reCAPTCHA Version**: Choose between v2 (invisible) or v3
- **Site Key & Secret Key**: Your reCAPTCHA keys from Google
- **Threshold**: Minimum score for reCAPTCHA v3 (0.0 to 1.0)
- **Hide Badge**: Hide the reCAPTCHA badge

## Usage

### Basic Form

```html
<form method="post" action="">
    {{ csrfInput() }}
    {{ actionInput('simple-contact-form/send') }}
    
    <input type="text" name="fromName" value="{{ message.fromName ?? '' }}" placeholder="Your Name">
    <input type="email" name="fromEmail" value="{{ message.fromEmail ?? '' }}" placeholder="Your Email">
    <input type="text" name="subject" value="{{ message.subject ?? '' }}" placeholder="Subject">
    <textarea name="message[body]" placeholder="Your Message">{{ message.message.body ?? '' }}</textarea>
    
    <button type="submit">Send Message</button>
</form>
```

### With reCAPTCHA

```html
<form method="post" action="">
    {{ csrfInput() }}
    {{ actionInput('simple-contact-form/send') }}
    
    <input type="text" name="fromName" value="{{ message.fromName ?? '' }}" placeholder="Your Name">
    <input type="email" name="fromEmail" value="{{ message.fromEmail ?? '' }}" placeholder="Your Email">
    <input type="text" name="subject" value="{{ message.subject ?? '' }}" placeholder="Subject">
    <textarea name="message[body]" placeholder="Your Message">{{ message.message.body ?? '' }}</textarea>
    
    {{ craft.simpleContactForm.recaptcha() |raw }}
    
    <button type="submit">Send Message</button>
</form>
```

### With reCAPTCHA v3

```html
<form method="post" action="">
    {{ csrfInput() }}
    {{ actionInput('simple-contact-form/send') }}
    
    <input type="text" name="fromName" value="{{ message.fromName ?? '' }}" placeholder="Your Name">
    <input type="email" name="fromEmail" value="{{ message.fromEmail ?? '' }}" placeholder="Your Email">
    <input type="text" name="subject" value="{{ message.subject ?? '' }}" placeholder="Subject">
    <textarea name="message[body]" placeholder="Your Message">{{ message.message.body ?? '' }}</textarea>
    
    {{ craft.simpleContactForm.recaptcha('contact') |raw }}
    
    <button type="submit">Send Message</button>
</form>
```

### Custom Form Names

```html
<input type="hidden" name="message[formName]" value="support">
```

### Override Email Templates

```html
<input type="hidden" name="message[notificationTemplate]" value="{{ 'custom-notification'|hash }}">
<input type="hidden" name="message[confirmationTemplate]" value="{{ 'custom-confirmation'|hash }}">
```

### Disable Features Per Form

```html
<input type="hidden" name="message[disableRecaptcha]" value="true">
<input type="hidden" name="message[disableSaveSubmission]" value="true">
<input type="hidden" name="message[disableConfirmation]" value="true">
```

## Template Variables

### Get Form Submissions

```twig
{% for submission in craft.simpleContactForm.submissions %}
    <p>{{ submission.dateCreated|date('d-m-Y H:i') }} - {{ submission.fromEmail }} - {{ submission.fromName }}</p>
{% endfor %}
```

### Get Plugin Settings

```twig
{% if craft.simpleContactForm.settings.enableDatabase %}
    <p>Database storage is enabled</p>
{% endif %}
```

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## License

MIT

## Credits

This plugin combines functionality from:
- [Craft CMS Contact Form](https://github.com/craftcms/contact-form) by Pixel & Tonic
- [Craft Contact Form Extensions](https://github.com/hybridinteractive/craft-contact-form-extensions) by Hybrid Interactive

## Support

If you find this plugin useful, consider [buying us a coffee](https://buymeacoffee.com/himp).
