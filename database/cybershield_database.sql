-- ============================================================
-- CyberShield Database — Complete Schema with Bias Reduction Features
-- Import this file in phpMyAdmin or run via MySQL CLI:
--   mysql -u root -p < cybershield_complete.sql
-- ============================================================

-- Use existing database (replace with your actual database name)
-- Uncomment and modify the line below to use your database name
-- USE your_existing_database_name;

-- If you have a database already created, use that instead
-- Otherwise, ask your hosting provider to create the 'cybershield' database for you

-- ============================================================
-- CORE TABLES
-- ============================================================

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    store_name VARCHAR(100) NOT NULL,
    role ENUM('Admin', 'Seller', 'Viewer') DEFAULT 'Seller',
    is_active BOOLEAN DEFAULT TRUE,
    last_assessment_score DECIMAL(5,2) DEFAULT NULL,
    last_assessment_date DATETIME DEFAULT NULL,
    total_assessments INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ============================================================
-- AUDIT LOG TABLE
-- ============================================================
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('login', 'profile_update', 'password_change', 'assessment_complete', 'data_clear', 'other') NOT NULL,
    action_description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Vendors table (legacy support)
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    industry VARCHAR(100),
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    store_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    flagged BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_email (email),
    INDEX idx_flagged (flagged)
);

-- ============================================================
-- QUESTION BANK TABLE (with bias reduction fields)
-- ============================================================
CREATE TABLE question_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('password', 'phishing', 'device', 'network', 'social_engineering', 'data_handling') NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL,
    question_text TEXT NOT NULL,
    correct_answer VARCHAR(255) NOT NULL,
    options JSON NOT NULL,
    explanation TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    bias_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Score indicating question bias (0=unbiased, 100=highly biased)',
    times_used INT DEFAULT 0 COMMENT 'Number of times this question has been used',
    correct_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Historical correct rate to detect biased questions',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty),
    INDEX idx_is_active (is_active)
);

INSERT INTO question_bank (category, difficulty, question_text, correct_answer, options, explanation, bias_score) VALUES
-- PASSWORD SECURITY (20 questions)
('password', 'easy', 'Scenario: You receive a notification that your account was accessed from a new device. What do you do first?', 'Secure the account by changing the password and enabling MFA, then review recent activity', 
 '["Ignore it if nothing seems wrong", "Reply to the notification email to ask for details", "Secure the account by changing the password and enabling MFA, then review recent activity", "Click the notification link and sign in to check"]', 
 'Treat unexpected login alerts as potential compromise: secure the account via trusted navigation (not email links), enable MFA, and review activity.', 0),

('password', 'easy', 'Scenario: A website tells you your password is weak and suggests a simple pattern. What should you do?', 'Use a long passphrase or password manager instead of the suggested pattern', 
 '["Accept the suggested pattern", "Use a long passphrase or password manager instead of the suggested pattern", "Add a number to the end", "Reuse a strong password from another site"]', 
 'Avoid predictable patterns. Use unique, long passphrases or a password manager to generate/store strong passwords.', 0),

('password', 'medium', 'Scenario: Your password was reused on another site that was breached. What should you do?', 'Change the password everywhere it was reused and enable MFA', 
 '["Wait to see if your account is affected", "Change the password everywhere it was reused and enable MFA", "Use the same password but add a symbol", "Disable login alerts to reduce notifications"]', 
 'Reused credentials are commonly exploited through credential stuffing. Update unique passwords and enable MFA.', 0),

('password', 'medium', 'Scenario: You need to share a password with a coworker for an emergency task. What is the safest way?', 'Use a secure password sharing tool or encrypted message; change it afterward', 
 '["Send it in plain text via chat", "Use a secure password sharing tool or encrypted message; change it afterward", "Write it on a sticky note", "Tell them verbally over the phone"]', 
 'Never send passwords in plain text. Use encrypted sharing methods and rotate the password after use.', 0),

('password', 'hard', 'Scenario: You suspect a keylogger on your work computer. What should you do first?', 'Report to IT immediately and use a different clean device to change critical passwords', 
 '["Restart the computer", "Report to IT immediately and use a different clean device to change critical passwords", "Install antivirus yourself", "Continue working and monitor accounts"]', 
 'Keyloggers can capture credentials. Report immediately and change passwords from a known-clean device.', 0),

('password', 'hard', 'Scenario: You must log in to a public computer. What is the safest approach?', 'Use a password manager on your phone instead of typing; avoid saving any credentials', 
 '["Type the password quickly", "Use a password manager on your phone instead of typing; avoid saving any credentials", "Use incognito mode", "Log in and clear history afterward"]', 
 'Avoid typing passwords on public devices. Use a password manager or your phone to autofill securely.', 0),

('password', 'medium', 'Scenario: A mobile app asks for your device password to "enhance security". What should you do?', 'Deny the request and research the app; legitimate apps rarely need device passwords', 
 '["Allow it to enhance security", "Deny the request and research the app; legitimate apps rarely need device passwords", "Restart the phone", "Uninstall immediately"]', 
 'Apps asking for device/system passwords are suspicious. Deny and verify legitimacy before proceeding.', 0),

('password', 'easy', 'Scenario: You receive an email claiming your account will be locked unless you "verify your password" now. What do you do?', 'Do not click links; go directly to the official site/app to check your account status', 
 '["Click the link to verify quickly", "Do not click links; go directly to the official site/app to check your account status", "Reply with your password", "Forward to IT"]', 
 'Urgent password verification requests are phishing. Always navigate to the official site yourself.', 0),

('password', 'medium', 'Scenario: Your browser offers to save a new password. Should you accept?', 'Only if you trust the device and use a master password/encryption; otherwise use a password manager', 
 '["Always accept", "Only if you trust the device and use a master password/encryption; otherwise use a password manager", "Never accept", "Write it down instead"]', 
 'Browser password managers are convenient but should be encrypted with a master password or replaced by a dedicated password manager.', 0),

('password', 'hard', 'Scenario: You discover your password manager was compromised. What is the correct recovery order?', 'Change master password first, then rotate all critical passwords from a clean device', 
 '["Rotate passwords first, then change master password", "Change master password first, then rotate all critical passwords from a clean device", "Delete account and start over", "Enable 2FA and keep same master"]', 
 'Secure the master password first to prevent further compromise, then rotate individual passwords.', 0),

('password', 'medium', 'Scenario: A shared spreadsheet requires login credentials to access. How should you store them?', 'Use a password manager or encrypted vault; never store in the sheet', 
 '["Store in the spreadsheet as hidden cells", "Use a password manager or encrypted vault; never store in the sheet", "Email credentials to team", "Print and keep in drawer"]', 
 'Never store credentials in documents. Use encrypted password managers or vaults.', 0),

('password', 'easy', 'Scenario: You forgot your password and the recovery hint is obvious to others. What should you do?', 'Skip the hint and use account recovery; change the hint to something non-obvious', 
 '["Use the obvious hint", "Skip the hint and use account recovery; change the hint to something non-obvious", "Ask a coworker for help", "Create a new account"]', 
 'Obvious hints weaken security. Use secure recovery methods and update hints to private information.', 0),

('password', 'medium', 'Scenario: A colleague asks for your password to "help troubleshoot" your account. What do you do?', 'Refuse and offer to share your screen instead; never share passwords', 
 '["Share it to get help faster", "Refuse and offer to share your screen instead; never share passwords", "Ask for their password in return", "Change it temporarily then share"]', 
 'Never share passwords. Offer screen sharing or controlled access instead.', 0),

('password', 'hard', 'Scenario: You need to create a password policy for your team. What should you prioritize?', 'Length and uniqueness over complexity; encourage password managers', 
 '["Require special characters and numbers", "Length and uniqueness over complexity; encourage password managers", "Frequent mandatory changes", "Disallow password managers"]', 
 'Long, unique passwords with managers are more effective than frequent changes or complexity rules.', 0),

('password', 'medium', 'Scenario: Your phone shows a "trusted device" prompt after login. Should you enable it?', 'Only on personal, secured devices; avoid on public/shared devices', 
 '["Always enable for convenience", "Only on personal, secured devices; avoid on public/shared devices", "Never enable", "Enable and write down the code"]', 
 'Trusted device features improve convenience but should only be used on secure, personal devices.', 0),

('password', 'easy', 'Scenario: You receive a password reset you didn\'t request. What do you do?', 'Secure your account immediately and check for other unauthorized activity', 
 '["Ignore it", "Secure your account immediately and check for other unauthorized activity", "Click the link to see who did it", "Contact support only"]', 
 'Unexpected password resets may indicate attempted takeover. Secure the account and review activity.', 0),

('password', 'hard', 'Scenario: You must use a third-party service that requires your main account password. What is the safest approach?', 'Create a unique, strong password for that service; never reuse your main password', 
 '["Use your main password for consistency", "Create a unique, strong password for that service; never reuse your main password", "Use a variation of your main password", "Decline to use the service"]', 
 'Never reuse primary passwords. Create unique credentials for each service.', 0),

('password', 'medium', 'Scenario: A website offers passwordless login options. Which is most secure?', 'Use hardware security keys (YubiKey) or biometrics if available', 
 '["SMS codes", "Email links", "Use hardware security keys (YubiKey) or biometrics if available", "Push notifications"]', 
 'Hardware keys and device-based biometrics are more secure than SMS/email for passwordless auth.', 0),

('password', 'easy', 'Scenario: Your child asks for your Netflix password. What should you do?', 'Create a separate profile or use guest features; don\'t share your main password', 
 '["Share your main password", "Create a separate profile or use guest features; don\'t share your main password", "Tell them to guess", "Change it after they use it"]', 
 'Use profiles/guest access instead of sharing passwords, especially with family.', 0),

('password', 'hard', 'Scenario: You\'re leaving a job. What should you do with your work passwords?', 'Transfer knowledge and change all shared passwords; do not keep them', 
 '["Save them in a personal file", "Transfer knowledge and change all shared passwords; do not keep them", "Delete them from your memory", "Share with your replacement"]', 
 'Never retain work passwords after leaving. Ensure proper handoff and change all shared credentials.', 0),

-- PHISHING (20 questions)
('phishing', 'easy', 'Scenario: A message says your account will be locked in 30 minutes unless you verify now. What should you do?', 'Do not click links; go to the official site/app directly or contact support using trusted info', 
 '["Click the link quickly to avoid lockout", "Forward the message to coworkers to warn them", "Do not click links; go to the official site/app directly or contact support using trusted info", "Reply asking the sender to prove it is real"]', 
 'Urgency is a common phishing tactic. Verify through official channels you access yourself, not through the message.', 0),

('phishing', 'easy', 'Scenario: You receive an unexpected package delivery email with a tracking link. What should you do?', 'Verify on the official carrier site using the tracking number; avoid clicking email links', 
 '["Click the tracking link immediately", "Verify on the official carrier site using the tracking number; avoid clicking email links", "Reply to confirm delivery", "Ignore it"]', 
 'Phishing often uses fake delivery notifications. Always verify on the official carrier website.', 0),

('phishing', 'medium', 'Scenario: Your boss texts you asking for gift card purchases urgently. What is the correct action?', 'Confirm via a different channel (call/video) before taking any action', 
 '["Buy the cards immediately", "Confirm via a different channel (call/video) before taking any action", "Ask for an email confirmation", "Buy one card as a test"]', 
 'Executive impersonation scams rely on urgency. Always verify through a separate, trusted channel.', 0),

('phishing', 'medium', 'Scenario: A social media message says your account has violated terms and to click to appeal. What do you do?', 'Go directly to the platform\'s settings to check for violations; avoid clicking the message link', 
 '["Click the appeal link immediately", "Go directly to the platform\'s settings to check for violations; avoid clicking the message link", "Reply to dispute", "Share the message to warn others"]', 
 'Account violation alerts are common phishing. Navigate to the platform yourself to verify.', 0),

('phishing', 'hard', 'Scenario: You receive a PDF invoice via email that asks you to enable macros to view. What should you do?', 'Do not enable macros; verify the invoice through a known contact or portal', 
 '["Enable macros to view the invoice", "Do not enable macros; verify the invoice through a known contact or portal", "Forward to IT for review", "Delete it"]', 
 'Macro-enabled documents are common malware vectors. Never enable macros from unsolicited emails.', 0),

('phishing', 'hard', 'Scenario: A banking app popup says your session has expired and to re-enter credentials. What do you do?', 'Close the app and reopen it manually; never enter credentials in popups', 
 '["Enter credentials quickly", "Close the app and reopen it manually; never enter credentials in popups", "Take a screenshot and report", "Call the bank"]', 
 'Fake session expiry popups steal credentials. Always restart the app manually.', 0),

('phishing', 'medium', 'Scenario: You get a QR code via email claiming it leads to a company vaccine sign-up. What should you do?', 'Scan only from official sources; ignore unsolicited QR codes in emails', 
 '["Scan immediately to secure your spot", "Scan only from official sources; ignore unsolicited QR codes in emails", "Forward to colleagues", "Print and scan later"]', 
 'QR codes in emails can redirect to malicious sites. Use only official, trusted sources.', 0),

('phishing', 'easy', 'Scenario: A coworker forwards an email chain asking you to click a link to "verify your email". What do you do?', 'Check the original sender and verify independently; do not trust forwarded chains', 
 '["Click to verify immediately", "Check the original sender and verify independently; do not trust forwarded chains", "Ask the coworker if it\'s safe", "Reply all to confirm"]', 
 'Forwarded chains can hide phishing. Verify the original request independently.', 0),

('phishing', 'medium', 'Scenario: A website displays a security warning saying your connection is not private. What should you do?', 'Do not proceed; check the URL manually or use the official site', 
 '["Click continue to the site", "Do not proceed; check the URL manually or use the official site", "Take a screenshot and report", "Try a different browser"]', 
 'Security warnings often indicate phishing or malicious sites. Do not proceed.', 0),

('phishing', 'hard', 'Scenario: You receive a voice message with instructions to call a number about "suspicious activity". What should you do?', 'Call the official number from the company\'s website; not the number in the message', 
 '["Call the number provided immediately", "Call the official number from the company\'s website; not the number in the message", "Reply to the message", "Ignore it"]', 
 'Vishing uses fake phone numbers. Always use official contact information.', 0),

('phishing', 'medium', 'Scenario: A social media quiz asks for your mother\'s maiden name to "reveal your celebrity match". What do you do?', 'Skip the quiz; never share security answers in fun apps', 
 '["Answer to see results", "Skip the quiz; never share security answers in fun apps", "Use fake information", "Share and let friends answer"]', 
 'Quizzes often harvest security question answers. Avoid sharing personal data.', 0),

('phishing', 'easy', 'Scenario: You get an email saying you won a prize but must pay shipping. What should you do?', 'Decline; legitimate prizes don\'t require payment', 
 '["Pay shipping to claim prize", "Decline; legitimate prizes don\'t require payment", "Research the company first", "Negotiate the shipping fee"]', 
 'Prize scams require payment. Real winnings don\'t ask you to pay.', 0),

('phishing', 'hard', 'Scenario: A login page looks slightly different from usual but asks for credentials. What do you do?', 'Check the URL carefully; if unsure, navigate to the site manually instead', 
 '["Enter credentials quickly", "Check the URL carefully; if unsure, navigate to the site manually instead", "Take a screenshot", "Ask IT to check"]', 
 'Look-alike domains are common. Verify the URL or navigate manually.', 0),

('phishing', 'medium', 'Scenario: You receive a calendar invite from an unknown sender with a link to "join". What should you do?', 'Decline the invite; verify the sender before accepting any links', 
 '["Accept and click to join", "Decline the invite; verify the sender before accepting any links", "Forward to IT", "Accept but ignore the link"]', 
 'Calendar invites can contain phishing links. Verify the sender before accepting.', 0),

('phishing', 'easy', 'Scenario: A popup on a website says you\'ve won and to enter your email to claim. What do you do?', 'Close the popup; never enter info in unexpected popups', 
 '["Enter email to claim", "Close the popup; never enter info in unexpected popups", "Take a screenshot", "Minimize and come back later"]', 
 'Popups claiming prizes are phishing. Do not enter information.', 0),

('phishing', 'hard', 'Scenario: A colleague shares a document that asks you to enable content for "security verification". What should you do?', 'Contact the colleague via another channel to verify; do not enable content', 
 '["Enable to proceed", "Contact the colleague via another channel to verify; do not enable content", "Forward to IT", "Delete it"]', 
 'Document-based phishing asks you to enable content. Verify before enabling.', 0),

('phishing', 'medium', 'Scenario: You get a text with a link saying a package is delayed. What should you do?', 'Check the tracking number on the official carrier site; avoid clicking the link', 
 '["Click the link to reschedule", "Check the tracking number on the official carrier site; avoid clicking the link", "Reply STOP", "Ignore"]', 
 'Smishing uses fake delivery links. Verify on the official site.', 0),

('phishing', 'easy', 'Scenario: A website asks you to upload a photo of your ID for "verification". What should you do?', 'Only upload ID on official, trusted sites; avoid unknown sites', 
 '["Upload to proceed quickly", "Only upload ID on official, trusted sites; avoid unknown sites", "Use a fake ID", "Ask customer support first"]', 
 'ID theft scams use fake verification. Only upload on trusted platforms.', 0),

('phishing', 'hard', 'Scenario: You receive a fake security alert that looks like it\'s from your antivirus. What do you do?', 'Open your antivirus software directly; do not click the alert', 
 '["Click the alert to scan", "Open your antivirus software directly; do not click the alert", "Restart the computer", "Uninstall the antivirus"]', 
 'Fake security alerts install malware. Use your antivirus directly.', 0),

('phishing', 'medium', 'Scenario: A dating app match asks you to verify your identity via their link. What should you do?', 'Use the app\'s official verification features; avoid external links', 
 '["Click the link to verify", "Use the app\'s official verification features; avoid external links", "Video chat instead", "Ask for their ID first"]', 
 'Identity verification scams use external links. Use in-app verification.', 0),

-- DEVICE SECURITY (20 questions)
('device', 'easy', 'Scenario: You find a USB drive in the parking lot. What should you do?', 'Turn it in to IT/security; do not plug it into your computer', 
 '["Plug it in to see who it belongs to", "Turn it in to IT/security; do not plug it into your computer", "Format it before using", "Give it to a coworker"]', 
 'Unknown USB drives can contain malware. Never plug them in.', 0),

('device', 'easy', 'Scenario: Your laptop battery is dying and you need to charge in public. What is safest?', 'Use your own charger and power bank; avoid public USB ports', 
 '["Use any available USB port", "Use your own charger and power bank; avoid public USB ports", "Charge at home only", "Borrow a charger"]', 
 'Public USB ports can be compromised. Use your own charger/power bank.', 0),

('device', 'medium', 'Scenario: Your phone asks to install an app from an unknown source. What should you do?', 'Decline; only install from official app stores', 
 '["Install to try the app", "Decline; only install from official app stores", "Research the app first", "Ask a friend if it\'s safe"]', 
 'Sideloading apps increases risk. Stick to official stores.', 0),

('device', 'medium', 'Scenario: You must use a public computer for sensitive work. What should you do?', 'Use a secure browser session and avoid saving any data; log out fully', 
 '["Save work to cloud and log out", "Use a secure browser session and avoid saving any data; log out fully", "Use incognito and email yourself files", "Work quickly and hope for the best"]', 
 'Avoid saving data on public computers. Use secure sessions and log out completely.', 0),

('device', 'hard', 'Scenario: Your work laptop is stolen. What is the first priority?', 'Report immediately to IT and enable remote wipe if available', 
 '["Buy a new laptop", "Report immediately to IT and enable remote wipe if available", "Change passwords when convenient", "File a police report only"]', 
 'Immediate reporting enables remote wipe to protect data. Notify IT right away.', 0),

('device', 'hard', 'Scenario: You notice unusual battery drain and data usage on your phone. What should you do?', 'Check for malware/spyware and remove unknown apps; consider factory reset if needed', 
 '["Ignore it", "Check for malware/spyware and remove unknown apps; consider factory reset if needed", "Restart the phone", "Delete large files"]', 
 'Unusual drain can indicate malware. Scan devices and remove suspicious apps.', 0),

('device', 'medium', 'Scenario: A software update requires restarting during work hours. What should you do?', 'Schedule the update for downtime; do not delay critical security updates', 
 '["Postpone until next month", "Schedule the update for downtime; do not delay critical security updates", "Restart immediately", "Ask IT if you can skip"]', 
 'Security updates should be applied promptly. Schedule appropriately but don\'t delay.', 0),

('device', 'easy', 'Scenario: Your smart TV asks for your Wi‑Fi password during setup. What should you do?', 'Create a guest network for IoT devices; avoid sharing main network credentials', 
 '["Enter your main password", "Create a guest network for IoT devices; avoid sharing main network credentials", "Skip Wi‑Fi setup", "Use a simpler password"]', 
 'IoT devices often have weak security. Use a separate network for them.', 0),

('device', 'medium', 'Scenario: You need to dispose of an old work phone. What is the correct method?', 'Factory reset and return to IT; do not sell or donate without clearing', 
 '["Factory reset and sell", "Factory reset and return to IT; do not sell or donate without clearing", "Remove SIM and keep", "Give to a family member"]', 
 'Work devices must be wiped and returned. Don\'t dispose without clearance.', 0),

('device', 'hard', 'Scenario: Your computer shows a blue screen with a phone number for "tech support". What should you do?', 'Force restart and run antivirus; do not call the number', 
 '["Call the number for help", "Force restart and run antivirus; do not call the number", "Take a photo and report", "Unplug and wait"]', 
 'Fake BSOD scams trick users into calling scammers. Restart and scan for malware.', 0),

('device', 'medium', 'Scenario: A public Wi‑Fi hotspot requires accepting a certificate. What should you do?', 'Avoid connecting; use VPN or trusted network instead', 
 '["Accept to connect", "Avoid connecting; use VPN or trusted network instead", "Accept but use incognito", "Connect and quickly log out"]', 
 'Certificate warnings on public Wi‑Fi indicate risk. Avoid or use VPN.', 0),

('device', 'easy', 'Scenario: Your tablet asks to back up to a cloud service you don\'t recognize. What should you do?', 'Decline and use only trusted backup services you set up', 
 '["Accept to back up", "Decline and use only trusted backup services you set up", "Research the service first", "Ask IT for help"]', 
 'Unknown cloud services may be malicious. Use only trusted backup providers.', 0),

('device', 'medium', 'Scenario: You need to share files with a client securely. What is the best method?', 'Use encrypted file transfer or a trusted secure share link; avoid email attachments', 
 '["Email as attachments", "Use encrypted file transfer or a trusted secure share link; avoid email attachments", "Upload to public cloud and share link", "Print and hand deliver"]', 
 'Use encrypted transfer tools for sensitive files. Email is not secure.', 0),

('device', 'easy', 'Scenario: Your smartwatch asks to sync contacts with your phone. Should you allow?', 'Only if you trust the device; limit data sharing to necessary items', 
 '["Always allow", "Only if you trust the device; limit data sharing to necessary items", "Never allow", "Allow but delete contacts later"]', 
 'Wearables can access sensitive data. Only allow on trusted devices.', 0),

('device', 'hard', 'Scenario: You suspect your webcam light is on without use. What should you do?', 'Cover or disconnect the camera; scan for malware', 
 '["Ignore it", "Cover or disconnect the camera; scan for malware", "Restart the computer", "Uninstall webcam software"]', 
 'Unexpected webcam activity may indicate spyware. Cover the camera and scan.', 0),

('device', 'medium', 'Scenario: Your router admin page is accessible from the internet. What should you do?', 'Disable remote management and change default passwords', 
 '["Leave it for convenience", "Disable remote management and change default passwords", "Set a complex password only", "Contact ISP"]', 
 'Remote router access increases risk. Disable it and use strong passwords.', 0),

('device', 'easy', 'Scenario: You receive a Bluetooth pairing request you don\'t recognize. What should you do?', 'Decline the request; verify the device before pairing', 
 '["Accept to see what it is", "Decline the request; verify the device before pairing", "Ignore it", "Restart Bluetooth"]', 
 'Unknown Bluetooth requests can be malicious. Decline and verify.', 0),

('device', 'medium', 'Scenario: Your printer asks to connect to cloud services. Should you enable?', 'Only if necessary and with strong credentials; avoid if not needed', 
 '["Enable for convenience", "Only if necessary and with strong credentials; avoid if not needed", "Always disable cloud features", "Ask IT first"]', 
 'Printers can be attack vectors. Enable cloud only if required and secure it.', 0),

('device', 'hard', 'Scenario: You need to use a coworker\'s computer temporarily. What should you do?', 'Use a guest account or incognito mode; don\'t save any credentials', 
 '["Log in with your account", "Use a guest account or incognito mode; don\'t save any credentials", "Ask to borrow their password", "Use your phone instead"]', 
 'Avoid saving credentials on shared devices. Use guest/incognito modes.', 0),

('device', 'medium', 'Scenario: Your fitness app requests location permissions always. What should you do?', 'Allow only while using the app; review privacy policy', 
 '["Allow always", "Allow only while using the app; review privacy policy", "Deny and use app without location", "Use a different app"]', 
 'Limit location access to when necessary. Avoid always-on permissions.', 0),

('device', 'easy', 'Scenario: You find a SIM card on the ground. What should you do?', 'Turn it in to lost and found; do not insert it', 
 '["Insert it to see whose it is", "Turn it in to lost and found; do not insert it", "Throw it away", "Keep it as backup"]', 
 'Unknown SIM cards can be used for fraud. Turn them in; don\'t use.', 0),

-- NETWORK SECURITY (20 questions)
('network', 'easy', 'Scenario: You must use public Wi‑Fi to access your work account. What should you do?', 'Use a trusted VPN and avoid sensitive actions if you cannot verify the network', 
 '["Turn off the firewall to improve speed", "Use a trusted VPN and avoid sensitive actions if you cannot verify the network", "Use any free VPN advertised on a pop-up", "Only use websites that load quickly"]', 
 'Public Wi‑Fi can be intercepted. A trusted VPN reduces exposure; avoid sensitive access if you cannot secure the connection.', 0),

('network', 'easy', 'Scenario: A coffee shop Wi‑Fi requires your email to connect. What should you do?', 'Use a disposable email or decline; avoid giving real credentials', 
 '["Use your work email to connect", "Use a disposable email or decline; avoid giving real credentials", "Use a fake email", "Connect without email if possible"]', 
 'Public Wi‑Fi credential harvesting is common. Use disposable or fake emails.', 0),

('network', 'medium', 'Scenario: You receive an email asking you to update your network settings via a link. What should you do?', 'Ignore the link; update settings directly from your router or IT', 
 '["Click the link to update", "Ignore the link; update settings directly from your router or IT", "Forward to IT", "Reply asking for confirmation"]', 
 'Network setting updates should be done directly, not via email links.', 0),

('network', 'medium', 'Scenario: Your home network shows an unknown device connected. What should you do?', 'Investigate and remove the device; change Wi‑Fi password', 
 '["Ignore it", "Investigate and remove the device; change Wi‑Fi password", "Leave it connected", "Restart the router"]', 
 'Unknown devices may indicate unauthorized access. Remove them and change passwords.', 0),

('network', 'hard', 'Scenario: Your browser warns a site\'s certificate is invalid. What should you do?', 'Do not proceed; verify the site or use a different trusted site', 
 '["Proceed anyway", "Do not proceed; verify the site or use a different trusted site", "Take a screenshot and report", "Try a different browser"]', 
 'Invalid certificates indicate risk. Do not proceed.', 0),

('network', 'hard', 'Scenario: You suspect your DNS is being tampered with. What should you do?', 'Change to trusted DNS servers (e.g., 8.8.8.8, 1.1.1.1)', 
 '["Ignore it", "Change to trusted DNS servers (e.g., 8.8.8.8, 1.1.1.1)", "Restart the router", "Contact ISP"]', 
 'DNS tampering redirects traffic. Use trusted DNS providers.', 0),

('network', 'medium', 'Scenario: A website asks you to download a "security plugin" to view content. What should you do?', 'Decline; use a trusted browser or official plugin source', 
 '["Download to view content", "Decline; use a trusted browser or official plugin source", "Scan the plugin after download", "Ask IT first"]', 
 'Fake security plugins are malware. Only install from official sources.', 0),

('network', 'easy', 'Scenario: You need to share your Wi‑Fi with a guest. What is the safest method?', 'Create a guest network with a separate password', 
 '["Share your main password", "Create a guest network with a separate password", "Let them use your phone hotspot", "Write the password on a sticky note"]', 
 'Guest networks isolate visitors from your main network. Use them.', 0),

('network', 'medium', 'Scenario: Your router firmware is outdated. What should you do?', 'Update the firmware immediately; set auto-updates if available', 
 '["Ignore it", "Update the firmware immediately; set auto-updates if available", "Buy a new router", "Turn off the router"]', 
 'Outdated firmware has vulnerabilities. Update promptly.', 0),

('network', 'hard', 'Scenario: You receive a text claiming your bank account is frozen with a link to unlock. What should you do?', 'Call the bank using the official number; do not click the link', 
 '["Click the link to unlock", "Call the bank using the official number; do not click the link", "Reply to the text", "Ignore it"]', 
 'Smishing uses fake bank links. Use official contact numbers.', 0),

('network', 'medium', 'Scenario: A public charging port looks suspicious. What should you do?', 'Use your own charger/power bank; avoid public USB data ports', 
 '["Use the port to charge quickly", "Use your own charger/power bank; avoid public USB data ports", "Use the port but turn off phone", "Ask others if it\'s safe"]', 
 'Public USB ports can be used for data theft/juice jacking. Use your own charger.', 0),

('network', 'easy', 'Scenario: Your browser offers to save passwords for a site. Should you accept?', 'Only if it\'s your personal device with encryption; otherwise use a password manager', 
 '["Always accept", "Only if it\'s your personal device with encryption; otherwise use a password manager", "Never accept", "Accept and email yourself the passwords"]', 
 'Browser password saving is risky on shared devices. Use encrypted managers.', 0),

('network', 'hard', 'Scenario: You notice unusual outbound traffic from your computer. What should you do?', 'Disconnect from network and scan for malware; investigate the source', 
 '["Ignore it", "Disconnect from network and scan for malware; investigate the source", "Restart the computer", "Close browser tabs"]', 
 'Unusual outbound traffic can indicate malware. Disconnect and scan.', 0),

('network', 'medium', 'Scenario: A website asks for permission to show notifications. What should you do?', 'Deny unless you trust the site and need notifications', 
 '["Always allow", "Deny unless you trust the site and need notifications", "Allow and block later", "Ignore the prompt"]', 
 'Notifications can be abused. Allow only from trusted sites.', 0),

('network', 'easy', 'Scenario: You need to send sensitive files over email. What should you do?', 'Encrypt the files or use a secure file transfer service', 
 '["Attach and send", "Encrypt the files or use a secure file transfer service", "Compress the files", "Send from personal email"]', 
 'Email is not secure. Use encryption or secure transfer.', 0),

('network', 'hard', 'Scenario: Your ISP contacts you claiming your account is compromised and asks for your password. What should you do?', 'Do not provide password; contact ISP using official channels', 
 '["Provide the password to fix the issue", "Do not provide password; contact ISP using official channels", "Change your password", "Ask for proof first"]', 
 'ISPs don\'t ask for passwords. Use official contact methods.', 0),

('network', 'medium', 'Scenario: A social media site asks to link your contacts. Should you allow?', 'Decline unless necessary; limit third-party access to contacts', 
 '["Allow to find friends", "Decline unless necessary; limit third-party access to contacts", "Allow but delete contacts later", "Use a fake account"]', 
 'Contact access can be abused. Decline unless you truly need it.', 0),

('network', 'easy', 'Scenario: You receive a QR code to join Wi‑Fi instantly. What should you do?', 'Avoid scanning unknown QR codes; connect manually instead', 
 '["Scan to connect quickly", "Avoid scanning unknown QR codes; connect manually instead", "Research the QR code first", "Ask the venue for the password"]', 
 'QR codes can hide malicious network settings. Connect manually.', 0),

('network', 'medium', 'Scenario: Your browser autofills a password on a site you don\'t recognize. What should you do?', 'Do not log in; check the URL carefully and navigate to the official site', 
 '["Log in to check the site", "Do not log in; check the URL carefully and navigate to the official site", "Change the password immediately", "Clear autofill data"]', 
 'Autofill on phishing sites steals credentials. Verify sites before logging in.', 0),

('network', 'hard', 'Scenario: You need to use a coworker\'s network cable temporarily. What should you do?', 'Use your own if possible; avoid sharing network hardware', 
 '["Use their cable", "Use your own if possible; avoid sharing network hardware", "Use Wi‑Fi instead", "Ask IT for a spare"]', 
 'Shared network hardware can be compromised. Use your own equipment.', 0),

('network', 'medium', 'Scenario: A website asks you to disable your ad blocker to view content. What should you do?', 'Decline or use a trusted site; ad blockers improve security', 
 '["Disable to view", "Decline or use a trusted site; ad blockers improve security", "Disable temporarily", "Use a different browser"]', 
 'Ad blockers prevent malicious ads. Keep them enabled.', 0),

-- SOCIAL ENGINEERING (20 questions)
('social_engineering', 'easy', 'Scenario: Someone claiming to be IT asks you for a one-time code to "fix your account". What is the best response?', 'Refuse and verify the request through official IT channels', 
 '["Share the code because IT needs it", "Refuse and verify the request through official IT channels", "Ask them for their password to confirm identity", "Send your username so they can look up your account"]', 
 'Legitimate support will not ask for your password or one-time codes. Verify identity via known helpdesk contacts.', 0),

('social_engineering', 'easy', 'Scenario: A delivery person at your door asks to come inside to "verify a package". What should you do?', 'Ask for ID and verify with the company; do not let them inside', 
 '["Let them in to verify", "Ask for ID and verify with the company; do not let them inside", "Take the package at the door", "Refuse the delivery"]', 
 'Delivery scams can be pretext for burglary. Verify credentials and don\'t allow entry.', 0),

('social_engineering', 'medium', 'Scenario: You receive a call from "tech support" saying your computer has a virus and to install software. What should you do?', 'Hang up and run your own antivirus; never install software from callers', 
 '["Install the software they recommend", "Hang up and run your own antivirus; never install software from callers", "Ask for a callback number", "Pay for the service"]', 
 'Tech support scams install malware. Hang up and use your own security tools.', 0),

('social_engineering', 'medium', 'Scenario: A stranger on social media offers you a job after only a few messages. What should you do?', 'Research the company and verify through official channels; be cautious', 
 '["Accept the offer immediately", "Research the company and verify through official channels; be cautious", "Ask for references", "Share your resume"]', 
 'Job offers from strangers can be scams. Verify through official company channels.', 0),

('social_engineering', 'hard', 'Scenario: Someone at your desk asks to "borrow your badge" while you step away. What should you do?', 'Never share badges; escort them or use proper visitor procedures', 
 '["Lend it to them", "Never share badges; escort them or use proper visitor procedures", "Ask them to wait", "Let security know"]', 
 'Badge sharing violates security. Use proper visitor access procedures.', 0),

('social_engineering', 'hard', 'Scenario: You receive a voicemail from your "bank" asking to call back about fraud. What should you do?', 'Call the bank using the official number on your card; not the number in the voicemail', 
 '["Call the number in the voicemail", "Call the bank using the official number on your card; not the number in the voicemail", "Reply to the voicemail", "Ignore it"]', 
 'Vishing uses fake callback numbers. Use official contact information.', 0),

('social_engineering', 'medium', 'Scenario: A survey asks for your work email and department for a "company prize". What should you do?', 'Decline; legitimate internal surveys don\'t need sensitive details', 
 '["Provide details to enter", "Decline; legitimate internal surveys don\'t need sensitive details", "Use a personal email", "Ask HR if it\'s legit"]', 
 'Surveys can harvest corporate data. Decline if they ask for sensitive info.', 0),

('social_engineering', 'easy', 'Scenario: Someone at the entrance says they forgot their badge and asks you to let them in. What should you do?', 'Direct them to security or reception; do not tailgate', 
 '["Let them in quickly", "Direct them to security or reception; do not tailgate", "Ask for their name first", "Let them in if they look familiar"]', 
 'Tailgating bypasses security. Direct visitors to proper access points.', 0),

('social_engineering', 'medium', 'Scenario: A pop-up claims your computer is locked and to call a number to unlock. What should you do?', 'Force restart and run malware scans; do not call the number', 
 '["Call the number to unlock", "Force restart and run malware scans; do not call the number", "Pay the fee", "Take a photo and report"]', 
 'Ransomware pop-ups are scams. Restart and scan for malware.', 0),

('social_engineering', 'hard', 'Scenario: You receive a LinkedIn request from a CEO you don\'t know with an urgent message. What should you do?', 'Verify the identity through official channels; be skeptical of urgent requests', 
 '["Respond immediately", "Verify the identity through official channels; be skeptical of urgent requests", "Accept and ignore", "Report as fake"]', 
 'CEO impersonation scams use urgency. Verify identities through official channels.', 0),

('social_engineering', 'medium', 'Scenario: A coworker asks you to approve an unusual financial request via email. What should you do?', 'Confirm via phone or in person before approving', 
 '["Approve to help them", "Confirm via phone or in person before approving", "Ask for more details via email", "Decline and let them handle it"]', 
 'Financial requests should be verified through separate channels. Email can be spoofed.', 0),

('social_engineering', 'easy', 'Scenario: Someone at a conference offers you a free USB drive. What should you do?', 'Decline; unknown USB drives can contain malware', 
 '["Accept and scan it", "Decline; unknown USB drives can contain malware", "Take it but don\'t use it", "Give it to IT"]', 
 'Free USB drives often contain malware. Decline unknown devices.', 0),

('social_engineering', 'hard', 'Scenario: You receive a court summons via email with an attachment. What should you do?', 'Verify with the court directly; do not open attachments', 
 '["Open the attachment", "Verify with the court directly; do not open attachments", "Reply to confirm", "Forward to legal"]', 
 'Legal documents via email can be phishing. Verify with official sources.', 0),

('social_engineering', 'medium', 'Scenario: A stranger claims to be a new hire and asks for help accessing systems. What should you do?', 'Direct them to IT/onboarding; do not share your credentials', 
 '["Log them in with your account", "Direct them to IT/onboarding; do not share your credentials", "Help them reset their password", "Ask your manager"]', 
 'New hires should use official onboarding. Don\'t share credentials.', 0),

('social_engineering', 'easy', 'Scenario: You receive a text from your "manager" asking to buy gift cards urgently. What should you do?', 'Call your manager using their known number to verify', 
 '["Buy the cards immediately", "Call your manager using their known number to verify", "Reply to confirm", "Ask for an email approval"]', 
 'Gift card scams impersonate managers. Verify via known contact methods.', 0),

('social_engineering', 'medium', 'Scenario: Someone claims to be from partner company and needs access to your server room. What should you do?', 'Verify with the partner company and follow proper access procedures', 
 '["Escort them in", "Verify with the partner company and follow proper access procedures", "Ask for ID only", "Let security handle it"]', 
 'Verify third-party access through official channels and follow procedures.', 0),

('social_engineering', 'hard', 'Scenario: You receive a fake security alert asking to enter credentials to "secure your account". What should you do?', 'Navigate to the official site yourself; do not enter credentials in the alert', 
 '["Enter credentials to secure", "Navigate to the official site yourself; do not enter credentials in the alert", "Take a screenshot", "Ignore it"]', 
 'Fake security alerts steal credentials. Navigate to sites yourself.', 0),

('social_engineering', 'medium', 'Scenario: A researcher calls asking about your company\'s security practices. What should you do?', 'Refer them to PR/legal; don\'t share internal details', 
 '["Answer their questions", "Refer them to PR/legal; don\'t share internal details", "Ask for their credentials", "Hang up"]', 
 'Unverified researchers may be social engineers. Direct them to official channels.', 0),

('social_engineering', 'easy', 'Scenario: Someone at the door claims to be from utilities and needs access inside. What should you do?', 'Ask for ID and verify with the utility company; do not allow entry without verification', 
 '["Let them in", "Ask for ID and verify with the utility company; do not allow entry without verification", "Refuse entry", "Call security"]', 
 'Utility scams gain entry to homes/businesses. Verify credentials.', 0),

('social_engineering', 'hard', 'Scenario: You receive a fake termination notice with a link to "appeal". What should you do?', 'Contact HR directly; do not click links in unexpected employment notices', 
 '["Click to appeal", "Contact HR directly; do not click links in unexpected employment notices", "Reply to confirm", "Ask coworkers"]', 
 'Employment scams use fear. Verify with HR directly.', 0),

-- DATA HANDLING (20 questions)
('data_handling', 'easy', 'Scenario: You need to send a file with customer data to a partner. What is the safest first step?', 'Check if sharing is allowed and use an approved secure method (encrypted transfer / access controls)', 
 '["Send it quickly by email attachment", "Upload it to a personal cloud drive for convenience", "Check if sharing is allowed and use an approved secure method (encrypted transfer / access controls)", "Remove only names; the rest is fine"]', 
 'Start with policy and approved tools. Use least-privilege sharing and encryption, and minimize data shared.', 0),

('data_handling', 'easy', 'Scenario: You\'re working in a coffee shop with sensitive documents open. What should you do?', 'Use a privacy screen and lock the device when stepping away', 
 '["Work quickly and pack up", "Use a privacy screen and lock the device when stepping away", "Turn your back to the crowd", "Minimize the screen"]', 
 'Public workspaces expose data. Use privacy screens and lock devices.', 0),

('data_handling', 'medium', 'Scenario: A colleague asks for a list of all customer emails for a "marketing campaign". What should you do?', 'Verify the request through proper channels and minimize data shared', 
 '["Send the full list", "Verify the request through proper channels and minimize data shared", "Ask your manager first", "Send only emails from last month"]', 
 'Bulk data requests should be verified and minimized. Follow data handling policies.', 0),

('data_handling', 'medium', 'Scenario: You must dispose of printed client files. What is the correct method?', 'Use cross-cut shredding or professional destruction service', 
 '["Throw in regular trash", "Use cross-cut shredding or professional destruction service", "Recycle without shredding", "Keep for future reference"]', 
 'Sensitive documents must be shredded or professionally destroyed.', 0),

('data_handling', 'hard', 'Scenario: You find a USB drive with labeled "client data" in the parking lot. What should you do?', 'Turn it in to security/IT; do not attempt to access it', 
 '["Plug it in to identify the owner", "Turn it in to security/IT; do not attempt to access it", "Keep it safe until someone claims it", "Destroy it"]', 
 'Unknown media with client data must be handled securely. Turn it in; don\'t access.', 0);

-- Track questions shown to users (for repetition prevention)
CREATE TABLE user_question_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_ids JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Track assessment sessions
CREATE TABLE assessment_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(64) NOT NULL,
    question_ids JSON NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_session (user_id, session_id),
    INDEX idx_started_at (started_at)
);

-- Track answers for bias analytics
CREATE TABLE answer_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    user_answer VARCHAR(255),
    is_correct BOOLEAN,
    time_taken_ms INT DEFAULT 0,
    answer_position INT DEFAULT 0 COMMENT 'Position of answer in options (0-3)',
    session_id VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_question (question_id),
    INDEX idx_user (user_id),
    INDEX idx_session (session_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE
);

-- Track question order to detect position bias
CREATE TABLE question_order_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(64) NOT NULL,
    question_id INT NOT NULL,
    position_in_assessment INT NOT NULL,
    page_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_question (question_id),
    INDEX idx_session (session_id),
    INDEX idx_position (position_in_assessment),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES question_bank(id) ON DELETE CASCADE
);

-- ============================================================
-- ASSESSMENT TABLES
-- ============================================================

-- Assessments table
CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    score INT NOT NULL,
    rank VARCHAR(2) NOT NULL,
    password_score INT DEFAULT 0,
    phishing_score INT DEFAULT 0,
    device_score INT DEFAULT 0,
    network_score INT DEFAULT 0,
    social_engineering_score INT DEFAULT 0,
    data_handling_score INT DEFAULT 0,
    time_spent INT NOT NULL,
    questions_answered INT NOT NULL,
    total_questions INT NOT NULL,
    assessment_date DATETIME NOT NULL,
    assessment_token VARCHAR(64) UNIQUE,
    session_id VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_score (score),
    INDEX idx_rank (rank),
    INDEX idx_assessment_date (assessment_date),
    INDEX idx_session (session_id)
);

-- Assessment answers table
CREATE TABLE assessment_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_id INT NOT NULL,
    question_text TEXT NOT NULL,
    user_answer TEXT NOT NULL,
    correct_answer VARCHAR(255) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    category VARCHAR(50) NOT NULL,
    answer_position INT DEFAULT 0,
    time_taken_ms INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_question_id (question_id),
    INDEX idx_is_correct (is_correct),
    INDEX idx_category (category)
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    category VARCHAR(100) DEFAULT 'Other',
    status ENUM('active', 'inactive') DEFAULT 'active',
    image_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- Activity log
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50) NOT NULL,
    action_description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);

-- Badges table
CREATE TABLE badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#3B8BFF',
    category ENUM('assessment', 'consistency', 'improvement', 'milestone', 'special') NOT NULL,
    requirement_type ENUM('score', 'count', 'rank', 'streak', 'special') NOT NULL,
    requirement_value INT NOT NULL,
    points INT DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
);

-- User achievements
CREATE TABLE user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assessment_id INT NULL,
    points_earned INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_badge (user_id, badge_id)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users with bcrypt hashed passwords (password: Admin@123, Seller@123, Viewer@123)
INSERT INTO users (username, password_hash, email, full_name, store_name, role, total_assessments) VALUES
('admin', '$2y$10$BUZrq5cvZo7XL7063kWc2uM8P1j2X0kvNHQ/QTpVNUAuV7q7WiRfO', 'admin@cybershield.ph', 'System Administrator', 'CyberShield Admin', 'Admin', 5),
('seller', '$2y$10$sj2SICvEmuRT3vhsa9IskO6dc3gFmfMYuSN.U/MYtUjNJtCjt4/.q', 'seller@demo.ph', 'Demo Seller', 'Demo Store', 'Seller', 3),
('viewer', '$2y$10$VP6fVllWeLjea6OYxMNhMOYgrSmJX04yazqQFX8u0KY4.ZGjvG98a', 'viewer@demo.ph', 'Demo Viewer', 'Demo Company', 'Viewer', 1);

-- Vendors
INSERT INTO vendors (name, email, industry, contact_person, phone, store_name) VALUES
('TechCorp Solutions', 'security@techcorp.com', 'Technology', 'John Smith', '+63-2-555-0123', 'TechCorp Store'),
('SecureNet Inc', 'info@securenet.com', 'Network Security', 'Sarah Johnson', '+63-2-555-0124', 'SecureNet Shop'),
('DataSafe Systems', 'contact@datasafe.com', 'Data Management', 'Michael Brown', '+63-2-555-0125', 'DataSafe Hub');

-- Products
INSERT INTO products (user_id, name, description, price, stock, category, status) VALUES
(2, 'Wireless Noise-Cancelling Headphones', 'Premium audio with 30-hour battery life', 3499.00, 25, 'Electronics', 'active'),
(2, 'Mechanical Gaming Keyboard', 'RGB backlit keyboard with Cherry MX switches', 2299.00, 40, 'Electronics', 'active'),
(2, 'Ergonomic Office Chair', 'Lumbar support mesh chair', 8999.00, 8, 'Furniture', 'active');

-- Badges
INSERT INTO badges (name, description, icon, color, category, requirement_type, requirement_value, points) VALUES
('Security Elite', 'Achieve a perfect score (100%)', '🏆', '#f5c518', 'assessment', 'score', 100, 50),
('Security Master', 'Score 90% or higher', '🥇', '#f5c518', 'assessment', 'score', 90, 40),
('Security Expert', 'Score 80% or higher', '🥈', '#c0c0c0', 'assessment', 'score', 80, 30),
('Consistent Learner', 'Complete 5 assessments', '📚', '#4090ff', 'consistency', 'count', 5, 20),
('First Steps', 'Complete your first assessment', '🎯', '#3b8bff', 'milestone', 'count', 1, 10),
('Quick Learner', 'Improve score by 20%', '📈', '#10d982', 'improvement', 'special', 20, 30);

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

DELIMITER //

-- Procedure to detect biased questions
CREATE PROCEDURE DetectBiasedQuestions()
BEGIN
    -- Update bias scores based on answer patterns
    UPDATE question_bank qb
    SET bias_score = (
        SELECT COALESCE(
            (ABS(0.5 - AVG(CASE WHEN aa.is_correct = 1 THEN 1 ELSE 0 END)) * 100),
            0
        )
        FROM answer_analytics aa
        WHERE aa.question_id = qb.id
        AND aa.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    )
    WHERE EXISTS (
        SELECT 1 FROM answer_analytics aa WHERE aa.question_id = qb.id
    );
    
    -- Flag questions with high bias (over 40% deviation from expected)
    UPDATE question_bank
    SET is_active = FALSE
    WHERE bias_score > 40
    AND times_used > 100;
END//

-- Procedure to update question usage stats
CREATE PROCEDURE UpdateQuestionStats()
BEGIN
    UPDATE question_bank qb
    SET times_used = (
        SELECT COUNT(*) 
        FROM assessment_answers aa 
        WHERE aa.question_id = qb.id
    ),
    correct_rate = (
        SELECT AVG(CASE WHEN is_correct = 1 THEN 100 ELSE 0 END)
        FROM assessment_answers aa
        WHERE aa.question_id = qb.id
    )
    WHERE EXISTS (
        SELECT 1 FROM assessment_answers aa WHERE aa.question_id = qb.id
    );
END//

DELIMITER ;

-- ============================================================
-- VIEWS
-- ============================================================

-- View for question bias analysis
CREATE VIEW biased_questions_view AS
SELECT 
    id,
    category,
    difficulty,
    question_text,
    bias_score,
    times_used,
    correct_rate,
    CASE 
        WHEN bias_score > 40 THEN 'High Bias - Needs Review'
        WHEN bias_score > 25 THEN 'Moderate Bias - Monitor'
        WHEN bias_score > 10 THEN 'Low Bias'
        ELSE 'Unbiased'
    END AS bias_level
FROM question_bank
WHERE times_used > 50
ORDER BY bias_score DESC;

-- View for user assessment summary
CREATE VIEW user_assessment_summary AS
SELECT 
    u.id AS user_id,
    u.username,
    u.full_name,
    COUNT(a.id) AS total_assessments,
    AVG(a.score) AS avg_score,
    MAX(a.score) AS best_score,
    MIN(a.score) AS worst_score,
    AVG(a.time_spent) AS avg_time_spent,
    MAX(a.assessment_date) AS last_assessment
FROM users u
LEFT JOIN assessments a ON u.id = a.vendor_id
GROUP BY u.id, u.username, u.full_name;

-- ============================================================
-- VERIFICATION
-- ============================================================

SELECT '=== CYBERSHIELD DATABASE SETUP COMPLETE ===' AS Status;
SELECT COUNT(*) AS total_users FROM users;
SELECT COUNT(*) AS total_questions FROM question_bank;
SELECT COUNT(*) AS total_badges FROM badges;
SELECT 
    category, 
    COUNT(*) AS question_count 
FROM question_bank 
GROUP BY category 
ORDER BY category;