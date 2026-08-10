<?php

declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireAdmin();
require_once dirname(__DIR__) . '/config.php';

$errors = [];
$success = isset($_GET['saved']);

function postValue(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrfToken(), postValue('csrf_token'))) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
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
        if ($data[$field] === '') $errors[] = 'Please complete all required fields.';
    }
    $errors = array_values(array_unique($errors));
    if (!in_array($data['profile_type'], ['Boy', 'Girl'], true)) $errors[] = 'Select Boy or Girl.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[6-9][0-9]{9}$/', $data['mobile'])) $errors[] = 'Enter a valid 10-digit Indian mobile number.';
    if (!in_array($data['manglik_status'], ['Manglik', 'Non-Manglik', 'Partial Manglik', 'Not Known'], true)) $errors[] = 'Select a valid Manglik status.';
    if ($data['income_period'] !== null && !in_array($data['income_period'], ['Monthly', 'Annual'], true)) $errors[] = 'Select a valid income period.';
    if ($data['payment_method'] !== null && !in_array($data['payment_method'], ['Draft', 'Bank Transfer', 'Paytm', 'Cash', 'Other'], true)) $errors[] = 'Select a valid payment method.';
    if ((float) $data['height_cm'] < 100 || (float) $data['height_cm'] > 250) $errors[] = 'Height must be between 100 and 250 cm.';
    if ((float) $data['weight_kg'] < 25 || (float) $data['weight_kg'] > 300) $errors[] = 'Weight must be between 25 and 300 kg.';
    if ($data['date_of_birth'] > date('Y-m-d')) $errors[] = 'Date of birth cannot be in the future.';

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
            $mime = $finfo->file($temporaryPath) ?: '';
            if (!isset($allowedTypes[$mime])) {
                $errors[] = "{$originalName} must be a JPG, PNG, or WebP image.";
                continue;
            }
            $validImages[] = compact('originalName', 'temporaryPath', 'size', 'mime') + ['extension' => $allowedTypes[$mime]];
        }
    }

    if ($errors === []) {
        $pdo = database();
        $movedFiles = [];
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO matrimonial_registrations (profile_type, full_name, father_name, address, date_of_birth, birth_time, birth_place, height_cm, weight_kg, manglik_status, education, professional_qualification, occupation, income_amount, income_period, other_details, email, mobile, registration_charge, payment_method, payment_reference, payment_date) VALUES (:profile_type, :full_name, :father_name, :address, :date_of_birth, :birth_time, :birth_place, :height_cm, :weight_kg, :manglik_status, :education, :professional_qualification, :occupation, :income_amount, :income_period, :other_details, :email, :mobile, :registration_charge, :payment_method, :payment_reference, :payment_date)');
            $statement->execute($data);
            $registrationId = (int) $pdo->lastInsertId();
            $uploadDirectory = dirname(__DIR__) . '/uploads/matrimonial';
            if ($validImages !== [] && !is_dir($uploadDirectory) && !@mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('The photograph storage folder could not be created. Please check its server permissions.');
            }
            if ($validImages !== [] && !is_writable($uploadDirectory)) {
                throw new RuntimeException('The photograph storage folder is not writable. Please check its server permissions.');
            }
            $imageStatement = $pdo->prepare('INSERT INTO matrimonial_images (matrimonial_registration_id, file_name, original_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)');
            foreach ($validImages as $image) {
                $fileName = $registrationId . '-' . bin2hex(random_bytes(12)) . '.' . $image['extension'];
                $destination = $uploadDirectory . '/' . $fileName;
                if (!move_uploaded_file($image['temporaryPath'], $destination)) throw new RuntimeException('An image could not be stored.');
                $movedFiles[] = $destination;
                $imageStatement->execute([$registrationId, $fileName, mb_substr(basename($image['originalName']), 0, 255), $image['mime'], $image['size']]);
            }
            $pdo->commit();
            header('Location: matrimonial.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($movedFiles as $file) if (is_file($file)) unlink($file);
            error_log($exception->getMessage());
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The registration could not be saved. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Matrimonial Registration</title>
    <link rel="stylesheet" href="admin.css?v=7">
    <link rel="stylesheet" href="matrimonial.css?v=1">
    <link rel="stylesheet" href="matrimonial-preview.css?v=1">
    <link rel="stylesheet" href="matrimonial-selects.css?v=1">
</head>

<body>
    <main class="matrimonial-shell">
        <header class="matrimonial-header">
            <div><a class="back-link" href="index.php"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M19 12H5m7 7-7-7 7-7" />
                    </svg>Registrations</a>
                <p class="eyebrow">Admin dashboard</p>
                <h1>Matrimonial registration</h1>
                <p class="muted">Create a matrimonial profile and add up to 5 photographs.</p>
            </div>
        </header>
        <?php if ($success): ?><div class="success-message" role="status">Matrimonial registration saved successfully.</div><?php endif; ?>
        <?php if ($errors !== []): ?><div class="error-summary" role="alert"><strong>Please correct the following:</strong>
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div><?php endif; ?>
        <form class="matrimonial-form" method="post" enctype="multipart/form-data" id="matrimonialForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <section class="type-step"><span class="step-number">1</span>
                <div>
                    <h2>Who are you registering?</h2>
                    <p>Select one option to open the registration form.</p>
                </div>
                <div class="type-options">
                    <label><input type="radio" name="profile_type" value="Boy" <?= postValue('profile_type') === 'Boy' ? 'checked' : '' ?> required><span><svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="7" r="4" />
                                <path d="M5 21v-2a7 7 0 0 1 14 0v2" />
                            </svg>Boy</span></label>
                    <label><input type="radio" name="profile_type" value="Girl" <?= postValue('profile_type') === 'Girl' ? 'checked' : '' ?> required><span><svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="7" r="4" />
                                <path d="M5 21v-2a7 7 0 0 1 14 0v2" />
                            </svg>Girl</span></label>
                </div>
            </section>
            <div class="form-content" id="formContent" <?= postValue('profile_type') === '' ? 'hidden' : '' ?>>
                <section class="form-section">
                    <div class="section-title"><span>2</span>
                        <div>
                            <h2><span data-profile-label>Candidate</span> details</h2>
                            <p>Personal, birth and physical information</p>
                        </div>
                    </div>
                    <div class="field-grid">
                        <label><span>Full name *</span><input name="full_name" maxlength="100" value="<?= e(postValue('full_name')) ?>" required></label><label><span>Father's name *</span><input name="father_name" maxlength="100" value="<?= e(postValue('father_name')) ?>" required></label>
                        <label class="wide"><span>Address *</span><textarea name="address" maxlength="500" rows="3" required><?= e(postValue('address')) ?></textarea></label>
                        <label><span>Date of birth *</span><input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= e(postValue('date_of_birth')) ?>" required></label><label><span>Birth time</span><input type="time" name="birth_time" value="<?= e(postValue('birth_time')) ?>"></label><label><span>Birth place *</span><input name="birth_place" maxlength="150" value="<?= e(postValue('birth_place')) ?>" required></label>
                        <label><span>Height (cm) *</span><input type="number" name="height_cm" min="100" max="250" step="0.01" value="<?= e(postValue('height_cm')) ?>" required></label><label><span>Weight (kg) *</span><input type="number" name="weight_kg" min="25" max="300" step="0.01" value="<?= e(postValue('weight_kg')) ?>" required></label><label><span>Manglik status *</span><select name="manglik_status" required>
                                <option value="">Select status</option><?php foreach (['Manglik', 'Non-Manglik', 'Partial Manglik', 'Not Known'] as $option): ?><option <?= postValue('manglik_status') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                            </select></label>
                    </div>
                </section>
                <section class="form-section">
                    <div class="section-title"><span>3</span>
                        <div>
                            <h2>Education and profession</h2>
                            <p>Qualification, work and income details</p>
                        </div>
                    </div>
                    <div class="field-grid">
                        <label class="wide"><span>Educational qualification *</span><textarea name="education" maxlength="500" rows="2" required><?= e(postValue('education')) ?></textarea></label><label class="wide"><span>Professional qualification</span><textarea name="professional_qualification" maxlength="500" rows="2"><?= e(postValue('professional_qualification')) ?></textarea></label><label><span>Occupation / business *</span><select name="occupation" id="occupationSelect" data-selected="<?= e(postValue('occupation')) ?>" required>
                                <option value="">Select occupation</option>
                            </select></label><label><span>Income amount</span><input type="number" name="income_amount" min="0" step="0.01" value="<?= e(postValue('income_amount')) ?>"></label><label><span>Income period</span><select name="income_period">
                                <option value="">Select period</option>
                                <option <?= postValue('income_period') === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option <?= postValue('income_period') === 'Annual' ? 'selected' : '' ?>>Annual</option>
                            </select></label><label class="wide"><span>Other details</span><textarea name="other_details" maxlength="1000" rows="3"><?= e(postValue('other_details')) ?></textarea></label>
                    </div>
                </section>
                <section class="form-section">
                    <div class="section-title"><span>4</span>
                        <div>
                            <h2>Contact and payment</h2>
                            <p>Contact person and registration payment details</p>
                        </div>
                    </div>
                    <div class="field-grid">
                        <label><span>Email ID *</span><input type="email" name="email" maxlength="150" value="<?= e(postValue('email')) ?>" required></label><label><span>Phone / mobile number *</span><input type="tel" name="mobile" maxlength="10" inputmode="numeric" value="<?= e(postValue('mobile')) ?>" required></label><label><span>Registration charge (₹)</span><input type="number" name="registration_charge" min="0" step="0.01" value="<?= e(postValue('registration_charge')) ?>"></label><label><span>Payment method</span><select name="payment_method">
                                <option value="">Select method</option><?php foreach (['Draft', 'Bank Transfer', 'Paytm', 'Cash', 'Other'] as $option): ?><option <?= postValue('payment_method') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                            </select></label><label><span>Payment reference number</span><input name="payment_reference" maxlength="100" value="<?= e(postValue('payment_reference')) ?>"></label><label><span>Payment date</span><input type="date" name="payment_date" max="<?= date('Y-m-d') ?>" value="<?= e(postValue('payment_date')) ?>"></label>
                    </div>
                </section>
                <section class="form-section">
                    <div class="section-title"><span>5</span>
                        <div>
                            <h2>Photographs</h2>
                            <p>JPG, PNG or WebP · maximum 2 MB each · up to 5 images</p>
                        </div>
                    </div>
                    <div class="upload-list" id="uploadList">
                        <div class="upload-card"><label class="upload-picker" for="matrimonialImage1"><svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5" />
                                </svg><span><strong>Choose image</strong><small>JPG, PNG or WebP</small></span></label><input class="image-file-input" id="matrimonialImage1" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" required>
                            <div class="image-preview" hidden><img alt="Selected photograph preview">
                                <div class="image-caption"></div><button type="button" class="remove-image" aria-label="Remove image"><svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 6l12 12M18 6 6 18" />
                                    </svg></button>
                            </div>
                        </div>
                    </div><button class="add-image" type="button" id="addImage"><svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>Add another image</button>
                </section>
                <button class="save-button" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6" />
                    </svg>Save matrimonial registration</button>
            </div>
        </form>
    </main>
    <script src="matrimonial.js?v=1"></script>
</body>

</html>
