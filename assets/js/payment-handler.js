// assets/js/payment-handler.js

class PaymentHandler {
    constructor() {
        this.stripe = null;
        this.elements = null;
        this.card = null;
    }
    
    async initStripe(publishableKey) {
        this.stripe = Stripe(publishableKey);
        this.elements = this.stripe.elements();
        this.card = this.elements.create('card');
        return this.card;
    }
    
    async processPayPal(amount, currency) {
        return new Promise((resolve, reject) => {
            // Redirect to PayPal
            window.location.href = `/e-commerce/payment/paypal.php?amount=${amount}&currency=${currency}`;
        });
    }
    
    async processStripe(amount, currency, paymentMethodId) {
        try {
            const response = await fetch('/e-commerce/api/create-payment-intent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: amount,
                    currency: currency,
                    payment_method_id: paymentMethodId
                })
            });
            
            const data = await response.json();
            
            if (data.requires_action) {
                const result = await this.stripe.handleCardAction(data.payment_intent_client_secret);
                if (result.error) {
                    throw new Error(result.error.message);
                }
                return result;
            } else if (data.success) {
                return data;
            }
        } catch (error) {
            console.error('Stripe payment error:', error);
            throw error;
        }
    }
    
    async processEasypaisa(amount, mobileNumber, email) {
        try {
            const response = await fetch('/e-commerce/api/easypaisa-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: amount,
                    mobile_number: mobileNumber,
                    email: email
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.payment_url) {
                window.location.href = data.payment_url;
            } else {
                throw new Error(data.error || 'Payment failed');
            }
        } catch (error) {
            console.error('Easypaisa payment error:', error);
            throw error;
        }
    }
    
    async processJazzCash(amount, mobileNumber, email) {
        try {
            const response = await fetch('/e-commerce/api/jazzcash-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: amount,
                    mobile_number: mobileNumber,
                    email: email
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.payment_url) {
                window.location.href = data.payment_url;
            } else {
                throw new Error(data.error || 'Payment failed');
            }
        } catch (error) {
            console.error('JazzCash payment error:', error);
            throw error;
        }
    }
    
    validateCardNumber(number) {
        // Luhn algorithm
        number = number.replace(/\s/g, '');
        let sum = 0;
        let alternate = false;
        
        for (let i = number.length - 1; i >= 0; i--) {
            let n = parseInt(number[i], 10);
            
            if (alternate) {
                n *= 2;
                if (n > 9) {
                    n -= 9;
                }
            }
            
            sum += n;
            alternate = !alternate;
        }
        
        return sum % 10 === 0;
    }
    
    detectCardType(number) {
        const patterns = {
            visa: /^4/,
            mastercard: /^5[1-5]/,
            amex: /^3[47]/,
            discover: /^6(?:011|5)/
        };
        
        number = number.replace(/\s/g, '');
        
        for (let [type, pattern] of Object.entries(patterns)) {
            if (pattern.test(number)) {
                return type;
            }
        }
        
        return 'unknown';
    }
}

// Initialize global payment handler
window.paymentHandler = new PaymentHandler();