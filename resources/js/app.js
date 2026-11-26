import './bootstrap';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mostrar/Ocultar contraseña en login
    const togglePasswordBtn = document.getElementById('togglePassword');
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function () {
            const input = document.getElementById('password');
            if (input) {
                const oculto = input.type === 'password';
                input.type = oculto ? 'text' : 'password';
                this.textContent = oculto ? 'Ocultar' : 'Mostrar';
            }
        });
    }

    // 2. Pre-registro: Control dinámico de tipo y número de documento, coherencia con edad
    const docTypeSelect = document.getElementById('document_type');
    const docNumberInput = document.getElementById('document_number');
    const birthDateInput = document.getElementById('birth_date');
    const approximateAgeInput = document.getElementById('approximate_age');
    const minorNotice = document.getElementById('minorNotice');
    const companionStatusBadge = document.getElementById('companionStatusBadge');

    function calculateAge() {
        if (birthDateInput && birthDateInput.value) {
            const birth = new Date(birthDateInput.value);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            return isNaN(age) ? null : age;
        }
        if (approximateAgeInput && approximateAgeInput.value !== '') {
            const val = parseInt(approximateAgeInput.value, 10);
            return isNaN(val) ? null : val;
        }
        return null;
    }

    function updateDocTypeState() {
        if (!docTypeSelect || !docNumberInput) return;
        const age = calculateAge();

        // Filtrar / Deshabilitar opciones incompatibles según la edad calculada
        Array.from(docTypeSelect.options).forEach(opt => {
            if (!opt.value) return;
            opt.disabled = false;

            if (age !== null) {
                // CC: sólo >= 18 años
                if (opt.value === 'Cédula de ciudadanía' && age < 18) {
                    opt.disabled = true;
                }
                // TI: sólo 7 a 17 años
                if (opt.value === 'Tarjeta de identidad' && (age < 7 || age >= 18)) {
                    opt.disabled = true;
                }
                // Registro civil: sólo menores (< 18)
                if (opt.value === 'Registro civil' && age >= 18) {
                    opt.disabled = true;
                }
                // Menor sin identificación: sólo < 18
                if (opt.value === 'Menor sin identificación' && age >= 18) {
                    opt.disabled = true;
                }
                // CE: a partir de 7 años
                if (opt.value === 'Cédula de extranjería' && age < 7) {
                    opt.disabled = true;
                }
            }
        });

        // Si la opción seleccionada quedó deshabilitada, deseleccionarla
        const selectedOpt = docTypeSelect.selectedOptions[0];
        if (selectedOpt && selectedOpt.disabled) {
            docTypeSelect.value = '';
        }

        const currentVal = docTypeSelect.value;
        const isMinorByDoc = ['Tarjeta de identidad', 'Registro civil', 'Menor sin identificación'].includes(currentVal);
        const isMinor = (age !== null && age < 18) || isMinorByDoc;

        if (currentVal === 'Menor sin identificación') {
            docNumberInput.value = '';
            docNumberInput.disabled = true;
            docNumberInput.placeholder = 'No aplica para menor sin identificación';
        } else {
            docNumberInput.disabled = false;
            docNumberInput.placeholder = 'Número de identificación';
        }

        if (minorNotice && companionStatusBadge) {
            if (isMinor) {
                minorNotice.classList.remove('d-none');
                companionStatusBadge.textContent = 'Obligatorio para menores';
                companionStatusBadge.className = 'badge bg-danger';
            } else {
                minorNotice.classList.add('d-none');
                companionStatusBadge.textContent = 'Opcional para adultos';
                companionStatusBadge.className = 'badge bg-secondary';
            }
        }
    }

    if (docTypeSelect) {
        docTypeSelect.addEventListener('change', updateDocTypeState);
        if (birthDateInput) {
            birthDateInput.addEventListener('change', updateDocTypeState);
        }
        if (approximateAgeInput) {
            approximateAgeInput.addEventListener('input', updateDocTypeState);
        }
        updateDocTypeState();
    }

    // 3. Pre-registro: Control dinámico de detalle de alergias
    const allergyRadios = document.querySelectorAll('input[name="medication_allergies"]');
    const allergyDetailContainer = document.getElementById('allergyDetailContainer');
    const allergyDetailInput = document.getElementById('allergies_detail');

    function updateAllergyState() {
        if (!allergyDetailContainer) return;
        const selected = document.querySelector('input[name="medication_allergies"]:checked');
        if (selected && selected.value === 'si') {
            allergyDetailContainer.classList.remove('d-none');
            if (allergyDetailInput) allergyDetailInput.required = true;
        } else {
            allergyDetailContainer.classList.add('d-none');
            if (allergyDetailInput) allergyDetailInput.required = false;
        }
    }

    allergyRadios.forEach(radio => {
        radio.addEventListener('change', updateAllergyState);
    });

    // 4. Pre-registro: Detección y banner dinámico de signos de alarma
    const alarmCheckboxes = document.querySelectorAll('.alarm-sign-checkbox');
    const alarmBanner = document.getElementById('alarmSignsAlertBanner');

    function updateAlarmBanner() {
        if (!alarmBanner) return;
        const hasChecked = Array.from(alarmCheckboxes).some(cb => cb.checked);
        if (hasChecked) {
            alarmBanner.classList.remove('d-none');
        } else {
            alarmBanner.classList.add('d-none');
        }
    }

    alarmCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateAlarmBanner);
    });
    updateAlarmBanner();

    // 5. Pre-registro: Contadores de caracteres
    function setupCharCounter(inputId, counterId, maxLen) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);
        if (input && counter) {
            const updateCount = () => {
                counter.textContent = input.value.length;
            };
            input.addEventListener('input', updateCount);
            updateCount();
        }
    }
    setupCharCounter('emergency_reason', 'reasonCharCount', 500);
    setupCharCounter('additional_notes', 'notesCharCount', 1000);

    // 6. Botón de impresión en comprobante de pre-registro
    const printCodeBtn = document.getElementById('printCodeBtn');
    if (printCodeBtn) {
        printCodeBtn.addEventListener('click', () => {
            window.print();
        });
    }

    // 7. Botón refrescar estado en portal de acompañantes (respetando CSP)
    const btnRefreshStatus = document.getElementById('btnRefreshCompanionStatus');
    if (btnRefreshStatus) {
        btnRefreshStatus.addEventListener('click', function (e) {
            e.preventDefault();
            this.classList.add('disabled');
            this.setAttribute('aria-disabled', 'true');
            const icon = this.querySelector('.bi');
            if (icon) {
                icon.className = 'bi bi-arrow-repeat me-1 spin-animation';
            }
            const label = this.querySelector('span');
            if (label) {
                label.textContent = 'Actualizando...';
            }
            const cleanUrl = this.href.split('?')[0];
            window.location.href = cleanUrl + '?t=' + Date.now();
        });
    }
});