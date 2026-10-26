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

    // 2. Pre-registro: Control dinámico de tipo y número de documento
    const docTypeSelect = document.getElementById('document_type');
    const docNumberInput = document.getElementById('document_number');
    const minorNotice = document.getElementById('minorNotice');
    const companionStatusBadge = document.getElementById('companionStatusBadge');

    function updateDocTypeState() {
        if (!docTypeSelect || !docNumberInput) return;
        const val = docTypeSelect.value;
        const isMinor = ['Tarjeta de identidad', 'Registro civil', 'Menor sin identificación'].includes(val);

        if (val === 'Menor sin identificación') {
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