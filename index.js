const express = require('express');
const cors = require('cors');
const axios = require('axios');
const app = express();
const PORT = process.env.PORT || 3000;

// Hardcoded API Key
const PAYNECTAR_SECRET_KEY = 'hmp_YJhMK3H8lIy7AsgsS4K56pInqCuVqny49IMWSbKr';
const CALLBACK_URL = 'https://soccertipske-callback.onrender.com/callback';
const SITE_URL = 'https://soccertipsme.wuaze.com';
const TEST_MODE = false;

// Enable CORS
app.use(cors({
    origin: '*',
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type']
}));

app.use(express.json());

const payments = new Map();

// Health check
app.get('/', (req, res) => {
    res.json({
        status: 'online',
        service: 'SoccerTipsKE STK Handler',
        mode: TEST_MODE ? 'TEST' : 'LIVE',
        time: new Date().toISOString()
    });
});

// Initiate payment - Trying different API formats
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
    
    // TEST MODE - Skip real API
    if (TEST_MODE) {
        console.log('TEST MODE: Simulating success');
        setTimeout(() => {
            const payment = payments.get(reference);
            if (payment && payment.status === 'pending') {
                payment.status = 'completed';
                payments.set(reference, payment);
                console.log(`✅ TEST: Payment ${reference} completed`);
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
    // TRY DIFFERENT API FORMATS
    // ============================================
    
    // Try different API endpoints
    const endpoints = [
        'https://api.paynectar.co.ke/api/v1/payments/initiate',
        'https://api.paynectar.co.ke/api/v1/stk/push',
        'https://api.paynectar.co.ke/api/v1/mpesa/stk',
        'https://api.paynectar.co.ke/v1/payments',
        'https://api.paynectar.co.ke/api/payments'
    ];
    
    // Try different request formats
    const requestFormats = [
        // Format 1: Standard
        {
            amount: amount,
            currency: 'KES',
            reference: reference,
            phone: formattedPhone,
            callback_url: CALLBACK_URL,
            description: `SoccerTipsKE Package ${packageId}`
        },
        // Format 2: Different field names
        {
            amount: amount,
            currency: 'KES',
            transaction_reference: reference,
            msisdn: formattedPhone,
            phone_number: formattedPhone,
            callback_url: CALLBACK_URL
        },
        // Format 3: M-Pesa specific
        {
            BusinessShortCode: '174379',
            Amount: amount,
            PartyA: formattedPhone,
            PartyB: '174379',
            PhoneNumber: formattedPhone,
            CallBackURL: CALLBACK_URL,
            AccountReference: `STKE${packageId}`,
            TransactionDesc: `SoccerTipsKE Package ${packageId}`
        },
        // Format 4: Simple format
        {
            amount: amount,
            phone: formattedPhone,
            reference: reference
        }
    ];
    
    // Try different auth headers
    const authHeaders = [
        { 'Authorization': `Bearer ${PAYNECTAR_SECRET_KEY}` },
        { 'Api-Key': PAYNECTAR_SECRET_KEY },
        { 'X-API-Key': PAYNECTAR_SECRET_KEY },
        { 'Authorization': `ApiKey ${PAYNECTAR_SECRET_KEY}` }
    ];
    
    // Try combinations
    for (let endpoint of endpoints) {
        for (let requestData of requestFormats) {
            for (let auth of authHeaders) {
                try {
                    console.log(`\nTrying endpoint: ${endpoint}`);
                    console.log('Auth:', Object.keys(auth)[0]);
                    console.log('Request:', JSON.stringify(requestData, null, 2));
                    
                    const response = await axios.post(endpoint, requestData, {
                        headers: {
                            ...auth,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        timeout: 10000
                    });
                    
                    console.log('✅ SUCCESS! Response:', response.data);
                    
                    // If successful, return immediately
                    return res.json({
                        status: 'success',
                        message: 'STK Push sent successfully',
                        reference: reference,
                        data: response.data
                    });
                    
                } catch (error) {
                    // Continue trying next combination
                    console.log(`❌ Failed: ${error.response?.status} - ${error.response?.data?.message || error.message}`);
                }
            }
        }
    }
    
    // If all combinations failed
    console.log('All API combinations failed');
    
    // Update payment status
    const payment = payments.get(reference);
    if (payment) {
        payment.status = 'failed';
        payment.error = 'All API combinations failed';
        payments.set(reference, payment);
    }
    
    res.status(500).json({
        status: 'error',
        message: 'Failed to initiate payment. Please contact support.',
        reference: reference
    });
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
        completed_at: p.completed_at,
        error: p.error
    }));
    res.json({ count: allPayments.length, payments: allPayments });
});

app.listen(PORT, () => {
    console.log(`✅ Server running on port ${PORT}`);
    console.log(`🎯 Mode: ${TEST_MODE ? 'TEST' : 'LIVE'}`);
});
