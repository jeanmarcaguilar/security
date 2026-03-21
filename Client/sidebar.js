// ═══════════════════════════════════════════════════════════════
//  SIDEBAR COLLAPSE
// ═══════════════════════════════════════════════════════════════
var _sidebarCollapsed = false;

function toggleSidebar() {
  var sidebar    = document.getElementById('sidebar');
  var main       = document.getElementById('main-content');
  var icon       = document.getElementById('sidebar-toggle-icon');
  var toggleBtn  = document.getElementById('sidebar-toggle');
  _sidebarCollapsed = !_sidebarCollapsed;
  sidebar.classList.toggle('collapsed', _sidebarCollapsed);
  main.classList.toggle('sidebar-collapsed', _sidebarCollapsed);
  if (icon) {
    icon.innerHTML = _sidebarCollapsed
      ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>'
      : '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
  }
  if (toggleBtn) {
    toggleBtn.style.left = _sidebarCollapsed
      ? 'var(--sidebar-collapsed-w)'
      : 'var(--sidebar-w)';
  }
  localStorage.setItem('cs_sidebar_collapsed', _sidebarCollapsed ? '1' : '0');
}

function toggleMobileSidebar() {
  var sidebar = document.getElementById('sidebar');
  if (sidebar) sidebar.classList.toggle('mobile-open');
}

// Restore collapsed state on load
(function () {
  if (localStorage.getItem('cs_sidebar_collapsed') === '1') {
    var sidebar   = document.getElementById('sidebar');
    var main      = document.getElementById('main-content');
    var icon      = document.getElementById('sidebar-toggle-icon');
    var toggleBtn = document.getElementById('sidebar-toggle');
    if (sidebar)   sidebar.classList.add('collapsed');
    if (main)      main.classList.add('sidebar-collapsed');
    if (icon)      icon.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
    if (toggleBtn) toggleBtn.style.left = 'var(--sidebar-collapsed-w)';
    _sidebarCollapsed = true;
  }
})();

// Close mobile sidebar on outside click
document.addEventListener('click', function (e) {
  var sidebar   = document.getElementById('sidebar');
  var mobileBtn = document.getElementById('mobile-menu-btn');
  if (sidebar && sidebar.classList.contains('mobile-open')
      && !sidebar.contains(e.target)
      && e.target !== mobileBtn
      && !mobileBtn.contains(e.target)) {
    sidebar.classList.remove('mobile-open');
  }
});

// ═══════════════════════════════════════════════════════════════
//  ACTIVE NAV & PAGE TITLE
// ═══════════════════════════════════════════════════════════════
var PAGE_META = {
  dashboard:        { nav: 'nav-dashboard',        title: 'Dashboard' },
  assessment:       { nav: 'nav-assessment',       title: 'Take Assessment' },
  results:          { nav: 'nav-results',          title: 'My Results' },
  leaderboard:      { nav: 'nav-leaderboard',      title: 'Leaderboard' },
  profile:          { nav: 'nav-profile',          title: 'My Profile' },
  tips:             { nav: 'nav-tips',             title: 'Security Tips' },
  terms:            { nav: 'nav-terms',            title: 'Terms & Privacy' },
  'seller-store':   { nav: 'nav-seller-store',     title: 'My Store' },
  'seller-analytics':{ nav: 'nav-seller-analytics', title: 'Analytics Dashboard' },
};

function setActiveNav(page) {
  Object.values(PAGE_META).forEach(function (m) {
    var el = document.getElementById(m.nav);
    if (el) el.classList.remove('active');
  });
  var meta = PAGE_META[page];
  if (meta) {
    var el = document.getElementById(meta.nav);
    if (el) el.classList.add('active');
    var titleEl = document.getElementById('topbar-page-title');
    if (titleEl) titleEl.textContent = meta.title;
  }
  var sidebar = document.getElementById('sidebar');
  if (sidebar) sidebar.classList.remove('mobile-open');
}

// ═══════════════════════════════════════════════════════════════
//  OVERRIDE showPage TO HANDLE TIPS + ACTIVE NAV
// ═══════════════════════════════════════════════════════════════
var _origShowPage = showPage;
var _termsReturnPage = 'dashboard';

showPage = function (name) {
  // Track last non-terms page for back navigation
  if (name !== 'terms') _termsReturnPage = name;

  // Update active nav highlight + topbar title
  setActiveNav(name);

  // Tips page: handled here since original showPage doesn't know about it
  if (name === 'tips') {
    ['dashboard','assessment','results','profile','leaderboard','tips','terms','seller-store','seller-analytics'].forEach(function (p) {
      var el = document.getElementById('page-' + p);
      if (el) el.classList.add('hidden');
    });
    var tipsPage = document.getElementById('page-tips');
    if (tipsPage) {
      tipsPage.classList.remove('hidden');
      tipsPage.classList.remove('fade-in');
      void tipsPage.offsetWidth;
      tipsPage.classList.add('fade-in');
    }
    renderTipsPage();
    return;
  }

  // Seller Store
  if (name === 'seller-store') {
    ['dashboard','assessment','results','profile','leaderboard','tips','terms','seller-store','seller-analytics'].forEach(function (p) {
      var el = document.getElementById('page-' + p);
      if (el) el.classList.add('hidden');
    });
    var pg = document.getElementById('page-seller-store');
    if (pg) { pg.classList.remove('hidden'); pg.classList.remove('fade-in'); void pg.offsetWidth; pg.classList.add('fade-in'); }
    if (typeof renderProductGrid === 'function') renderProductGrid();
    return;
  }

  // Seller Analytics
  if (name === 'seller-analytics') {
    ['dashboard','assessment','results','profile','leaderboard','tips','terms','seller-store','seller-analytics'].forEach(function (p) {
      var el = document.getElementById('page-' + p);
      if (el) el.classList.add('hidden');
    });
    var pg2 = document.getElementById('page-seller-analytics');
    if (pg2) { pg2.classList.remove('hidden'); pg2.classList.remove('fade-in'); void pg2.offsetWidth; pg2.classList.add('fade-in'); }
    setTimeout(function() { if (typeof renderAnalyticsDashboard === 'function') renderAnalyticsDashboard(); }, 50);
    return;
  }

  // All other pages go through the original showPage
  _origShowPage(name);
};

function goBackFromTerms() { showPage(_termsReturnPage || 'dashboard'); }

function scrollToSection(id) {
  var el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearAllData() {
  if (!confirm('Are you sure you want to delete all your assessment data? This cannot be undone.')) return;
  ['cs_history','cs_badges','cs_notifs','cs_profile','cs_session','cs_prefs',
   'cs_leaderboard','cs_history_seeded','cs_lang','cs_theme','cs_a11y'].forEach(function (k) {
    localStorage.removeItem(k);
  });
  toast('All data cleared. Refreshing…');
  setTimeout(function () { location.reload(); }, 1500);
}

// ═══════════════════════════════════════════════════════════════
//  ACCESSIBILITY
// ═══════════════════════════════════════════════════════════════
function setAccessibility(on) {
  document.documentElement.setAttribute('data-a11y', on ? 'true' : 'false');
  var btn = document.getElementById('a11y-btn');
  if (btn) btn.classList.toggle('active', on);
  var chk = document.getElementById('pref-a11y');
  if (chk) chk.checked = on;
  localStorage.setItem('cs_a11y', on ? '1' : '0');
}

function toggleAccessibility() {
  var current = document.documentElement.getAttribute('data-a11y') === 'true';
  setAccessibility(!current);
  toast(!current ? 'Large text mode enabled' : 'Large text mode disabled');
}

// ═══════════════════════════════════════════════════════════════
//  TIP OF THE DAY
// ═══════════════════════════════════════════════════════════════
var DAILY_TIPS = [
  'Use a password manager like Bitwarden to store all your passwords safely — you only need to remember one strong master password.',
  'Turn on Two-Factor Authentication (2FA) for your email and banking. It adds a second lock even if your password is stolen.',
  'Before clicking any link in an email, hover over it to see the real address. If it looks unusual, do not click.',
  'Always lock your screen when you step away from your computer — press Windows+L or Command+Control+Q on Mac.',
  'Avoid using public Wi-Fi for work tasks. If you must, connect through a VPN to encrypt your traffic.',
  'Keep your operating system and applications updated. Updates patch security vulnerabilities that attackers exploit.',
  'Back up your important files regularly to an external drive or an approved cloud storage service.',
  'Only install applications from trusted, official sources such as the App Store or Google Play.',
  'Be suspicious of any email that creates urgency — "Act now!" or "Your account will be suspended!" are classic phishing tactics.',
  'Use a different password for every account. If one is compromised, the rest remain safe.',
  'A strong password should be at least 12 characters and include a mix of letters, numbers, and symbols.',
  'Never plug an unknown USB drive into your computer — it can automatically install malware the moment it is connected.',
];

function initTipOfDay() {
  var el = document.getElementById('tip-text');
  if (!el) return;
  el.textContent = DAILY_TIPS[new Date().getDate() % DAILY_TIPS.length];
}

function refreshTip() {
  var el = document.getElementById('tip-text');
  if (!el) return;
  el.style.opacity = '0';
  el.style.transform = 'translateY(5px)';
  el.style.transition = 'opacity .25s, transform .25s';
  setTimeout(function () {
    el.textContent = DAILY_TIPS[Math.floor(Math.random() * DAILY_TIPS.length)];
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';
  }, 220);
}

// ═══════════════════════════════════════════════════════════════
//  SECURITY TIPS PAGE
// ═══════════════════════════════════════════════════════════════
var ALL_TIPS = [
  { cat:'password', title:'Use a Password Manager',       body:'A password manager creates and remembers strong, unique passwords for every website so you never have to. Recommended: Bitwarden (free) or 1Password.',                                                       difficulty:'Easy'   },
  { cat:'password', title:'Enable Two-Factor Authentication', body:'Even if your password is stolen, attackers still cannot log in without a second verification step. Enable this on email, banking, and work accounts first.',                                         difficulty:'Easy'   },
  { cat:'password', title:'Create Long, Unique Passwords', body:'Use at least 12 characters and combine random words. For example, "PurpleElephant$42Rain" is far stronger than "P@ssword1" and easier to remember.',                                                  difficulty:'Easy'   },
  { cat:'password', title:'Change Passwords After Breaches', body:'If a service you use reports a data breach, change your password immediately. Visit haveibeenpwned.com to check whether your email has appeared in known breaches.',                                 difficulty:'Medium' },
  { cat:'phishing', title:'Verify Links Before Clicking',  body:'Hover over any link in an email to preview the real web address before clicking. If the domain looks unfamiliar or misspelled, do not proceed.',                                                       difficulty:'Easy'   },
  { cat:'phishing', title:'Call to Confirm Suspicious Requests', body:'If you receive an urgent email requesting passwords or payments, call the sender directly using a phone number you already know — never use a number provided in the suspicious email.',      difficulty:'Easy'   },
  { cat:'phishing', title:'Recognise Phishing Warning Signs', body:'Be cautious of emails with urgent language, spelling mistakes, mismatched sender addresses, unexpected attachments, or requests for sensitive personal information.',                              difficulty:'Easy'   },
  { cat:'phishing', title:'Report Suspicious Emails',      body:'Do not simply delete phishing emails — report them to your IT or security team. This helps protect your entire organization from similar attacks.',                                                  difficulty:'Easy'   },
  { cat:'device',   title:'Set Auto-Lock on Your Screen',  body:'Configure your computer to lock automatically after 2–5 minutes of inactivity. On Windows go to Settings → Personalization → Lock Screen; on Mac, System Preferences → Security.',                 difficulty:'Easy'   },
  { cat:'device',   title:'Enable Full-Disk Encryption',   body:'Encryption protects all data on your device if it is ever lost or stolen. Use BitLocker on Windows or FileVault on Mac — both are built-in and free to activate.',                                   difficulty:'Medium' },
  { cat:'device',   title:'Install Security Updates Promptly', body:'Apply operating system and application updates as soon as they are available. Most attacks target known vulnerabilities that updates have already fixed.',                                      difficulty:'Easy'   },
  { cat:'device',   title:'Never Use Unknown USB Devices', body:'A USB drive found in a public place could be a deliberate trap. Inserting it can install malware automatically, even before you open any files.',                                                    difficulty:'Easy'   },
  { cat:'network',  title:'Use a VPN on Public Wi-Fi',     body:'Public Wi-Fi at cafes, airports, and hotels is not secure. A VPN encrypts your connection so others on the same network cannot intercept your data.',                                               difficulty:'Medium' },
  { cat:'network',  title:'Look for HTTPS on Websites',    body:'Before entering passwords or payment details, verify the address begins with "https://" and shows a padlock icon. This confirms that your data is encrypted in transit.',                          difficulty:'Easy'   },
  { cat:'network',  title:'Disable Auto-Connect to Wi-Fi', body:'Turn off automatic Wi-Fi connections on your devices. Attackers can create fake hotspots with names like "Airport Free WiFi" to intercept your traffic.',                                          difficulty:'Easy'   },
  { cat:'network',  title:'Secure Your Home Router',       body:'Change your router\'s default admin password, use WPA3 or WPA2 encryption, and keep the firmware updated to prevent unauthorised access to your network.',                                         difficulty:'Medium' },
];

var _currentTipFilter = 'all';
var _catLabels = { password:'Passwords', phishing:'Phishing', device:'Devices', network:'Networks' };
var _difficultyLabel = { Easy:'Easy', Medium:'Intermediate', Hard:'Advanced' };

function filterTips(cat, btn) {
  _currentTipFilter = cat;
  document.querySelectorAll('.filter-bar .filter-btn').forEach(function (b) { b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  renderTipsPage();
}

function renderTipsPage() {
  var grid = document.getElementById('tips-grid');
  if (!grid) return;
  var list = _currentTipFilter === 'all'
    ? ALL_TIPS
    : ALL_TIPS.filter(function (t) { return t.cat === _currentTipFilter; });
  grid.innerHTML = list.map(function (tip, i) {
    return '<div class="tip-card-item" style="animation-delay:' + (i * 0.05) + 's">'
      + '<span class="tip-cat-badge ' + tip.cat + '">' + _catLabels[tip.cat] + '</span>'
      + '<h4>' + tip.title + '</h4>'
      + '<p>' + tip.body + '</p>'
      + '<div class="tip-difficulty">' + _difficultyLabel[tip.difficulty] + '</div>'
      + '</div>';
  }).join('');
}

// ═══════════════════════════════════════════════════════════════
//  GLOBAL SEARCH
// ═══════════════════════════════════════════════════════════════
var _searchTimer = null;

function handleGlobalSearch(query) {
  clearTimeout(_searchTimer);
  if (!query || query.trim().length < 2) { closeSearchOverlay(); return; }
  _searchTimer = setTimeout(function () { _runSearch(query.trim()); }, 240);
}

function _runSearch(query) {
  var q = query.toLowerCase();
  var results = [];

  // Pages
  Object.keys(PAGE_META).forEach(function (page) {
    var meta = PAGE_META[page];
    if (meta.title.toLowerCase().includes(q)) {
      results.push({ icon: _pageIcon(page), title: meta.title, sub: 'Navigate to page', page: page });
    }
  });

  // Tips
  ALL_TIPS.forEach(function (tip) {
    if (tip.title.toLowerCase().includes(q) || tip.body.toLowerCase().includes(q)) {
      results.push({ icon: null, title: tip.title, sub: 'Security Tip · ' + _catLabels[tip.cat], page: 'tips' });
    }
  });

  var overlay = document.getElementById('search-overlay');
  var list    = document.getElementById('search-results-list');
  if (!overlay || !list) return;
  overlay.classList.remove('hidden');

  if (!results.length) {
    list.innerHTML = '<div class="search-no-results">No results for "' + query + '"</div>';
    return;
  }

  list.innerHTML = results.slice(0, 7).map(function (r, i) {
    return '<div class="search-result-item" data-idx="' + i + '">'
      + '<div class="search-result-icon">' + (r.icon || '●') + '</div>'
      + '<div><div class="search-result-title">' + r.title + '</div>'
      + '<div class="search-result-sub">' + r.sub + '</div></div>'
      + '</div>';
  }).join('');

  results.slice(0, 7).forEach(function (r, i) {
    var el = list.querySelector('[data-idx="' + i + '"]');
    if (el) el.addEventListener('click', function () { showPage(r.page); closeSearchOverlay(); var inp = document.getElementById('global-search'); if (inp) inp.value = ''; });
  });
}

function _pageIcon(page) {
  var icons = { dashboard:'◈', assessment:'✓', results:'▤', leaderboard:'▲', profile:'○', tips:'●', terms:'▥' };
  return icons[page] || '●';
}

function closeSearchOverlay(e) {
  if (e && e.target && e.target.id !== 'search-overlay') return;
  var overlay = document.getElementById('search-overlay');
  if (overlay) overlay.classList.add('hidden');
}

// ESC closes search
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    closeSearchOverlay();
    var panel = document.getElementById('notif-panel');
    if (panel) panel.classList.add('hidden');
  }
});

// ═══════════════════════════════════════════════════════════════
//  WELCOME BANNER
// ═══════════════════════════════════════════════════════════════
function checkWelcomeBanner() {
  var banner  = document.getElementById('welcome-banner');
  if (!banner) return;
  var history = getHistory();
  banner.classList.toggle('hidden', history.length > 0);
}

// ═══════════════════════════════════════════════════════════════
//  SIDEBAR USER UPDATE
// ═══════════════════════════════════════════════════════════════
function updateSidebarUser() {
  var sAvatar = document.getElementById('sidebar-avatar');
  var sName   = document.getElementById('sidebar-name');
  if (window.session) {
    if (sAvatar) sAvatar.textContent = (session.name || 'U').charAt(0).toUpperCase();
    if (sName)   sName.textContent   = session.name || 'User';
  }
}

// ═══════════════════════════════════════════════════════════════
//  QUIT ASSESSMENT CONFIRM
// ═══════════════════════════════════════════════════════════════
function confirmQuitAssessment() {
  if (confirm('Are you sure you want to quit? Your progress will be lost.')) {
    stopTimer();
    showPage('dashboard');
  }
}

// ═══════════════════════════════════════════════════════════════
//  EXTEND bootApp
// ═══════════════════════════════════════════════════════════════
var _origBootApp = bootApp;
bootApp = function () {
  _origBootApp();
  updateSidebarUser();
  initTipOfDay();
  checkWelcomeBanner();
  setActiveNav('dashboard');
  // Restore a11y
  if (localStorage.getItem('cs_a11y') === '1') setAccessibility(true);
  // Restore lang
  var savedLang = localStorage.getItem('cs_lang') || 'en';
  var langLabel = document.getElementById('lang-label');
  if (langLabel) langLabel.textContent = savedLang === 'en' ? 'EN' : 'FIL';
};

// Keep sidebar user in sync after profile save
var _origSaveProfile = saveProfile;
saveProfile = function () {
  _origSaveProfile();
  updateSidebarUser();
};

// Keyboard shortcuts: Alt+D/A/R/L/P/T
document.addEventListener('keydown', function (e) {
  if (!e.altKey) return;
  var map = { d:'dashboard', r:'results', l:'leaderboard', p:'profile', t:'tips' };
  if (map[e.key.toLowerCase()]) {
    e.preventDefault();
    showPage(map[e.key.toLowerCase()]);
  }
  if (e.key.toLowerCase() === 'a') { e.preventDefault(); startAssessment(); }
});