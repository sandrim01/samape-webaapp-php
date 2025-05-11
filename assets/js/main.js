/**
 * SAMAPE - Main JavaScript file
 * Handles common functionality across the application
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        });
    }
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if (tooltips.length > 0) {
        const tooltipTriggerList = [].slice.call(tooltips);
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Initialize popovers
    const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
    if (popovers.length > 0) {
        const popoverTriggerList = [].slice.call(popovers);
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
    
    // Auto-close alerts after 5 seconds
    const autoCloseAlerts = document.querySelectorAll('.alert-dismissible');
    if (autoCloseAlerts.length > 0) {
        autoCloseAlerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    }
    
    // Confirm deletions
    const confirmDeleteButtons = document.querySelectorAll('.confirm-delete');
    if (confirmDeleteButtons.length > 0) {
        confirmDeleteButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                if (!confirm('Tem certeza que deseja excluir este item? Esta ação não pode ser desfeita.')) {
                    e.preventDefault();
                }
            });
        });
    }
    
    // Handle client selection in service order form
    const clientSelect = document.getElementById('cliente_id');
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            const clientId = this.value;
            if (clientId) {
                fetchClientMachinery(clientId);
            } else {
                const machinerySelect = document.getElementById('maquinario_id');
                machinerySelect.innerHTML = '<option value="">Selecione o cliente primeiro</option>';
                machinerySelect.setAttribute('disabled', 'disabled');
            }
        });
    }
    
    // Date picker initialization for date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    if (dateInputs.length > 0) {
        dateInputs.forEach(function(input) {
            if (!input.value && input.hasAttribute('data-default-today')) {
                const today = new Date().toISOString().split('T')[0];
                input.value = today;
            }
        });
    }
    
    // Print functionality
    const printButtons = document.querySelectorAll('.btn-print');
    if (printButtons.length > 0) {
        printButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                window.print();
            });
        });
    }
    
    // Show/hide password toggle
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    if (togglePasswordButtons.length > 0) {
        togglePasswordButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const passwordField = document.querySelector(this.getAttribute('data-target'));
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                // Toggle icon
                const icon = this.querySelector('i');
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }
    
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.searchable-table tbody tr');
            
            tableRows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});

/**
 * Fetch machinery for a selected client
 * @param {number} clientId - The ID of the selected client
 */
function fetchClientMachinery(clientId) {
    const machinerySelect = document.getElementById('maquinario_id');
    
    // Show loading state
    machinerySelect.innerHTML = '<option value="">Carregando...</option>';
    
    fetch('/api/clients.php?action=get_machinery&client_id=' + clientId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                machinerySelect.innerHTML = '<option value="">Selecione o maquinário</option>';
                
                if (data.machinery.length > 0) {
                    data.machinery.forEach(function(machine) {
                        const option = document.createElement('option');
                        option.value = machine.id;
                        option.textContent = `${machine.tipo} ${machine.marca} ${machine.modelo} (${machine.numero_serie || 'S/N'})`;
                        machinerySelect.appendChild(option);
                    });
                    
                    machinerySelect.removeAttribute('disabled');
                } else {
                    machinerySelect.innerHTML = '<option value="">Nenhum maquinário encontrado</option>';
                    machinerySelect.setAttribute('disabled', 'disabled');
                }
            } else {
                machinerySelect.innerHTML = '<option value="">Erro ao carregar maquinários</option>';
                machinerySelect.setAttribute('disabled', 'disabled');
                console.error('Error fetching machinery:', data.message);
            }
        })
        .catch(error => {
            machinerySelect.innerHTML = '<option value="">Erro ao carregar maquinários</option>';
            machinerySelect.setAttribute('disabled', 'disabled');
            console.error('Fetch error:', error);
        });
}

/**
 * Format a number as Brazilian currency
 * @param {number} value - The number to format
 * @return {string} Formatted currency string
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

/**
 * Format a date in the DD/MM/YYYY format
 * @param {string} dateString - The date string to format
 * @return {string} Formatted date string
 */
function formatDate(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    return `${day}/${month}/${year}`;
}

/**
 * Export table data to CSV
 * @param {string} tableId - The ID of the table to export
 * @param {string} filename - The name of the CSV file
 */
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    const csvContent = [];
    
    rows.forEach(function(row) {
        const rowData = [];
        const cols = row.querySelectorAll('th, td');
        
        cols.forEach(function(col) {
            // Skip columns with the 'no-export' class
            if (!col.classList.contains('no-export')) {
                // Replace HTML entities and remove HTML tags
                let text = col.innerText;
                text = text.replace(/"/g, '""'); // Escape quotes
                rowData.push('"' + text + '"');
            }
        });
        
        csvContent.push(rowData.join(','));
    });
    
    const csvString = csvContent.join('\n');
    const blob = new Blob(['\uFEFF' + csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        // For IE
        navigator.msSaveBlob(blob, filename);
    } else {
        // For other browsers
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
