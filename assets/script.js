const form = document.querySelector('#registrationForm');
const businessPanel = document.querySelector('#businessPanel');
const dialog = document.querySelector('#resultDialog');
const submitButton = form.querySelector('.submit-button');
const stateSelect = document.querySelector('#stateSelect');
const citySelect = document.querySelector('#citySelect');
let locations = [];

fetch('assets/india-locations.json')
    .then((response) => {
        if (!response.ok) throw new Error('Location data could not be loaded.');
        return response.json();
    })
    .then((data) => {
        locations = data;
        data.forEach(({ state }) => stateSelect.add(new Option(state, state)));
    })
    .catch(() => {
        stateSelect.innerHTML = '<option value="">Unable to load states</option>';
        stateSelect.disabled = true;
    });

stateSelect.addEventListener('change', () => {
    const selected = locations.find(({ state }) => state === stateSelect.value);
    citySelect.innerHTML = '';
    citySelect.add(new Option(selected ? 'Select city' : 'Select state first', ''));
    (selected?.cities || []).forEach((city) => citySelect.add(new Option(city, city)));
    citySelect.disabled = !selected;
    citySelect.classList.remove('invalid');
    citySelect.closest('.field').querySelector('.error').textContent = '';
    validateField(stateSelect);
});

document.querySelectorAll('input[name="occupation"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        const show = radio.value === 'Business' && radio.checked;
        businessPanel.classList.toggle('open', show);
        businessPanel.setAttribute('aria-hidden', String(!show));
        document.querySelector('#occupationError').textContent = '';
    });
});

const messages = {
    name: 'Enter a valid full name (at least 2 letters).', father_name: "Enter a valid father's name (at least 2 letters).",
    mobile: 'Enter a valid 10-digit Indian mobile number.', email: 'Enter a valid email address.',
    house_number: 'Please enter your house number.', locality: 'Please enter your locality.',
    city: 'Please select your city.', state: 'Please select your state.',
    pin_code: 'Enter a valid 6-digit PIN code.'
};

function validateField(input) {
    let valid = input.value.trim() !== '';
    if (input.name === 'name' || input.name === 'father_name') valid = /^[\p{L}][\p{L}\p{M} .'-]{1,99}$/u.test(input.value.trim());
    if (input.name === 'mobile') valid = /^[6-9]\d{9}$/.test(input.value.trim());
    if (input.name === 'email') valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim());
    if (input.name === 'pin_code') valid = /^[1-9]\d{5}$/.test(input.value.trim());
    input.classList.toggle('invalid', !valid);
    const error = input.closest('.field')?.querySelector('.error');
    if (error) error.textContent = valid ? '' : messages[input.name];
    return valid;
}

function showFieldErrors(errors = {}) {
    Object.entries(errors).forEach(([name, message]) => {
        if (name === 'occupation') {
            document.querySelector('#occupationError').textContent = message;
            return;
        }
        const input = form.elements.namedItem(name);
        if (!input || input instanceof RadioNodeList) return;
        input.classList.add('invalid');
        const error = input.closest('.field')?.querySelector('.error');
        if (error) error.textContent = message;
    });
    form.querySelector('.invalid')?.focus();
}

form.elements.mobile.addEventListener('input', (event) => {
    let digits = event.target.value.replace(/\D/g, '');
    if (digits.length > 10 && digits.startsWith('91')) digits = digits.slice(2);
    event.target.value = digits.slice(0, 10);
});

form.elements.pin_code.addEventListener('input', (event) => {
    event.target.value = event.target.value.replace(/\D/g, '').slice(0, 6);
});

form.querySelectorAll('input[required]:not([type="radio"]), select[required]').forEach((input) => {
    input.addEventListener('blur', () => validateField(input));
    input.addEventListener('input', () => { if (input.classList.contains('invalid')) validateField(input); });
});

function showDialog(success, message) {
    const icon = document.querySelector('#dialogIcon');
    icon.className = `dialog-icon ${success ? 'success' : 'failed'}`;
    icon.querySelector('i').className = `fa-solid ${success ? 'fa-check' : 'fa-xmark'}`;
    document.querySelector('#dialogTitle').textContent = success ? 'Registration complete!' : 'Submission failed';
    document.querySelector('#dialogMessage').textContent = message;
    dialog.showModal();
}

document.querySelector('#dialogClose').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const requiredInputs = [...form.querySelectorAll('input[required]:not([type="radio"]), select[required]')];
    const fieldsValid = requiredInputs.map(validateField).every(Boolean);
    const occupation = form.querySelector('input[name="occupation"]:checked');
    document.querySelector('#occupationError').textContent = occupation ? '' : 'Please select your occupation.';

    if (!fieldsValid || !occupation) {
        form.querySelector('.invalid')?.focus();
        return;
    }

    submitButton.disabled = true;
    submitButton.classList.add('loading');
    try {
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.success) {
            showFieldErrors(result.errors);
            throw new Error(result.message || 'Something went wrong. Please try again.');
        }
        showDialog(true, result.message);
        form.reset();
        citySelect.innerHTML = '<option value="">Select state first</option>';
        citySelect.disabled = true;
        businessPanel.classList.remove('open');
        businessPanel.setAttribute('aria-hidden', 'true');
    } catch (error) {
        showDialog(false, error.message || 'Unable to connect to the server. Please try again.');
    } finally {
        submitButton.disabled = false;
        submitButton.classList.remove('loading');
    }
});
