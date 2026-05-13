/**
 * sira-security-ui.js
 * 
 * Propósito: Gestionar los ayudantes visuales de seguridad (ojo de contraseña,
 * complejidad y match) con el mínimo código indispensable.
 * 
 * SIRA "Zero-JS" Standard (UI Helpers Exception)
 */

/**
 * Alterna la visibilidad de un campo de contraseña.
 */
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    
    // Iconos SVG minimalistas (Eye / Eye-Off)
    const eyeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

    btn.innerHTML = isPassword ? eyeOffIcon : eyeIcon;
}

/**
 * Valida la complejidad de la contraseña en tiempo real para el feedback visual.
 */
function validateComplexity(pw, minLenRequired) {
    const rules = {
        len: pw.length >= minLenRequired,
        num: /[0-9]/.test(pw),
        cap: /[A-Z]/.test(pw),
        low: /[a-z]/.test(pw),
        sym: /[!@#$%^&*(),.?":{}|<>]/.test(pw)
    };

    const updateUI = (id, valid) => {
        const el = document.getElementById('req-' + id);
        if (!el) return;
        
        // Guardar el texto original para que no se "coma" caracteres por el emoji
        if (!el.hasAttribute('data-label')) {
            el.setAttribute('data-label', el.innerText.replace(/^[❌✅]\s/, ''));
        }
        
        const label = el.getAttribute('data-label');
        el.innerText = (valid ? '✅ ' : '❌ ') + label;
        el.style.opacity = valid ? '1' : '0.5';
        el.style.color = valid ? 'var(--color-primary)' : 'inherit';
    };

    updateUI('len', rules.len);
    updateUI('num', rules.num);
    updateUI('cap', rules.cap);
    updateUI('low', rules.low);
    updateUI('sym', rules.sym);

    checkMatch(minLenRequired); 
}

/**
 * Valida que los dos campos de contraseña coincidan.
 */
function checkMatch(minLenRequired) {
    const pwInput = document.getElementById('new_password');
    const cpwInput = document.getElementById('confirm_password');
    const btn = document.getElementById('submit_btn');
    
    if (!pwInput || !cpwInput || !btn) return;

    const pw = pwInput.value;
    const cpw = cpwInput.value;

    const validComplexity = (
        pw.length >= minLenRequired && 
        /[0-9]/.test(pw) && 
        /[A-Z]/.test(pw) && 
        /[a-z]/.test(pw) && 
        /[!@#$%^&*(),.?":{}|<>]/.test(pw)
    );
    
    const match = (pw === cpw && pw !== "");

    if (validComplexity && match) {
        btn.disabled = false;
        btn.style.filter = "none";
        btn.style.opacity = "1";
        btn.style.cursor = "pointer";
        btn.style.background = "var(--color-primary)"; 
    } else {
        btn.disabled = true;
        btn.style.filter = "grayscale(1)";
        btn.style.opacity = "0.5";
        btn.style.cursor = "not-allowed";
        btn.style.background = ""; 
    }
}

/**
 * Genera una contraseña temporal segura que cumpla con todos los requisitos.
 */
function generateTemporaryPassword(passId = 'password', confId = 'confirm_password', btnContext = null) {
    const chars = "abcdefghijklmnopqrstuvwxyz";
    const CAPS = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const NUMS = "0123456789";
    const SYMS = "!@#$%^&*()_+~`|}{[]:;?><,./-=";

    // Asegurar al menos uno de cada
    let pw = chars[Math.floor(Math.random() * chars.length)]
           + CAPS[Math.floor(Math.random() * CAPS.length)]
           + NUMS[Math.floor(Math.random() * NUMS.length)]
           + SYMS[Math.floor(Math.random() * SYMS.length)];

    const all = chars + CAPS + NUMS + SYMS;
    const length = 12; // 12 chars es seguro y cumple req de 10
    
    for (let i = pw.length; i < length; i++) {
        pw += all[Math.floor(Math.random() * all.length)];
    }

    // Mezclar carácteres
    pw = pw.split('').sort(() => 0.5 - Math.random()).join('');

    const passInput = document.getElementById(passId);
    const confInput = document.getElementById(confId);
    
    if (passInput) {
        passInput.value = pw;
        passInput.type = 'text'; // Mostrarla
        // Activar el botón de alternancia para mostrar el icono de "ojo tachado" si existe
        const wrapper = passInput.closest('.password-toggle-wrapper');
        if (wrapper) {
            const btn = wrapper.querySelector('.password-toggle-btn');
            if (btn) togglePassword(passId, btn); // Esto podría volver a tipo password, forzamos texto a continuación
        }
        passInput.type = 'text'; // Forzar tipo texto de nuevo por seguridad visual
    }
    
    if (confInput) {
        confInput.value = pw;
        confInput.type = 'text';
    }

    // Actualizar el UI de la complejidad si existe
    const event = new Event('input');
    if (passInput) passInput.dispatchEvent(event);
    if (confInput) confInput.dispatchEvent(event);

    // Eliminar el cambio de texto para mantener el diseño de botones con icono
}

/**
 * Copia el valor del input especificado al portapapeles.
 */
function copyToClipboard(inputId, btnContext) {
    const input = document.getElementById(inputId);
    if (input && input.value) {
        navigator.clipboard.writeText(input.value).then(() => {
            const originalHTML = btnContext.innerHTML;
            // Solo icono de check
            btnContext.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
            setTimeout(() => { btnContext.innerHTML = originalHTML; }, 2000);
        }).catch(err => {
            console.error('Error al copiar: ', err);
            btnContext.innerText = "Error";
        });
    }
}

