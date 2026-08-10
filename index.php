<?php

declare(strict_types=1);
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration Form</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css?v=6">
    <link rel="stylesheet" href="assets/theme.css?v=6">
</head>

<body>
    <main class="page-shell">
        <section class="intro" aria-labelledby="page-title">
            <img class="organization-logo" src="assets/logo.jpeg" alt="Bhatnagar Sabha Ghaziabad logo">
            <p class="eyebrow">Registration portal</p>
            <h1 id="page-title">Let’s get to know you.</h1>
            <p class="intro-copy">Share a few details to complete your registration. It only takes a minute.</p>
            <div class="privacy-note">
                <span class="shield" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                <span>Your information is safely stored and never shared.</span>
            </div>
        </section>

        <section class="form-card" aria-label="Registration details">
            <form id="registrationForm" action="submit.php" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input class="sr-only" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="form-heading">
                    <span>01</span>
                    <div>
                        <h2>Personal details</h2>
                        <p>Fields marked with * are required</p>
                    </div>
                </div>

                <div class="field-grid two-columns">
                    <label class="field">
                        <span>Full name *</span>
                        <input type="text" name="name" autocomplete="name" minlength="2" maxlength="100" placeholder="Enter your full name" required>
                        <small class="error"></small>
                    </label>
                    <label class="field">
                        <span>Father's name *</span>
                        <input type="text" name="father_name" minlength="2" maxlength="100" placeholder="Enter father's name" required>
                        <small class="error"></small>
                    </label>
                    <label class="field">
                        <span>Mobile number *</span>
                        <input type="tel" name="mobile" autocomplete="tel" inputmode="numeric" maxlength="10" placeholder="10-digit mobile number" required>
                        <small class="error"></small>
                    </label>
                    <label class="field">
                        <span>Email ID *</span>
                        <input type="email" name="email" autocomplete="email" maxlength="150" placeholder="you@example.com" required>
                        <small class="error"></small>
                    </label>
                </div>

                <div class="section-divider"></div>
                <div class="form-heading compact">
                    <span>02</span>
                    <div>
                        <h2>Address</h2>
                        <p>Where can we reach you?</p>
                    </div>
                </div>
                <div class="field-grid address-grid">
                    <label class="field house"><span>House number *</span><input type="text" name="house_number" maxlength="80" placeholder="H.No. / Flat" required><small class="error"></small></label>
                    <label class="field locality"><span>Locality *</span><input type="text" name="locality" maxlength="150" placeholder="Street or locality" required><small class="error"></small></label>
                    <label class="field"><span>State *</span><span class="select-wrap"><select name="state" id="stateSelect" required>
                                <option value="">Select state</option>
                            </select><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span><small class="error"></small></label>
                    <label class="field"><span>City *</span><span class="select-wrap"><select name="city" id="citySelect" required disabled>
                                <option value="">Select state first</option>
                            </select><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span><small class="error"></small></label>
                    <label class="field"><span>PIN code *</span><input type="text" name="pin_code" inputmode="numeric" maxlength="6" placeholder="6-digit PIN" required><small class="error"></small></label>
                </div>

                <div class="section-divider"></div>
                <div class="form-heading compact">
                    <span>03</span>
                    <div>
                        <h2>Occupation</h2>
                        <p>Choose the option that describes you</p>
                    </div>
                </div>
                <fieldset class="occupation-options">
                    <legend class="sr-only">Occupation *</legend>
                    <label><input type="radio" name="occupation" value="Business" required><span>Business</span></label>
                    <label><input type="radio" name="occupation" value="Job"><span>Job</span></label>
                    <label><input type="radio" name="occupation" value="Shop"><span>Shop</span></label>
                    <label><input type="radio" name="occupation" value="Home Maker"><span>Home Maker</span></label>
                </fieldset>
                <small class="occupation-error" id="occupationError"></small>

                <div class="business-panel" id="businessPanel" aria-hidden="true">
                    <div class="business-inner">
                        <div class="business-title"><span>Optional</span>
                            <h3>Tell us about your business</h3>
                        </div>
                        <div class="field-grid two-columns">
                            <label class="field"><span>Business name</span><input type="text" name="business_name" maxlength="150" placeholder="Your business name"></label>
                            <label class="field"><span>Business category</span><input type="text" name="business_category" maxlength="150" placeholder="e.g. Retail, Services"></label>
                            <label class="field full-width"><span>Business address</span><textarea name="business_address" maxlength="500" rows="3" placeholder="Enter business address"></textarea></label>
                        </div>
                    </div>
                </div>

                <div class="section-divider"></div>
                <div class="form-heading compact">
                    <span>04</span>
                    <div>
                        <h2>Family details</h2>
                        <p>Tell us about your marital status and family</p>
                    </div>
                </div>
                <fieldset class="occupation-options family-status-options">
                    <legend class="sr-only">Marital status *</legend>
                    <label><input type="radio" name="marital_status" value="Married" required><span>Married</span></label>
                    <label><input type="radio" name="marital_status" value="Unmarried"><span>Unmarried</span></label>
                </fieldset>
                <small class="occupation-error" id="maritalStatusError"></small>

                <div class="family-panel" id="familyPanel" aria-hidden="true">
                    <div class="family-inner">
                        <div class="business-title"><span>Family</span>
                            <h3>Married family details</h3>
                        </div>
                        <div class="spouse-options">
                            <label><input type="checkbox" name="has_husband" value="1"><span>Husband</span></label>
                            <label><input type="checkbox" name="has_wife" value="1"><span>Wife</span></label>
                        </div>
                        <small class="occupation-error" id="spouseError"></small>
                        <div class="field-grid two-columns spouse-name-fields">
                            <label class="field spouse-name" data-spouse="husband"><span>Name of husband *</span><input type="text" name="husband_name" maxlength="100" placeholder="Enter husband's name"><small class="error"></small></label>
                            <label class="field spouse-name" data-spouse="wife"><span>Name of wife *</span><input type="text" name="wife_name" maxlength="100" placeholder="Enter wife's name"><small class="error"></small></label>
                        </div>
                        <div class="field-grid two-columns child-counts">
                            <label class="field"><span>How many sons?</span><span class="select-wrap"><select name="son_count" id="sonCount">
                                        <option value="0">0</option>
                                    </select><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></label>
                            <label class="field"><span>How many daughters?</span><span class="select-wrap"><select name="daughter_count" id="daughterCount">
                                        <option value="0">0</option>
                                    </select><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></label>
                        </div>
                        <div id="childrenFields"></div>
                    </div>
                </div>

                <button class="submit-button" type="submit">
                    <span class="button-text">Submit registration</span>
                    <span class="button-loader" aria-hidden="true"></span>
                    <i class="arrow fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                <p class="form-footnote">By submitting, you confirm that the information provided is correct.</p>
            </form>
        </section>
    </main>

    <dialog class="result-dialog" id="resultDialog">
        <div class="dialog-icon" id="dialogIcon"><i class="fa-solid" aria-hidden="true"></i></div>
        <h2 id="dialogTitle"></h2>
        <p id="dialogMessage"></p>
        <button type="button" id="dialogClose">Done</button>
    </dialog>
    <script src="assets/script.js?v=6"></script>
</body>

</html>