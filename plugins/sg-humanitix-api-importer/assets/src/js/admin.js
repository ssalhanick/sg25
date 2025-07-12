/**
 * Humanitix API Importer Admin JavaScript
 *
 * @package SG\HumanitixApiImporter
 */

// Import admin styles
import '../sass/admin.scss';

console.log('Humanitix Admin JS loaded');

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    console.log('DOM Content Loaded');
    console.log('humanitixAdmin object:', typeof humanitixAdmin !== 'undefined' ? humanitixAdmin : 'NOT DEFINED');

    // API Test functionality
    const testApiButton = document.getElementById('test-api');
    console.log('Test API button found:', !!testApiButton);
    if (testApiButton) {
        testApiButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const resultDiv = document.getElementById('api-test-result');
            
            // Disable button and show loading
            this.disabled = true;
            this.textContent = 'Testing...';
            resultDiv.innerHTML = '<div class="notice notice-info"><p>Testing API connection...</p></div>';
            
            // Make AJAX request
            fetch(humanitixAdmin.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'test_api_connection',
                    nonce: humanitixAdmin.apiTestNonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="notice notice-success"><p>' + data.data.message + '</p></div>';
                } else {
                    resultDiv.innerHTML = '<div class="notice notice-error"><p>' + data.data.message + '</p></div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="notice notice-error"><p>Failed to test API connection. Please try again.</p></div>';
                console.error('API test error:', error);
            })
            .finally(() => {
                this.disabled = false;
                this.textContent = 'Test API Connection';
            });
        });
    }

    // Import functionality
    const startImportButton = document.getElementById('start-import');
    if (startImportButton) {
        startImportButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const stopButton = document.getElementById('stop-import');
            const statusDiv = document.getElementById('import-status');
            const progressDiv = document.getElementById('import-progress');
            const resultsDiv = document.getElementById('import-results');
            
            // Show progress and disable start button
            this.disabled = true;
            this.textContent = 'Importing...';
            stopButton.style.display = 'inline-block';
            progressDiv.style.display = 'block';
            resultsDiv.style.display = 'none';
            statusDiv.innerHTML = '<span class="spinner is-active"></span> Starting import...';
            
            // Make AJAX request
            fetch(humanitixAdmin.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'import_events',
                    nonce: humanitixAdmin.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> Import completed successfully';
                    const resultsContent = resultsDiv.querySelector('#results-content');
                    resultsContent.innerHTML = 
                        '<p><strong>Events imported:</strong> ' + data.data.imported_count + '</p>' +
                        '<p><strong>Duration:</strong> ' + data.data.duration + ' seconds</p>' +
                        (data.data.errors.length > 0 ? '<p><strong>Errors:</strong> ' + data.data.errors.join(', ') + '</p>' : '');
                    resultsDiv.style.display = 'block';
                } else {
                    statusDiv.innerHTML = '<span class="dashicons dashicons-no-alt"></span> Import failed: ' + data.data.message;
                }
            })
            .catch(error => {
                statusDiv.innerHTML = '<span class="dashicons dashicons-no-alt"></span> Import failed. Please try again.';
                console.error('Import error:', error);
            })
            .finally(() => {
                this.disabled = false;
                this.textContent = 'Start Import';
                stopButton.style.display = 'none';
                progressDiv.style.display = 'none';
            });
        });
    }

    // Log filtering
    const filterLogsButton = document.getElementById('filter-logs');
    if (filterLogsButton) {
        filterLogsButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const levelSelect = document.getElementById('log-level');
            const dateInput = document.getElementById('log-date');
            
            fetch(humanitixAdmin.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_import_logs',
                    nonce: humanitixAdmin.logsNonce,
                    level: levelSelect.value,
                    date: dateInput.value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateLogsTable(data.data);
                }
            })
            .catch(error => {
                console.error('Log filtering error:', error);
            });
        });
    }

    // Export logs
    const exportLogsButton = document.getElementById('export-logs');
    if (exportLogsButton) {
        exportLogsButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const levelSelect = document.getElementById('log-level');
            const dateInput = document.getElementById('log-date');
            
            // Create download link
            const url = humanitixAdmin.ajaxUrl + '?action=export_logs&level=' + encodeURIComponent(levelSelect.value) + '&date=' + encodeURIComponent(dateInput.value) + '&nonce=' + humanitixAdmin.logsNonce;
            
            // Trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = 'humanitix-logs-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // Helper function to update logs table
    function updateLogsTable(logs) {
        const tableBody = document.querySelector('#logs-container table tbody');
        if (!tableBody) return;
        
        tableBody.innerHTML = '';
        
        if (logs.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4">No logs found</td></tr>';
            return;
        }
        
        logs.forEach(function(log) {
            const row = document.createElement('tr');
            row.innerHTML = 
                '<td>' + log.created_at + '</td>' +
                '<td><span class="log-level-' + log.level + '">' + log.level + '</span></td>' +
                '<td>' + log.message + '</td>' +
                '<td>' + (log.context ? JSON.stringify(log.context) : '') + '</td>';
            tableBody.appendChild(row);
        });
    }

    // Error details toggle functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('error-toggle')) {
            e.preventDefault();
            
            const targetId = e.target.dataset.target;
            const errorRow = document.getElementById(targetId);
            
            if (errorRow) {
                const isVisible = errorRow.style.display !== 'none';
                
                if (isVisible) {
                    errorRow.style.display = 'none';
                    e.target.classList.remove('expanded');
                } else {
                    errorRow.style.display = 'table-row';
                    e.target.classList.add('expanded');
                }
            }
        }
    });
}); 