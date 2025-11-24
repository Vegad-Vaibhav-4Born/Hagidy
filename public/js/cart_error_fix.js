// Cart Error Fix - Add this to your cart page to catch and fix common errors

// Global error handler for cart operations
window.addEventListener('error', function(e) {
    if (e.message.includes('cart') || e.message.includes('Cart')) {
        console.error('Cart Error Detected:', e.message, e.filename, e.lineno);
        
        // Try to reinitialize cart if needed
        setTimeout(() => {
            if (typeof window.cartManager === 'undefined') {
                console.log('Attempting to reinitialize cart manager...');
                // Reload the page as last resort
                location.reload();
            }
        }, 1000);
    }
});

// Add cart debugging function
window.debugCart = function() {
    console.log('=== Cart Debug Information ===');
    console.log('cartManager exists:', typeof window.cartManager !== 'undefined');
    console.log('addToCart function exists:', typeof window.addToCart === 'function');
    console.log('updateHeaderCart exists:', typeof window.updateHeaderCart === 'function');
    
    // Check DOM elements
    const elements = [
        'cart-total',
        'cart-subtotal', 
        'cart-item-count',
        'total-coins',
        'final-total-amount'
    ];
    
    elements.forEach(id => {
        const element = document.getElementById(id);
        console.log(`Element ${id}:`, element ? 'Found' : 'Missing');
    });
    
    // Check cart table
    const cartTable = document.getElementById('cart-table-body');
    console.log('Cart table body:', cartTable ? 'Found' : 'Missing');
    
    if (cartTable) {
        const rows = cartTable.querySelectorAll('tr[data-product-id]');
        console.log('Cart rows found:', rows.length);
    }
    
    // Check localStorage for guest cart
    const guestCart = localStorage.getItem('guest_cart');
    console.log('Guest cart in localStorage:', guestCart ? JSON.parse(guestCart) : 'None');
    
    console.log('=== End Debug Information ===');
};

// Auto-fix common cart issues
document.addEventListener('DOMContentLoaded', function() {
    // Fix 1: Ensure cart manager is initialized
    setTimeout(() => {
        if (typeof window.cartManager === 'undefined') {
            console.warn('Cart manager not found, attempting manual initialization...');
            // You can add manual initialization here if needed
        }
    }, 2000);
    
    // Fix 2: Add fallback for missing elements
    const requiredElements = [
        { id: 'cart-total', defaultValue: '₹0.00' },
        { id: 'cart-subtotal', defaultValue: '₹0.00' },
        { id: 'cart-item-count', defaultValue: '(0 items)' },
        { id: 'total-coins', defaultValue: '0' },
        { id: 'final-total-amount', defaultValue: '₹0.00' }
    ];
    
    requiredElements.forEach(({ id, defaultValue }) => {
        const element = document.getElementById(id);
        if (element && !element.textContent.trim()) {
            element.textContent = defaultValue;
        }
    });
    
    // Fix 3: Add error boundary for quantity updates
    document.addEventListener('click', function(e) {
        if (e.target.closest('.quantity-plus') || e.target.closest('.quantity-minus')) {
            try {
                // Let the original handler run first
                setTimeout(() => {
                    // Check if totals were updated properly
                    const cartTotal = document.getElementById('cart-total');
                    if (cartTotal && cartTotal.textContent === '') {
                        console.warn('Cart total is empty, fixing...');
                        cartTotal.textContent = '₹0.00';
                    }
                }, 100);
            } catch (error) {
                console.error('Error in quantity update:', error);
            }
        }
    });
});

// Export debug function to global scope
window.cartDebug = window.debugCart;

