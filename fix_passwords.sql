-- FIX PASSWORDS FOR CYBERSHIELD LOGIN
-- Copy and paste this entire script in phpMyAdmin SQL tab

-- Update admin password (Admin@123)
UPDATE users SET password_hash = '$2y$10$BUZrq5cvZo7XL7063kWc2uM8P1j2X0kvNHQ/QTpVNUAuV7q7WiRfO' WHERE username = 'admin';

-- Update seller password (Seller@123) 
UPDATE users SET password_hash = '$2y$10$sj2SICvEmuRT3vhsa9IskO6dc3gFmfMYuSN.U/MYtUjNJtCjt4/.q' WHERE username = 'seller';

-- Update viewer password (Viewer@123)
UPDATE users SET password_hash = '$2y$10$VP6fVllWeLjea6OYxMNhMOYgrSmJX04yazqQFX8u0KY4.ZGjvG98a' WHERE username = 'viewer';

-- Verify the updates
SELECT username, full_name, role, 'Password Updated' as status FROM users WHERE username IN ('admin', 'seller', 'viewer');
