
/**
 * Input Sanitization System
 * Automatically removes malicious code from input fields
 */
(function () {
    'use strict';

    // Patterns to detect and remove malicious code
    const maliciousPatterns = [
        // Script tags (various formats)
        /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
        /<script[^>]*>/gi,
        /<\/script>/gi,

        // PHP tags (complete and incomplete)
        /<\?php[\s\S]*?\?>/gi,
        /<\?php[\s\S]*$/gi,
        /<\?[\s\S]*?\?>/gi,
        /<\?[\s\S]*$/gi,

        // Python tags (complete and incomplete)
        /<python\b[^<]*(?:(?!<\/python>)<[^<]*)*<\/python>/gi,
        /<python[^>]*>/gi,
        /<\/python>/gi,
        /<python[\s\S]*$/gi,

        // Other programming language tags
        /<java\b[^<]*(?:(?!<\/java>)<[^<]*)*<\/java>/gi,
        /<java[^>]*>/gi,
        /<\/java>/gi,
        /<java[\s\S]*$/gi,

        /<c\+\+\b[^<]*(?:(?!<\/c\+\+>)<[^<]*)*<\/c\+\+>/gi,
        /<c\+\+[^>]*>/gi,
        /<\/c\+\+>/gi,
        /<c\+\+[\s\S]*$/gi,

        /<javascript\b[^<]*(?:(?!<\/javascript>)<[^<]*)*<\/javascript>/gi,
        /<javascript[^>]*>/gi,
        /<\/javascript>/gi,
        /<javascript[\s\S]*$/gi,

        /<ruby\b[^<]*(?:(?!<\/ruby>)<[^<]*)*<\/ruby>/gi,
        /<ruby[^>]*>/gi,
        /<\/ruby>/gi,
        /<ruby[\s\S]*$/gi,

        /<perl\b[^<]*(?:(?!<\/perl>)<[^<]*)*<\/perl>/gi,
        /<perl[^>]*>/gi,
        /<\/perl>/gi,
        /<perl[\s\S]*$/gi,

        /<asp\b[^<]*(?:(?!<\/asp>)<[^<]*)*<\/asp>/gi,
        /<asp[^>]*>/gi,
        /<\/asp>/gi,
        /<asp[\s\S]*$/gi,

        /<jsp\b[^<]*(?:(?!<\/jsp>)<[^<]*)*<\/jsp>/gi,
        /<jsp[^>]*>/gi,
        /<\/jsp>/gi,
        /<jsp[\s\S]*$/gi,

        // HTML tags that could be dangerous
        /<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi,
        /<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/gi,
        /<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/gi,
        /<applet\b[^<]*(?:(?!<\/applet>)<[^<]*)*<\/applet>/gi,
        /<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/gi,

        // JavaScript event handlers
        /on\w+\s*=\s*["'][^"']*["']/gi,
        /on\w+\s*=\s*[^>\s]+/gi,

        // JavaScript protocol
        /javascript\s*:/gi,

        // Data URLs (potentially dangerous)
        /data\s*:\s*text\/html/gi,
        /data\s*:\s*application\/javascript/gi,

        // VBScript
        /vbscript\s*:/gi,

        // Expression (IE)
        /expression\s*\(/gi,

        // CSS expressions
        /expression\s*\([^)]*\)/gi,

        // SQL injection patterns (basic)
        /(\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b)/gi,

        // Command injection patterns (allow parentheses for normal product titles)
        /[;&|`$]/g,

        // Path traversal
        /\.\.\//g,
        /\.\.\\/g,

        // Null bytes
        /\x00/g,

        // Control characters
        /[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g
    ];

    // Additional dangerous patterns
    const dangerousPatterns = [
        // XSS vectors
        /<img[^>]+src[^>]*>/gi,
        /<link[^>]+href[^>]*>/gi,
        /<meta[^>]*>/gi,
        /<style[^>]*>[\s\S]*?<\/style>/gi,

        // HTML entities that could be dangerous
        /&#x?[0-9a-fA-F]+;/g,

        // Unicode escapes
        /\\u[0-9a-fA-F]{4}/g,
        /\\x[0-9a-fA-F]{2}/g
    ];

    /**
     * Sanitize text by removing malicious patterns
     * @param {string} text - The text to sanitize
     * @returns {string} - Sanitized text
     */
    function sanitizeText(text) {
        if (typeof text !== 'string') {
            return text;
        }

        let sanitized = text;

        // Remove malicious patterns
        maliciousPatterns.forEach(pattern => {
            sanitized = sanitized.replace(pattern, '');
        });

        // Remove dangerous patterns
        dangerousPatterns.forEach(pattern => {
            sanitized = sanitized.replace(pattern, '');
        });

        // Additional cleanup: remove any remaining HTML tags only (preserve spaces)
        sanitized = sanitized.replace(/<[^>]*>/g, '');

        return sanitized;
    }

    /**
     * Check if text contains malicious content
     * @param {string} text - The text to check
     * @returns {boolean} - True if malicious content is detected
     */
    function isMalicious(text) {
        if (typeof text !== 'string') {
            return false;
        }

        return maliciousPatterns.some(pattern => pattern.test(text)) ||
            dangerousPatterns.some(pattern => pattern.test(text));
    }

    /**
     * Show security warning to user
     * @param {string} message - Warning message
     */
    function showSecurityWarning(message) {
        // Create warning element
        const warning = document.createElement('div');
        warning.className = 'security-warning';
        warning.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #ff4444;
                color: white;
                padding: 12px 16px;
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                font-size: 14px;
                max-width: 300px;
                animation: slideInRight 0.3s ease;
            ">
                <strong>⚠️ Security Alert:</strong><br>
                ${message}
            </div>
        `;

        // Add CSS animation
        if (!document.getElementById('security-warning-styles')) {
            const style = document.createElement('style');
            style.id = 'security-warning-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(warning);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (warning.parentNode) {
                warning.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (warning.parentNode) {
                        warning.remove();
                    }
                }, 300);
            }
        }, 5000);
    }

    /**
     * Initialize input sanitization
     */
    function initInputSanitization() {
        // Track sanitized inputs to prevent infinite loops
        const sanitizedInputs = new WeakSet();

        // Function to sanitize input
        function sanitizeInput(input) {
            if (sanitizedInputs.has(input)) {
                return;
            }

            const originalValue = input.value;
            const sanitizedValue = sanitizeText(originalValue);

            if (originalValue !== sanitizedValue) {
                // Mark as sanitized to prevent recursion
                sanitizedInputs.add(input);

                // Update the input value
                input.value = sanitizedValue;

                // Trigger input event to notify other scripts
                input.dispatchEvent(new Event('input', { bubbles: true }));

                // Show warning if malicious content was detected
                if (isMalicious(originalValue)) {
                    showSecurityWarning('Potentially harmful content has been removed from your input.');
                }

                // Remove from sanitized set after a short delay
                setTimeout(() => {
                    sanitizedInputs.delete(input);
                }, 100);
            }
        }

        // Add event listeners to all input fields
        function addSanitizationListeners() {
            // Get all input and textarea elements
            const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], input[type="search"], input[type="url"], input[type="tel"], textarea');

            inputs.forEach(input => {
                // Sanitize on input
                input.addEventListener('input', function () {
                    sanitizeInput(this);
                });

                // Sanitize on paste
                input.addEventListener('paste', function () {
                    // Use setTimeout to allow paste to complete
                    setTimeout(() => {
                        sanitizeInput(this);
                    }, 10);
                });

                // Sanitize on blur (when user leaves field)
                input.addEventListener('blur', function () {
                    sanitizeInput(this);
                });
            });
        }

        // Initial sanitization of existing inputs
        addSanitizationListeners();

        // Watch for dynamically added inputs
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) { // Element node
                        // Check if the added node is an input
                        if (node.tagName && ['INPUT', 'TEXTAREA'].includes(node.tagName)) {
                            const input = node;
                            input.addEventListener('input', function () {
                                sanitizeInput(this);
                            });
                            input.addEventListener('paste', function () {
                                setTimeout(() => {
                                    sanitizeInput(this);
                                }, 10);
                            });
                            input.addEventListener('blur', function () {
                                sanitizeInput(this);
                            });
                        }

                        // Check for inputs within the added node
                        const inputs = node.querySelectorAll ? node.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], input[type="search"], input[type="url"], input[type="tel"], textarea') : [];
                        inputs.forEach(input => {
                            input.addEventListener('input', function () {
                                sanitizeInput(this);
                            });
                            input.addEventListener('paste', function () {
                                setTimeout(() => {
                                    sanitizeInput(this);
                                }, 10);
                            });
                            input.addEventListener('blur', function () {
                                sanitizeInput(this);
                            });
                        });
                    }
                });
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInputSanitization);
    } else {
        initInputSanitization();
    }

    // Make functions globally available for manual use
    window.sanitizeInput = sanitizeText;
    window.isMaliciousInput = isMalicious;

})();