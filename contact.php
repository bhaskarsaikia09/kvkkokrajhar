<?php
require_once __DIR__ . '/app/auth.php';
$page_title = 'Contact | ' . APP_NAME;
$sent = !empty($_SESSION['contact_form_success']);
unset($_SESSION['contact_form_success']);
$contact = contact_details();
$error = '';
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($formData as $field => $value) {
        $formData[$field] = auth_safe_session_text($_POST[$field] ?? '', $field === 'message' ? 2000 : 254);
    }

    $now = time();
    $attempts = $_SESSION['contact_form_attempts'] ?? [];
    if (!is_array($attempts)) {
        $attempts = [];
    }
    $attempts = array_values(array_filter(
        $attempts,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - 600)
    ));

    if (!auth_request_is_same_origin() || !csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form session expired. Please refresh the page and try again.';
    } elseif (auth_safe_session_text($_POST['website'] ?? '', 200) !== '') {
        $error = 'The submission could not be verified.';
    } elseif (count($attempts) >= 6) {
        $error = 'Too many enquiries have been submitted recently. Please try again later.';
    } else {
        $attempts[] = $now;
        $_SESSION['contact_form_attempts'] = $attempts;

        $nameLength = function_exists('mb_strlen') ? mb_strlen($formData['name'], 'UTF-8') : strlen($formData['name']);
        $subjectLength = function_exists('mb_strlen') ? mb_strlen($formData['subject'], 'UTF-8') : strlen($formData['subject']);
        $messageLength = function_exists('mb_strlen') ? mb_strlen($formData['message'], 'UTF-8') : strlen($formData['message']);
        $phoneDigits = preg_replace('/\D+/', '', $formData['phone']) ?? '';

        if ($nameLength < 2 || $nameLength > 120) {
            $error = 'Please enter a valid name.';
        } elseif ($formData['email'] !== '' && filter_var($formData['email'], FILTER_VALIDATE_EMAIL) === false) {
            $error = 'Please enter a valid email address.';
        } elseif ($formData['phone'] !== '' && (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15)) {
            $error = 'Please enter a valid phone number.';
        } elseif ($subjectLength < 2 || $subjectLength > 150) {
            $error = 'Please enter a valid subject.';
        } elseif ($messageLength < 10 || $messageLength > 2000) {
            $error = 'Please provide a message between 10 and 2000 characters.';
        }
    }

    if ($error === '') {
        try {
            $pdo = db();
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('Database connection is unavailable.');
            }

            $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(array_values($formData));
            $_SESSION['contact_form_success'] = true;
            csrf_rotate();
            header('Location: contact.php#contact-form', true, 303);
            exit;
        } catch (Throwable $exception) {
            error_log('Contact form submission failed: ' . $exception->getMessage());
            $error = 'We could not save your enquiry at this time. Please try again shortly.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero contact-hero">
    <div class="container">
        <p class="eyebrow">Contact</p>
        <h1 color="white">Reach KVK Kokrajhar</h1>
        <p>Visit us at Telipara, Gossaigaon or send your query to connect with our subject matter specialists.</p>
        <div class="contact-hero__actions">
            <a class="btn btn--light" href="tel:<?= e((string) ($contact['phone'] ?? '+917896793028')) ?>"><i class="fa-solid fa-phone"></i> Call office</a>
            <a class="btn btn--light" href="mailto:<?= e((string) ($contact['email'] ?? 'kvk_kokrajhar@aau.ac.in')) ?>"><i class="fa-solid fa-envelope"></i> Email us</a>
        </div>
    </div>
</section>
<section class="section contact-page">
    <div class="container">
        <div class="contact-shell">
            <div class="contact-map" aria-label="KVK Kokrajhar map">
                <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d4274.535754502364!2d89.944471!3d26.449574000000002!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39e27e42057c95ab%3A0x8a99a17d395bb1bc!2sKrishi%20Vigyan%20Kendra%2C%20Kokrajhar!5e1!3m2!1sen!2sin!4v1717309191555!5m2!1sen!2sin" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Krishi Vigyan Kendra Kokrajhar location"></iframe>
            </div>
            <form class="form contact-form" method="post" id="contact-form" action="contact.php#contact-form" accept-charset="UTF-8">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div hidden aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
                <p class="eyebrow">Send enquiry</p>
                <h2>How can we help?</h2>
                <p>Share your crop, livestock, fishery, training, or advisory requirement. The KVK team will review your message and respond through the contact details you provide.</p>
                <?php if ($sent): ?><p class="success">Your message has been received. Thank you for contacting KVK Kokrajhar.</p><?php endif; ?>
                <?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
                <div class="contact-form__grid">
                    <label for="contact-name"><span>Name</span><input id="contact-name" name="name" placeholder="Full name" maxlength="120" value="<?= e($formData['name']) ?>" required></label>
                    <label for="contact-email"><span>Email</span><input id="contact-email" type="email" name="email" placeholder="Email address" maxlength="254" value="<?= e($formData['email']) ?>"></label>
                    <label for="contact-phone"><span>Phone</span><input id="contact-phone" name="phone" placeholder="Mobile number" maxlength="30" value="<?= e($formData['phone']) ?>"></label>
                    <label for="contact-subject"><span>Subject</span><input id="contact-subject" name="subject" placeholder="Subject" maxlength="150" value="<?= e($formData['subject']) ?>" required></label>
                    <label class="contact-form__message" for="contact-message"><span>Message</span><textarea id="contact-message" name="message" rows="6" maxlength="2000" placeholder="Write your message" required><?= e($formData['message']) ?></textarea></label>
                </div>
                <div class="contact-form__actions">
                    <button class="btn btn--primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Send enquiry</button>
                    <?php if (!empty($contact['map_link'])): ?>
                        <a class="btn btn--light" href="<?= e((string) $contact['map_link']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-location-dot"></i> Open map</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="contact-info-grid">
            <article class="contact-info-card">
                <i class="fa-solid fa-location-dot"></i>
                <div>
                    <span>Office address</span>
                    <h3>KVK Kokrajhar</h3>
                    <p><?= nl2br(e((string) ($contact['office_address'] ?? 'Telipara, Gossaigaon, Assam - 783360'))) ?></p>
                </div>
            </article>
            <article class="contact-info-card">
                <i class="fa-solid fa-phone-volume"></i>
                <div>
                    <span>Phone and email</span>
                    <h3><?= e((string) ($contact['phone'] ?? '+91 7896793028')) ?></h3>
                    <p><?= e((string) ($contact['email'] ?? 'kvk_kokrajhar@aau.ac.in')) ?></p>
                </div>
            </article>
            <article class="contact-info-card">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <span>Working hours</span>
                    <h3><?= e((string) ($contact['working_hours'] ?? 'Mon-Sat: 8am-5pm')) ?></h3>
                    <p>Sunday closed</p>
                </div>
            </article>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
