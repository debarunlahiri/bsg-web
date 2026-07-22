<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
session_start();

function fail(string $message, array $errors = [], int $status = 422): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message, 'errors' => $errors]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Only POST requests are allowed.', [], 405);
}

require_once __DIR__ . '/config.php';

function value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), value('csrf_token'))) {
    fail('Your form session expired. Please refresh the page and try again.', [], 403);
}

if (value('website') !== '') {
    fail('Your submission could not be verified.', [], 400);
}

$data = [
    'name' => value('name'),
    'father_name' => value('father_name'),
    'mobile' => value('mobile'),
    'email' => value('email'),
    'house_number' => value('house_number'),
    'locality' => value('locality'),
    'city' => value('city'),
    'state' => value('state'),
    'pin_code' => value('pin_code'),
    'occupation' => value('occupation'),
    'business_name' => value('business_name'),
    'business_category' => value('business_category'),
    'business_address' => value('business_address'),
];

$data['mobile'] = preg_replace('/\D+/', '', $data['mobile']) ?? '';
if (strlen($data['mobile']) === 12 && str_starts_with($data['mobile'], '91')) {
    $data['mobile'] = substr($data['mobile'], 2);
}
$data['email'] = strtolower($data['email']);

$required = ['name', 'father_name', 'mobile', 'email', 'house_number', 'locality', 'city', 'state', 'pin_code', 'occupation'];
$errors = [];
foreach ($required as $field) {
    if ($data[$field] === '') {
        $errors[$field] = 'This field is required.';
    }
}

if (!preg_match("/^[\p{L}][\p{L}\p{M} .'-]{1,99}$/u", $data['name'])) {
    $errors['name'] = 'Enter a valid full name (at least 2 letters).';
}
if (!preg_match("/^[\p{L}][\p{L}\p{M} .'-]{1,99}$/u", $data['father_name'])) {
    $errors['father_name'] = "Enter a valid father's name (at least 2 letters).";
}
if (!preg_match('/^[6-9][0-9]{9}$/', $data['mobile'])) {
    $errors['mobile'] = 'Enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.';
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 150) {
    $errors['email'] = 'Enter a valid email address.';
}

if (!preg_match('/^[1-9][0-9]{5}$/', $data['pin_code'])) {
    $errors['pin_code'] = 'Enter a valid 6-digit Indian PIN code.';
}

$locationFile = __DIR__ . '/assets/india-locations.json';
$locations = is_file($locationFile) ? json_decode((string) file_get_contents($locationFile), true) : null;
$selectedState = is_array($locations)
    ? array_values(array_filter($locations, static fn(array $item): bool => ($item['state'] ?? '') === $data['state']))
    : [];

if ($selectedState === []) {
    $errors['state'] = 'Select a valid Indian state.';
}
if ($selectedState === [] || !in_array($data['city'], $selectedState[0]['cities'] ?? [], true)) {
    $errors['city'] = 'Select a city belonging to the chosen state.';
}

if (!in_array($data['occupation'], ['Business', 'Job', 'Shop', 'Home Maker'], true)) {
    $errors['occupation'] = 'Select a valid occupation.';
}

$limits = ['house_number' => 80, 'locality' => 150, 'business_name' => 150, 'business_category' => 150, 'business_address' => 500];
foreach ($limits as $field => $limit) {
    if (mb_strlen($data[$field]) > $limit) {
        $errors[$field] = "Maximum allowed length is {$limit} characters.";
    }
}

if ($errors !== []) {
    fail('Please correct the highlighted fields.', $errors);
}

if ($data['occupation'] !== 'Business') {
    $data['business_name'] = '';
    $data['business_category'] = '';
    $data['business_address'] = '';
}

try {
    $duplicate = database()->prepare('SELECT mobile, email FROM registrations WHERE mobile = :mobile OR email = :email LIMIT 1');
    $duplicate->execute(['mobile' => $data['mobile'], 'email' => $data['email']]);
    $existing = $duplicate->fetch();
    if ($existing) {
        $duplicateErrors = [];
        if ($existing['mobile'] === $data['mobile']) $duplicateErrors['mobile'] = 'This mobile number is already registered.';
        if (strtolower($existing['email']) === $data['email']) $duplicateErrors['email'] = 'This email address is already registered.';
        fail('A registration already exists with these details.', $duplicateErrors, 409);
    }

    $sql = 'INSERT INTO registrations
        (name, father_name, mobile, email, house_number, locality, city, state, pin_code, occupation, business_name, business_category, business_address)
        VALUES
        (:name, :father_name, :mobile, :email, :house_number, :locality, :city, :state, :pin_code, :occupation, :business_name, :business_category, :business_address)';
    database()->prepare($sql)->execute($data);

    echo json_encode(['success' => true, 'message' => 'Your registration has been submitted successfully.']);
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'We could not save your registration. Please check the database connection and try again.']);
}
