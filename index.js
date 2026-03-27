const express = require('express');
const cors = require('cors');
const axios = require('axios');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

// Allow all origins - Critical for InfinityFree
app.use(cors({
    origin: '*',
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
    credentials: true,
    preflightContinue: false,
    optionsSuccessStatus: 204
}));

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// In-memory storage
const pendingPayments = new Map();

// ============================================
// HEALTH CHECK
// ============================================
app.get('/', (req, res) => {
    res.json({
        status: 'healthy',
        service: 'SoccerTipsKE Callback Handler',
        timestamp: new Date().toISOString(),
        cors_enabled: true,
        endpoints: ['/initiate-payment', '/check-payment-status']
    });
});

// ============================================
// OPTIONS handler for CORS preflight
// ============================================
app.options('*', (req, res) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');
    res.sendStatus(200);
});

// ============================================
// INITIATE PAYMENT
// ============================================
app.post('/initiate-payment', async (req, res) => {
    console.log('=== Initiate Payment ===');
    console.log('Body:', req.body);
    
    // Always set CORS headers
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Headers', 'Content-Type');

    const { amount, package: packageId, phone, reference } = req.body;

    // Validate
    if (!amount || !packageId || !phone || !reference) {
        return res.status(400).json({
            status: 'error',
            message: 'Missing required fields'
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

    // Store payment
    pendingPayments.set(reference, {
        reference,
        amount,
        package: packageId,
        phone: formattedPhone,
        status: 'pending',
        created_at: new Date().toISOString()
    });

    console.log(`Payment stored: ${reference}`);

    // Auto-expire after 5 minutes
    setTimeout(() => {
        const payment = pendingPayments.get(reference);
        if (payment && payment.status === 'pending') {
            payment.status = 'expired';
            pendingPayments.set(reference, payment);
            console.log(`Payment ${reference} expired`);
        }
    }, 300000);

    // TEST MODE - Always return success for now
    console.log(`TEST MODE: STK Push simulated for ${formattedPhone}`);
    
    // Simulate successful payment after 15 seconds
    setTimeout(() => {
        const payment = pendingPayments.get(reference);
        if (payment && payment.status === 'pending') {
            payment.status = 'completed';
            payment.completed_at = new Date().toISOString();
            pendingPayments.set(reference, payment);
            console.log(`✅ Payment ${reference} completed`);
        }
    }, 15000);
    
    return res.json({
        status: 'success',
        message: 'STK Push sent',
        reference: reference,
        test_mode: true
    });
});

// ============================================
// CHECK PAYMENT STATUS
// ============================================
app.post('/check-payment-status', (req, res) => {
    console.log('=== Check Status ===');
    console.log('Reference:', req.body.reference);
    
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
    console.log(`Payment status for ${reference}:`, payment?.status);

    if (!payment) {
        return res.json({
            status: 'error',
            message: 'Payment not found'
        });
    }

    if (payment.status === 'completed') {
        return res.json({
            status: 'success',
            data: {
                status: 'completed',
                reference: reference
            }
        });
    }

    if (payment.status === 'expired') {
        return res.json({
            status: 'error',
            message: 'Payment expired'
        });
    }

    // Still pending
    res.json({
        status: 'pending',
        message: 'Waiting for payment',
        data: {
            status: 'pending'
        }
    });
});

// ============================================
// GET ALL PAYMENTS (Debug)
// ============================================
app.get('/payments', (req, res) => {
    const payments = Array.from(pendingPayments.entries()).map(([key, value]) => ({
        reference: key,
        ...value
    }));
    res.json({ payments, count: payments.length });
});

app.listen(PORT, () => {
    console.log(`✅ Server running on port ${PORT}`);
    console.log(`📍 URL: http://localhost:${PORT}`);
    console.log(`🌐 CORS enabled for all origins`);
});
