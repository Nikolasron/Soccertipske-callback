const express = require('express');
const cors = require('cors');
const axios = require('axios');
const app = express();
const PORT = process.env.PORT || 3000;

// Enable CORS for all origins
app.use(cors({
    origin: '*',
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type']
}));

app.use(express.json());

// Store pending payments
const payments = new Map();

// Health check
app.get('/', (req, res) => {
    res.json({
        status: 'online',
        service: 'SoccerTipsKE STK Handler',
        mode: process.env.TEST_MODE === 'true' ? 'TEST' : 'LIVE',
        time: new Date().toISOString()
    });
});

// Initiate payment - REAL MODE
app.post('/initiate-payment', async (req, res) => {
    console.log('=== Initiate Payment (LIVE MODE) ===');
    console.log('Request body:', req.body);
    
    const { amount, package: packageId, phone, reference } = req.body;
    
    if (!amount || !packageId || !phone || !reference) {
        return res.status(400).json({
            status: 'error',
            message: 'Missing required fields'
        });
    }
    
    // Format phone number
    let formattedPhone = phone.replace(/\D/g, '');
    if (formattedPhone.startsWith('0')) {
        formattedPhone = '254' + formattedPhone.substring(1);
    }
    if (!formattedPhone.startsWith('254')) {
        formattedPhone = '254' + formattedPhone;
    }
    
    // Store payment in memory
    payments.set(reference, {
        reference,
        amount,
        package: packageId,
        phone: formattedPhone,
        status: 'pending',
        created_at: new Date().toISOString()
    });
    
    // Auto-expire after 5 minutes
    setTimeout(() => {
        const payment = payments.get(reference);
        if (payment && payment.status === 'pending') {
            payment.status = 'expired';
            payments.set(reference, payment);
            console.log(`Payment ${reference} expired`);
        }
    }, 300000);
    
    // Check if we're in TEST MODE
    if (process.env.TEST_MODE === 'true') {
        console.log('TEST MODE: Simulating payment');
        
        // Simulate success after 10 seconds
        setTimeout(() => {
            const payment = payments.get(reference);
            if (payment && payment.status === 'pending') {
                payment.status = 'completed';
                payment.completed_at = new Date().toISOString();
                payments.set(reference, payment);
                console.log(`✅ TEST: Payment ${reference} completed`);
                
                // Notify main site
                if (process.env.SITE_URL) {
                    axios.get(`${process.env.SITE_URL}/unlock.php`, {
                        params: { reference, package: packageId, status: 'success' },
                        timeout: 10000
                    }).catch(err => console.error('Unlock failed:', err.message));
                }
            }
        }, 10000);
        
        return res.json({
            status: 'success',
            message: 'TEST MODE: STK Push simulated',
            reference: reference,
            test_mode: true
        });
    }
    
    // ============================================
    // REAL PAYMENT - Paynectar API Integration
    // ============================================
    const paynectarApiKey = process.env.PAYNECTAR_SECRET_KEY;
    const paynectarApiUrl = process.env.PAYNECTAR_API_URL || 'https://api.paynectar.co.ke/api/v1';
    const callbackUrl = process.env.CALLBACK_URL || 'https://soccertipske-callback.onrender.com/callback';
    
    if (!paynectarApiKey) {
        console.error('❌ PAYNECTAR_SECRET_KEY not configured');
        return res.status(500).json({
            status: 'error',
            message: 'Payment gateway not configured. Please contact support.'
        });
    }
    
    try {
        console.log('Calling Paynectar API...');
        console.log('Phone:', formattedPhone);
        console.log('Amount:', amount);
        console.log('Reference:', reference);
        
        const response = await axios.post(`${paynectarApiUrl}/payments/initiate`, {
            amount: amount,
            currency: 'KES',
            reference: reference,
            phone: formattedPhone,
            callback_url: callbackUrl,
            description: `SoccerTipsKE Premium Package ${packageId}`,
            metadata: {
                package: packageId,
                site: 'SoccerTipsKE',
                reference: reference
            }
        }, {
            headers: {
                'Authorization': `Bearer ${paynectarApiKey}`,
                'Content-Type': 'application/json'
            },
            timeout: 30000
        });
        
        console.log('✅ Paynectar Response:', response.data);
        
        res.json({
            status: 'success',
            message: 'STK Push sent successfully',
            reference: reference,
            data: response.data
        });
        
    } catch (error) {
        console.error('❌ Paynectar API Error:', error.response?.data || error.message);
        
        // Update payment status to failed
        const payment = payments.get(reference);
        if (payment) {
            payment.status = 'failed';
            payment.error = error.response?.data?.message || error.message;
            payments.set(reference, payment);
        }
        
        res.status(500).json({
            status: 'error',
            message: error.response?.data?.message || 'Failed to initiate payment. Please try again.',
            reference: reference
        });
    }
});

// Check payment status - REAL MODE
app.post('/check-payment-status', async (req, res) => {
    console.log('=== Check Payment Status ===');
    const { reference } = req.body;
    
    if (!reference) {
        return res.status(400).json({ status: 'error', message: 'Reference required' });
    }
    
    const payment = payments.get(reference);
    
    if (!payment) {
        return res.json({ status: 'pending', message: 'Payment not found' });
    }
    
    // If already completed locally
    if (payment.status === 'completed') {
        return res.json({
            status: 'success',
            data: { status: 'completed' }
        });
    }
    
    // If expired
    if (payment.status === 'expired') {
        return res.json({ status: 'error', message: 'Payment expired' });
    }
    
    // If failed
    if (payment.status === 'failed') {
        return res.json({ status: 'error', message: payment.error || 'Payment failed' });
    }
    
    // If not in test mode, verify with Paynectar API
    if (process.env.TEST_MODE !== 'true') {
        const paynectarApiKey = process.env.PAYNECTAR_SECRET_KEY;
        const paynectarApiUrl = process.env.PAYNECTAR_API_URL || 'https://api.paynectar.co.ke/api/v1';
        
        try {
            const response = await axios.post(`${paynectarApiUrl}/payments/verify`, {
                reference: reference
            }, {
                headers: {
                    'Authorization': `Bearer ${paynectarApiKey}`,
                    'Content-Type': 'application/json'
                },
                timeout: 30000
            });
            
            console.log(`Verification response for ${reference}:`, response.data);
            
            if (response.data.status === 'success' || response.data.status === 'completed') {
                payment.status = 'completed';
                payment.completed_at = new Date().toISOString();
                payments.set(reference, payment);
                
                return res.json({
                    status: 'success',
                    data: { status: 'completed' }
                });
            }
        } catch (error) {
            console.error('Verification error:', error.message);
        }
    }
    
    // Still pending
    res.json({ 
        status: 'pending', 
        message: 'Waiting for payment confirmation',
        data: { status: 'pending' }
    });
});

// Callback endpoint for Paynectar webhook
app.post('/callback', async (req, res) => {
    console.log('=== Webhook Callback Received ===');
    console.log('Body:', req.body);
    
    const { reference, status, metadata } = req.body;
    
    if (!reference) {
        return res.status(400).json({ status: 'error', message: 'No reference' });
    }
    
    const payment = payments.get(reference);
    
    if (payment) {
        if (status === 'success' || status === 'completed') {
            payment.status = 'completed';
            payment.completed_at = new Date().toISOString();
            payments.set(reference, payment);
            console.log(`✅ Payment ${reference} completed via webhook`);
            
            // Notify main site
            const siteUrl = process.env.SITE_URL;
            const packageId = metadata?.package || payment.package;
            
            if (siteUrl) {
                try {
                    await axios.get(`${siteUrl}/unlock.php`, {
                        params: {
                            reference: reference,
                            package: packageId,
                            status: 'success'
                        },
                        timeout: 10000
                    });
                    console.log(`✅ Unlock notification sent to main site`);
                } catch (err) {
                    console.error('Unlock notification failed:', err.message);
                }
            }
        } else {
            payment.status = 'failed';
            payments.set(reference, payment);
            console.log(`❌ Payment ${reference} failed: ${status}`);
        }
    }
    
    res.json({ status: 'success', message: 'Callback received' });
});

// List all payments (debug only - protect in production)
app.get('/payments', (req, res) => {
    const allPayments = Array.from(payments.values()).map(p => ({
        reference: p.reference,
        amount: p.amount,
        package: p.package,
        phone: p.phone,
        status: p.status,
        created_at: p.created_at,
        completed_at: p.completed_at
    }));
    res.json({ count: allPayments.length, payments: allPayments });
});

app.listen(PORT, () => {
    console.log(`✅ Server running on port ${PORT}`);
    console.log(`🎯 Mode: ${process.env.TEST_MODE === 'true' ? 'TEST' : 'LIVE'}`);
    console.log(`📍 URL: http://localhost:${PORT}`);
});
