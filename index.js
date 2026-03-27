const express = require('express');
const cors = require('cors');
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
        time: new Date().toISOString()
    });
});

// Initiate payment
app.post('/initiate-payment', (req, res) => {
    console.log('Initiate payment:', req.body);
    
    const { amount, package: packageId, phone, reference } = req.body;
    
    if (!amount || !packageId || !phone || !reference) {
        return res.status(400).json({
            status: 'error',
            message: 'Missing fields'
        });
    }
    
    // Store payment
    payments.set(reference, {
        reference,
        amount,
        package: packageId,
        phone,
        status: 'pending',
        created: Date.now()
    });
    
    // Auto-complete after 10 seconds (test mode)
    setTimeout(() => {
        const payment = payments.get(reference);
        if (payment && payment.status === 'pending') {
            payment.status = 'completed';
            payments.set(reference, payment);
            console.log(`Payment ${reference} completed`);
        }
    }, 10000);
    
    res.json({
        status: 'success',
        message: 'STK Push sent',
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
    
    // Check timeout (5 minutes)
    if (Date.now() - payment.created > 300000) {
        return res.json({ status: 'error', message: 'Payment expired' });
    }
    
    res.json({ status: 'pending', message: 'Waiting for payment' });
});

// List all payments (for debugging)
app.get('/payments', (req, res) => {
    const allPayments = Array.from(payments.values());
    res.json({ count: allPayments.length, payments: allPayments });
});

app.listen(PORT, () => {
    console.log(`✅ Server running on port ${PORT}`);
});
