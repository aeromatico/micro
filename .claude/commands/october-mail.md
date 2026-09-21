Create and send emails in OctoberCMS 4 / Laravel 12 — Mailable classes, Twig templates, and OctoberCMS mail views.

## Usage
`/october-mail <Vendor>/<Plugin> <MailName> [--queue] [--markdown]`

**Examples:**
- `/october-mail Micro/Blog WelcomeEmail --queue` — Queued welcome email
- `/october-mail Micro/Shop OrderConfirmation --queue` — Order confirmation
- `/october-mail Micro/Blog ContactMessage` — Simple contact form notification

---

## What to create

Plugin base: `/www/wwwroot/micro.clouds.com.bo/plugins/{vendor_lower}/{plugin_lower}/`

### 1. Mailable class — `mail/{MailName}.php`

```php
<?php namespace {Vendor}\{Plugin}\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Remove ShouldQueue if not --queue
class {MailName} extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        // Pass minimal data — primitives or small models
        // public readonly string $recipientName,
        // public readonly \{Vendor}\{Plugin}\Models\Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu asunto aquí',
            // from: new Address('no-reply@micro.clouds.com.bo', 'Micro Clouds'),
            // replyTo: [new Address($this->replyTo)],
        );
    }

    public function content(): Content
    {
        return new Content(
            // OctoberCMS mail view (registered below):
            view: '{vendor_lower}.{plugin_lower}::{mail_lower}',
            
            // Data passed to the view:
            with: [
                // 'name'  => $this->recipientName,
                // 'order' => $this->order,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            // Attachment::fromPath('/path/to/file.pdf'),
            // Attachment::fromStorageDisk('local', 'invoices/1234.pdf')->as('invoice.pdf'),
        ];
    }
}
```

### 2. OctoberCMS mail template — `views/mail/{mailname}.htm`

OctoberCMS wraps this automatically in the admin-configured mail layout.

```html
subject = "{Mail subject}"
==
{# HTML version #}
<h2>Hola {{ name }}!</h2>

<p>Gracias por tu mensaje. Nos pondremos en contacto contigo pronto.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
    <tr>
        <td style="background: #f8fafc; border-radius: 8px; padding: 16px;">
            <strong>Detalles:</strong><br>
            {# order details, dynamic content, etc. #}
        </td>
    </tr>
</table>

<p style="margin-top: 24px;">
    <a href="{{ button_url }}" style="background: #4f46e5; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">
        {{ button_text }}
    </a>
</p>

<p style="color: #9ca3af; font-size: 12px; margin-top: 32px;">
    Si no solicitaste esto, puedes ignorar este correo.
</p>
==
{# Plain text version (auto-generated if omitted) #}
Hola {{ name }},

Gracias por tu mensaje. Nos pondremos en contacto pronto.
```

### 3. Register mail view in Plugin.php

```php
public function registerMailTemplates(): array
{
    return [
        '{vendor_lower}.{plugin_lower}::{mail_lower}' => 'views/mail/{mailname}.htm',
    ];
}
```

### 4. Sending the email

```php
use Illuminate\Support\Facades\Mail;
use {Vendor}\{Plugin}\Mail\{MailName};

// Standard send
Mail::to('user@example.com')->send(new {MailName}());

// With name
Mail::to(['address' => 'user@example.com', 'name' => 'Juan'])->send(new {MailName}());

// CC / BCC
Mail::to($user)
    ->cc($manager)
    ->bcc('archive@company.com')
    ->send(new {MailName}());

// Queued (if ShouldQueue)
Mail::to($user)->queue(new {MailName}());

// Delayed
Mail::to($user)->later(now()->addMinutes(10), new {MailName}());
```

### 5. OctoberCMS sendTo helper (uses CMS mail layout)

```php
// Uses October's mail system with template from backend
\Mail::sendTo($email, '{vendor_lower}.{plugin_lower}::{mail_lower}', [
    'name'    => $user->name,
    'content' => 'Your message here',
]);
```

---

### Testing emails in development

`.env` is configured to log emails. To see them:
```bash
tail -f /www/wwwroot/micro.clouds.com.bo/storage/logs/laravel.log | grep -A 20 "Message-ID"
```

To use Mailpit for local email testing:
```bash
# Pull and run mailpit
docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit
```

Then update `.env`:
```
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_MAILER=smtp
```

---

Report all files created, the registration code for Plugin.php, and usage examples.
