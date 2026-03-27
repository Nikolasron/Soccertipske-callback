const express = require('express');
const cors = require('cors');
const axios = require('axios');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

// Enhanced CORS configuration
app.use(cors({
    origin: '*', // Allow all origins for testing
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'Accept'],
    credentials: true
}));

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// In-memory storage
const pendingPayments = new Map();

// ============================================
// HEALTH CHECK - Test if server is running
// ============================================
app.get('/', (req, res) => {
    res.json({
        status: 'healthy',
        service: 'SoccerTipsKE Callback Handler',
        version: '1.0',
        timestamp: new Date().toISOString(),
        endpoints: ['/initiate-payment', '/check-payment-status', '/callback'],
        cors_enabled: true
    });
});

// ============================================
// OPTIONS handler for CORS preflight
// ============================================
app.options('*', (req, res) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
    res.sendStatus(200);
});

// ============================================
// INITIATE PAYMENT
// ============================================
app.post('/initiate-payment', async (req, res) => {
    console.log('=== Initiate Payment ===');
    console.log('Headers:', req.headers);
    console.log('Body:', req.body);

    // Set CORS headers
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Headers', 'Content-Type');

    const { amount, package: packageId, phone, reference } = req.body;

    // Validate
    if (!amount || !packageId || !phone || !reference) {
        return res.status(400).json({
            status: 'error',
            message: 'Missing required fields: amount, package, phone, reference'
        });
    }

    // Format phone
    let formattedPhone = phone.replace(/\D/g, '');
    if (formattedPhone.startsWith('0')) {
        formattedPhone = '254' + formattedPhone.substring(1);
    }
    if (!formattedPhone.startsWith('254')) {
        formattedPhone = '254' + formattedPhone;
    }

    // Validate amount
    const validAmounts = [50, 100, 150];
    if (!validAmounts.includes(amount)) {
        return res.status(400).json({
            status: 'error',
            message: 'Invalid amount. Allowed: 50, 100, 150'
        });
    }

    // Store payment
    pendingPayments.set(reference, {
        reference,
        amount,
        package: packageId,
        phone: formattedPhone,
        status: 'pending',
        created_at: new Date().toISOString()
    });

    // Auto-expire after 5 minutes
    setTimeout(() => {
        const payment = pendingPayments.get(reference);
        if (payment && payment.status === 'pending') {
            payment.status = 'expired';
            pendingPayments.set(reference, payment);
            console.log(`Payment ${reference} expired`);
        }
    }, 300000);

    // TEST MODE
    if (process.env.TEST_MODE === 'true') {
        console.log(`TEST MODE: STK Push simulated for ${formattedPhone}`);
        
        // Simulate successful payment after 10 seconds
        setTimeout(() => {
            const payment = pendingPayments.get(reference);
            if (payment && payment.status === 'pending') {
                payment.status = 'completed';
                payment.completed_at = new Date().toISOString();
                pendingPayments.set(reference, payment);
                console.log(`TEST MODE: Payment ${reference} completed`);
                
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

    // REAL PAYMENT
    const apiKey = process.env.PAYNECTAR_SECRET_KEY;
    const apiUrl = process.env.PAYNECTAR_API_URL || 'https://api.paynectar.co.ke/api/v1';
    const callbackUrl = process.env.CALLBACK_URL || 'https://soccertipske-callback.onrender.com/callback';

    if (!apiKey) {
        return res.status(500).json({
            status: 'error',
            message: 'Paynectar API key not configured'
        });
    }

    try {
        const response = await axios.post(`${apiUrl}/payments/initiate`, {
            amount,
            currency: 'KES',
            reference,
            phone: formattedPhone,
            callback_url: callbackUrl,
            description: `SoccerTipsKE Package ${packageId}`
        }, {
            headers: {
                'Authorization': `Bearer ${apiKey}`,
                'Content-Type': 'application/json'
            },
            timeout: 30000
        });

        res.json({
            status: 'success',
            message: 'STK Push sent',
            reference: reference,
            data: response.data
        });

    } catch (error) {
        console.error('Paynectar error:', error.response?.data || error.message);
        res.status(500).json({
            status: 'error',
            message: error.response?.data?.message || 'Payment initiation failed'
        });
    }
});

// ============================================
// CHECK PAYMENT STATUS
// ============================================
app.post('/check-payment-status', (req, res) => {
    console.log('=== Check Status ===');
    console.log('Body:', req.body);

    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Headers', 'Content-Type');

    const { reference } = req.body;

    if (!reference) {
        return res.status(400).json({
            status: 'error',
            message: 'Reference required'
        });
    }

    const payment = pendingPayments.get(reference);

    if (!payment) {
        return res.json({
            status: 'error',
            message: 'Payment not found',
            reference: reference
        });
    }

    if (payment.status === 'completed') {
        return res.json({
            status: 'success',
            data: {
                status: 'completed',
                reference: reference,
                completed_at: payment.completed_at
            }
        });
    }

    if (payment.status === 'expired') {
        return res.json({
            status: 'error',
            message: 'Payment expired',
            reference: reference
        });
    }

    // Still pending
    res.json({
        status: 'pending',
        message: 'Payment still pending',
        reference: reference,
        data: {
            status: 'pending',
            created_at: payment.created_at
        }
    });
});

// ============================================
// CALLBACK ENDPOINT
// ============================================
app.post('/callback', (req, res) => {
    console.log('=== Callback ===');
    console.log('Body:', req.body);

    res.header('Access-Control-Allow-Origin', '*');

    const { reference, status } = req.body;

    if (reference) {
        const payment = pendingPayments.get(reference);
        if (payment) {
            payment.status = (status === 'success' || status === 'completed') ? 'completed' : 'failed';
            payment.completed_at = new Date().toISOString();
            pendingPayments.set(reference, payment);
            console.log(`Payment ${reference} updated to ${payment.status}`);
        }
    }

    res.json({ status: 'success', message: 'Callback received' });
});

// ============================================
// DEBUG - List all payments
// ============================================
app.get('/payments', (req, res) => {
    const payments = Array.from(pendingPayments.entries()).map(([key, value]) => ({
        reference: key,
        ...value
    }));
    res.json({ payments });
});

// Start server
app.listen(PORT, () => {
    console.log(`🚀 Server running on port ${PORT}`);
    console.log(`📍 URL: http://localhost:${PORT}`);
    console.log(`🧪 Test mode: ${process.env.TEST_MODE === 'true' ? 'ON' : 'OFF'}`);
    console.log(`🌐 CORS enabled for all origins`);
});
