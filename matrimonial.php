<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('bsg_matrimonial');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postValue(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function matrimonialCsrfToken(): string
{
    if (empty($_SESSION['matrimonial_csrf'])) {
        $_SESSION['matrimonial_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['matrimonial_csrf'];
}

function isValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function isValidTime(string $value): bool
{
    $time = DateTimeImmutable::createFromFormat('!H:i', $value);
    return $time !== false && $time->format('H:i') === $value;
}

$errors = [];
$success = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(matrimonialCsrfToken(), postValue('csrf_token'))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    if (postValue('website') !== '') {
        $errors[] = 'The registration could not be submitted.';
    }

    $data = [
        'profile_type' => postValue('profile_type'),
        'full_name' => postValue('full_name'),
        'father_name' => postValue('father_name'),
        'address' => postValue('address'),
        'date_of_birth' => postValue('date_of_birth'),
        'birth_time' => postValue('birth_time') ?: null,
        'birth_place' => postValue('birth_place'),
        'height_cm' => postValue('height_cm'),
        'weight_kg' => postValue('weight_kg'),
        'manglik_status' => postValue('manglik_status'),
        'education' => postValue('education'),
        'professional_qualification' => postValue('professional_qualification') ?: null,
        'occupation' => postValue('occupation'),
        'income_amount' => postValue('income_amount') ?: null,
        'income_period' => postValue('income_period') ?: null,
        'other_details' => postValue('other_details') ?: null,
        'email' => strtolower(postValue('email')),
        'mobile' => preg_replace('/\D+/', '', postValue('mobile')) ?? '',
        'registration_charge' => postValue('registration_charge') ?: null,
        'payment_method' => postValue('payment_method') ?: null,
        'payment_reference' => postValue('payment_reference') ?: null,
        'payment_date' => postValue('payment_date') ?: null,
    ];

    foreach (['profile_type', 'full_name', 'father_name', 'address', 'date_of_birth', 'birth_place', 'height_cm', 'weight_kg', 'manglik_status', 'education', 'occupation', 'email', 'mobile'] as $field) {
        if ($data[$field] === '') {
            $errors[] = 'Please complete all required fields.';
        }
    }

    $errors = array_values(array_unique($errors));
    if (!in_array($data['profile_type'], ['Boy', 'Girl'], true)) $errors[] = 'Select Boy or Girl.';
    $occupations = $data['profile_type'] === 'Girl'
        ? ['Job', 'Business', 'Professional', 'Teacher', 'Home Maker', 'Student', 'Other']
        : ['Job', 'Business', 'Professional', 'Government Service', 'Self Employed', 'Student', 'Other'];
    if (!in_array($data['occupation'], $occupations, true)) $errors[] = 'Select a valid occupation.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[6-9][0-9]{9}$/', $data['mobile'])) $errors[] = 'Enter a valid 10-digit Indian mobile number.';
    if (!in_array($data['manglik_status'], ['Manglik', 'Non-Manglik', 'Partial Manglik', 'Not Known'], true)) $errors[] = 'Select a valid Manglik status.';
    if ($data['income_period'] !== null && !in_array($data['income_period'], ['Monthly', 'Annual'], true)) $errors[] = 'Select a valid income period.';
    if ($data['payment_method'] !== null && !in_array($data['payment_method'], ['Draft', 'Bank Transfer', 'Paytm', 'Cash', 'Other'], true)) $errors[] = 'Select a valid payment method.';
    $height = filter_var($data['height_cm'], FILTER_VALIDATE_FLOAT);
    $weight = filter_var($data['weight_kg'], FILTER_VALIDATE_FLOAT);
    if ($height === false || $height < 100 || $height > 250) $errors[] = 'Height must be between 100 and 250 cm.';
    if ($weight === false || $weight < 25 || $weight > 300) $errors[] = 'Weight must be between 25 and 300 kg.';
    if (!isValidDate($data['date_of_birth'])) {
        $errors[] = 'Enter a valid date of birth.';
    } elseif ($data['date_of_birth'] > date('Y-m-d')) {
        $errors[] = 'Date of birth cannot be in the future.';
    }
    if ($data['birth_time'] !== null && !isValidTime($data['birth_time'])) $errors[] = 'Enter a valid birth time.';
    if ($data['payment_date'] !== null && (!isValidDate($data['payment_date']) || $data['payment_date'] > date('Y-m-d'))) $errors[] = 'Enter a valid payment date that is not in the future.';
    foreach (['income_amount' => 'Income amount', 'registration_charge' => 'Registration charge'] as $field => $label) {
        if ($data[$field] !== null && (filter_var($data[$field], FILTER_VALIDATE_FLOAT) === false || (float) $data[$field] < 0)) {
            $errors[] = "{$label} must be a valid non-negative number.";
        }
    }
    foreach (['full_name' => 100, 'father_name' => 100, 'address' => 500, 'birth_place' => 150, 'education' => 500, 'professional_qualification' => 500, 'other_details' => 1000, 'email' => 150, 'payment_reference' => 100] as $field => $maximum) {
        if ($data[$field] !== null && mb_strlen((string) $data[$field]) > $maximum) $errors[] = 'One or more fields exceed the allowed length.';
    }
    if (postValue('consent') !== '1') $errors[] = 'You must confirm that the information is correct.';

    $images = $_FILES['images'] ?? null;
    $imageCount = $images && is_array($images['name'] ?? null) ? count(array_filter($images['name'], static fn($name): bool => $name !== '')) : 0;
    if ($imageCount === 0) $errors[] = 'Please upload at least one photograph.';
    if ($imageCount > 5) $errors[] = 'You can upload a maximum of 5 images.';

    $validImages = [];
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($images && is_array($images['name'] ?? null)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($images['name'] as $index => $originalName) {
            if ($originalName === '') continue;
            $error = (int) ($images['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            $size = (int) ($images['size'][$index] ?? 0);
            $temporaryPath = (string) ($images['tmp_name'][$index] ?? '');
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "Could not upload {$originalName}.";
                continue;
            }
            if ($size > 2 * 1024 * 1024) {
                $errors[] = "{$originalName} is larger than 2 MB.";
                continue;
            }
            if ($size < 1 || !is_uploaded_file($temporaryPath)) {
                $errors[] = "{$originalName} is not a valid uploaded image.";
                continue;
            }
            $mime = $finfo->file($temporaryPath) ?: '';
            if (!isset($allowedTypes[$mime])) {
                $errors[] = "{$originalName} must be a JPG, PNG, or WebP image.";
                continue;
            }
            if (@getimagesize($temporaryPath) === false) {
                $errors[] = "{$originalName} does not contain a valid image.";
                continue;
            }
            $validImages[] = compact('originalName', 'temporaryPath', 'size', 'mime') + ['extension' => $allowedTypes[$mime]];
        }
    }

    $errors = array_values(array_unique($errors));

    if ($errors === []) {
        $pdo = database();
        $movedFiles = [];
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO matrimonial_registrations (profile_type, full_name, father_name, address, date_of_birth, birth_time, birth_place, height_cm, weight_kg, manglik_status, education, professional_qualification, occupation, income_amount, income_period, other_details, email, mobile, registration_charge, payment_method, payment_reference, payment_date) VALUES (:profile_type, :full_name, :father_name, :address, :date_of_birth, :birth_time, :birth_place, :height_cm, :weight_kg, :manglik_status, :education, :professional_qualification, :occupation, :income_amount, :income_period, :other_details, :email, :mobile, :registration_charge, :payment_method, :payment_reference, :payment_date)');
            $statement->execute($data);
            $registrationId = (int) $pdo->lastInsertId();
            $uploadDirectory = __DIR__ . '/uploads/matrimonial';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('The photograph storage folder could not be created.');
            }
            if (!is_writable($uploadDirectory)) throw new RuntimeException('The photograph storage folder is not writable.');
            $imageStatement = $pdo->prepare('INSERT INTO matrimonial_images (matrimonial_registration_id, file_name, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)');
            foreach ($validImages as $image) {
                $fileName = $registrationId . '-' . bin2hex(random_bytes(12)) . '.' . $image['extension'];
                $destination = $uploadDirectory . '/' . $fileName;
                if (!move_uploaded_file($image['temporaryPath'], $destination)) throw new RuntimeException('An image could not be stored.');
                $movedFiles[] = $destination;
                $imageStatement->execute([$registrationId, $fileName, mb_substr(basename($image['originalName']), 0, 255), $image['mime'], $image['size']]);
            }
            $pdo->commit();
            $_SESSION['matrimonial_csrf'] = bin2hex(random_bytes(32));
            header('Location: matrimonial.php?saved=1#registration');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($movedFiles as $file) {
                if (is_file($file)) unlink($file);
            }
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'The registration could not be saved. Please try again.';
        }
    }
}

$hindiErrors = [
    'Your session expired. Please refresh the page and try again.' => 'आपका सत्र समाप्त हो गया है। कृपया पेज रीफ्रेश करके दोबारा प्रयास करें।',
    'The registration could not be submitted.' => 'पंजीकरण जमा नहीं किया जा सका।',
    'Please complete all required fields.' => 'कृपया सभी आवश्यक फ़ील्ड भरें।',
    'Select Boy or Girl.' => 'कृपया युवक या युवती चुनें।',
    'Select a valid occupation.' => 'कृपया सही व्यवसाय चुनें।',
    'Enter a valid email address.' => 'कृपया सही ईमेल पता दर्ज करें।',
    'Enter a valid 10-digit Indian mobile number.' => 'कृपया सही 10 अंकों का भारतीय मोबाइल नंबर दर्ज करें।',
    'Select a valid Manglik status.' => 'कृपया सही मांगलिक स्थिति चुनें।',
    'Select a valid income period.' => 'कृपया सही आय अवधि चुनें।',
    'Select a valid payment method.' => 'कृपया सही भुगतान विधि चुनें।',
    'Height must be between 100 and 250 cm.' => 'लंबाई 100 से 250 सेमी के बीच होनी चाहिए।',
    'Weight must be between 25 and 300 kg.' => 'वजन 25 से 300 किग्रा के बीच होना चाहिए।',
    'Date of birth cannot be in the future.' => 'जन्म तिथि भविष्य की नहीं हो सकती।',
    'Enter a valid date of birth.' => 'कृपया सही जन्म तिथि दर्ज करें।',
    'Enter a valid birth time.' => 'कृपया सही जन्म समय दर्ज करें।',
    'Enter a valid payment date that is not in the future.' => 'कृपया सही भुगतान तिथि दर्ज करें, जो भविष्य की न हो।',
    'Income amount must be a valid non-negative number.' => 'आय राशि शून्य या उससे अधिक की सही संख्या होनी चाहिए।',
    'Registration charge must be a valid non-negative number.' => 'पंजीकरण शुल्क शून्य या उससे अधिक की सही संख्या होना चाहिए।',
    'One or more fields exceed the allowed length.' => 'एक या अधिक फ़ील्ड अनुमत लंबाई से अधिक हैं।',
    'You must confirm that the information is correct.' => 'आपको पुष्टि करनी होगी कि दी गई जानकारी सही है।',
    'Please upload at least one photograph.' => 'कृपया कम से कम एक फोटो अपलोड करें।',
    'You can upload a maximum of 5 images.' => 'आप अधिकतम 5 फोटो अपलोड कर सकते हैं।',
    'The photograph storage folder could not be created.' => 'फोटो संग्रह फ़ोल्डर नहीं बनाया जा सका।',
    'The photograph storage folder is not writable.' => 'फोटो संग्रह फ़ोल्डर में लिखने की अनुमति नहीं है।',
    'An image could not be stored.' => 'एक फोटो सुरक्षित नहीं की जा सकी।',
    'The registration could not be saved. Please try again.' => 'पंजीकरण सुरक्षित नहीं किया जा सका। कृपया दोबारा प्रयास करें।',
];
?>
<!doctype html>
<html lang="hi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="25वां कायस्थ वैवाहिक परिचय सम्मेलन 2026 - भटनागर सभा गाजियाबाद">
    <title>वैवाहिक परिचय सम्मेलन 2026 | भटनागर सभा गाजियाबाद</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/matrimonial.css?v=6">
</head>

<body>
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="भटनागर सभा गाजियाबाद होम">
            <img src="assets/logo.jpeg" alt="भटनागर सभा गाजियाबाद लोगो">
            <span><strong data-i18n="brandName">भटनागर सभा</strong><small data-i18n="brandLocation">गाजियाबाद (पंजी.)</small></span>
        </a>
        <div class="header-actions">
            <div class="language-switcher" role="group" aria-label="Choose language">
                <button type="button" data-language="hi" aria-pressed="true">हिंदी</button>
                <button type="button" data-language="en" aria-pressed="false">English</button>
            </div>
            <a class="header-cta" href="#registration"><i class="fa-solid fa-file-pen" aria-hidden="true"></i> <span data-i18n="registerNow">पंजीकरण करें</span></a>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <p class="hero-kicker"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i> <span data-i18n="invocation">जय श्री चित्रगुप्त भगवान</span></p>
                <span class="anniversary"><i class="fa-solid fa-award" aria-hidden="true"></i> <span data-i18n="silverJubilee">रजत जयंती वर्ष</span></span>
                <h1><span data-i18n="heroLineOne">25वां कायस्थ</span> <span data-i18n="heroLineTwo">वैवाहिक परिचय सम्मेलन</span> <em>2026</em></h1>
                <p class="hero-lead" data-i18n="heroLead">विवाह योग्य युवक-युवतियों और उनके परिवारों के लिए परिचय, संवाद और नए संबंधों का विश्वसनीय मंच।</p>
                <div class="event-summary">
                    <div><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span><small data-i18n="dateLabel">दिनांक</small><strong data-i18n="eventDate">रविवार, 20 दिसंबर 2026</strong></span></div>
                    <div><i class="fa-solid fa-clock" aria-hidden="true"></i><span><small data-i18n="timeLabel">समय</small><strong data-i18n="eventTime">प्रातः 10 बजे – सायं 4 बजे</strong></span></div>
                    <div><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><small data-i18n="venueLabel">स्थान</small><strong data-i18n="venue">महाराजा अग्रसेन भवन, लोहिया नगर</strong></span></div>
                </div>
                <div class="hero-actions">
                    <a class="primary-button" href="#event-details"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <span data-i18n="viewDetails">पूरा विवरण देखें</span></a>
                    <a class="secondary-button" href="tel:+919811395212"><i class="fa-solid fa-phone" aria-hidden="true"></i> <span data-i18n="contact">संपर्क करें</span></a>
                </div>
            </div>
            <figure class="poster-card">
                <img src="assets/matrimonial-event-2026.jpeg" alt="25वां कायस्थ वैवाहिक परिचय सम्मेलन 2026 पोस्टर">
                <figcaption><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <span data-i18n="officialInvitation">कार्यक्रम का आधिकारिक आमंत्रण</span></figcaption>
            </figure>
        </section>

        <section class="information-strip" id="event-details" aria-label="महत्वपूर्ण जानकारी">
            <article><i class="fa-solid fa-users" aria-hidden="true"></i><div><strong data-i18n="familyInvited">परिवार सहित आमंत्रित</strong><span data-i18n="familyDescription">बच्चों के साथ उपस्थित होने से परिचय और संबंध तय होने की संभावना बढ़ती है।</span></div></article>
            <article><i class="fa-solid fa-file-signature" aria-hidden="true"></i><div><strong data-i18n="deadline">अंतिम तिथि: 15 नवंबर 2026</strong><span data-i18n="deadlineDescription">फॉर्म और भुगतान की जानकारी निर्धारित तिथि तक भेजें।</span></div></article>
            <article><i class="fa-solid fa-shield-heart" aria-hidden="true"></i><div><strong data-i18n="trustedEvent">विश्वसनीय आयोजन</strong><span data-i18n="trustedDescription">भटनागर सभा गाजियाबाद द्वारा आयोजित 25वां परिचय सम्मेलन।</span></div></article>
        </section>

        <section class="payment-section">
            <div class="section-heading">
                <p data-i18n="registrationDetails">पंजीकरण विवरण</p>
                <h2 data-i18n="paymentInformation">भुगतान की जानकारी</h2>
                <span data-i18n="paymentInstruction">भुगतान के बाद Transaction ID, दिनांक और स्क्रीनशॉट फॉर्म में अवश्य भरें।</span>
            </div>
            <div class="payment-grid">
                <article><i class="fa-solid fa-building-columns" aria-hidden="true"></i><div><small data-i18n="bankAccount">बैंक खाता</small><strong>711910110004424</strong><span>Bank of India, RDC Rajnagar, Ghaziabad</span></div></article>
                <article><i class="fa-solid fa-code-branch" aria-hidden="true"></i><div><small data-i18n="ifscCode">IFSC कोड</small><strong>BKID0007119</strong><span data-i18n="accountName">खाता नाम: भटनागर सभा गाजियाबाद</span></div></article>
                <article><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i><div><small data-i18n="upiNumber">UPI नंबर</small><strong>7042347414</strong><span data-i18n="upiAccepted">UPI के माध्यम से भुगतान स्वीकार है</span></div></article>
            </div>
        </section>

        <section class="registration-section" id="registration">
            <div class="registration-intro">
                <p data-i18n="registrationForm">वैवाहिक पंजीकरण</p>
                <h2 data-i18n="completeProfile">अपना परिचय फॉर्म भरें</h2>
                <span data-i18n="formHelp">कृपया सभी आवश्यक जानकारी सही भरें और अधिकतम 5 फोटो अपलोड करें।</span>
            </div>

            <?php if ($success): ?>
                <div class="notice success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong data-i18n="successTitle">पंजीकरण सफल रहा</strong><span data-i18n="successMessage">आपका वैवाहिक पंजीकरण सफलतापूर्वक जमा हो गया है।</span></div></div>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <div class="notice error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div><strong><?= postValue('form_language') === 'en' ? 'Please correct the following:' : 'कृपया निम्न त्रुटियां ठीक करें:' ?></strong><ul><?php foreach ($errors as $error): ?><li><?= e(postValue('form_language') === 'en' ? $error : ($hindiErrors[$error] ?? $error)) ?></li><?php endforeach; ?></ul></div></div>
            <?php endif; ?>

            <form class="matrimonial-form" method="post" enctype="multipart/form-data" id="matrimonialForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(matrimonialCsrfToken()) ?>">
                <input type="hidden" name="form_language" id="formLanguage" value="<?= e(postValue('form_language') ?: 'hi') ?>">
                <label class="honeypot" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>

                <fieldset class="profile-choice">
                    <legend data-i18n="registeringFor">आप किसका पंजीकरण कर रहे हैं? *</legend>
                    <label><input type="radio" name="profile_type" value="Boy" <?= postValue('profile_type') === 'Boy' ? 'checked' : '' ?> required><span><i class="fa-solid fa-person" aria-hidden="true"></i><strong data-i18n="boy">युवक</strong><small data-i18n="selectBoy">Boy</small></span></label>
                    <label><input type="radio" name="profile_type" value="Girl" <?= postValue('profile_type') === 'Girl' ? 'checked' : '' ?> required><span><i class="fa-solid fa-person-dress" aria-hidden="true"></i><strong data-i18n="girl">युवती</strong><small data-i18n="selectGirl">Girl</small></span></label>
                </fieldset>

                <div id="formContent" <?= postValue('profile_type') === '' ? 'hidden' : '' ?>>
                    <section class="form-section">
                        <div class="form-section-title"><span>01</span><div><h3 data-i18n="personalDetails">व्यक्तिगत विवरण</h3><p data-i18n="personalHelp">व्यक्तिगत, जन्म और शारीरिक जानकारी</p></div></div>
                        <div class="field-grid">
                            <label><span data-i18n="fullName">पूरा नाम *</span><input name="full_name" maxlength="100" value="<?= e(postValue('full_name')) ?>" required></label>
                            <label><span data-i18n="fatherName">पिता का नाम *</span><input name="father_name" maxlength="100" value="<?= e(postValue('father_name')) ?>" required></label>
                            <label class="wide"><span data-i18n="address">पता *</span><textarea name="address" maxlength="500" rows="3" required><?= e(postValue('address')) ?></textarea></label>
                            <label><span data-i18n="dateOfBirth">जन्म तिथि *</span><input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= e(postValue('date_of_birth')) ?>" required></label>
                            <label><span data-i18n="birthTime">जन्म समय</span><input type="time" name="birth_time" value="<?= e(postValue('birth_time')) ?>"></label>
                            <label><span data-i18n="birthPlace">जन्म स्थान *</span><input name="birth_place" maxlength="150" value="<?= e(postValue('birth_place')) ?>" required></label>
                            <label><span data-i18n="height">लंबाई (सेमी) *</span><input type="number" name="height_cm" min="100" max="250" step="0.01" value="<?= e(postValue('height_cm')) ?>" required></label>
                            <label><span data-i18n="weight">वजन (किग्रा) *</span><input type="number" name="weight_kg" min="25" max="300" step="0.01" value="<?= e(postValue('weight_kg')) ?>" required></label>
                            <label><span data-i18n="manglikStatus">मांगलिक स्थिति *</span><select name="manglik_status" required><option value="" data-i18n="selectStatus">स्थिति चुनें</option><?php foreach (['Manglik' => 'manglik', 'Non-Manglik' => 'nonManglik', 'Partial Manglik' => 'partialManglik', 'Not Known' => 'notKnown'] as $option => $translationKey): ?><option value="<?= e($option) ?>" data-i18n="<?= e($translationKey) ?>" <?= postValue('manglik_status') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
                        </div>
                    </section>

                    <section class="form-section">
                        <div class="form-section-title"><span>02</span><div><h3 data-i18n="educationProfession">शिक्षा और व्यवसाय</h3><p data-i18n="educationHelp">योग्यता, कार्य और आय का विवरण</p></div></div>
                        <div class="field-grid">
                            <label class="wide"><span data-i18n="education">शैक्षिक योग्यता *</span><textarea name="education" maxlength="500" rows="2" required><?= e(postValue('education')) ?></textarea></label>
                            <label class="wide"><span data-i18n="professionalQualification">व्यावसायिक योग्यता</span><textarea name="professional_qualification" maxlength="500" rows="2"><?= e(postValue('professional_qualification')) ?></textarea></label>
                            <label><span data-i18n="occupation">व्यवसाय / नौकरी *</span><select name="occupation" id="occupationSelect" data-selected="<?= e(postValue('occupation')) ?>" required><option value="">Select occupation</option></select></label>
                            <label><span data-i18n="incomeAmount">आय राशि</span><input type="number" name="income_amount" min="0" step="0.01" value="<?= e(postValue('income_amount')) ?>"></label>
                            <label><span data-i18n="incomePeriod">आय अवधि</span><select name="income_period"><option value="" data-i18n="selectPeriod">अवधि चुनें</option><option value="Monthly" data-i18n="monthly" <?= postValue('income_period') === 'Monthly' ? 'selected' : '' ?>>Monthly</option><option value="Annual" data-i18n="annual" <?= postValue('income_period') === 'Annual' ? 'selected' : '' ?>>Annual</option></select></label>
                            <label class="wide"><span data-i18n="otherDetails">अन्य विवरण</span><textarea name="other_details" maxlength="1000" rows="3"><?= e(postValue('other_details')) ?></textarea></label>
                        </div>
                    </section>

                    <section class="form-section">
                        <div class="form-section-title"><span>03</span><div><h3 data-i18n="contactPayment">संपर्क और भुगतान</h3><p data-i18n="contactPaymentHelp">संपर्क एवं पंजीकरण भुगतान का विवरण</p></div></div>
                        <div class="field-grid">
                            <label><span data-i18n="email">ईमेल आईडी *</span><input type="email" name="email" maxlength="150" value="<?= e(postValue('email')) ?>" required></label>
                            <label><span data-i18n="mobile">फोन / मोबाइल नंबर *</span><input type="tel" name="mobile" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" autocomplete="tel" value="<?= e(postValue('mobile')) ?>" required></label>
                            <label><span data-i18n="registrationCharge">पंजीकरण शुल्क (₹)</span><input type="number" name="registration_charge" min="0" step="0.01" value="<?= e(postValue('registration_charge')) ?>"></label>
                            <label><span data-i18n="paymentMethod">भुगतान विधि</span><select name="payment_method"><option value="" data-i18n="selectMethod">विधि चुनें</option><?php foreach (['Draft' => 'draft', 'Bank Transfer' => 'bankTransfer', 'Paytm' => 'paytm', 'Cash' => 'cash', 'Other' => 'other'] as $option => $translationKey): ?><option value="<?= e($option) ?>" data-i18n="<?= e($translationKey) ?>" <?= postValue('payment_method') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
                            <label><span data-i18n="paymentReference">भुगतान संदर्भ संख्या</span><input name="payment_reference" maxlength="100" value="<?= e(postValue('payment_reference')) ?>"></label>
                            <label><span data-i18n="paymentDate">भुगतान तिथि</span><input type="date" name="payment_date" max="<?= date('Y-m-d') ?>" value="<?= e(postValue('payment_date')) ?>"></label>
                        </div>
                    </section>

                    <section class="form-section">
                        <div class="form-section-title"><span>04</span><div><h3 data-i18n="photographs">फोटो</h3><p data-i18n="photoHelp">JPG, PNG या WebP · प्रत्येक अधिकतम 2 MB · अधिकतम 5 फोटो</p></div></div>
                        <div class="upload-grid" id="uploadList">
                            <div class="upload-card"><label class="upload-picker" for="matrimonialImage1"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i><strong data-i18n="chooseImage">फोटो चुनें</strong><small>JPG, PNG or WebP</small></label><input class="image-input" id="matrimonialImage1" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" required><div class="image-preview" hidden><img alt="Selected photograph preview"><span></span><button type="button" class="remove-image" aria-label="Remove image"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div></div>
                        </div>
                        <button class="add-photo" type="button" id="addImage"><i class="fa-solid fa-plus" aria-hidden="true"></i><span data-i18n="addImage">एक और फोटो जोड़ें</span></button>
                    </section>

                    <label class="consent"><input type="checkbox" name="consent" value="1" <?= postValue('consent') === '1' ? 'checked' : '' ?> required><span><i class="fa-solid fa-check" aria-hidden="true"></i></span><em data-i18n="consent">मैं पुष्टि करता/करती हूं कि दी गई जानकारी सही है और इसे वैवाहिक परिचय सम्मेलन के लिए उपयोग करने की सहमति देता/देती हूं।</em></label>
                    <button class="submit-registration" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><span data-i18n="submitRegistration" aria-live="polite">पंजीकरण जमा करें</span></button>
                </div>
            </form>
        </section>

        <section class="contact-section">
            <div><p data-i18n="needHelp">सहायता चाहिए?</p><h2 data-i18n="contactOrganizers">आयोजकों से संपर्क करें</h2></div>
            <a href="tel:+919810026736"><i class="fa-solid fa-phone" aria-hidden="true"></i><span><small data-i18n="president">अध्यक्ष</small><strong data-i18n="pramodName">प्रमोद भटनागर</strong><em>9810026736</em></span></a>
            <a href="tel:+919811395212"><i class="fa-solid fa-phone" aria-hidden="true"></i><span><small data-i18n="generalSecretary">महासचिव</small><strong data-i18n="kkName">के. के. भटनागर</strong><em>9811395212</em></span></a>
            <a href="tel:+919811947383"><i class="fa-solid fa-phone" aria-hidden="true"></i><span><small data-i18n="committeeHead">समिति प्रमुख</small><strong data-i18n="scName">एस. सी. भटनागर</strong><em>9811947383</em></span></a>
        </section>
    </main>

    <footer><img src="assets/logo.jpeg" alt=""><span data-i18n="copyright">© 2026 भटनागर सभा गाजियाबाद (पंजी.)</span><a href="mailto:bhatnagarkk@gmail.com">bhatnagarkk@gmail.com</a></footer>
    <script src="assets/matrimonial-language.js?v=4"></script>
</body>

</html>
