const express = require('express');
const cors = require('cors');
const axios = require('axios');
const app = express();
const PORT = process.env.PORT || 3000;

// ============================================
// TEMPORARY - HARDCODED API KEY
// IMPORTANT: Remove this once environment variable works!
// ============================================
const PAYNECTAR_SECRET_KEY = 'hmp_YJhMK3H8lIy7AsgsS4K56pInqCuVqny49IMWSbKr';
const PAYNECTAR_API_URL = 'https://api.paynectar.co.ke/api/v1';
const CALLBACK_URL = 'https://soccertipske-callback.onrender.com/callback';
const SITE_URL = 'https://soccertipsme.wuaze.com';
const TEST_MODE = false; // Set to false for live payments
// ============================================

// Enable CORS
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
        mode: TEST_MODE ? 'TEST' : 'LIVE',
        api_configured: true,
        api_key_prefix: PAYNECTAR_SECRET_KEY.substring(0, 10) + '...',
        time: new Date().toISOString()
    });
});

// Initiate payment
app.post('/initiate-payment', async (req, res) => {
    console.log('=== Initiate Payment ===');
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
    
    // Store payment
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
    
    // TEST MODE
    if (TEST_MODE) {
        console.log('TEST MODE: Simulating payment');
        
        setTimeout(() => {
            const payment = payments.get(reference);
            if (payment && payment.status === 'pending') {
                payment.status = 'completed';
                payments.set(reference, payment);
                console.log(`✅ TEST: Payment ${reference} completed`);
                
                axios.get(`${SITE_URL}/unlock.php`, {
                    params: { reference, package: packageId, status: 'success' }
                }).catch(err => console.error('Unlock failed:', err.message));
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
    // REAL PAYMENT - LIVE MODE
    // ============================================
    console.log('LIVE MODE: Calling Paynectar API...');
    console.log('Phone:', formattedPhone);
    console.log('Amount:', amount);
    console.log('Reference:', reference);
    
    try {
        const response = await axios.post(`${PAYNECTAR_API_URL}/payments/initiate`, {
            amount: amount,
            currency: 'KES',
            reference: reference,
            phone: formattedPhone,
            callback_url: CALLBACK_URL,
            description: `SoccerTipsKE Premium Package ${packageId}`,
            metadata: {
                package: packageId,
                site: 'SoccerTipsKE'
            }
        }, {
            headers: {
                'Authorization': `Bearer ${PAYNECTAR_SECRET_KEY}`,
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
        console.error('❌ Paynectar API Error:');
        console.error('Status:', error.response?.status);
        console.error('Data:', error.response?.data);
        console.error('Message:', error.message);
        
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

// Check payment status
app.post('/check-payment-status', (req, res) => {
    const { reference } = req.body;
    
    if (!reference) {
        return res.status(400).json({ status: 'error', message: 'Reference required' });
    }
    
    const payment = payments.get(reference);
    
    if (!payment) {
        return res.json({ status: 'pending', message: 'Payment not found' });
    }
    
    if (payment.status === 'completed') {
        return res.json({
            status: 'success',
            data: { status: 'completed' }
        });
    }
    
    if (payment.status === 'expired') {
        return res.json({ status: 'error', message: 'Payment expired' });
    }
    
    if (payment.status === 'failed') {
        return res.json({ status: 'error', message: payment.error || 'Payment failed' });
    }
    
    res.json({ 
        status: 'pending', 
        message: 'Waiting for payment confirmation',
        data: { status: 'pending' }
    });
});

// Callback endpoint
app.post('/callback', async (req, res) => {
    console.log('=== Webhook Callback ===');
    console.log('Body:', req.body);
    
    const { reference, status, metadata } = req.body;
    
    if (!reference) {
        return res.status(400).json({ status: 'error', message: 'No reference' });
    }
    
    const payment = payments.get(reference);
    
    if (payment && (status === 'success' || status === 'completed')) {
        payment.status = 'completed';
        payment.completed_at = new Date().toISOString();
        payments.set(reference, payment);
        console.log(`✅ Payment ${reference} completed`);
        
        const packageId = metadata?.package || payment.package;
        
        try {
            await axios.get(`${SITE_URL}/unlock.php`, {
                params: { reference, package: packageId, status: 'success' }
            });
            console.log(`✅ Unlock notification sent`);
        } catch (err) {
            console.error('Unlock failed:', err.message);
        }
    }
    
    res.json({ status: 'success', message: 'Callback received' });
});

// List payments (debug)
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
    console.log(`🎯 Mode: ${TEST_MODE ? 'TEST' : 'LIVE'}`);
    console.log(`🔑 API Key: ${PAYNECTAR_SECRET_KEY ? '✅ CONFIGURED' : '❌ NOT CONFIGURED'}`);
    console.log(`📍 URL: http://localhost:${PORT}`);
});
