/**
 * ============================================================
 * CyberShield Admin Panel - Common JavaScript Functions
 * ============================================================
 */

// Theme Management
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update theme toggle icons
    const moonIcon = document.getElementById('tmoon');
    const sunIcon = document.getElementById('tsun');
    
    if (newTheme === 'light') {
        moonIcon.style.display = 'none';
        sunIcon.style.display = 'block';
    } else {
        moonIcon.style.display = 'block';
        sunIcon.style.display = 'none';
    }
}

// Initialize theme on page load
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    const moonIcon = document.getElementById('tmoon');
    const sunIcon = document.getElementById('tsun');
    
    if (savedTheme === 'light') {
        moonIcon.style.display = 'none';
        sunIcon.style.display = 'block';
    } else {
        moonIcon.style.display = 'block';
        sunIcon.style.display = 'none';
    }
}

// Sidebar Management
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    
    // Save preference
    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebar-collapsed', isCollapsed);
}

// Initialize sidebar state
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
    }
}

// Toast Notification System
class ToastManager {
    constructor() {
        this.container = document.getElementById('toast-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            document.body.appendChild(this.container);
        }
    }
    
    show(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        
        const indicator = document.createElement('div');
        indicator.className = 'toast-indicator';
        
        // Set color based on type
        const colors = {
            success: '#10D982',
            error: '#FF3B5C',
            warning: '#F5B731',
            info: '#3B8BFF'
        };
        
        indicator.style.backgroundColor = colors[type] || colors.info;
        
        const text = document.createElement('span');
        text.textContent = message;
        
        toast.appendChild(indicator);
        toast.appendChild(text);
        
        this.container.appendChild(toast);
        
        // Auto remove
        setTimeout(() => {
            toast.style.animation = 'slideIn 0.2s ease reverse';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 200);
        }, duration);
        
        return toast;
    }
    
    success(message, duration) {
        return this.show(message, 'success', duration);
    }
    
    error(message, duration) {
        return this.show(message, 'error', duration);
    }
    
    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }
    
    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// Global toast instance
const toast = new ToastManager();

// Modal Management
class ModalManager {
    constructor() {
        this.activeModal = null;
        this.bindEvents();
    }
    
    bindEvents() {
        // Close modal on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                this.close();
            }
        });
        
        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.close();
            }
        });
    }
    
    open(content, title = '') {
        // Close existing modal
        if (this.activeModal) {
            this.close();
        }
        
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        
        const modal = document.createElement('div');
        modal.className = 'modal';
        
        modal.innerHTML = `
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close" onclick="modalManager.close()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        this.activeModal = overlay;
        
        // Focus management
        setTimeout(() => {
            const firstInput = modal.querySelector('input, button, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
    }
    
    close() {
        if (this.activeModal) {
            this.activeModal.remove();
            this.activeModal = null;
        }
    }
}

// Global modal instance
const modalManager = new ModalManager();

// Form Validation
class FormValidator {
    constructor(form) {
        this.form = form;
        this.rules = {};
        this.messages = {};
        this.bindEvents();
    }
    
    bindEvents() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Real-time validation
        this.form.addEventListener('blur', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                this.validateField(e.target);
            }
        }, true);
    }
    
    addRule(fieldName, rule, message) {
        if (!this.rules[fieldName]) {
            this.rules[fieldName] = [];
        }
        this.rules[fieldName].push({ rule, message });
    }
    
    validateField(field) {
        const fieldName = field.name;
        const value = field.value.trim();
        const errors = [];
        
        if (this.rules[fieldName]) {
            this.rules[fieldName].forEach(({ rule, message }) => {
                if (!this.applyRule(value, rule)) {
                    errors.push(message);
                }
            });
        }
        
        // Show/hide errors
        this.showFieldErrors(field, errors);
        
        return errors.length === 0;
    }
    
    applyRule(value, rule) {
        if (typeof rule === 'function') {
            return rule(value);
        }
        
        switch (rule) {
            case 'required':
                return value.length > 0;
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            case 'phone':
                return /^[\d\s\-\+\(\)]+$/.test(value);
            default:
                return true;
        }
    }
    
    showFieldErrors(field, errors) {
        // Remove existing errors
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
                color: var(--primary-red);
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
}

// API Helper
class ApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            toast.error(error.message || 'Network error occurred');
            throw error;
        }
    }
    
    get(endpoint) {
        return this.request(endpoint);
    }
    
    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }
}

// Global API instance
const api = new ApiClient();

// Data Table Helper
class DataTable {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            pageSize: 10,
            searchable: true,
            sortable: true,
            ...options
        };
        this.data = [];
        this.filteredData = [];
        this.currentPage = 1;
        this.sortColumn = null;
        this.sortDirection = 'asc';
    }
    
    setData(data) {
        this.data = data;
        this.filteredData = [...data];
        this.currentPage = 1;
        this.render();
    }
    
    filter(searchTerm) {
        if (!searchTerm) {
            this.filteredData = [...this.data];
        } else {
            const term = searchTerm.toLowerCase();
            this.filteredData = this.data.filter(row => {
                return Object.values(row).some(value => 
                    String(value).toLowerCase().includes(term)
                );
            });
        }
        this.currentPage = 1;
        this.render();
    }
    
    sort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        
        this.filteredData.sort((a, b) => {
            const aVal = a[column];
            const bVal = b[column];
            
            if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
        
        this.render();
    }
    
    render() {
        const startIndex = (this.currentPage - 1) * this.options.pageSize;
        const endIndex = startIndex + this.options.pageSize;
        const pageData = this.filteredData.slice(startIndex, endIndex);
        
        // Render table
        this.renderTable(pageData);
        
        // Render pagination
        this.renderPagination();
    }
    
    renderTable(data) {
        // Implementation depends on specific table structure
        // This is a basic template
        const tbody = this.container.querySelector('tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (data.length === 0) {
            const row = tbody.insertRow();
            const cell = row.insertCell();
            cell.colSpan = this.container.querySelectorAll('thead th').length;
            cell.textContent = 'No data available';
            cell.style.textAlign = 'center';
            cell.style.color = 'var(--text-muted)';
            return;
        }
        
        data.forEach(rowData => {
            const row = tbody.insertRow();
            Object.values(rowData).forEach(value => {
                const cell = row.insertCell();
                cell.textContent = value;
            });
        });
    }
    
    renderPagination() {
        const totalPages = Math.ceil(this.filteredData.length / this.options.pageSize);
        const pagination = this.container.querySelector('.pagination');
        
        if (!pagination || totalPages <= 1) {
            if (pagination) pagination.style.display = 'none';
            return;
        }
        
        pagination.style.display = 'flex';
        pagination.innerHTML = '';
        
        // Previous button
        const prevBtn = this.createPageButton('Previous', this.currentPage > 1, () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.render();
            }
        });
        pagination.appendChild(prevBtn);
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                const pageBtn = this.createPageButton(i, true, () => {
                    this.currentPage = i;
                    this.render();
                });
                
                if (i === this.currentPage) {
                    pageBtn.classList.add('active');
                }
                
                pagination.appendChild(pageBtn);
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.padding = '0 0.5rem';
                dots.style.color = 'var(--text-muted)';
                pagination.appendChild(dots);
            }
        }
        
        // Next button
        const nextBtn = this.createPageButton('Next', this.currentPage < totalPages, () => {
            if (this.currentPage < totalPages) {
                this.currentPage++;
                this.render();
            }
        });
        pagination.appendChild(nextBtn);
    }
    
    createPageButton(text, enabled, onClick) {
        const button = document.createElement('button');
        button.className = 'page-btn';
        button.textContent = text;
        button.disabled = !enabled;
        
        if (!enabled) {
            button.style.opacity = '0.5';
            button.style.cursor = 'not-allowed';
        }
        
        button.addEventListener('click', onClick);
        return button;
    }
}

// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        toast.success('Copied to clipboard');
    }).catch(() => {
        toast.error('Failed to copy');
    });
}

// Loading States
function showLoading(element) {
    element.disabled = true;
    element.dataset.originalText = element.textContent;
    element.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
            <path d="M21 12a9 9 0 11-6.219-8.56"/>
        </svg>
        Loading...
    `;
}

function hideLoading(element) {
    element.disabled = false;
    element.textContent = element.dataset.originalText;
    delete element.dataset.originalText;
}

// Add spin animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        100% { transform: rotate(360deg); }
    }
    .field-error {
        color: var(--primary-red);
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }
    .error {
        border-color: var(--primary-red) !important;
    }
`;
document.head.appendChild(style);

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
    
    // Update date/time
    const updateDateTime = () => {
        const dateElement = document.getElementById('tb-date');
        if (dateElement) {
            dateElement.textContent = new Date().toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    };
    
    updateDateTime();
    setInterval(updateDateTime, 60000); // Update every minute
});

// Global error handler
window.addEventListener('error', (e) => {
    console.error('Global error:', e.error);
    toast.error('An unexpected error occurred');
});

// Export for use in other files
window.CyberShield = {
    toast,
    modalManager,
    api,
    DataTable,
    FormValidator,
    debounce,
    formatBytes,
    formatDate,
    formatNumber,
    escapeHtml,
    copyToClipboard,
    showLoading,
    hideLoading
};
