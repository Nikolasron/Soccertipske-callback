const express = require('express');
const cors = require('cors');
const axios = require('axios');
const app = express();
const PORT = process.env.PORT || 3000;

// Enable CORS
app.use(cors({
    origin: '*',
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type']
}));

app.use(express.json());

// Store pending payments
const payments = new Map();

// Health check - Shows config status
app.get('/', (req, res) => {
    const apiKey = process.env.PAYNECTAR_SECRET_KEY;
    res.json({
        status: 'online',
        service: 'SoccerTipsKE STK Handler',
        mode: process.env.TEST_MODE === 'true' ? 'TEST' : 'LIVE',
        api_configured: !!apiKey,
        api_key_prefix: apiKey ? apiKey.substring(0, 10) + '...' : 'NOT SET',
        environment_vars: {
            TEST_MODE: process.env.TEST_MODE || 'not set',
            PAYNECTAR_API_URL: process.env.PAYNECTAR_API_URL || 'not set',
            CALLBACK_URL: process.env.CALLBACK_URL || 'not set',
            SITE_URL: process.env.SITE_URL || 'not set'
        },
        time: new Date().toISOString()
    });
});

// Initiate payment
app.post('/initiate-payment', async (req, res) => {
    console.log('=== Initiate Payment ===');
    console.log('Request body:', req.body);
    
    // Check if API key is configured
    const paynectarApiKey = process.env.PAYNECTAR_SECRET_KEY;
    
    if (!paynectarApiKey) {
        console.error('❌ PAYNECTAR_SECRET_KEY is NOT SET in environment variables');
        return res.status(500).json({
            status: 'error',
            message: 'Payment gateway not configured. Please contact support.',
            debug: 'API key missing in environment variables'
        });
    }
    
    console.log('✅ API Key found:', paynectarApiKey.substring(0, 10) + '...');
    
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
    
    // Check if TEST MODE
    if (process.env.TEST_MODE === 'true') {
        console.log('TEST MODE: Simulating payment');
        
        setTimeout(() => {
            const payment = payments.get(reference);
            if (payment && payment.status === 'pending') {
                payment.status = 'completed';
                payments.set(reference, payment);
                console.log(`✅ TEST: Payment ${reference} completed`);
                
                if (process.env.SITE_URL) {
                    axios.get(`${process.env.SITE_URL}/unlock.php`, {
                        params: { reference, package: packageId, status: 'success' }
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
    // REAL PAYMENT - Call Paynectar API
    // ============================================
    const paynectarApiUrl = process.env.PAYNECTAR_API_URL || 'https://api.paynectar.co.ke/api/v1';
    const callbackUrl = process.env.CALLBACK_URL || 'https://soccertipske-callback.onrender.com/callback';
    
    console.log('Calling Paynectar API...');
    console.log('URL:', `${paynectarApiUrl}/payments/initiate`);
    console.log('Phone:', formattedPhone);
    console.log('Amount:', amount);
    console.log('Reference:', reference);
    
    try {
        const response = await axios.post(`${paynectarApiUrl}/payments/initiate`, {
            amount: amount,
            currency: 'KES',
            reference: reference,
            phone: formattedPhone,
            callback_url: callbackUrl,
            description: `SoccerTipsKE Premium Package ${packageId}`,
            metadata: {
                package: packageId,
                site: 'SoccerTipsKE'
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
        
        const siteUrl = process.env.SITE_URL;
        const packageId = metadata?.package || payment.package;
        
        if (siteUrl) {
            try {
                await axios.get(`${siteUrl}/unlock.php`, {
                    params: { reference, package: packageId, status: 'success' }
                });
                console.log(`✅ Unlock notification sent`);
            } catch (err) {
                console.error('Unlock failed:', err.message);
            }
        }
    }
    
    res.json({ status: 'success', message: 'Callback received' });
});

app.listen(PORT, () => {
    console.log(`✅ Server running on port ${PORT}`);
    console.log(`🎯 Mode: ${process.env.TEST_MODE === 'true' ? 'TEST' : 'LIVE'}`);
    console.log(`🔑 API Key: ${process.env.PAYNECTAR_SECRET_KEY ? '✅ CONFIGURED' : '❌ NOT CONFIGURED'}`);
});
