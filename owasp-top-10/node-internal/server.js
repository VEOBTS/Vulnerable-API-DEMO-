const express = require('express');
const app     = express();
app.use(express.json());

// Health check
app.get('/health', (req, res) => {
    res.json({ status: 'ok', service: 'node-internal' });
});

// SENSITIVE admin route — should NEVER be reached from the internet.
// This is the SSRF target for API7 demonstration.

app.get('/admin', (req, res) => {
    res.json({
        secret: 'INTERNAL_ADMIN_SECRET_KEY_12345',
        users:  ['alice','bob','charlie','admin'],
        database_password: 'labpass'
    });
});

// Internal data processor
app.post('/process', (req, res) => {
    const { data } = req.body;
    res.json({ processed: true, result: data + '_processed' });
});

// Simulates cloud metadata endpoint (like AWS EC2 metadata)
app.get('/metadata', (req, res) => {
    res.json({
        hostname:  'node-internal',
        role:      'internal-processor',
        iam_token: 'SIMULATED_IAM_TOKEN_ABCD1234'
    });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log('Node internal service running on port ' + PORT);
});