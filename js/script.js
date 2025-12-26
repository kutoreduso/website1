const express = require('express');
const sqlite3 = require('sqlite3').verbose();
const bodyParser = require('body-parser');
const path = require('path');

const app = express();
const db = new sqlite3.Database('./users.db');

app.use(bodyParser.urlencoded({ extended: false }));
app.use(express.static(__dirname)); // Serves your HTML file

// Initialize Database Table
db.serialize(() => {
    db.run("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT, password TEXT)");
    // Insert a dummy user for testing
    db.run("INSERT OR IGNORE INTO users (id, username, password) VALUES (1, 'admin', 'password123')");
});

// Handle Login Post
app.post('/login', (req, res) => {
    const { username, password } = req.body;

    db.get("SELECT * FROM users WHERE username = ? AND password = ?", [username, password], (err, row) => {
        if (row) {
            res.send("<h1>Login Successful! Welcome " + row.username + "</h1>");
        } else {
            res.send("<h1>Invalid credentials</h1> <a href='/'>Try again</a>");
        }
    });
});

app.listen(3000, () => {
    console.log('Server running at http://localhost:3000');
});


function togglePassword() {
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('toggleIcon');
    
    // Toggle the type attribute
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    // Toggle the icon class between eye and eye-slash
    toggleIcon.classList.toggle('bi-eye');
    toggleIcon.classList.toggle('bi-eye-slash');
}
