/* ============================================================
   Lógica interactiva de Perfil de Usuario y Cambio de Clave
   ============================================================ */

// Toggle de mostrar/ocultar contraseña
function togglePassInput(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Copiar al portapapeles con feedback instantáneo
function copiarAlPortapapeles(texto, btn) {
    if (!navigator.clipboard) return;
    navigator.clipboard.writeText(texto).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 text-success"></i> ¡Copiado!';
        btn.classList.add('text-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('text-success');
        }, 2000);
    });
}

// Validación interactiva de fuerza de contraseña en tiempo real
document.addEventListener('DOMContentLoaded', function () {
    const inputNueva = document.getElementById('password_nueva');
    const inputConfirm = document.getElementById('password_confirm');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const matchMessage = document.getElementById('matchMessage');

    const checkLength = document.getElementById('checkLength');
    const checkLetter = document.getElementById('checkLetter');
    const checkNumber = document.getElementById('checkNumber');
    const checkMatch  = document.getElementById('checkMatch');

    function actualizarRequisitos() {
        const val = inputNueva.value;
        const confirmVal = inputConfirm.value;

        // Longitud mínima
        const tieneLongitud = val.length >= 6;
        actualizarItem(checkLength, tieneLongitud);

        // Letras
        const tieneLetra = /[a-zA-Z]/.test(val);
        actualizarItem(checkLetter, tieneLetra);

        // Números o especiales
        const tieneNumOEspecial = /[0-9!@#$%^&*(),.?":{}|<>]/.test(val);
        actualizarItem(checkNumber, tieneNumOEspecial);

        // Coincidencia
        const coinciden = val !== '' && val === confirmVal;
        actualizarItem(checkMatch, coinciden);

        if (confirmVal.length > 0) {
            matchMessage.style.display = 'block';
            if (coinciden) {
                matchMessage.className = 'small mt-1 text-success fw-semibold';
                matchMessage.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Las contraseñas coinciden.';
            } else {
                matchMessage.className = 'small mt-1 text-danger fw-semibold';
                matchMessage.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Las contraseñas no coinciden.';
            }
        } else {
            matchMessage.style.display = 'none';
        }

        // Calcular barra de fuerza
        let score = 0;
        if (tieneLongitud) score++;
        if (tieneLetra) score++;
        if (tieneNumOEspecial) score++;
        if (val.length >= 10) score++;

        if (val.length === 0) {
            strengthBar.style.width = '0%';
            strengthBar.style.backgroundColor = '#e5e7eb';
            strengthText.textContent = 'Seguridad de la clave';
            strengthText.className = 'text-muted';
        } else if (score <= 1) {
            strengthBar.style.width = '25%';
            strengthBar.style.backgroundColor = '#ef4444';
            strengthText.textContent = 'Seguridad: Débil';
            strengthText.className = 'text-danger fw-semibold';
        } else if (score === 2) {
            strengthBar.style.width = '50%';
            strengthBar.style.backgroundColor = '#f59e0b';
            strengthText.textContent = 'Seguridad: Media';
            strengthText.className = 'text-warning fw-semibold';
        } else if (score === 3) {
            strengthBar.style.width = '75%';
            strengthBar.style.backgroundColor = '#10b981';
            strengthText.textContent = 'Seguridad: Buena';
            strengthText.className = 'text-success fw-semibold';
        } else {
            strengthBar.style.width = '100%';
            strengthBar.style.backgroundColor = '#059669';
            strengthText.textContent = 'Seguridad: Excelente';
            strengthText.className = 'text-success fw-bold';
        }
    }

    function actualizarItem(elem, valido) {
        if (!elem) return;
        const icon = elem.querySelector('i');
        if (valido) {
            elem.classList.add('valid');
            icon.className = 'bi bi-check-circle-fill';
        } else {
            elem.classList.remove('valid');
            icon.className = 'bi bi-circle';
        }
    }

    if (inputNueva) {
        inputNueva.addEventListener('input', actualizarRequisitos);
    }
    if (inputConfirm) {
        inputConfirm.addEventListener('input', actualizarRequisitos);
    }
});
