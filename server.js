const express = require('express');
const app = express();

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// TEMP STORAGE (use database later)
let payments = {};

// ✅ Initiate Payment
app.post('/initiate-payment', (req, res) => {
    const { phone, amount, reference } = req.body;

    console.log("STK Request:", phone, amount, reference);

    // Simulate STK Push (replace with Paynectar API)
    payments[reference] = {
        status: "pending",
        phone,
        amount
    };

    res.json({
        status: "success",
        message: "STK push sent"
    });
});

// ✅ Check Payment Status
app.post('/check-payment-status', (req, res) => {
    const { reference } = req.body;

    const payment = payments[reference];

    res.json({
        status: "success",
        data: payment || { status: "pending" }
    });
});

// ✅ Callback (VERY IMPORTANT)
app.post('/callback', (req, res) => {
    console.log("CALLBACK RECEIVED:", req.body);

    // Example (adjust depending on Paynectar response)
    const reference = req.body.reference || req.body.checkout_request_id;

    if (payments[reference]) {
        payments[reference].status = "completed";
    }

    res.send("OK");
});

// Test route
app.get('/', (req, res) => {
    res.send("Server is running ✅");
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server running on port ${PORT}`));
