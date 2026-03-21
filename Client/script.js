// ═══════════════════════════════════════════════════════════════
//  DATA
// ═══════════════════════════════════════════════════════════════
const ALL_QUESTIONS = [
  { id:'p1', cat:'Password Security', catKey:'password', icon:'🔑', q:'How do you typically manage your passwords for work accounts?', opts:[{text:'Use a password manager with unique passwords',score:3},{text:'Write them down in a secure notebook',score:1},{text:'Reuse the same strong password across sites',score:1},{text:'Use simple, memorable passwords',score:0}] },
  { id:'p2', cat:'Password Security', catKey:'password', icon:'🔑', q:'How often do you update your work account passwords?', opts:[{text:'Every 90 days or when prompted by policy',score:3},{text:'Once a year',score:1},{text:'Only when forced or after a breach',score:1},{text:'Never — I keep the same password',score:0}] },
  { id:'p3', cat:'Password Security', catKey:'password', icon:'🔑', q:'Which of the following qualifies as a strong password?', opts:[{text:'T#9mK@2vL!qR (random mixed characters)',score:3},{text:'Company2024! (company + year)',score:1},{text:'P@ssword1 (common substitution)',score:0},{text:'12345678 (sequential digits)',score:0}] },
  { id:'ph1', cat:'Phishing Awareness', catKey:'phishing', icon:'🎣', q:'You receive an urgent email from "IT Support" asking you to click a link and reset your credentials immediately. What do you do?', opts:[{text:'Do not click; verify via official channel or call IT directly',score:3},{text:'Click but only enter a fake password first',score:1},{text:'Forward to colleagues to warn them',score:1},{text:'Click the link and follow instructions',score:0}] },
  { id:'ph2', cat:'Phishing Awareness', catKey:'phishing', icon:'🎣', q:'Which is a reliable indicator that an email may be a phishing attempt?', opts:[{text:'Mismatched sender domain (e.g. support@paypa1.com)',score:3},{text:'The email arrived on a Monday',score:0},{text:'It is written in formal English',score:0},{text:'It has a company logo attached',score:0}] },
  { id:'ph3', cat:'Phishing Awareness', catKey:'phishing', icon:'🎣', q:'Multi-Factor Authentication (MFA) primarily helps by:', opts:[{text:'Requiring a second verification even if a password is stolen',score:3},{text:'Making your password longer automatically',score:0},{text:'Blocking all phishing emails before they arrive',score:0},{text:'Encrypting your email attachments',score:0}] },
  { id:'d1', cat:'Device Safety', catKey:'device', icon:'💻', q:'Your laptop will be left unattended in a coffee shop for 10 minutes. What should you do?', opts:[{text:'Lock the screen (Win+L / Cmd+Ctrl+Q) and take it with you',score:3},{text:'Lock the screen and leave it at the table',score:2},{text:'Put the lid down and leave it',score:1},{text:"Leave it open — it's only 10 minutes",score:0}] },
  { id:'d2', cat:'Device Safety', catKey:'device', icon:'💻', q:'How frequently should operating system security patches be applied to work devices?', opts:[{text:'As soon as possible after release, within policy window',score:3},{text:'Once a quarter during scheduled downtime',score:1},{text:'Only when performance issues occur',score:0},{text:'Never — patches can break software',score:0}] },
  { id:'d3', cat:'Device Safety', catKey:'device', icon:'💻', q:'Which storage practice is safest for sensitive work files?', opts:[{text:'Company-approved encrypted cloud storage with access control',score:3},{text:'Personal USB drive kept in your desk drawer',score:1},{text:'Personal Google Drive or Dropbox (free tier)',score:1},{text:'Email attachments to yourself for easy access',score:0}] },
  { id:'n1', cat:'Network Safety', catKey:'network', icon:'📡', q:"You need to access your company's internal system while working at a café. What should you do?", opts:[{text:'Use the company-issued VPN before connecting to any work resources',score:3},{text:'Use the café Wi-Fi only for non-sensitive browsing',score:1},{text:'Use your phone hotspot for all work tasks',score:2},{text:'Connect directly — café Wi-Fi is usually fine',score:0}] },
  { id:'n2', cat:'Network Safety', catKey:'network', icon:'📡', q:'What does HTTPS in a browser URL primarily guarantee?', opts:[{text:'The connection between your browser and the server is encrypted',score:3},{text:'The website is 100% safe and verified legitimate',score:0},{text:'Your IP address is hidden from the server',score:0},{text:'No data is stored by the website',score:0}] },
  { id:'n3', cat:'Network Safety', catKey:'network', icon:'📡', q:'Someone connects an unknown USB drive found in the parking lot to their work laptop to "see whose it is." This action is:', opts:[{text:'Dangerous — USB drives can auto-execute malware',score:3},{text:'Fine if antivirus is active',score:1},{text:'Acceptable if you only view files, not run them',score:0},{text:'Helpful — you can return it to the owner',score:0}] },
];

const VIDEOS = {
  phishing:[{id:'XBkzBrXlle0',label:'Phishing Attacks Explained',sub:'How attackers craft convincing emails'},{id:'aO858HyFbKI',label:'Recognizing Phishing',sub:'Red flags to watch for'}],
  password:[{id:'aEmXfkwJ3pk',label:'Password Security Best Practices',sub:'Creating and managing strong passwords'}],
  device:[{id:'Dk-ZqQ-bfy4',label:'Endpoint Security Fundamentals',sub:'Protecting your devices from threats'}],
  network:[{id:'_GzE99AmAQU',label:'Public Wi-Fi Dangers',sub:'Risks of unprotected networks'},{id:'iYWT5oE8pAw',label:'VPN Explained',sub:'Why and how to use a VPN'}]
};

const RECOMMENDATIONS = {
  password:[{icon:'🔐',title:'Use a Password Manager',body:'Tools like Bitwarden or 1Password generate and store unique credentials for every account.'},{icon:'🔢',title:'Enable Two-Factor Authentication',body:'Add a second layer via authenticator app (TOTP) — not just SMS.'},{icon:'📏',title:'Minimum 16-Character Passphrases',body:'Combine 4+ random words for strong yet memorable passwords.'}],
  phishing:[{icon:'🎣',title:'Verify Before You Click',body:'Always hover over links to inspect the destination URL and confirm sender email domains.'},{icon:'📞',title:'Call to Confirm',body:'If an urgent request arrives by email, call the sender directly using a known official number.'},{icon:'🛡️',title:'Report Suspicious Emails',body:"Use your company's phishing report button or forward to your security team immediately."}],
  device:[{icon:'🔒',title:'Auto-Lock Your Screen',body:'Set your device to lock automatically after 2–5 minutes of inactivity.'},{icon:'💾',title:'Enable Full-Disk Encryption',body:'Use BitLocker (Windows) or FileVault (Mac) to protect data if your device is lost or stolen.'},{icon:'🔄',title:'Keep Software Updated',body:'Apply OS and application patches promptly — most exploits target known, unpatched vulnerabilities.'}],
  network:[{icon:'🌐',title:'Always Use VPN on Public Wi-Fi',body:"A VPN encrypts your traffic, preventing eavesdropping on coffee shop or hotel networks."},{icon:'📵',title:'Disable Auto-Connect to Wi-Fi',body:'Prevent your device from joining rogue hotspots that mimic trusted networks.'},{icon:'🔌',title:'Never Use Unknown USB Devices',body:"Malicious USBs can silently install malware the moment they're plugged in."}]
};

const RANK_CONFIG = {
  A:{color:'var(--green)', faint:'var(--green-faint)', label:'Low Risk',      desc:'Excellent security posture! Keep up the good practices.'},
  B:{color:'var(--yellow)',faint:'var(--yellow-faint)',label:'Moderate Risk', desc:'Good foundation — a few areas need attention.'},
  C:{color:'var(--orange)',faint:'var(--orange-faint)',label:'High Risk',     desc:'Several vulnerabilities identified. Action recommended.'},
  D:{color:'var(--red)',   faint:'var(--red-faint)',   label:'Critical Risk', desc:'Significant security gaps. Immediate training required.'}
};

// ═══════════════════════════════════════════════════════════════
//  BADGES SYSTEM
// ═══════════════════════════════════════════════════════════════
const ALL_BADGES = [
  {id:'first',    icon:'🎯', label:'First Step',     desc:'Complete your first assessment'},
  {id:'streak3',  icon:'🔥', label:'On Fire',        desc:'Complete 3 assessments'},
  {id:'streak5',  icon:'⚡', label:'Consistent',     desc:'Complete 5 assessments'},
  {id:'perfect',  icon:'💎', label:'Perfect Score',  desc:'Score 100% on any assessment'},
  {id:'rankA',    icon:'🛡️', label:'Low Risk',       desc:'Achieve Rank A'},
  {id:'improved', icon:'📈', label:'Improving',      desc:'Score higher than your previous attempt'},
  {id:'speedster',icon:'⏱️', label:'Speed Demon',    desc:'Finish under 3 minutes'},
  {id:'allcat',   icon:'🌐', label:'All-Rounder',    desc:'Score ≥70% in all 4 categories'},
];

function getEarnedBadges() { return JSON.parse(localStorage.getItem('cs_badges') || '[]'); }
function saveEarnedBadges(b){ localStorage.setItem('cs_badges', JSON.stringify(b)); }

function checkAndAwardBadges(record, elapsed) {
  const history = getHistory();
  const earned  = getEarnedBadges();
  const newBadges = [];

  function award(id) {
    if (!earned.includes(id)) { earned.push(id); newBadges.push(id); }
  }

  if (history.length >= 1) award('first');
  if (history.length >= 3) award('streak3');
  if (history.length >= 5) award('streak5');
  if (record.pct === 100)  award('perfect');
  if (record.rank === 'A') award('rankA');
  if (history.length >= 2 && record.pct > history[history.length - 2].pct) award('improved');
  if (elapsed && elapsed < 180) award('speedster');
  const cats = ['password','phishing','device','network'];
  if (cats.every(k => (record.catPct[k] || 0) >= 70)) award('allcat');

  saveEarnedBadges(earned);
  return newBadges;
}

function renderBadges(containerId, highlightNew) {
  const el = document.getElementById(containerId);
  if (!el) return;
  const earned = getEarnedBadges();
  el.innerHTML = ALL_BADGES.map(b => {
    const isNew = highlightNew && highlightNew.includes(b.id);
    const locked = !earned.includes(b.id);
    return '<div class="badge-chip' + (locked ? ' locked' : '') + (isNew ? ' badge-new' : '') + '">' +
      '<span class="badge-icon">' + b.icon + '</span>' +
      '<span class="badge-label">' + b.label + '</span>' +
      '<span class="badge-desc">' + b.desc + '</span>' +
    '</div>';
  }).join('');
}

// ═══════════════════════════════════════════════════════════════
//  NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════
function getNotifs()  { return JSON.parse(localStorage.getItem('cs_notifs') || '[]'); }
function saveNotifs(n){ localStorage.setItem('cs_notifs', JSON.stringify(n)); }

function addNotif(icon, title, body) {
  if (!getPref('notif')) return;
  const notifs = getNotifs();
  notifs.unshift({ icon, title, body, time: new Date().toISOString() });
  saveNotifs(notifs.slice(0, 20));
  renderNotifBadge();
}

function renderNotifBadge() {
  const n = getNotifs();
  const dot = document.getElementById('notif-dot');
  if (dot) dot.classList.toggle('hidden', n.length === 0);
}

function toggleNotifPanel() {
  const panel = document.getElementById('notif-panel');
  if (!panel) return;
  panel.classList.toggle('hidden');
  if (!panel.classList.contains('hidden')) renderNotifList();
  document.addEventListener('click', closeNotifOutside, { once: true });
}

function closeNotifOutside(e) {
  const panel = document.getElementById('notif-panel');
  const btn   = document.getElementById('notif-btn');
  if (panel && !panel.contains(e.target) && e.target !== btn) {
    panel.classList.add('hidden');
  }
}

function renderNotifList() {
  const notifs = getNotifs();
  const el = document.getElementById('notif-list');
  if (!el) return;
  if (!notifs.length) { el.innerHTML = '<p class="notif-empty">No notifications</p>'; return; }
  el.innerHTML = notifs.map(n =>
    '<div class="notif-item">' +
      '<span class="notif-item-icon">' + n.icon + '</span>' +
      '<div><div class="notif-item-title">' + n.title + '</div>' +
      '<div class="notif-item-body">' + n.body + '</div>' +
      '<div class="notif-item-time">' + formatDate(n.time) + '</div></div>' +
    '</div>'
  ).join('');
}

function clearNotifs() {
  saveNotifs([]);
  document.getElementById('notif-list').innerHTML = '<p class="notif-empty">No notifications</p>';
  renderNotifBadge();
}

// ═══════════════════════════════════════════════════════════════
//  THEME
// ═══════════════════════════════════════════════════════════════
function applyTheme(dark) {
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  const moonIcon = document.getElementById('theme-icon-moon');
  const sunIcon  = document.getElementById('theme-icon-sun');
  if (moonIcon) moonIcon.style.display = dark ? '' : 'none';
  if (sunIcon)  sunIcon.style.display  = dark ? 'none' : '';
  const themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) themeToggle.classList.toggle('active', !dark);
  const pref = document.getElementById('pref-dark');
  if (pref) pref.checked = dark;
  localStorage.setItem('cs_theme', dark ? 'dark' : 'light');
}

function toggleTheme() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  applyTheme(!isDark);
}

// ═══════════════════════════════════════════════════════════════
//  PREFERENCES
// ═══════════════════════════════════════════════════════════════
function getPref(key) {
  const prefs = JSON.parse(localStorage.getItem('cs_prefs') || '{}');
  return prefs[key] !== undefined ? prefs[key] : true;
}
function setPref(key, val) {
  const prefs = JSON.parse(localStorage.getItem('cs_prefs') || '{}');
  prefs[key] = val; localStorage.setItem('cs_prefs', JSON.stringify(prefs));
}

// ═══════════════════════════════════════════════════════════════
//  TIMER
// ═══════════════════════════════════════════════════════════════
const TIMER_SECONDS = 30;
let timerInterval = null;
let timerLeft     = TIMER_SECONDS;
let assessStart   = null;

function startTimer() {
  clearInterval(timerInterval);
  timerLeft = TIMER_SECONDS;
  updateTimerUI();
  if (!getPref('timer')) {
    document.getElementById('timer-wrap').style.display = 'none';
    document.getElementById('timer-bar-fill').style.width = '100%';
    return;
  }
  document.getElementById('timer-wrap').style.display = '';
  timerInterval = setInterval(function() {
    timerLeft--;
    updateTimerUI();
    if (timerLeft <= 0) {
      clearInterval(timerInterval);
      autoAdvance();
    }
  }, 1000);
}

function updateTimerUI() {
  const valEl = document.getElementById('timer-val');
  const barEl = document.getElementById('timer-bar-fill');
  if (!valEl || !barEl) return;
  valEl.textContent = timerLeft;
  valEl.className = 'timer-val' + (timerLeft <= 5 ? ' urgent' : timerLeft <= 10 ? ' warning' : '');
  const pct = (timerLeft / TIMER_SECONDS) * 100;
  barEl.style.width = pct + '%';
  barEl.className = 'timer-bar-fill' + (timerLeft <= 5 ? ' urgent' : timerLeft <= 10 ? ' warning' : '');
}

function stopTimer() { clearInterval(timerInterval); }

function autoAdvance() {
  // If unanswered, mark wrong (0) and advance
  if (answers[questions[currentQ].id] === undefined) {
    answers[questions[currentQ].id] = -1; // timed out
    document.querySelectorAll('.option-btn').forEach(function(btn, idx) {
      const maxScore = Math.max.apply(null, questions[currentQ].opts.map(function(o){ return o.score; }));
      if (questions[currentQ].opts[idx] && questions[currentQ].opts[idx].score === maxScore) btn.classList.add('correct');
      btn.style.cursor = 'default';
    });
    document.getElementById('q-nav').style.display = 'flex';
  }
  setTimeout(nextQuestion, 600);
}

// ═══════════════════════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════════════════════
let session    = {};
let questions  = [];
let currentQ   = 0;
let answers    = {};
let lastAnswers= {}; // for review mode
let trendChart = null, trendChart2 = null, radarChart = null;

// ═══════════════════════════════════════════════════════════════
//  UTILS
// ═══════════════════════════════════════════════════════════════
function shuffle(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}
function getHistory()   { return JSON.parse(localStorage.getItem('cs_history') || '[]'); }
function saveHistory(h) { localStorage.setItem('cs_history', JSON.stringify(h)); }
function getRank(pct)   { return pct >= 80 ? 'A' : pct >= 60 ? 'B' : pct >= 40 ? 'C' : 'D'; }
function formatDate(iso){ return new Date(iso).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}); }

function toast(msg) {
  const el = document.createElement('div');
  el.className = 'toast'; el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(function(){ el.remove(); }, 2800);
}

function doLogout() {
  if(confirm('Are you sure you want to sign out?')){
    localStorage.removeItem('cs_session');
    window.location.href = '../landingpage.php';
  }
}

// ═══════════════════════════════════════════════════════════════
//  AUTHENTICATION
// ═══════════════════════════════════════════════════════════════
function checkSession() {
  const urlParams = new URLSearchParams(window.location.search);
  const user = urlParams.get('user');
  const role = urlParams.get('role');
  
  if (user && role) {
    session = { 
      email: user + '@company.com', 
      name: user.charAt(0).toUpperCase() + user.slice(1), 
      company: '',
      role: role 
    };
    localStorage.setItem('cs_session', JSON.stringify(session));
    return true;
  }
  
  const stored = localStorage.getItem('cs_session');
  if (stored) {
    session = JSON.parse(stored);
    return true;
  }
  
  return false;
}

// ═══════════════════════════════════════════════════════════════
//  APP BOOT
// ═══════════════════════════════════════════════════════════════
function bootApp() {
  if (!checkSession()) {
    window.location.href = '../Landingpage/landingpage.html';
    return;
  }

  var appEl = document.getElementById('app');
  if (appEl) appEl.classList.remove('hidden');

  // Footer is optional in new layout
  var footerEl = document.getElementById('main-footer');
  if (footerEl) footerEl.classList.remove('hidden');
  var footerYearEl = document.getElementById('footer-year');
  if (footerYearEl) footerYearEl.textContent = new Date().getFullYear();

  var navAvatar = document.getElementById('nav-avatar');
  if (navAvatar) navAvatar.textContent = session.name.charAt(0).toUpperCase();
  var navName = document.getElementById('nav-name');
  if (navName) navName.textContent = session.name;

  var savedTheme = localStorage.getItem('cs_theme') || 'dark';
  applyTheme(savedTheme === 'dark');

  renderNotifBadge();
  showPage('dashboard');
}

// ═══════════════════════════════════════════════════════════════
//  NAVIGATION
// ═══════════════════════════════════════════════════════════════
function showPage(name) {
  ['dashboard','assessment','results','profile','leaderboard','tips','terms'].forEach(function(p){
    var pageEl = document.getElementById('page-' + p);
    if (pageEl) pageEl.classList.add('hidden');
  });
  var el = document.getElementById('page-' + name);
  if (!el) return; // guard against unknown page names
  el.classList.remove('hidden');
  el.classList.remove('fade-in');
  void el.offsetWidth;
  el.classList.add('fade-in');

  if (name === 'dashboard')   renderDashboard();
  if (name === 'results')     renderResults();
  if (name === 'profile')     renderProfile();
  if (name === 'leaderboard') renderLeaderboard();
}

// ═══════════════════════════════════════════════════════════════
//  DASHBOARD
// ═══════════════════════════════════════════════════════════════
function renderDashboard() {
  const history = getHistory();
  if (history.length > 0) {
    const latest = history[history.length - 1];
    const rank = latest.rank, cfg = RANK_CONFIG[rank];
    document.getElementById('stat-score').textContent = latest.pct + '%';
    document.getElementById('stat-rank').innerHTML = '<span class="rank-pill ' + rank + '">' + rank + '</span>';
    document.getElementById('stat-rank-text').textContent = cfg.label;
    document.getElementById('stat-rank-sub').textContent  = latest.score + ' / ' + latest.max + ' pts';
    if (history.length >= 2) {
      const diff = latest.pct - history[history.length - 2].pct;
      const tEl  = document.getElementById('stat-trend');
      tEl.textContent = diff >= 0 ? ('↑ +' + diff + '%') : ('↓ ' + diff + '%');
      tEl.style.color = diff >= 0 ? 'var(--green)' : 'var(--red)';
      document.getElementById('stat-trend-sub').textContent = diff >= 0 ? 'Improved from last' : 'Declined from last';
    } else {
      document.getElementById('stat-trend').textContent = '—';
      document.getElementById('stat-trend-sub').textContent = 'Need 2+ sessions';
    }
  } else {
    ['stat-score','stat-rank','stat-trend'].forEach(function(id){ document.getElementById(id).textContent='—'; });
    document.getElementById('stat-rank-text').textContent = 'No assessments yet';
    document.getElementById('stat-rank-sub').textContent  = '—';
    document.getElementById('stat-trend-sub').textContent = '—';
  }
  document.getElementById('stat-count').textContent = history.length;

  renderBadges('dash-badges', null);

  const container = document.getElementById('history-container');
  if (!history.length) {
    container.innerHTML = '<p style="color:var(--text-2);font-size:.88rem;padding:.5rem 0;">No assessments taken yet. Start one now!</p>';
  } else {
    const rows = [...history].reverse().slice(0,8).map(function(h){
      return '<tr><td>' + formatDate(h.date) + '</td>' +
        '<td class="mono" style="font-size:.88rem;">' + h.pct + '%</td>' +
        '<td><span class="rank-pill ' + h.rank + '">' + h.rank + ' — ' + RANK_CONFIG[h.rank].label + '</span></td>' +
        '<td style="color:var(--text-2);font-size:.8rem;">' + h.score + '/' + h.max + '</td></tr>';
    }).join('');
    container.innerHTML = '<table class="history-table"><thead><tr><th>Date</th><th>Score</th><th>Rank</th><th>Points</th></tr></thead><tbody>' + rows + '</tbody></table>';
  }
  renderTrendChart('trend-chart', trendChart, function(c){ trendChart = c; });
}

function renderTrendChart(canvasId, chartRef, setter) {
  const history = getHistory();
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  if (chartRef) chartRef.destroy();
  const labels = history.map(function(h){ return formatDate(h.date); });
  const data   = history.map(function(h){ return h.pct; });
  const chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels.length ? labels : ['No data'],
      datasets: [{ label:'Risk Score %', data: data.length ? data : [0],
        borderColor:'#7B72F0', backgroundColor:'rgba(91,79,232,.1)', fill:true, tension:0.45,
        pointBackgroundColor:'#7B72F0', pointBorderColor:'#0A0F1E', pointBorderWidth:2, pointRadius:5, pointHoverRadius:8 }]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      plugins: { legend:{display:false}, tooltip:{backgroundColor:'#0d1421',borderColor:'rgba(255,255,255,.1)',borderWidth:1,titleColor:'#dde4f0',bodyColor:'#8898b4',padding:10,callbacks:{label:function(ctx){return ' Score: '+ctx.parsed.y+'%';}}} },
      scales: {
        y:{ min:0, max:100, grid:{color:'rgba(59,139,255,.04)'}, ticks:{color:'#8898b4',font:{size:11},callback:function(v){return v+'%';}} },
        x:{ grid:{display:false}, ticks:{color:'#8898b4',font:{size:11}} }
      }
    }
  });
  setter(chart);
}

// ═══════════════════════════════════════════════════════════════
//  ASSESSMENT
// ═══════════════════════════════════════════════════════════════
function startAssessment() {
  const cats = ['password','phishing','device','network'];
  questions = [];
  cats.forEach(function(cat){
    const pool = ALL_QUESTIONS.filter(function(q){ return q.catKey===cat; });
    shuffle(pool).slice(0,3).forEach(function(q){ questions.push(Object.assign({},q,{opts:shuffle(q.opts)})); });
  });
  questions = shuffle(questions);
  currentQ = 0; answers = {}; lastAnswers = {};
  assessStart = Date.now();
  showPage('assessment');
  renderQuestion();
}

function renderQuestion() {
  const total = questions.length, idx = currentQ, q = questions[idx];
  const pct   = Math.round((idx / total) * 100);
  document.getElementById('progress-fill').style.width = pct + '%';
  document.getElementById('progress-label').textContent = 'Question ' + (idx+1) + ' of ' + total;
  const pctEl = document.getElementById('progress-pct');
  if (pctEl) pctEl.textContent = pct + '%';

  const letters = ['A','B','C','D'];
  const optHtml = q.opts.map(function(o,i){
    return '<button class="option-btn" id="opt-'+i+'" onclick="selectOption('+i+')">' +
      '<span class="option-letter">'+letters[i]+'</span><span>'+o.text+'</span></button>';
  }).join('');

  document.getElementById('q-card').innerHTML =
    '<div class="q-num">QUESTION '+(idx+1)+' / '+total+'</div>' +
    '<div class="q-category">'+q.icon+' '+q.cat+'</div>' +
    '<div class="q-text">'+q.q+'</div>' +
    '<div class="options-list">'+optHtml+'</div>' +
    '<div class="q-nav" id="q-nav" style="display:none;">' +
      '<button class="btn btn-primary btn-lg" onclick="nextQuestion()">' +
        (idx+1 < total ? 'Next Question →' : '🏁 View My Results →') +
      '</button></div>';

  startTimer();
}

function selectOption(i) {
  if (answers[questions[currentQ].id] !== undefined) return;
  stopTimer();
  answers[questions[currentQ].id] = i;
  const selected = questions[currentQ].opts[i].score;
  const maxScore = Math.max.apply(null, questions[currentQ].opts.map(function(o){ return o.score; }));
  document.querySelectorAll('.option-btn').forEach(function(btn, idx){
    if (idx===i) btn.classList.add(selected===maxScore ? 'correct' : 'wrong');
    else if (questions[currentQ].opts[idx] && questions[currentQ].opts[idx].score===maxScore && selected<maxScore) btn.classList.add('correct');
    btn.style.cursor = 'default';
  });
  document.getElementById('q-nav').style.display = 'flex';
}

function nextQuestion() {
  stopTimer();
  currentQ++;
  if (currentQ >= questions.length) { finishAssessment(); return; }
  const card = document.getElementById('q-card');
  card.style.transition = 'opacity .2s ease, transform .2s ease';
  card.style.opacity = '0'; card.style.transform = 'translateY(10px)';
  setTimeout(function(){ renderQuestion(); card.style.opacity='1'; card.style.transform='translateY(0)'; }, 200);
}

function finishAssessment() {
  stopTimer();
  const elapsed = Math.round((Date.now() - assessStart) / 1000);

  const catScores = {password:0,phishing:0,device:0,network:0};
  const catMax    = {password:0,phishing:0,device:0,network:0};
  questions.forEach(function(q){
    const ai = answers[q.id], maxS = Math.max.apply(null, q.opts.map(function(o){ return o.score; }));
    catMax[q.catKey] += maxS;
    if (ai !== undefined && ai >= 0) catScores[q.catKey] += q.opts[ai].score;
  });

  const totalScore = Object.values(catScores).reduce(function(a,b){ return a+b; },0);
  const totalMax   = Object.values(catMax).reduce(function(a,b){ return a+b; },0);
  const pct  = Math.round((totalScore / totalMax) * 100);
  const rank = getRank(pct);
  const catPct = {};
  Object.keys(catScores).forEach(function(k){ catPct[k] = catMax[k]>0 ? Math.round((catScores[k]/catMax[k])*100) : 0; });

  const record = {date:new Date().toISOString(),score:totalScore,max:totalMax,pct,rank,catPct,catScores,catMax,elapsed};
  const history = getHistory();
  history.push(record);
  saveHistory(history);

  // Save answers for review
  lastAnswers = JSON.parse(JSON.stringify(answers));

  // Check badges
  const newBadges = checkAndAwardBadges(record, elapsed);

  // Risk notifications
  if (rank === 'C' || rank === 'D') {
    addNotif('⚠️', 'High Risk Alert', 'Your latest score is ' + pct + '% (' + RANK_CONFIG[rank].label + '). Review your weak areas.');
  }
  if (newBadges.length) {
    const names = newBadges.map(function(id){ return ALL_BADGES.find(function(b){ return b.id===id; }).label; }).join(', ');
    addNotif('🏅', 'New Badge Earned!', 'You unlocked: ' + names);
    toast('🏅 New badge: ' + names);
  }

  showPage('results');
}

// ═══════════════════════════════════════════════════════════════
//  RESULTS
// ═══════════════════════════════════════════════════════════════
function renderResults() {
  const history = getHistory();
  if (!history.length) {
    document.getElementById('result-hero').innerHTML = '<p style="padding:2rem;color:var(--text-2);">No assessments yet. Start one from the dashboard.</p>';
    return;
  }
  const latest = history[history.length-1], rank = latest.rank, cfg = RANK_CONFIG[rank];

  document.getElementById('result-hero').innerHTML =
    '<div class="rank-big" style="background:'+cfg.faint+';color:'+cfg.color+';">'+rank+'</div>' +
    '<h2>'+cfg.label+'</h2>' +
    '<p class="score-text">Score: <span class="score-num">'+latest.pct+'%</span> &nbsp;·&nbsp; '+latest.score+'/'+latest.max+' points</p>' +
    '<p style="color:var(--text-2);font-size:.88rem;margin-top:.6rem;max-width:460px;margin-left:auto;margin-right:auto;line-height:1.6;">'+cfg.desc+'</p>' +
    '<p style="color:var(--text-3);font-size:.76rem;margin-top:1rem;letter-spacing:.04em;">ASSESSED ON '+formatDate(latest.date).toUpperCase()+'</p>';

  // Earned badges
  const earned = getEarnedBadges();
  const badgesCard = document.getElementById('badges-card');
  if (earned.length) {
    badgesCard.style.display = '';
    renderBadges('result-badges', null);
  } else {
    badgesCard.style.display = 'none';
  }

  // Radar
  const catLabels = ['Password Security','Phishing Awareness','Device Safety','Network Safety'];
  const catKeys   = ['password','phishing','device','network'];
  if (radarChart) radarChart.destroy();
  radarChart = new Chart(document.getElementById('radar-chart'), {
    type:'radar',
    data:{ labels:catLabels, datasets:[{ label:'Score (%)', data:catKeys.map(function(k){ return latest.catPct[k]||0; }),
      backgroundColor:'rgba(91,79,232,.15)', borderColor:'#7B72F0',
      pointBackgroundColor:'#7B72F0', pointBorderColor:'#0d1421', pointBorderWidth:2, pointRadius:5, pointHoverRadius:7, borderWidth:2 }] },
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#0d1421',borderColor:'rgba(255,255,255,.1)',borderWidth:1,titleColor:'#dde4f0',bodyColor:'#8898b4',padding:10} },
      scales:{ r:{ min:0, max:100, backgroundColor:'rgba(10,15,30,.5)',
        ticks:{stepSize:25,color:'#4d5d7a',font:{size:10},backdropColor:'transparent'},
        pointLabels:{color:'#8898b4',font:{size:11,weight:'600'}},
        grid:{color:'rgba(59,139,255,.05)'}, angleLines:{color:'rgba(59,139,255,.04)'} } } }
  });

  // Recs
  const sorted = catKeys.map(function(k){ return {k,pct:latest.catPct[k]||0}; }).sort(function(a,b){ return a.pct-b.pct; });
  const recoHtml = [];
  sorted.slice(0,2).forEach(function(s){
    RECOMMENDATIONS[s.k].forEach(function(r){
      recoHtml.push('<li class="reco-item"><span class="reco-icon">'+r.icon+'</span><div><strong>'+r.title+'</strong><p>'+r.body+'</p></div></li>');
    });
  });
  document.getElementById('reco-list').innerHTML = recoHtml.slice(0,5).join('');

  // Trend
  renderTrendChart('trend-chart-2', trendChart2, function(c){ trendChart2=c; });

  // Videos
  const weakCats = sorted.slice(0,2).map(function(s){ return s.k; });
  document.getElementById('video-grid').innerHTML = weakCats.flatMap(function(cat){
    return (VIDEOS[cat]||[]).map(function(v){
      return '<div class="video-thumb"><iframe src="https://www.youtube.com/embed/'+v.id+'" allowfullscreen loading="lazy"></iframe>' +
        '<div class="video-thumb-label">'+v.label+'</div><div class="video-thumb-sub">'+v.sub+'</div></div>';
    });
  }).join('');

  // Build review data
  buildReviewData(sorted);
}

// ═══════════════════════════════════════════════════════════════
//  REVIEW MODE
// ═══════════════════════════════════════════════════════════════
function buildReviewData(sorted) {
  const history = getHistory();
  if (!history.length || !questions.length) return;
  const letters = ['A','B','C','D'];
  const html = questions.map(function(q, qi){
    const ai      = lastAnswers[q.id];
    const timedOut = ai === -1 || ai === undefined;
    const maxScore = Math.max.apply(null, q.opts.map(function(o){ return o.score; }));
    const userScore= (!timedOut && ai >= 0) ? q.opts[ai].score : 0;
    const isCorrect= userScore === maxScore;

    let inner = '<div class="review-cat">'+q.icon+' '+q.cat+'</div>' +
      '<div class="review-q">'+(qi+1)+'. '+q.q+'</div>';

    if (timedOut) {
      inner += '<div class="review-ans" style="color:var(--orange);">⏱ Timed out — no answer selected</div>';
    } else {
      inner += '<div class="review-ans ' + (isCorrect ? 'user-correct' : 'user-wrong') + '">' +
        (isCorrect ? '✓' : '✗') + ' Your answer: ' + letters[ai] + '. ' + q.opts[ai].text + '</div>';
    }
    if (!isCorrect) {
      const correctIdx = q.opts.findIndex(function(o){ return o.score===maxScore; });
      inner += '<div class="review-ans show-correct">💡 Best answer: '+letters[correctIdx]+'. '+q.opts[correctIdx].text+'</div>';
    }
    return '<div class="review-item '+(isCorrect?'correct':'wrong')+'">'+inner+'</div>';
  }).join('');

  document.getElementById('review-container').innerHTML = html;
}

function toggleReview() {
  const container = document.getElementById('review-container');
  const btn = document.getElementById('review-toggle-btn');
  if (!container) return;
  const hidden = container.classList.contains('hidden');
  container.classList.toggle('hidden', !hidden);
  if (btn) btn.textContent = hidden ? 'Hide Review' : 'Show Review';
}

// ═══════════════════════════════════════════════════════════════
//  LEADERBOARD
// ═══════════════════════════════════════════════════════════════
const MOCK_VENDORS = [
  'Acme Logistics','BrightEdge Solutions','ClearPath Systems','Delta Dynamics',
  'Echo Technologies','Frontier Supplies','GlobalNet Partners','HorizonTech',
  'Integrated Dynamics','Javelin Corp','Keystone Ventures','Luminary Systems'
];

function generateLeaderboardData() {
  const stored = localStorage.getItem('cs_leaderboard');
  if (stored) return JSON.parse(stored);
  const data = MOCK_VENDORS.map(function(name, i){
    const pct = Math.floor(Math.random()*65)+30;
    return { name, pct, rank: getRank(pct), isMe: false };
  });
  data.sort(function(a,b){ return b.pct - a.pct; });
  localStorage.setItem('cs_leaderboard', JSON.stringify(data));
  return data;
}

function renderLeaderboard(filter) {
  filter = filter || 'all';
  const history  = getHistory();
  const myLatest = history.length ? history[history.length-1] : null;
  let data = generateLeaderboardData();

  // Inject self
  const myEntry = { name: session.name + ' (You)', pct: myLatest ? myLatest.pct : 0, rank: myLatest ? myLatest.rank : 'D', isMe: true };
  const selfIdx = data.findIndex(function(d){ return d.isMe; });
  if (selfIdx >= 0) data.splice(selfIdx, 1);
  data.push(myEntry);
  data.sort(function(a,b){ return b.pct - a.pct; });

  // Filter
  if (filter !== 'all') {
    data = data.filter(function(d){
      if (filter === 'CD') return d.rank === 'C' || d.rank === 'D';
      return d.rank === filter;
    });
  }

  const medals = ['🥇','🥈','🥉'];
  const html = data.map(function(d, i){
    return '<div class="lb-row' + (d.isMe ? ' me' : '') + '">' +
      '<div class="lb-rank-num">' + (i+1) + '</div>' +
      '<div class="lb-medal">' + (i < 3 ? medals[i] : '') + '</div>' +
      '<div class="lb-name">' + d.name + '</div>' +
      '<span class="rank-pill ' + d.rank + '" style="margin-right:.5rem;">' + d.rank + '</span>' +
      '<div class="lb-score">' + d.pct + '%</div>' +
      (d.isMe ? '<span class="lb-you">YOU</span>' : '') +
    '</div>';
  }).join('');

  document.getElementById('leaderboard-list').innerHTML = html || '<p style="color:var(--text-2);padding:1rem 0;font-size:.88rem;">No vendors found for this filter.</p>';
}

function filterLeaderboard(filter, btn) {
  document.querySelectorAll('.lb-filter-btn').forEach(function(b){ b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  renderLeaderboard(filter);
}

// ═══════════════════════════════════════════════════════════════
//  PROFILE
// ═══════════════════════════════════════════════════════════════
function renderProfile() {
  const p = JSON.parse(localStorage.getItem('cs_profile') || '{}');
  const name    = p.name    || session.name;
  const email   = session.email;
  const company = p.company || '';

  document.getElementById('profile-name').value   = name;
  document.getElementById('profile-email').value  = email;
  document.getElementById('profile-company').value= company;
  document.getElementById('profile-name-display').textContent  = name;
  document.getElementById('profile-email-display').textContent = email;
  document.getElementById('profile-avatar-big').textContent    = name.charAt(0).toUpperCase();

  // Prefs
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const prefDark  = document.getElementById('pref-dark');
  const prefTimer = document.getElementById('pref-timer');
  const prefNotif = document.getElementById('pref-notif');
  if (prefDark)  prefDark.checked  = isDark;
  if (prefTimer) prefTimer.checked = getPref('timer');
  if (prefNotif) prefNotif.checked = getPref('notif');

  // Sync toggle changes
  if (prefTimer) prefTimer.onchange = function(){ setPref('timer', this.checked); };
  if (prefNotif) prefNotif.onchange = function(){ setPref('notif', this.checked); };

  // Stats
  const history = getHistory();
  const latestPct = history.length ? history[history.length-1].pct : 0;
  const best = history.length ? Math.max.apply(null, history.map(function(h){ return h.pct; })) : 0;
  const avg  = history.length ? Math.round(history.reduce(function(s,h){ return s+h.pct; },0)/history.length) : 0;
  const grid = document.getElementById('profile-stats-grid');
  grid.innerHTML = [
    {label:'Assessments',val:history.length},
    {label:'Best Score',val:best+'%'},
    {label:'Avg Score',val:avg+'%'},
    {label:'Badges',val:getEarnedBadges().length+'/'+ALL_BADGES.length}
  ].map(function(s){
    return '<div class="profile-stat-item"><div class="label">'+s.label+'</div><div class="val mono">'+s.val+'</div></div>';
  }).join('');

  renderBadges('profile-badges', null);
}

function saveProfile() {
  const name    = document.getElementById('profile-name').value.trim() || session.name;
  const company = document.getElementById('profile-company').value.trim();
  localStorage.setItem('cs_profile', JSON.stringify({name,company}));
  session.name = name;
  localStorage.setItem('cs_session', JSON.stringify(session));
  document.getElementById('nav-name').textContent = name;
  document.getElementById('nav-avatar').textContent = name.charAt(0).toUpperCase();
  document.getElementById('dash-greeting').textContent = name.split(' ')[0];
  document.getElementById('profile-name-display').textContent = name;
  document.getElementById('profile-avatar-big').textContent   = name.charAt(0).toUpperCase();
  toast('✅ Profile saved!');
}

// ═══════════════════════════════════════════════════════════════
//  PRINT
// ═══════════════════════════════════════════════════════════════
function printResult() {
  const history = getHistory();
  if (!history.length) { toast('No results to print.'); return; }
  const latest = history[history.length-1], rank = latest.rank, cfg = RANK_CONFIG[rank];
  const catLabels = {password:'Password Security',phishing:'Phishing Awareness',device:'Device Safety',network:'Network Safety'};
  const cats = Object.keys(catLabels);

  const catBars = cats.map(function(k){
    const pct = latest.catPct[k]||0;
    return '<div class="print-cat-bar">' +
      '<div class="print-cat-label">'+catLabels[k]+'</div>' +
      '<div class="print-bar-bg"><div class="print-bar-fill" style="width:'+pct+'%"></div></div>' +
      '<div class="print-pct">'+pct+'%</div></div>';
  }).join('');

  const recoItems = [];
  const sorted = cats.map(function(k){ return {k,pct:latest.catPct[k]||0}; }).sort(function(a,b){ return a.pct-b.pct; });
  sorted.slice(0,2).forEach(function(s){ RECOMMENDATIONS[s.k].slice(0,2).forEach(function(r){ recoItems.push(r); }); });

  document.getElementById('print-area').innerHTML =
    '<div class="print-page">' +
      '<div class="print-header"><span style="font-size:2rem;">🛡️</span><div><h1>CyberShield</h1><p style="color:#475569;font-size:.85rem;">Cyber Hygiene Assessment Report</p></div>' +
        '<div style="margin-left:auto;text-align:right;font-size:.8rem;color:#64748B;"><div>'+session.name+'</div><div>'+formatDate(latest.date)+'</div></div></div>' +
      '<div class="print-score-block">' +
        '<div class="print-rank" style="color:'+cfg.color+'">'+rank+'</div>' +
        '<div><div style="font-size:1.2rem;font-weight:800;">'+cfg.label+'</div>' +
        '<div style="font-size:.9rem;color:#475569;margin-top:.25rem;">Score: '+latest.pct+'% &nbsp;·&nbsp; '+latest.score+'/'+latest.max+' points</div></div></div>' +
      '<h3 style="margin-bottom:.75rem;font-size:1rem;">Category Breakdown</h3>' + catBars +
      '<h3 style="margin:1.25rem 0 .75rem;font-size:1rem;">Top Recommendations</h3>' +
      recoItems.slice(0,4).map(function(r){ return '<div class="print-reco"><strong>'+r.icon+' '+r.title+'</strong><div style="font-size:.82rem;color:#475569;margin-top:.2rem;">'+r.body+'</div></div>'; }).join('') +
      '<div style="margin-top:2rem;font-size:.75rem;color:#94A3B8;border-top:1px solid #E2E8F0;padding-top:.75rem;">Generated by CyberShield Assessment Platform</div>' +
    '</div>';

  window.print();
}

// ═══════════════════════════════════════════════════════════════
//  EXPORTS
// ═══════════════════════════════════════════════════════════════
function exportCSV() {
  const history = getHistory();
  if (!history.length) { toast('No data to export.'); return; }
  const headers = ['Date','Score (%)','Points','Max Points','Rank','Password %','Phishing %','Device %','Network %'];
  const rows = history.map(function(h){
    return [formatDate(h.date),h.pct,h.score,h.max,h.rank,
      (h.catPct&&h.catPct.password)||0,(h.catPct&&h.catPct.phishing)||0,
      (h.catPct&&h.catPct.device)||0,(h.catPct&&h.catPct.network)||0].join(',');
  });
  const csv = [headers.join(',')].concat(rows).join('\n');
  const blob = new Blob([csv],{type:'text/csv'});
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href=url; a.download='cybershield_assessment_history.csv'; a.click();
  URL.revokeObjectURL(url); toast('📥 CSV exported!');
}

function exportPDF() {
  const history = getHistory();
  if (!history.length) { toast('No data to export.'); return; }
  const latest = history[history.length-1], rank = latest.rank, cfg = RANK_CONFIG[rank];
  const doc = new window.jspdf.jsPDF();
  doc.setFillColor(10,15,30); doc.rect(0,0,210,40,'F');
  doc.setFillColor(91,79,232); doc.rect(0,38,210,2,'F');
  doc.setTextColor(240,244,255); doc.setFontSize(22); doc.setFont('helvetica','bold');
  doc.text('CyberShield',14,20);
  doc.setFontSize(9); doc.setFont('helvetica','normal'); doc.setTextColor(136,152,187);
  doc.text('CYBER HYGIENE ASSESSMENT REPORT',14,30);
  doc.text(new Date().toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}),145,30);
  doc.setTextColor(50,60,80); doc.setFontSize(10);
  doc.text('Vendor: '+session.name,14,54);
  doc.text('Assessed: '+formatDate(latest.date),14,62);
  doc.text('Platform: CyberShield Assessment System',14,70);
  doc.setFillColor(20,31,56); doc.roundedRect(14,78,182,42,5,5,'F');
  doc.setFillColor(91,79,232); doc.roundedRect(14,78,4,42,2,0,'F');
  doc.setFontSize(32); doc.setFont('helvetica','bold'); doc.setTextColor(123,114,240);
  doc.text(rank,28,106);
  doc.setFontSize(15); doc.setTextColor(240,244,255); doc.text(cfg.label,55,96);
  doc.setFontSize(11); doc.setFont('helvetica','normal'); doc.setTextColor(136,152,187);
  doc.text('Score: '+latest.pct+'%  ·  '+latest.score+' / '+latest.max+' points',55,108);
  doc.setFontSize(12); doc.setFont('helvetica','bold'); doc.setTextColor(30,40,60);
  doc.text('Category Breakdown',14,136);
  var cats = [{label:'Password Security',key:'password'},{label:'Phishing Awareness',key:'phishing'},{label:'Device Safety',key:'device'},{label:'Network Safety',key:'network'}];
  cats.forEach(function(c,i){
    var y=148+i*16, pct=(latest.catPct&&latest.catPct[c.key])||0;
    doc.setFontSize(9); doc.setFont('helvetica','normal'); doc.setTextColor(60,70,90);
    doc.text(c.label,14,y);
    doc.setFillColor(20,31,56); doc.roundedRect(80,y-5,92,7,2,2,'F');
    doc.setFillColor(91,79,232); doc.roundedRect(80,y-5,Math.max(2,92*(pct/100)),7,2,2,'F');
    doc.setTextColor(50,60,80); doc.text(pct+'%',178,y);
  });
  // Badges in PDF
  const earned = getEarnedBadges();
  if (earned.length) {
    doc.setFontSize(12); doc.setFont('helvetica','bold'); doc.setTextColor(30,40,60);
    doc.text('Badges Earned ('+earned.length+')',14,218);
    doc.setFontSize(9); doc.setFont('helvetica','normal'); doc.setTextColor(80,90,110);
    doc.text(earned.map(function(id){ var b=ALL_BADGES.find(function(x){ return x.id===id; }); return b?b.label:''; }).join('  ·  '),14,227);
  }
  doc.setFontSize(12); doc.setFont('helvetica','bold'); doc.setTextColor(30,40,60);
  doc.text('Top Recommendations',14,240);
  var sortedCats=cats.slice().sort(function(a,b){ return ((latest.catPct&&latest.catPct[a.key])||0)-((latest.catPct&&latest.catPct[b.key])||0); });
  var allRecos=[];
  sortedCats.slice(0,2).forEach(function(c){ RECOMMENDATIONS[c.key].slice(0,2).forEach(function(r){ allRecos.push(r); }); });
  allRecos.slice(0,3).forEach(function(r,i){
    var y=250+i*14;
    doc.setFontSize(9); doc.setFont('helvetica','bold'); doc.setTextColor(50,60,80);
    doc.text(r.icon+' '+r.title,14,y);
    doc.setFont('helvetica','normal'); doc.setTextColor(100,110,130); doc.setFontSize(8);
    doc.text(doc.splitTextToSize(r.body,180)[0],14,y+5);
  });
  doc.setFillColor(10,15,30); doc.rect(0,283,210,14,'F');
  doc.setFontSize(8); doc.setTextColor(74,90,122);
  doc.text('Generated by CyberShield Assessment Platform — Confidential',14,291);
  doc.text('Page 1 of 1',185,291);
  doc.save('CyberShield_Report.pdf'); toast('📄 PDF exported!');
}

// ═══════════════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════════════
window.addEventListener('load', function() {
  if (checkSession()) bootApp();
  if (!localStorage.getItem('cs_history')) {
    var seed=[], now=Date.now(), demoScores=[52,61,58,70,75,82];
    demoScores.forEach(function(pct,i){
      seed.push({
        date:new Date(now-(demoScores.length-1-i)*7*86400000).toISOString(),
        score:Math.round(pct*0.36), max:36, pct, rank:getRank(pct), elapsed:180+Math.floor(Math.random()*120),
        catPct:{
          password:Math.max(0,Math.min(100,pct-5+Math.floor(Math.random()*12))),
          phishing:Math.max(0,Math.min(100,pct-8+Math.floor(Math.random()*14))),
          device:  Math.max(0,Math.min(100,pct+2+Math.floor(Math.random()*10))),
          network: Math.max(0,Math.min(100,pct-3+Math.floor(Math.random()*10)))
        }
      });
    });
    saveHistory(seed);
    // Seed some badges too
    saveEarnedBadges(['first','streak3']);
  }
});

// ═══════════════════════════════════════════════════════════════
//  MULTI-LANGUAGE SUPPORT (EN / FIL)
// ═══════════════════════════════════════════════════════════════
const TRANSLATIONS = {
  en: {
    // Nav
    sign_out: 'Sign out',
    // Login
    welcome_back: 'Welcome back',
    login_sub: 'Sign in to access your cybersecurity assessment portal and track your risk profile.',
    email_label: 'Email address',
    pass_label: 'Password',
    login_btn: 'Sign in to Dashboard →',
    login_hint: 'Demo: demo@company.com / password123',
    // Dashboard
    good_day: 'Good day',
    dash_sub: "Here's your cybersecurity hygiene overview for today.",
    leaderboard_btn: '🏆 Leaderboard',
    view_results_btn: '📊 View Results',
    start_btn: '🚀 Start Assessment',
    latest_score: 'Latest Score',
    risk_rank: 'Risk Rank',
    assessments_done: 'Assessments Done',
    total_sessions: 'Total sessions',
    trend: 'Trend',
    your_badges: '🏅 Your Badges',
    new_assessment: 'New Assessment',
    new_assessment_sub: 'Take a fresh 12-question cybersecurity quiz',
    view_detailed: 'View Detailed Results',
    view_detailed_sub: 'Charts, scores & AI recommendations',
    risk_trend: '📈 Risk Score Trend',
    assess_history: 'Assessment History',
    no_history: 'No assessments taken yet. Start one now!',
    // Assessment
    assess_title: 'Cyber Hygiene Assessment',
    // Results
    export_csv: '⬇ Export CSV',
    export_pdf: '⬇ Export PDF',
    print_btn: '🖨 Print',
    back_dash: '← Dashboard',
    cat_breakdown: '📊 Category Breakdown',
    ai_reco: '💡 AI Recommendations',
    review_title: '🔍 Review Your Answers',
    show_review: 'Show Review',
    hide_review: 'Hide Review',
    progress_time: '📈 Your Progress Over Time',
    edu_resources: '🎥 Educational Resources',
    edu_sub: 'Curated videos tailored to your weakest areas.',
    // Profile
    profile_title: '👤 Profile & Settings',
    profile_sub: 'Manage your account and preferences.',
    account_info: 'Account Information',
    display_name: 'Display Name',
    company_label: 'Company / Organization',
    save_changes: 'Save Changes',
    my_stats: 'My Stats',
    my_badges: 'My Badges',
    preferences: 'Preferences',
    dark_mode: 'Dark Mode',
    dark_mode_sub: 'Toggle light/dark theme',
    timer_pref: 'Question Timer',
    timer_pref_sub: 'Enable 30s countdown per question',
    pref_notif: 'Risk Notifications',
    pref_notif_sub: 'Alert when score is high/critical',
    pref_a11y: 'Accessibility Mode',
    pref_a11y_sub: 'Larger text & high contrast',
    pref_lang: 'Language',
    pref_lang_sub: 'Choose display language',
    legal_title: 'Legal',
    legal_terms: 'Terms & Privacy Policy',
    legal_terms_sub: 'View our terms of service',
    legal_view_btn: 'View →',
    legal_clear: 'Clear All Data',
    legal_clear_sub: 'Remove all stored assessment data',
    legal_clear_btn: '🗑 Clear',
    // Forgot Password
    fp_title: 'Reset Password',
    fp_sub: "Enter your registered email and we'll send you a reset link.",
    fp_email_label: 'Email Address',
    fp_send_btn: 'Send Reset Link →',
    fp_back: '← Back to Sign In',
    fp_sent_title: 'Check Your Email',
    fp_sent_sub: 'A reset link has been sent to',
    fp_demo_note: 'Demo Reset Code:',
    fp_code_label: 'Enter Reset Code',
    fp_verify_btn: 'Verify Code →',
    fp_new_pass_title: 'Set New Password',
    fp_new_pass_sub: 'Choose a strong new password for your account.',
    fp_new_pass_label: 'New Password',
    fp_confirm_label: 'Confirm Password',
    fp_reset_btn: 'Reset Password →',
    fp_done_title: 'Password Reset!',
    fp_done_sub: 'Your password has been updated. You can now sign in.',
    fp_signin_btn: '← Back to Sign In',
    // Terms
    terms_title: '📄 Terms & Privacy Policy',
    terms_sub: 'Last updated: March 2025',
    footer_terms: 'Terms & Privacy',
  },
  fil: {
    sign_out: 'Mag-sign out',
    welcome_back: 'Maligayang pagbabalik',
    login_sub: 'Mag-sign in para ma-access ang iyong portal ng pagtatasa sa cybersecurity.',
    email_label: 'Email address',
    pass_label: 'Password',
    login_btn: 'Mag-sign in sa Dashboard →',
    login_hint: 'Demo: demo@company.com / password123',
    good_day: 'Magandang araw',
    dash_sub: 'Narito ang iyong pangkalahatang-ideya ng cybersecurity hygiene ngayon.',
    leaderboard_btn: '🏆 Leaderboard',
    view_results_btn: '📊 Tingnan ang Mga Resulta',
    start_btn: '🚀 Magsimula ng Pagtatasa',
    latest_score: 'Pinakabagong Puntos',
    risk_rank: 'Ranggo ng Panganib',
    assessments_done: 'Mga Pagtatasa',
    total_sessions: 'Kabuuang sesyon',
    trend: 'Trend',
    your_badges: '🏅 Iyong mga Badge',
    new_assessment: 'Bagong Pagtatasa',
    new_assessment_sub: 'Kumuha ng sariwang 12-tanong na pagsubok sa cybersecurity',
    view_detailed: 'Tingnan ang Detalyadong Resulta',
    view_detailed_sub: 'Mga chart, puntos at rekomendasyon ng AI',
    risk_trend: '📈 Trend ng Puntos ng Panganib',
    assess_history: 'Kasaysayan ng Pagtatasa',
    no_history: 'Walang pagtatasa pa. Magsimula na ngayon!',
    assess_title: 'Pagtatasa ng Cyber Hygiene',
    export_csv: '⬇ I-export ang CSV',
    export_pdf: '⬇ I-export ang PDF',
    print_btn: '🖨 I-print',
    back_dash: '← Dashboard',
    cat_breakdown: '📊 Breakdown ng Kategorya',
    ai_reco: '💡 Mga Rekomendasyon ng AI',
    review_title: '🔍 Suriin ang Iyong mga Sagot',
    show_review: 'Ipakita ang Review',
    hide_review: 'Itago ang Review',
    progress_time: '📈 Ang Iyong Pag-unlad sa Paglipas ng Panahon',
    edu_resources: '🎥 Mga Mapagkukunan ng Edukasyon',
    edu_sub: 'Mga video na iniayon sa iyong mga mahihinang lugar.',
    profile_title: '👤 Profile at Mga Setting',
    profile_sub: 'Pamahalaan ang iyong account at mga kagustuhan.',
    account_info: 'Impormasyon ng Account',
    display_name: 'Pangalan sa Display',
    company_label: 'Kumpanya / Organisasyon',
    save_changes: 'I-save ang mga Pagbabago',
    my_stats: 'Aking mga Istatistika',
    my_badges: 'Aking mga Badge',
    preferences: 'Mga Kagustuhan',
    dark_mode: 'Dark Mode',
    dark_mode_sub: 'I-toggle ang maliwanag/madilim na tema',
    timer_pref: 'Timer ng Tanong',
    timer_pref_sub: 'I-enable ang 30s countdown bawat tanong',
    pref_notif: 'Mga Abiso sa Panganib',
    pref_notif_sub: 'Alertuhan kapag mataas/kritikal ang puntos',
    pref_a11y: 'Accessibility Mode',
    pref_a11y_sub: 'Mas malaking teksto at mataas na contrast',
    pref_lang: 'Wika',
    pref_lang_sub: 'Pumili ng display na wika',
    legal_title: 'Legal',
    legal_terms: 'Mga Tuntunin at Patakaran sa Privacy',
    legal_terms_sub: 'Tingnan ang aming mga tuntunin ng serbisyo',
    legal_view_btn: 'Tingnan →',
    legal_clear: 'Burahin ang Lahat ng Data',
    legal_clear_sub: 'Alisin ang lahat ng nakaimbak na data',
    legal_clear_btn: '🗑 Burahin',
    fp_title: 'I-reset ang Password',
    fp_sub: 'Ilagay ang iyong email para makatanggap ng reset link.',
    fp_email_label: 'Email Address',
    fp_send_btn: 'Magpadala ng Reset Link →',
    fp_back: '← Bumalik sa Pag-sign In',
    fp_sent_title: 'Suriin ang Iyong Email',
    fp_sent_sub: 'Isang reset link ang naipadala sa',
    fp_demo_note: 'Demo Reset Code:',
    fp_code_label: 'Ilagay ang Reset Code',
    fp_verify_btn: 'I-verify ang Code →',
    fp_new_pass_title: 'Magtakda ng Bagong Password',
    fp_new_pass_sub: 'Pumili ng matibay na bagong password para sa iyong account.',
    fp_new_pass_label: 'Bagong Password',
    fp_confirm_label: 'Kumpirmahin ang Password',
    fp_reset_btn: 'I-reset ang Password →',
    fp_done_title: 'Na-reset ang Password!',
    fp_done_sub: 'Na-update na ang iyong password. Maaari ka nang mag-sign in.',
    fp_signin_btn: '← Bumalik sa Pag-sign In',
    terms_title: '📄 Mga Tuntunin at Patakaran sa Privacy',
    terms_sub: 'Huling na-update: Marso 2025',
    footer_terms: 'Mga Tuntunin at Privacy',
  }
};

let currentLang = 'en';

function setLanguage(lang) {
  currentLang = lang;
  localStorage.setItem('cs_lang', lang);
  applyTranslations();
  const sel = document.getElementById('pref-lang-select');
  if (sel) sel.value = lang;
  const langLabel = document.getElementById('lang-label');
  if (langLabel) langLabel.textContent = lang === 'en' ? 'EN' : 'FIL';
}

function cycleLang() {
  setLanguage(currentLang === 'en' ? 'fil' : 'en');
}

function applyTranslations() {
  const T = TRANSLATIONS[currentLang] || TRANSLATIONS.en;
  document.querySelectorAll('[data-i18n]').forEach(function(el) {
    const key = el.getAttribute('data-i18n');
    if (T[key] !== undefined) el.textContent = T[key];
  });
  // Update html lang
  document.documentElement.lang = currentLang === 'fil' ? 'tl' : 'en';
}

// ═══════════════════════════════════════════════════════════════
//  ACCESSIBILITY MODE
// ═══════════════════════════════════════════════════════════════
function setAccessibility(on) {
  document.documentElement.setAttribute('data-a11y', on ? 'true' : 'false');
  localStorage.setItem('cs_a11y', on ? '1' : '0');
  const btn = document.getElementById('a11y-btn');
  if (btn) btn.classList.toggle('active', on);
  const pref = document.getElementById('pref-a11y');
  if (pref) pref.checked = on;
  toast(on ? '♿ Accessibility mode on' : '♿ Accessibility mode off');
}

function toggleAccessibility() {
  const current = document.documentElement.getAttribute('data-a11y') === 'true';
  setAccessibility(!current);
}

// ═══════════════════════════════════════════════════════════════
//  FORGOT PASSWORD FLOW
// ═══════════════════════════════════════════════════════════════
const DEMO_RESET_CODE = 'CS-RESET-2024';

function showForgotPassword() {
  // Reset all steps
  ['fp-step-1','fp-step-2','fp-step-3','fp-step-4'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.classList.add('hidden');
  });
  var s1 = document.getElementById('fp-step-1');
  if (s1) s1.classList.remove('hidden');
  var err = document.getElementById('fp-error');
  if (err) err.style.display = 'none';
  var emailEl = document.getElementById('fp-email');
  if (emailEl) emailEl.value = '';
  document.getElementById('forgot-overlay').classList.remove('hidden');
}

function closeForgotPassword() {
  document.getElementById('forgot-overlay').classList.add('hidden');
}

function closeForgotOverlay(e) {
  if (e.target.id === 'forgot-overlay') closeForgotPassword();
}

function doForgotStep1() {
  var email = (document.getElementById('fp-email').value || '').trim();
  var err = document.getElementById('fp-error');
  if (!email || email !== 'demo@company.com') {
    err.style.display = 'block';
    return;
  }
  err.style.display = 'none';
  document.getElementById('fp-step-1').classList.add('hidden');
  document.getElementById('fp-step-2').classList.remove('hidden');
  var sentEl = document.getElementById('fp-sent-email');
  if (sentEl) sentEl.textContent = email;
  var codeEl = document.getElementById('fp-code');
  if (codeEl) codeEl.textContent = DEMO_RESET_CODE;
}

function doForgotStep2() {
  var code = (document.getElementById('fp-code-input').value || '').trim().toUpperCase();
  var err = document.getElementById('fp-code-error');
  if (code !== DEMO_RESET_CODE) {
    err.style.display = 'block'; return;
  }
  err.style.display = 'none';
  document.getElementById('fp-step-2').classList.add('hidden');
  document.getElementById('fp-step-3').classList.remove('hidden');
  var np = document.getElementById('fp-newpass');
  if (np) { np.value = ''; np.addEventListener('input', checkPassStrength); }
}

function checkPassStrength() {
  var pass = (document.getElementById('fp-newpass').value || '');
  var fill = document.getElementById('pass-strength-fill');
  var label = document.getElementById('pass-strength-label');
  if (!fill || !label) return;
  var score = 0;
  if (pass.length >= 8) score++;
  if (pass.length >= 12) score++;
  if (/[A-Z]/.test(pass)) score++;
  if (/[0-9]/.test(pass)) score++;
  if (/[^A-Za-z0-9]/.test(pass)) score++;
  var pct = (score / 5) * 100;
  var colors = ['#FF4D6A','#FF7A45','#F5B731','#10D982','#10D982'];
  var labels = ['','Weak','Fair','Good','Strong','Very Strong'];
  fill.style.width = pct + '%';
  fill.style.background = colors[Math.max(0,score-1)] || '#FF4D6A';
  label.textContent = score > 0 ? labels[score] : '';
  label.style.color = colors[Math.max(0,score-1)] || '#FF4D6A';
}

function doForgotStep3() {
  var np = (document.getElementById('fp-newpass').value || '');
  var cp = (document.getElementById('fp-confirmpass').value || '');
  var err = document.getElementById('fp-pass-error');
  if (np.length < 8) { err.style.display='block'; err.textContent='Password must be at least 8 characters.'; return; }
  if (np !== cp) { err.style.display='block'; err.textContent='Passwords do not match.'; return; }
  err.style.display = 'none';
  document.getElementById('fp-step-3').classList.add('hidden');
  document.getElementById('fp-step-4').classList.remove('hidden');
  toast('✅ Password reset successfully!');
}

// ═══════════════════════════════════════════════════════════════
//  TERMS / SECTION NAVIGATION
// (showPage override handled in sidebar.js)
// ═══════════════════════════════════════════════════════════════
function goBackFromTerms() {
  if (typeof _termsReturnPage !== 'undefined') {
    showPage(_termsReturnPage || 'dashboard');
  } else {
    showPage('dashboard');
  }
}

function scrollToSection(id) {
  var el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearAllData() {
  if (!confirm('Are you sure you want to delete all your assessment data? This cannot be undone.')) return;
  var keys = ['cs_history','cs_badges','cs_notifs','cs_profile','cs_session','cs_prefs','cs_leaderboard','cs_history_seeded','cs_lang','cs_theme','cs_a11y'];
  keys.forEach(function(k){ localStorage.removeItem(k); });
  toast('🗑 All data cleared. Refreshing…');
  setTimeout(function(){ location.reload(); }, 1500);
}

// Boot extension handled by sidebar.js
// ═══════════════════════════════════════════════════════════════
//  SELLER MODULE — Products & Analytics
// ═══════════════════════════════════════════════════════════════

// ── Storage helpers ──────────────────────────────────────────
function getProducts() { return JSON.parse(localStorage.getItem('cs_products') || '[]'); }
function saveProducts(p) { localStorage.setItem('cs_products', JSON.stringify(p)); }
function getAnalyticsData() {
  var d = localStorage.getItem('cs_analytics');
  if (d) return JSON.parse(d);
  // Seed realistic demo data
  var data = generateAnalyticsSeed();
  localStorage.setItem('cs_analytics', JSON.stringify(data));
  return data;
}

function generateAnalyticsSeed() {
  var now = Date.now();
  var DAY = 86400000;
  var days = [];
  for (var i = 89; i >= 0; i--) {
    var base = 3000 + Math.random() * 4000;
    var views = Math.floor(40 + Math.random() * 120);
    var orders = Math.floor(views * (0.04 + Math.random() * 0.08));
    days.push({
      ts: now - i * DAY,
      revenue: parseFloat(base.toFixed(2)),
      views: views,
      orders: orders,
      engagement: parseFloat((orders / views * 100).toFixed(1))
    });
  }
  return days;
}

// ── Product Filter State ──────────────────────────────────────
var _productFilter = 'all';

function filterProducts(f, btn) {
  _productFilter = f;
  document.querySelectorAll('#page-seller-store .filter-btn').forEach(function(b){ b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  renderProductGrid();
}

// ── Render Product Grid ───────────────────────────────────────
function renderProductGrid() {
  var products = getProducts();
  var search = (document.getElementById('product-search') || {}).value || '';
  var q = search.toLowerCase();

  var filtered = products.filter(function(p) {
    var matchFilter = _productFilter === 'all' ||
      (_productFilter === 'active' && p.status === 'active' && p.stock > 0) ||
      (_productFilter === 'inactive' && p.status === 'inactive') ||
      (_productFilter === 'out_of_stock' && p.stock <= 0);
    var matchSearch = !q || p.name.toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  var grid = document.getElementById('product-grid');
  var empty = document.getElementById('product-empty');
  if (!grid) return;

  // Update stats
  updateSellerStats(products);

  if (!filtered.length) {
    grid.innerHTML = '';
    grid.style.display = 'none';
    if (products.length === 0) { empty.style.display = 'block'; }
    else { empty.style.display = 'none'; }
    return;
  }
  grid.style.display = 'grid';
  empty.style.display = 'none';

  var ICONS = { Electronics:'💻', Clothing:'👕', 'Home & Garden':'🏡', Sports:'⚽', Books:'📚', 'Food & Beverage':'🍔', 'Health & Beauty':'💄', Other:'📦' };

  grid.innerHTML = filtered.map(function(p) {
    var statusLabel = p.stock <= 0 ? 'out_of_stock' : p.status;
    var statusText  = p.stock <= 0 ? 'Out of Stock' : (p.status === 'active' ? 'Active' : 'Inactive');
    var stockClass  = p.stock <= 0 ? 'out' : (p.stock < 5 ? 'low' : '');
    var stockText   = p.stock <= 0 ? 'Out of stock' : (p.stock < 5 ? 'Low: ' + p.stock + ' left' : p.stock + ' in stock');
    var imgHtml = p.image
      ? '<img src="' + p.image + '" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"/><div class="product-img-placeholder" style="display:none">' + (ICONS[p.category]||'📦') + '</div>'
      : '<div class="product-img-placeholder">' + (ICONS[p.category]||'📦') + '</div>';

    return '<div class="product-card">' +
      '<div class="product-img-wrap">' + imgHtml +
      '<span class="product-status-badge ' + statusLabel + '">' + statusText + '</span></div>' +
      '<div class="product-body">' +
        '<div class="product-category">' + (p.category || 'Other') + '</div>' +
        '<div class="product-name">' + escHtml(p.name) + '</div>' +
        '<div class="product-desc">' + escHtml(p.description || 'No description provided.') + '</div>' +
        '<div class="product-meta">' +
          '<div class="product-price">₱' + parseFloat(p.price).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2}) + '</div>' +
          '<div class="product-stock ' + stockClass + '">' + stockText + '</div>' +
        '</div>' +
      '</div>' +
      '<div class="product-actions">' +
        '<button class="product-action-btn edit" onclick="openProductModal(\'' + p.id + '\')">' +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Edit' +
        '</button>' +
        '<button class="product-action-btn" onclick="toggleProductStatus(\'' + p.id + '\')">' +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/>' + (p.status==='active'?'<line x1="8" y1="12" x2="16" y2="12"/>':'<polyline points="9 12 11 14 15 10"/>') + '</svg>' + (p.status==='active'?'Pause':'Activate') +
        '</button>' +
        '<button class="product-action-btn delete" onclick="deleteProduct(\'' + p.id + '\')">' +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>Delete' +
        '</button>' +
      '</div>' +
    '</div>';
  }).join('');
}

function updateSellerStats(products) {
  var total  = products.length;
  var active = products.filter(function(p){ return p.status==='active' && p.stock > 0; }).length;
  var oos    = products.filter(function(p){ return p.stock <= 0; }).length;
  var value  = products.reduce(function(s,p){ return s + parseFloat(p.price||0)*parseInt(p.stock||0); }, 0);
  setText('s-stat-total',  total);
  setText('s-stat-active', active);
  setText('s-stat-oos',    oos);
  setText('s-stat-value',  '₱' + value.toLocaleString('en-PH',{minimumFractionDigits:0,maximumFractionDigits:0}));
}

// ── Product Modal ─────────────────────────────────────────────
function openProductModal(id) {
  var modal = document.getElementById('product-modal');
  if (!modal) return;
  document.getElementById('product-modal-error').style.display = 'none';

  if (id) {
    var p = getProducts().find(function(x){ return x.id === id; });
    if (!p) return;
    document.getElementById('product-modal-title').textContent = 'Edit Product';
    document.getElementById('product-edit-id').value = id;
    document.getElementById('p-name').value      = p.name || '';
    document.getElementById('p-desc').value      = p.description || '';
    document.getElementById('p-price').value     = p.price || '';
    document.getElementById('p-stock').value     = p.stock || '';
    document.getElementById('p-category').value  = p.category || 'Other';
    document.getElementById('p-status').value    = p.status || 'active';
    document.getElementById('p-image').value     = p.image || '';
  } else {
    document.getElementById('product-modal-title').textContent = 'Add New Product';
    document.getElementById('product-edit-id').value = '';
    ['p-name','p-desc','p-price','p-stock','p-image'].forEach(function(id){ document.getElementById(id).value = ''; });
    document.getElementById('p-category').value = 'Electronics';
    document.getElementById('p-status').value   = 'active';
  }
  modal.classList.remove('hidden');
}

function closeProductModal(e) {
  if (e && e.target && e.target.id !== 'product-modal') return;
  document.getElementById('product-modal').classList.add('hidden');
}

function saveProduct() {
  var name  = (document.getElementById('p-name').value || '').trim();
  var price = parseFloat(document.getElementById('p-price').value);
  var stock = parseInt(document.getElementById('p-stock').value);
  var errEl = document.getElementById('product-modal-error');

  if (!name) { errEl.textContent = 'Product name is required.'; errEl.style.display = 'block'; return; }
  if (isNaN(price) || price < 0) { errEl.textContent = 'Enter a valid price.'; errEl.style.display = 'block'; return; }
  if (isNaN(stock) || stock < 0) { errEl.textContent = 'Enter a valid stock quantity.'; errEl.style.display = 'block'; return; }

  var products = getProducts();
  var editId = document.getElementById('product-edit-id').value;

  var product = {
    id: editId || 'p_' + Date.now(),
    name: name,
    description: document.getElementById('p-desc').value.trim(),
    price: price,
    stock: stock,
    category: document.getElementById('p-category').value,
    status: document.getElementById('p-status').value,
    image: document.getElementById('p-image').value.trim(),
    createdAt: editId ? (products.find(function(x){return x.id===editId;})||{}).createdAt || Date.now() : Date.now(),
    updatedAt: Date.now()
  };

  if (editId) {
    products = products.map(function(p){ return p.id === editId ? product : p; });
    toast('✅ Product updated successfully');
  } else {
    products.push(product);
    toast('✅ Product added to your store');
  }

  saveProducts(products);
  closeProductModal();
  renderProductGrid();
}

function deleteProduct(id) {
  if (!confirm('Delete this product? This cannot be undone.')) return;
  var products = getProducts().filter(function(p){ return p.id !== id; });
  saveProducts(products);
  renderProductGrid();
  toast('🗑 Product removed');
}

function toggleProductStatus(id) {
  var products = getProducts().map(function(p) {
    if (p.id === id) p.status = p.status === 'active' ? 'inactive' : 'active';
    return p;
  });
  saveProducts(products);
  renderProductGrid();
}

// ── Analytics Dashboard ───────────────────────────────────────
var _analyticsPeriod = 7;
var _analyticsCharts = {};

function setAnalyticsPeriod(days, btn) {
  _analyticsPeriod = days;
  document.querySelectorAll('#page-seller-analytics .filter-btn').forEach(function(b){ b.classList.remove('active'); });
  if (btn) btn.classList.add('active');
  renderAnalyticsDashboard();
}

function renderAnalyticsDashboard() {
  var allData = getAnalyticsData();
  var now = Date.now();
  var DAY = 86400000;
  var slice = allData.filter(function(d){ return d.ts >= now - _analyticsPeriod * DAY; });
  var prev  = allData.filter(function(d){ return d.ts >= now - _analyticsPeriod*2*DAY && d.ts < now-_analyticsPeriod*DAY; });

  var sum = function(arr, key) { return arr.reduce(function(s,d){ return s+(d[key]||0); }, 0); };

  var revenue  = sum(slice, 'revenue');
  var orders   = sum(slice, 'orders');
  var views    = sum(slice, 'views');
  var engage   = views > 0 ? (orders/views*100).toFixed(1) : 0;

  var pRevenue = sum(prev, 'revenue');
  var pOrders  = sum(prev, 'orders');
  var pViews   = sum(prev, 'views');
  var pEngage  = pViews > 0 ? (sum(prev,'orders')/pViews*100).toFixed(1) : 0;

  function trendText(cur, prev, isCur) {
    if (!prev) return '—';
    var diff = ((cur - prev) / prev * 100).toFixed(1);
    return (diff >= 0 ? '▲ +' : '▼ ') + diff + '% vs prior period';
  }
  function trendClass(cur, prev) {
    if (!prev) return '';
    return cur >= prev ? 'up' : 'down';
  }

  setText('kpi-revenue', '₱' + revenue.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}));
  setText('kpi-orders',  orders);
  setText('kpi-views',   views.toLocaleString());
  setText('kpi-engage',  engage + '%');

  setTrend('kpi-revenue-trend', trendText(revenue, pRevenue), trendClass(revenue, pRevenue));
  setTrend('kpi-orders-trend',  trendText(orders,  pOrders),  trendClass(orders,  pOrders));
  setTrend('kpi-views-trend',   trendText(views,   pViews),   trendClass(views,   pViews));
  setTrend('kpi-engage-trend',  trendText(parseFloat(engage), parseFloat(pEngage)), trendClass(parseFloat(engage), parseFloat(pEngage)));

  renderAnalyticsCharts(slice);
  renderTopProducts();
}

function setTrend(id, text, cls) {
  var el = document.getElementById(id);
  if (!el) return;
  el.textContent = text;
  el.className = 'stat-sub kpi-trend ' + cls;
}

function destroyChart(key) {
  if (_analyticsCharts[key]) { try { _analyticsCharts[key].destroy(); } catch(e){} delete _analyticsCharts[key]; }
}

function renderAnalyticsCharts(slice) {
  var labels = slice.map(function(d){ var dt=new Date(d.ts); return (dt.getMonth()+1)+'/'+(dt.getDate()); });

  var CHART_DEFAULTS = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#8898b4', font: { family: "'JetBrains Mono'" }, boxWidth: 12 } } },
    scales: {
      x: { ticks: { color: '#4d5d7a', font: { family: "'JetBrains Mono'", size: 9 } }, grid: { color: 'rgba(255,255,255,.035)' } },
      y: { ticks: { color: '#4d5d7a', font: { family: "'JetBrains Mono'", size: 9 } }, grid: { color: 'rgba(255,255,255,.035)' } }
    }
  };

  // Revenue Line Chart
  destroyChart('revenue');
  var rc = document.getElementById('analytics-revenue-chart');
  if (rc) {
    _analyticsCharts['revenue'] = new Chart(rc, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Revenue (₱)',
          data: slice.map(function(d){ return d.revenue; }),
          borderColor: '#3b8bff', backgroundColor: 'rgba(59,139,255,.08)',
          tension: 0.4, fill: true, pointRadius: slice.length > 30 ? 0 : 3,
          pointBackgroundColor: '#3b8bff'
        }]
      },
      options: JSON.parse(JSON.stringify(CHART_DEFAULTS))
    });
  }

  // Category Doughnut
  destroyChart('category');
  var cats = ['Electronics','Clothing','Home & Garden','Sports','Books','Food & Beverage','Health & Beauty','Other'];
  var catColors = ['#3b8bff','#00e882','#b061ff','#ff8c42','#ffd60a','#00d4aa','#ff3b5c','#5fa3ff'];
  var catData = cats.map(function(c, i){ return Math.floor(5 + Math.random()*40); });
  var cc = document.getElementById('analytics-category-chart');
  if (cc) {
    destroyChart('category');
    _analyticsCharts['category'] = new Chart(cc, {
      type: 'doughnut',
      data: { labels: cats, datasets: [{ data: catData, backgroundColor: catColors.map(function(c){ return c+'cc'; }), borderColor: catColors, borderWidth: 1.5 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color:'#8898b4', font:{family:"'JetBrains Mono'", size:9}, boxWidth:10, padding:8 } } } }
    });
  }

  // Views vs Orders Bar
  destroyChart('views');
  var vc = document.getElementById('analytics-views-chart');
  if (vc) {
    _analyticsCharts['views'] = new Chart(vc, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          { label: 'Views', data: slice.map(function(d){ return d.views; }), backgroundColor: 'rgba(176,97,255,.5)', borderColor: '#b061ff', borderWidth: 1, borderRadius: 3 },
          { label: 'Orders', data: slice.map(function(d){ return d.orders; }), backgroundColor: 'rgba(0,232,130,.5)', borderColor: '#00e882', borderWidth: 1, borderRadius: 3 }
        ]
      },
      options: JSON.parse(JSON.stringify(CHART_DEFAULTS))
    });
  }

  // Engagement Line
  destroyChart('engagement');
  var ec = document.getElementById('analytics-engagement-chart');
  if (ec) {
    _analyticsCharts['engagement'] = new Chart(ec, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Engagement %',
          data: slice.map(function(d){ return d.engagement; }),
          borderColor: '#ff8c42', backgroundColor: 'rgba(255,140,66,.08)',
          tension: 0.4, fill: true, pointRadius: slice.length > 30 ? 0 : 3,
          pointBackgroundColor: '#ff8c42'
        }]
      },
      options: JSON.parse(JSON.stringify(CHART_DEFAULTS))
    });
  }
}

function renderTopProducts() {
  var products = getProducts();
  var el = document.getElementById('analytics-top-products');
  if (!el) return;

  if (!products.length) {
    el.innerHTML = '<p class="empty-state">No products listed yet. Add products to see performance data.</p>';
    return;
  }

  // Assign mock analytics per product
  var enriched = products.map(function(p) {
    return {
      name: p.name,
      category: p.category || 'Other',
      price: p.price,
      views: Math.floor(50 + Math.random() * 500),
      revenue: parseFloat((Math.random() * 5000 + 100).toFixed(2)),
      orders: Math.floor(2 + Math.random() * 40)
    };
  }).sort(function(a,b){ return b.revenue - a.revenue; });

  var maxRev = enriched[0].revenue;

  el.innerHTML = '<table class="top-products-table">' +
    '<thead><tr><th>#</th><th>Product</th><th>Category</th><th>Orders</th><th>Views</th><th>Revenue</th></tr></thead>' +
    '<tbody>' +
    enriched.slice(0,8).map(function(p,i) {
      var pct = Math.round(p.revenue / maxRev * 100);
      return '<tr>' +
        '<td class="tp-rank">' + (i+1) + '</td>' +
        '<td><div style="font-weight:600;">' + escHtml(p.name) + '</div>' +
          '<div class="tp-bar-wrap"><div class="tp-bar-fill" style="width:' + pct + '%"></div></div></td>' +
        '<td><span style="font-family:var(--mono);font-size:.68rem;color:var(--blue)">' + escHtml(p.category) + '</span></td>' +
        '<td style="font-family:var(--mono)">' + p.orders + '</td>' +
        '<td style="font-family:var(--mono)">' + p.views.toLocaleString() + '</td>' +
        '<td style="font-family:var(--mono);font-weight:700">₱' + p.revenue.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}) + '</td>' +
      '</tr>';
    }).join('') +
    '</tbody></table>';
}

// ── Utility ───────────────────────────────────────────────────
function setText(id, val) { var el=document.getElementById(id); if(el) el.textContent=val; }
function escHtml(s) { var d=document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }