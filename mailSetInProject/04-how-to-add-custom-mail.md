# Section 4: How to Add Custom Mailables & Notifications

## 1. Overview
Currently, the application does not have custom Mailables in `app/Mail/` or custom Notifications in `app/Notifications/`. When business requirements call for sending custom emails (e.g., Journal submission notifications, Peer Review assignments, Certificate awards), follow the standard Laravel 12 patterns described below.

---

## 2. Approach A: Creating a Mailable (Recommended for Transactional Emails)

### Step 1: Generate Mailable Class
Run the Artisan command:
```bash
php artisan make:mail JournalSubmissionReceived --markdown=emails.journals.submission-received
```
This will create:
- `app/Mail/JournalSubmissionReceived.php`
- `resources/views/emails/journals/submission-received.blade.php`

### Step 2: Configure Mailable
```php
namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JournalSubmissionReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public array $submissionData)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Journal Submission Received - Scholar9',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.journals.submission-received',
        );
    }
}
```

### Step 3: Triggering Mail Dispatch
From any controller, Livewire component, or service:
```php
use App\Mail\JournalSubmissionReceived;
use Illuminate\Support\Facades\Mail;

Mail::to($user->email)->send(new JournalSubmissionReceived($user, $submissionData));
```
*(When `implements ShouldQueue` is applied, `Mail::send()` automatically pushes it to the queue).*

---

## 3. Approach B: Creating a Notification (Recommended for Multi-Channel Alerts)

If an alert should be sent via Email AND stored in the database / UI bell icon:

### Step 1: Generate Notification
```bash
php artisan make:notification PeerReviewAssignedNotification
```

### Step 2: Send Notification
```php
use App\Notifications\PeerReviewAssignedNotification;

$user->notify(new PeerReviewAssignedNotification($reviewDetails));
```

---

## 4. Testing Mail in Pest

Following project testing conventions, always write Pest tests using `Mail::fake()`:

```php
use App\Mail\JournalSubmissionReceived;
use Illuminate\Support\Facades\Mail;

test('submission triggers confirmation email', function () {
    Mail::fake();

    // Trigger action (e.g., Livewire call)
    
    Mail::assertQueued(JournalSubmissionReceived::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});
```

