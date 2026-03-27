const express = require('express');
const cors = require('cors');
const axios = require('axios');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// In-memory storage for pending payments (use Redis or database in production)
const pendingPayments = new Map();

// ============================================
// HEALTH CHECK ENDPOINT
// ============================================
app.get('/', (req, res) => {
    res.json({
        status: 'healthy',
        service: 'SoccerTipsKE Callback Handler',
        version: '1.0',
        timestamp: new Date().toISOString(),
        endpoints: ['/initiate-payment', '/check-payment-status', '/callback']
    });
});

// ============================================
// INITIATE PAYMENT - STK PUSH
// ============================================
app.post('/initiate-payment', async (req, res) => {
    console.log('=== Initiate Payment Request ===');
    console.log('Body:', req.body);

    const { amount, package: packageId, phone, reference } = req.body;

    // Validate required fields
    if (!amount || !packageId || !phone || !reference) {
        return res.status(400).json({
            status: 'error',
            message: 'Missing required fields: amount, package, phone, reference'
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

    // Validate amount
    const validAmounts = [50, 100, 150];
    if (!validAmounts.includes(amount)) {
        return res.status(400).json({
            status: 'error',
            message: 'Invalid amount. Allowed: 50, 100, 150'
        });
    }

    // Store pending payment
    pendingPayments.set(reference, {
        reference,
        amount,
        package: packageId,
        phone: formattedPhone,
        status: 'pending',
        created_at: new Date().toISOString(),
        attempts: 0
    });

    // Auto-expire pending payments after 5 minutes
    setTimeout(() => {
        const payment = pendingPayments.get(reference);
        if (payment && payment.status === 'pending') {
            payment.status = 'expired';
            pendingPayments.set(reference, payment);
            console.log(`Payment ${reference} expired`);
        }
    }, 300000);

    // TEST MODE - Simulate STK Push (no real money)
    if (process.env.TEST_MODE === 'true') {
        console.log(`TEST MODE: STK Push simulated for ${formattedPhone}`);
        
        // Simulate successful payment after 10 seconds (for testing)
        setTimeout(() => {
            const payment = pendingPayments.get(reference);
            if (payment && payment.status === 'pending') {
                payment.status = 'completed';
                payment.completed_at = new Date().toISOString();
                pendingPayments.set(reference, payment);
                console.log(`TEST MODE: Payment ${reference} marked as completed`);
                
                // Call your main site to unlock content
                if (process.env.SITE_URL) {
                    axios.get(`${process.env.SITE_URL}/unlock.php`, {
                        params: {
                            reference: reference,
                            package: packageId,
                            status: 'success'
                        }
                    }).catch(err => console.error('Unlock callback failed:', err.message));
                }
            }
        }, 10000);
        
        return res.json({
            status: 'success',
            message: 'TEST MODE: STK Push simulated',
            reference: reference,
            test_mode: true,
            data: {
                status: 'pending',
                reference: reference,
                phone: formattedPhone
            }
        });
    }

    // ============================================
    // REAL PAYMENT - Paynectar API Integration
    // ============================================
    const paynectarApiKey = process.env.PAYNECTAR_SECRET_KEY;
    const paynectarApiUrl = process.env.PAYNECTAR_API_URL || 'https://api.paynectar.co.ke/api/v1';
    const callbackUrl = process.env.CALLBACK_URL || 'https://soccertipske-callback.onrender.com/callback';

    if (!paynectarApiKey) {
        return res.status(500).json({
            status: 'error',
            message: 'Paynectar API key not configured'
        });
    }

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

        console.log('Paynectar Response:', response.data);

        res.json({
            status: 'success',
            message: 'STK Push sent',
            reference: reference,
            data: response.data
        });

    } catch (error) {
        console.error('Paynectar API Error:', error.response?.data || error.message);
        
        res.status(500).json({
            status: 'error',
            message: 'Failed to initiate payment',
            error: error.response?.data?.message || error.message
        });
    }
});

// ============================================
// CHECK PAYMENT STATUS
// ============================================
app.post('/check-payment-status', async (req, res) => {
    console.log('=== Check Payment Status Request ===');
    console.log('Body:', req.body);

    const { reference } = req.body;

    if (!reference) {
        return res.status(400).json({
            status: 'error',
            message: 'Reference required'
        });
    }

    // Get pending payment from memory
    const payment = pendingPayments.get(reference);

    if (!payment) {
        return res.json({
            status: 'error',
            message: 'Payment not found',
            reference: reference
        });
    }

    // If payment is completed
    if (payment.status === 'completed') {
        return res.json({
            status: 'success',
            data: {
                status: 'completed',
                reference: reference,
                amount: payment.amount,
                package: payment.package,
                completed_at: payment.completed_at
            }
        });
    }

    // If payment expired
    if (payment.status === 'expired') {
        return res.json({
            status: 'error',
            message: 'Payment expired',
            reference: reference
        });
    }

    // Check with Paynectar API for real payments (if not test mode)
    if (process.env.TEST_MODE !== 'true' && payment.status === 'pending') {
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

            if (response.data.status === 'success' || response.data.status === 'completed') {
                payment.status = 'completed';
                payment.completed_at = new Date().toISOString();
                pendingPayments.set(reference, payment);
                
                return res.json({
                    status: 'success',
                    data: {
                        status: 'completed',
                        reference: reference
                    }
                });
            }
        } catch (error) {
            console.error('Verification error:', error.message);
        }
    }

    // Still pending
    return res.json({
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
// CALLBACK ENDPOINT - Receives payment confirmation from Paynectar
// ============================================
app.post('/callback', async (req, res) => {
    console.log('=== Callback Received ===');
    console.log('Body:', req.body);
    console.log('Headers:', req.headers);

    const { reference, status, metadata } = req.body;

    if (!reference) {
        return res.status(400).json({ status: 'error', message: 'No reference' });
    }

    // Update payment status
    const payment = pendingPayments.get(reference);
    if (payment) {
        payment.status = status === 'success' || status === 'completed' ? 'completed' : 'failed';
        payment.completed_at = new Date().toISOString();
        payment.callback_data = req.body;
        pendingPayments.set(reference, payment);
    }

    // Call main site to unlock content
    const siteUrl = process.env.SITE_URL;
    const packageId = metadata?.package || payment?.package || '1';

    if (siteUrl && (status === 'success' || status === 'completed')) {
        try {
            await axios.get(`${siteUrl}/unlock.php`, {
                params: {
                    reference: reference,
                    package: packageId,
                    status: 'success'
                },
                timeout: 10000
            });
            console.log(`Unlock callback sent for ${reference}`);
        } catch (error) {
            console.error('Unlock callback failed:', error.message);
        }
    }

    res.json({
        status: 'success',
        message: 'Callback received',
        reference: reference
    });
});

// ============================================
// GET ALL PENDING PAYMENTS (Debug only)
// ============================================
app.get('/payments', (req, res) => {
    const payments = Array.from(pendingPayments.entries()).map(([key, value]) => ({
        reference: key,
        ...value
    }));
    res.json({ payments });
});

// ============================================
// GET SINGLE PAYMENT (Debug only)
// ============================================
app.get('/payment/:reference', (req, res) => {
    const payment = pendingPayments.get(req.params.reference);
    if (!payment) {
        return res.status(404).json({ status: 'error', message: 'Payment not found' });
    }
    res.json({ payment });
});

// Start server
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
    console.log(`Test mode: ${process.env.TEST_MODE === 'true' ? 'ON' : 'OFF'}`);
});
