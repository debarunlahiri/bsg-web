(() => {
    const formContent = document.getElementById('formContent');
    const typeInputs = document.querySelectorAll('input[name="profile_type"]');
    const labels = document.querySelectorAll('[data-profile-label]');
    const occupationSelect = document.getElementById('occupationSelect');
    const uploadList = document.getElementById('uploadList');
    const addImage = document.getElementById('addImage');
    const maxBytes = 2 * 1024 * 1024;

    function updateType(type) {
        formContent.hidden = false;
        labels.forEach((label) => { label.textContent = type; });
        const values = type === 'Girl' ? ['Job', 'Business', 'Professional', 'Teacher', 'Home Maker', 'Student', 'Other'] : ['Job', 'Business', 'Professional', 'Government Service', 'Self Employed', 'Student', 'Other'];
        const currentValue = occupationSelect.value || occupationSelect.dataset.selected || '';
        const placeholder = Object.assign(document.createElement('option'), { value: '', textContent: 'Select occupation' });
        occupationSelect.replaceChildren(placeholder, ...values.map((value) => Object.assign(document.createElement('option'), { value, textContent: value })));
        if (values.includes(currentValue)) occupationSelect.value = currentValue;
        occupationSelect.dataset.selected = '';
    }

    typeInputs.forEach((input) => input.addEventListener('change', () => updateType(input.value)));
    const selectedType = document.querySelector('input[name="profile_type"]:checked');
    if (selectedType) updateType(selectedType.value);

    function updateAddButton() {
        addImage.hidden = uploadList.children.length >= 5;
    }

    function bindUpload(card) {
        const input = card.querySelector('input');
        const picker = card.querySelector('.upload-picker');
        const detail = card.querySelector('small');
        const preview = card.querySelector('.image-preview');
        const image = card.querySelector('img');
        const caption = card.querySelector('.image-caption');
        const removeButton = card.querySelector('.remove-image');
        let previewUrl = '';

        function clearPreview() {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = '';
            input.value = '';
            image.removeAttribute('src');
            preview.hidden = true;
            picker.hidden = false;
            card.classList.remove('invalid');
        }

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) { detail.textContent = 'No file selected'; return; }
            if (file.size > maxBytes) {
                clearPreview();
                detail.textContent = 'Image must be 2 MB or smaller';
                card.classList.add('invalid');
                return;
            }
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);
            image.src = previewUrl;
            caption.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
            card.classList.remove('invalid');
            picker.hidden = true;
            preview.hidden = false;
        });

        removeButton.addEventListener('click', () => {
            clearPreview();
            if (uploadList.children.length > 1) {
                card.remove();
            } else {
                input.required = true;
            }
            updateAddButton();
        });
    }

    bindUpload(uploadList.firstElementChild);
    addImage.addEventListener('click', () => {
        if (uploadList.children.length >= 5) return;
        const card = uploadList.firstElementChild.cloneNode(true);
        const inputId = `matrimonialImage${uploadList.children.length + 1}`;
        card.querySelector('input').value = '';
        card.querySelector('input').id = inputId;
        card.querySelector('input').required = false;
        card.querySelector('.upload-picker').htmlFor = inputId;
        card.querySelector('small').textContent = 'JPG, PNG or WebP';
        card.querySelector('img').removeAttribute('src');
        card.querySelector('.image-preview').hidden = true;
        card.querySelector('.upload-picker').hidden = false;
        card.classList.remove('invalid');
        bindUpload(card);
        uploadList.append(card);
        updateAddButton();
    });
})();
