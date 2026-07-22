const form = document.querySelector('#registrationForm');
const businessPanel = document.querySelector('#businessPanel');
const dialog = document.querySelector('#resultDialog');
const submitButton = form.querySelector('.submit-button');
const stateSelect = document.querySelector('#stateSelect');
const citySelect = document.querySelector('#citySelect');
const familyPanel = document.querySelector('#familyPanel');
const childrenFields = document.querySelector('#childrenFields');
const sonCount = document.querySelector('#sonCount');
const daughterCount = document.querySelector('#daughterCount');
let locations = [];
const familyDrafts = new Map();

[sonCount, daughterCount].forEach((select) => {
    select.replaceChildren();
    for (let count = 0; count <= 10; count += 1) select.add(new Option(String(count), String(count)));
});

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

document.querySelectorAll('input[name="marital_status"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        const married = radio.value === 'Married' && radio.checked;
        familyPanel.classList.toggle('open', married);
        familyPanel.setAttribute('aria-hidden', String(!married));
        document.querySelector('#maritalStatusError').textContent = '';
        if (!married) {
            familyPanel.querySelectorAll('input[type="checkbox"]').forEach((input) => { input.checked = false; });
            sonCount.value = '0';
            daughterCount.value = '0';
            updateSpouseFields();
            renderChildren(false);
        }
    });
});

function updateSpouseFields() {
    ['husband', 'wife'].forEach((spouse) => {
        const checked = form.elements.namedItem(`has_${spouse}`).checked;
        const field = document.querySelector(`[data-spouse="${spouse}"]`);
        const input = field.querySelector('input');
        field.classList.toggle('visible', checked);
        input.required = checked;
        if (!checked) input.value = '';
    });
    const selected = familyPanel.querySelectorAll('.spouse-options input:checked').length;
    document.querySelector('#spouseError').textContent = selected === 1 ? '' : 'Select either Husband or Wife.';
}
familyPanel.querySelectorAll('.spouse-options input').forEach((input) => input.addEventListener('change', () => {
    if (input.checked) {
        familyPanel.querySelectorAll('.spouse-options input').forEach((other) => {
            if (other !== input) other.checked = false;
        });
    }
    updateSpouseFields();
}));

function childRow(type, index) {
    const label = type === 'sons' ? 'Son' : 'Daughter';
    return `<div class="field-grid child-row">
        <label class="field"><span>${label} ${index + 1} name *</span><input name="${type}[${index}][name]" maxlength="100" required placeholder="Enter name"><small class="error"></small></label>
        <label class="field"><span>Age *</span><input type="number" name="${type}[${index}][age]" min="0" max="120" required placeholder="Age"><small class="error"></small></label>
        <label class="field"><span>Marital status *</span><span class="select-wrap"><select name="${type}[${index}][marital_status]" required><option value="">Select</option><option>Married</option><option>Unmarried</option></select><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span><small class="error"></small></label>
    </div>`;
}

function renderChildren(preserveValues = true) {
    if (preserveValues) {
        childrenFields.querySelectorAll('input, select').forEach((field) => {
            familyDrafts.set(field.name, field.value);
        });
    } else {
        familyDrafts.clear();
    }
    const groups = [
        ['sons', Number(sonCount.value), 'Son details'],
        ['daughters', Number(daughterCount.value), 'Daughter details']
    ];
    childrenFields.innerHTML = groups.filter(([, count]) => count > 0).map(([type, count, title]) =>
        `<section class="child-group"><h4>${title}</h4>${Array.from({ length: count }, (_, index) => childRow(type, index)).join('')}</section>`
    ).join('');
    childrenFields.querySelectorAll('input, select').forEach((field) => {
        if (familyDrafts.has(field.name)) field.value = familyDrafts.get(field.name);
    });
}
sonCount.addEventListener('change', renderChildren);
daughterCount.addEventListener('change', renderChildren);

const messages = {
    name: 'Enter a valid full name (at least 2 letters).', father_name: "Enter a valid father's name (at least 2 letters).",
    mobile: 'Enter a valid 10-digit Indian mobile number.', email: 'Enter a valid email address.',
    house_number: 'Please enter your house number.', locality: 'Please enter your locality.',
    city: 'Please select your city.', state: 'Please select your state.',
    pin_code: 'Enter a valid 6-digit PIN code.'
};

function validateField(input) {
    let valid = input.value.trim() !== '';
    const isPersonName = input.name === 'name' || input.name === 'father_name' || input.name === 'husband_name' || input.name === 'wife_name' || /\[(?:name)\]$/.test(input.name);
    if (isPersonName) valid = /^[\p{L}][\p{L}\p{M} .'-]{1,99}$/u.test(input.value.trim());
    if (input.name === 'mobile') valid = /^[6-9]\d{9}$/.test(input.value.trim());
    if (input.name === 'email') valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim());
    if (input.name === 'pin_code') valid = /^[1-9]\d{5}$/.test(input.value.trim());
    if (/\[age\]$/.test(input.name)) valid = /^\d{1,3}$/.test(input.value) && Number(input.value) >= 0 && Number(input.value) <= 120;
    if (valid && input.maxLength > 0) valid = input.value.trim().length <= input.maxLength;
    input.classList.toggle('invalid', !valid);
    const error = input.closest('.field')?.querySelector('.error');
    let message = messages[input.name] || 'This field is required.';
    if (isPersonName) message = 'Enter a valid name using letters only (minimum 2 characters).';
    if (/\[age\]$/.test(input.name)) message = 'Enter a valid age from 0 to 120.';
    if (/\[marital_status\]$/.test(input.name)) message = 'Select Married or Unmarried.';
    if (error) error.textContent = valid ? '' : message;
    return valid;
}

function showFieldErrors(errors = {}) {
    Object.entries(errors).forEach(([name, message]) => {
        if (name === 'occupation') {
            document.querySelector('#occupationError').textContent = message;
            return;
        }
        if (name === 'marital_status') {
            document.querySelector('#maritalStatusError').textContent = message;
            return;
        }
        if (name === 'spouse') {
            document.querySelector('#spouseError').textContent = message;
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

form.addEventListener('focusout', (event) => {
    if (event.target.matches('#childrenFields input[required], #childrenFields select[required], .spouse-name input[required]')) validateField(event.target);
});
form.addEventListener('input', (event) => {
    if (event.target.matches('#childrenFields input')) familyDrafts.set(event.target.name, event.target.value);
    if (event.target.matches('#childrenFields input, .spouse-name input') && event.target.classList.contains('invalid')) validateField(event.target);
});
form.addEventListener('change', (event) => {
    if (event.target.matches('#childrenFields select')) familyDrafts.set(event.target.name, event.target.value);
    if (event.target.matches('#childrenFields select')) validateField(event.target);
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
    const maritalStatus = form.querySelector('input[name="marital_status"]:checked');
    const spouseCount = familyPanel.querySelectorAll('.spouse-options input:checked').length;
    const spouseValid = maritalStatus?.value !== 'Married' || spouseCount === 1;
    document.querySelector('#occupationError').textContent = occupation ? '' : 'Please select your occupation.';
    document.querySelector('#maritalStatusError').textContent = maritalStatus ? '' : 'Please select Married or Unmarried.';
    document.querySelector('#spouseError').textContent = spouseValid ? '' : 'Select either Husband or Wife.';

    if (!fieldsValid || !occupation || !maritalStatus || !spouseValid) {
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
        familyPanel.classList.remove('open');
        familyPanel.setAttribute('aria-hidden', 'true');
        updateSpouseFields();
        renderChildren(false);
    } catch (error) {
        showDialog(false, error.message || 'Unable to connect to the server. Please try again.');
    } finally {
        submitButton.disabled = false;
        submitButton.classList.remove('loading');
    }
});
