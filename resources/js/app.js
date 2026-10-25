import './bootstrap';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
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
});