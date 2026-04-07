/**
 * ============================================================
 * CyberShield Client Panel - Common JavaScript Functions
 * ============================================================
 */

// Theme Management
class ThemeManager {
    constructor() {
        this.currentTheme = localStorage.getItem('theme') || 'light';
        this.init();
    }
    
    init() {
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        this.updateThemeToggle();
    }
    
    toggle() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        localStorage.setItem('theme', this.currentTheme);
        this.updateThemeToggle();
        
        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { theme: this.currentTheme } 
        }));
    }
    
    updateThemeToggle() {
        const toggle = document.querySelector('[data-theme-toggle]');
        if (toggle) {
            const icon = toggle.querySelector('i, svg');
            if (icon) {
                if (this.currentTheme === 'dark') {
                    icon.className = 'fas fa-sun';
                    toggle.setAttribute('title', 'Switch to light mode');
                } else {
                    icon.className = 'fas fa-moon';
                    toggle.setAttribute('title', 'Switch to dark mode');
                }
            }
        }
    }
    
    getTheme() {
        return this.currentTheme;
    }
}

// Toast Notification System
class ToastManager {
    constructor() {
        this.container = this.createContainer();
        this.toasts = [];
    }
    
    createContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container';
        container.style.cssText = `
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            pointer-events: none;
        `;
        document.body.appendChild(container);
        return container;
    }
    
    show(message, options = {}) {
        const {
            type = 'info',
            duration = 5000,
            action = null,
            persistent = false
        } = options;
        
        const toast = this.createToast(message, type, action);
        this.container.appendChild(toast);
        this.toasts.push(toast);
        
        // Animate in
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
        
        // Auto remove
        if (!persistent) {
            setTimeout(() => this.remove(toast), duration);
        }
        
        return toast;
    }
    
    createToast(message, type, action) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${icons[type]} toast-icon"></i>
                <span class="toast-message">${message}</span>
            </div>
            ${action ? `<button class="toast-action">${action.text}</button>` : ''}
            <button class="toast-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add styles
        toast.style.cssText = `
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
            max-width: 500px;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: auto;
        `;
        
        if (type === 'success') {
            toast.style.borderLeft = '4px solid #10b981';
        } else if (type === 'error') {
            toast.style.borderLeft = '4px solid #ef4444';
        } else if (type === 'warning') {
            toast.style.borderLeft = '4px solid #f59e0b';
        } else {
            toast.style.borderLeft = '4px solid #3b82f6';
        }
        
        // Event listeners
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.remove(toast));
        
        if (action) {
            const actionBtn = toast.querySelector('.toast-action');
            actionBtn.addEventListener('click', () => {
                action.handler();
                this.remove(toast);
            });
        }
        
        return toast;
    }
    
    remove(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            const index = this.toasts.indexOf(toast);
            if (index > -1) {
                this.toasts.splice(index, 1);
            }
        }, 300);
    }
    
    success(message, options = {}) {
        return this.show(message, { ...options, type: 'success' });
    }
    
    error(message, options = {}) {
        return this.show(message, { ...options, type: 'error' });
    }
    
    warning(message, options = {}) {
        return this.show(message, { ...options, type: 'warning' });
    }
    
    info(message, options = {}) {
        return this.show(message, { ...options, type: 'info' });
    }
    
    clear() {
        this.toasts.forEach(toast => this.remove(toast));
    }
}

// Modal Manager
class ModalManager {
    constructor() {
        this.activeModal = null;
        this.bindEvents();
    }
    
    bindEvents() {
        // Close on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.close();
            }
        });
        
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.close();
            }
        });
    }
    
    open(content, options = {}) {
        const {
            title = '',
            size = 'md',
            closeOnEscape = true,
            closeOnOverlay = true,
            showCloseButton = true
        } = options;
        
        this.close();
        
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        
        const modal = document.createElement('div');
        modal.className = `modal modal-${size}`;
        
        modal.innerHTML = `
            ${title ? `
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    ${showCloseButton ? '<button class="modal-close" aria-label="Close"><i class="fas fa-times"></i></button>' : ''}
                </div>
            ` : ''}
            <div class="modal-body">
                ${content}
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        this.activeModal = overlay;
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Focus management
        setTimeout(() => {
            const focusableElement = modal.querySelector('input, button, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (focusableElement) {
                focusableElement.focus();
            }
        }, 100);
        
        return overlay;
    }
    
    close() {
        if (this.activeModal) {
            this.activeModal.remove();
            this.activeModal = null;
            document.body.style.overflow = '';
        }
    }
    
    confirm(message, options = {}) {
        const {
            title = 'Confirm Action',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            confirmClass = 'btn-danger',
            onConfirm = () => {},
            onCancel = () => {}
        } = options;
        
        return new Promise((resolve) => {
            const content = `
                <div class="text-center mb-4">
                    <i class="fas fa-exclamation-triangle text-warning text-4xl mb-3"></i>
                    <p>${message}</p>
                </div>
                <div class="flex gap-3 justify-end">
                    <button class="btn btn-secondary modal-cancel">${cancelText}</button>
                    <button class="btn ${confirmClass} modal-confirm">${confirmText}</button>
                </div>
            `;
            
            const modal = this.open(content, { title });
            
            modal.querySelector('.modal-confirm').addEventListener('click', () => {
                onConfirm();
                this.close();
                resolve(true);
            });
            
            modal.querySelector('.modal-cancel').addEventListener('click', () => {
                onCancel();
                this.close();
                resolve(false);
            });
        });
    }
}

// API Client
class ApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            headers: { ...this.defaultHeaders, ...options.headers },
            ...options
        };
        
        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || `HTTP ${response.status}`);
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('API Error:', error);
            toast.error(error.message || 'Network error occurred');
            throw error;
        }
    }
    
    get(endpoint, params = {}) {
        const url = new URL(endpoint, this.baseUrl);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });
        
        return this.request(url.toString(), { method: 'GET' });
    }
    
    post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
    
    upload(endpoint, formData) {
        return this.request(endpoint, {
            method: 'POST',
            body: formData,
            headers: {} // Let browser set Content-Type for FormData
        });
    }
}

// Form Validation
class FormValidator {
    constructor(form, options = {}) {
        this.form = form;
        this.options = {
            validateOnBlur: true,
            validateOnSubmit: true,
            ...options
        };
        this.rules = new Map();
        this.errors = new Map();
        this.init();
    }
    
    init() {
        if (this.options.validateOnSubmit) {
            this.form.addEventListener('submit', (e) => {
                if (!this.validate()) {
                    e.preventDefault();
                    return false;
                }
            });
        }
        
        if (this.options.validateOnBlur) {
            this.form.addEventListener('blur', (e) => {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                    this.validateField(e.target);
                }
            }, true);
        }
    }
    
    addRule(fieldName, validator, message) {
        if (!this.rules.has(fieldName)) {
            this.rules.set(fieldName, []);
        }
        this.rules.get(fieldName).push({ validator, message });
        return this;
    }
    
    required(fieldName, message = 'This field is required') {
        return this.addRule(fieldName, (value) => value.trim().length > 0, message);
    }
    
    email(fieldName, message = 'Please enter a valid email address') {
        return this.addRule(fieldName, (value) => {
            if (!value) return true; // Optional
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }, message);
    }
    
    minLength(fieldName, min, message = null) {
        return this.addRule(fieldName, (value) => {
            if (!value) return true; // Optional
            return value.length >= min;
        }, message || `Must be at least ${min} characters`);
    }
    
    maxLength(fieldName, max, message = null) {
        return this.addRule(fieldName, (value) => {
            if (!value) return true; // Optional
            return value.length <= max;
        }, message || `Must be no more than ${max} characters`);
    }
    
    pattern(fieldName, pattern, message = 'Invalid format') {
        return this.addRule(fieldName, (value) => {
            if (!value) return true; // Optional
            return pattern.test(value);
        }, message);
    }
    
    validateField(field) {
        const fieldName = field.name;
        const value = field.value;
        const errors = [];
        
        if (this.rules.has(fieldName)) {
            this.rules.get(fieldName).forEach(({ validator, message }) => {
                try {
                    if (!validator(value)) {
                        errors.push(message);
                    }
                } catch (e) {
                    console.error('Validation error:', e);
                    errors.push('Validation error');
                }
            });
        }
        
        this.showFieldErrors(field, errors);
        this.errors.set(fieldName, errors);
        
        return errors.length === 0;
    }
    
    showFieldErrors(field, errors) {
        // Remove existing error
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        field.classList.remove('error');
        
        if (errors.length > 0) {
            field.classList.add('error');
            
            const errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.textContent = errors[0];
            errorElement.style.cssText = `
                color: #ef4444;
                font-size: 0.75rem;
                margin-top: 0.25rem;
            `;
            
            field.parentNode.appendChild(errorElement);
        }
    }
    
    validate() {
        let isValid = true;
        const fields = this.form.querySelectorAll('input, textarea, select');
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    getErrors() {
        const allErrors = {};
        this.errors.forEach((errors, fieldName) => {
            if (errors.length > 0) {
                allErrors[fieldName] = errors;
            }
        });
        return allErrors;
    }
    
    clearErrors() {
        this.form.querySelectorAll('.field-error').forEach(error => error.remove());
        this.form.querySelectorAll('.error').forEach(field => field.classList.remove('error'));
        this.errors.clear();
    }
}

// Loading States
class LoadingManager {
    static show(element, options = {}) {
        const {
            text = 'Loading...',
            spinner = true,
            overlay = false
        } = options;
        
        element.disabled = true;
        element.dataset.originalText = element.textContent;
        
        if (overlay) {
            element.style.position = 'relative';
            
            const overlayDiv = document.createElement('div');
            overlayDiv.className = 'loading-overlay';
            overlayDiv.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: inherit;
                z-index: 10;
            `;
            
            const content = document.createElement('div');
            content.style.cssText = `
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: #6b7280;
            `;
            
            if (spinner) {
                content.innerHTML += `
                    <div class="spinner" style="
                        width: 1rem;
                        height: 1rem;
                        border: 2px solid #e5e7eb;
                        border-top-color: #3b82f6;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                    "></div>
                `;
            }
            
            content.innerHTML += `<span>${text}</span>`;
            overlayDiv.appendChild(content);
            element.appendChild(overlayDiv);
        } else {
            let html = '';
            if (spinner) {
                html += `
                    <div class="spinner" style="
                        width: 1rem;
                        height: 1rem;
                        border: 2px solid currentColor;
                        border-top-color: transparent;
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        display: inline-block;
                        margin-right: 0.5rem;
                    "></div>
                `;
            }
            html += text;
            element.innerHTML = html;
        }
    }
    
    static hide(element) {
        element.disabled = false;
        element.innerHTML = element.dataset.originalText || '';
        delete element.dataset.originalText;
        
        const overlay = element.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
}

// Utility Functions
const Utils = {
    debounce(func, wait, immediate = false) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    },
    
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    },
    
    formatDate(dateString, options = {}) {
        const date = new Date(dateString);
        const defaults = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };
        
        return date.toLocaleDateString('en-US', { ...defaults, ...options });
    },
    
    formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    formatDateTime(dateString, options = {}) {
        const date = new Date(dateString);
        const defaults = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return date.toLocaleString('en-US', { ...defaults, ...options });
    },
    
    formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    },
    
    formatCurrency(amount, currency = 'PHP') {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    slugify(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    },
    
    generateId(prefix = 'id') {
        return `${prefix}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    },
    
    copyToClipboard(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text)
                .then(() => toast.success('Copied to clipboard'))
                .catch(() => toast.error('Failed to copy'));
        } else {
            // Fallback
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    toast.success('Copied to clipboard');
                } else {
                    toast.error('Failed to copy');
                }
            } catch (err) {
                toast.error('Failed to copy');
            }
            
            document.body.removeChild(textArea);
        }
    },
    
    scrollTo(element, options = {}) {
        const defaults = {
            behavior: 'smooth',
            block: 'start'
        };
        
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element) {
            element.scrollIntoView({ ...defaults, ...options });
        }
    },
    
    animate(element, keyframes, options = {}) {
        const defaults = {
            duration: 300,
            easing: 'ease'
        };
        
        return element.animate(keyframes, { ...defaults, ...options });
    }
};

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .toast.show {
        transform: translateX(0);
        opacity: 1;
    }
    
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9998;
        padding: 1rem;
    }
    
    .modal {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        max-width: 90vw;
        max-height: 90vh;
        overflow: auto;
        animation: modalFadeIn 0.3s ease;
    }
    
    .modal-sm { max-width: 400px; }
    .modal-md { max-width: 600px; }
    .modal-lg { max-width: 800px; }
    .modal-xl { max-width: 1200px; }
    
    .modal-header {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .modal-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
    }
    
    .modal-close {
        background: none;
        border: none;
        padding: 0.5rem;
        border-radius: 0.25rem;
        cursor: pointer;
        color: #6b7280;
        transition: color 0.2s;
    }
    
    .modal-close:hover {
        color: #374151;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .field-error {
        color: #ef4444;
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }
    
    .error {
        border-color: #ef4444 !important;
    }
    
    .toast-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    
    .toast-icon {
        flex-shrink: 0;
    }
    
    .toast-message {
        flex: 1;
    }
    
    .toast-action {
        background: none;
        border: none;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: #3b82f6;
        cursor: pointer;
        white-space: nowrap;
    }
    
    .toast-action:hover {
        background: rgba(59, 130, 246, 0.1);
    }
    
    .toast-close {
        background: none;
        border: none;
        padding: 0.25rem;
        border-radius: 0.25rem;
        cursor: pointer;
        color: #6b7280;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .toast-close:hover {
        color: #374151;
    }
`;
document.head.appendChild(style);

// Initialize global instances
const themeManager = new ThemeManager();
const toast = new ToastManager();
const modal = new ModalManager();
const api = new ApiClient();

// Export for global use
window.CyberShield = {
    themeManager,
    toast,
    modal,
    api,
    FormValidator,
    LoadingManager,
    Utils
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Auto-initialize theme toggles
    document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
        toggle.addEventListener('click', () => themeManager.toggle());
    });
    
    // Auto-initialize tooltips
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', (e) => {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = e.target.dataset.tooltip;
            tooltip.style.cssText = `
                position: absolute;
                background: #1f2937;
                color: white;
                padding: 0.5rem;
                border-radius: 0.25rem;
                font-size: 0.75rem;
                z-index: 9999;
                pointer-events: none;
                white-space: nowrap;
            `;
            document.body.appendChild(tooltip);
            
            const rect = e.target.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            
            e.target.tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', (e) => {
            if (e.target.tooltip) {
                e.target.tooltip.remove();
                delete e.target.tooltip;
            }
        });
    });
});
