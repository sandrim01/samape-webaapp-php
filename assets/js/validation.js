/**
 * SAMAPE - Form validation JavaScript
 * Handles client-side form validation
 */

document.addEventListener('DOMContentLoaded', function() {
    // Find all forms with validation class
    const forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission if validation fails
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Find first invalid input and focus it
                const firstInvalidEl = form.querySelector(':invalid');
                if (firstInvalidEl) {
                    firstInvalidEl.focus();
                }
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Add validation for specific field types
    
    // CNPJ/CPF validation
    const cnpjInputs = document.querySelectorAll('input[data-validate="document"]');
    cnpjInputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            const value = this.value.replace(/[^\d]/g, '');
            const isValid = validateDocument(value);
            
            if (value.length > 0 && !isValid) {
                this.setCustomValidity('CNPJ/CPF inválido');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Phone validation
    const phoneInputs = document.querySelectorAll('input[data-validate="phone"]');
    phoneInputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            const value = this.value.replace(/[^\d]/g, '');
            
            if (value.length > 0 && (value.length < 10 || value.length > 11)) {
                this.setCustomValidity('Telefone inválido');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            const value = this.value.trim();
            
            if (value.length > 0 && !validateEmail(value)) {
                this.setCustomValidity('E-mail inválido');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Password strength validation
    const passwordInputs = document.querySelectorAll('input[data-validate="password"]');
    passwordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            validatePasswordStrength(this);
        });
    });
    
    // Password confirmation validation
    const confirmPasswordInputs = document.querySelectorAll('input[data-validate="confirm-password"]');
    confirmPasswordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const passwordField = document.querySelector(this.getAttribute('data-match'));
            
            if (passwordField && this.value !== passwordField.value) {
                this.setCustomValidity('As senhas não correspondem');
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Mask CNPJ/CPF
    const documentMasks = document.querySelectorAll('input[data-mask="document"]');
    documentMasks.forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = maskDocument(this.value);
        });
    });
    
    // Mask phone
    const phoneMasks = document.querySelectorAll('input[data-mask="phone"]');
    phoneMasks.forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = maskPhone(this.value);
        });
    });
    
    // Mask currency
    const currencyMasks = document.querySelectorAll('input[data-mask="currency"]');
    currencyMasks.forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = maskCurrency(this.value);
        });
    });
    
    // Mask date
    const dateMasks = document.querySelectorAll('input[data-mask="date"]');
    dateMasks.forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = maskDate(this.value);
        });
    });
});

/**
 * Validate if a document (CPF/CNPJ) is valid
 * @param {string} document - The document string (digits only)
 * @return {boolean} Whether the document is valid or not
 */
function validateDocument(document) {
    document = document.replace(/[^\d]/g, '');
    
    // Validate CPF
    if (document.length === 11) {
        // Check for all the same digits, which is invalid
        if (/^(\d)\1{10}$/.test(document)) {
            return false;
        }
        
        // Calculate verification digits
        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(document.charAt(i)) * (10 - i);
        }
        
        let remainder = sum % 11;
        let dv1 = remainder < 2 ? 0 : 11 - remainder;
        
        sum = 0;
        for (let i = 0; i < 10; i++) {
            sum += parseInt(document.charAt(i)) * (11 - i);
        }
        
        remainder = sum % 11;
        let dv2 = remainder < 2 ? 0 : 11 - remainder;
        
        return document.charAt(9) == dv1 && document.charAt(10) == dv2;
    }
    
    // Validate CNPJ
    else if (document.length === 14) {
        // Check for all the same digits, which is invalid
        if (/^(\d)\1{13}$/.test(document)) {
            return false;
        }
        
        // Calculate first verification digit
        let sum = 0;
        let weight = 5;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(document.charAt(i)) * weight;
            weight = weight === 2 ? 9 : weight - 1;
        }
        
        let remainder = sum % 11;
        let dv1 = remainder < 2 ? 0 : 11 - remainder;
        
        // Calculate second verification digit
        sum = 0;
        weight = 6;
        for (let i = 0; i < 13; i++) {
            sum += parseInt(document.charAt(i)) * weight;
            weight = weight === 2 ? 9 : weight - 1;
        }
        
        remainder = sum % 11;
        let dv2 = remainder < 2 ? 0 : 11 - remainder;
        
        return document.charAt(12) == dv1 && document.charAt(13) == dv2;
    }
    
    return false;
}

/**
 * Validate an email address
 * @param {string} email - The email to validate
 * @return {boolean} Whether the email is valid or not
 */
function validateEmail(email) {
    const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Validate password strength
 * @param {HTMLElement} input - The password input element
 */
function validatePasswordStrength(input) {
    const value = input.value;
    const minLength = parseInt(input.getAttribute('data-min-length') || '8');
    
    // Check minimum length
    if (value.length < minLength) {
        input.setCustomValidity(`A senha deve ter pelo menos ${minLength} caracteres`);
        return;
    }
    
    // Check for complexity requirements if specified
    if (input.hasAttribute('data-require-complex')) {
        // Pattern for at least one: uppercase, lowercase, digit, and special character
        const pattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/;
        
        if (!pattern.test(value)) {
            input.setCustomValidity('A senha deve conter pelo menos uma letra maiúscula, uma minúscula, um número e um caractere especial');
            return;
        }
    }
    
    input.setCustomValidity('');
}

/**
 * Format CNPJ/CPF with mask
 * @param {string} value - The value to be masked
 * @return {string} The masked value
 */
function maskDocument(value) {
    value = value.replace(/\D/g, '');
    
    if (value.length <= 11) {
        // CPF: 000.000.000-00
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        // CNPJ: 00.000.000/0000-00
        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
        value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    }
    
    return value;
}

/**
 * Format phone with mask
 * @param {string} value - The value to be masked
 * @return {string} The masked value
 */
function maskPhone(value) {
    value = value.replace(/\D/g, '');
    
    if (value.length > 10) {
        // Mobile: (00) 00000-0000
        value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
    } else {
        // Landline: (00) 0000-0000
        value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    }
    
    return value;
}

/**
 * Format currency with mask
 * @param {string} value - The value to be masked
 * @return {string} The masked value
 */
function maskCurrency(value) {
    value = value.replace(/\D/g, '');
    
    if (value === '') return '';
    
    // Convert to float: 12345 -> 123.45
    value = (parseInt(value) / 100).toFixed(2) + '';
    
    // Format with Brazilian currency pattern
    value = value.replace('.', ',');
    value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    
    return 'R$ ' + value;
}

/**
 * Format date with mask (DD/MM/YYYY)
 * @param {string} value - The value to be masked
 * @return {string} The masked value
 */
function maskDate(value) {
    value = value.replace(/\D/g, '');
    
    value = value.replace(/(\d{2})(\d)/, '$1/$2');
    value = value.replace(/(\d{2})(\d)/, '$1/$2');
    
    return value;
}
