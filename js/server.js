// 1. UPDATE TABLE CREATION
db.run("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT, password TEXT, status TEXT DEFAULT 'pending')");

// 2. SIGN UP ROUTE (When they register, they become 'pending')
app.post('/signup', (req, res) => {
    const { username, password } = req.body;
    db.run("INSERT INTO users (username, password, status) VALUES (?, ?, 'pending')", [username, password], (err) => {
        if(err) return res.send("Error");
        res.send("Registration successful! Please wait for admin approval.");
    });
});

// 3. GET USERS API (For the Users Page to fetch data)
app.get('/api/users', (req, res) => {
    db.all("SELECT * FROM users", [], (err, rows) => {
        const pending = rows.filter(r => r.status === 'pending');
        const active = rows.filter(r => r.status === 'active');
        res.json({ pending, active });
    });
});

// 4. APPROVE USER API
app.post('/api/approve', (req, res) => {
    const { id } = req.body;
    db.run("UPDATE users SET status = 'active' WHERE id = ?", [id], (err) => {
        res.json({ success: true });
    });
});
app.post('/api/reject', (req, res) => {
    const { id } = req.body;
    db.run("DELETE FROM users WHERE id = ?", [id], (err) => {
        if (err) {
            res.status(500).json({ error: "Failed to delete" });
        } else {
            res.json({ success: true });
        }
    });
});
// REJECT USER API (Deletes the user permanently)
app.post('/api/reject', (req, res) => {
    const { id } = req.body;
    db.run("DELETE FROM users WHERE id = ?", [id], (err) => {
        if (err) {
            res.status(500).json({ error: "Failed to delete" });
        } else {
            res.json({ success: true });
        }
    });
});