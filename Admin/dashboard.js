// ══════════════════════════════════
// API FUNCTIONS
// ══════════════════════════════════
async function apiCall(action, params = {}) {
  try {
    const url =
      API_BASE + "?action=" + encodeURIComponent(action) + "&_=" + Date.now(); // Cache busting
    console.log("API Call URL:", url); // Debug log

    const options = {
      method: "GET",
      credentials: "same-origin",
    };

    if (Object.keys(params).length > 0) {
      options.method = "POST";
      options.body = new FormData();
      for (const [key, value] of Object.entries(params)) {
        options.body.append(key, value);
      }
    }

    const response = await fetch(url, options);

    if (!response.ok) {
      const errorText = await response.text();
      console.error("API Response Error:", response.status, errorText);
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }

    const data = await response.json();
    return data;
  } catch (error) {
    console.error("API Error:", error);
    toast("❌ Error: " + error.message);
    throw error;
  }
}

async function loadDashboardData() {
  try {
    const [vendors, stats, assessments] = await Promise.all([
      apiCall("get_vendors"),
      apiCall("get_stats"),
      apiCall("get_assessments"),
    ]);

    // Transform data to match expected structure
    VENDORS_DATA = vendors.map((vendor) => {
      const vendorAssessments = assessments.filter(
        (a) => a.vendor_id === vendor.id,
      );
      return {
        id: vendor.id,
        name: vendor.name,
        flagged: vendor.flagged || false,
        history: vendorAssessments
          .map((assessment) => ({
            pct: assessment.score,
            rank: assessment.rank,
            date: assessment.created_at,
            catPct: {
              password: assessment.password_score || assessment.score,
              phishing: assessment.phishing_score || assessment.score,
              device: assessment.device_score || assessment.score,
              network: assessment.network_score || assessment.score,
            },
          }))
          .sort((a, b) => new Date(b.date) - new Date(a.date)),
      };
    });

    dashboardStats = stats;

    // Update stats display
    updateStatsDisplay(stats);

    // Update charts
    renderCharts();

    // Update tables
    renderTable();
    renderUsersTable();

    // Check for alerts
    checkHighRiskAlerts();

    logActivity(
      "refresh",
      "↻",
      "Data Refreshed",
      "Dashboard data loaded from server",
    );
  } catch (error) {
    console.error("Failed to load dashboard data:", error);
  }
}

function updateStatsDisplay(stats) {
  const statsRow = document.getElementById("stats-row");
  if (!statsRow) return;

  statsRow.innerHTML = `
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-content">
        <div class="stat-value">${stats.total_vendors || 0}</div>
        <div class="stat-label">Total Vendors</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📊</div>
      <div class="stat-content">
        <div class="stat-value">${Math.round(stats.avg_score || 0)}%</div>
        <div class="stat-label">Average Score</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">⚠️</div>
      <div class="stat-content">
        <div class="stat-value">${stats.high_risk_count || 0}</div>
        <div class="stat-label">High Risk</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-content">
        <div class="stat-value">${stats.low_risk_count || 0}</div>
        <div class="stat-label">Low Risk</div>
      </div>
    </div>
  `;
}

// ══════════════════════════════════
// CONFIGURATION
// ══════════════════════════════════
const API_BASE = "api.php";

const RANK_CFG = {
  A: { color: "#10D982", faint: "rgba(16,217,130,.12)", label: "Low Risk" },
  B: {
    color: "#F5B731",
    faint: "rgba(245,183,49,.12)",
    label: "Moderate Risk",
  },
  C: { color: "#FF7A45", faint: "rgba(255,122,69,.12)", label: "High Risk" },
  D: {
    color: "#FF4D6A",
    faint: "rgba(255,77,106,.12)",
    label: "Critical Risk",
  },
};

function getRank(p) {
  return p >= 80 ? "A" : p >= 60 ? "B" : p >= 40 ? "C" : "D";
}
function rnd(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}
function fmtDate(iso) {
  return new Date(iso).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}
function fmtTime(iso) {
  return new Date(iso).toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
  });
}
function clamp(v) {
  return Math.max(0, Math.min(100, v));
}

let VENDORS_DATA = [],
  barChart = null,
  pieChart = null,
  lineChart = null,
  barChart2 = null,
  pieChart2 = null,
  sidebarCollapsed = false;
let selectedRows = new Set();
let dashboardStats = {};

// ══════════════════════════════════
// THEME
// ══════════════════════════════════
function applyTheme(dark) {
  document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");
  const btn = document.getElementById("theme-toggle");
  if (btn) btn.textContent = dark ? "🌙" : "☀️";
  const pd = document.getElementById("pref-dark");
  if (pd) pd.checked = dark;
  localStorage.setItem("cs_admin_theme", dark ? "dark" : "light");
}
function toggleTheme() {
  const isDark = document.documentElement.getAttribute("data-theme") === "dark";
  applyTheme(!isDark);
  logActivity(
    "theme",
    isDark ? "☀️" : "🌙",
    "Theme Changed",
    "Switched to " + (isDark ? "light" : "dark") + " mode",
  );
}

// ══════════════════════════════════
// PREFERENCES
// ══════════════════════════════════
function getPref(k) {
  const p = JSON.parse(localStorage.getItem("cs_admin_prefs") || "{}");
  return p[k] !== undefined ? p[k] : true;
}
function savePref(k, v) {
  const p = JSON.parse(localStorage.getItem("cs_admin_prefs") || "{}");
  p[k] = v;
  localStorage.setItem("cs_admin_prefs", JSON.stringify(p));
}

// ══════════════════════════════════
// ACTIVITY LOG
// ══════════════════════════════════
function getActivity() {
  return JSON.parse(localStorage.getItem("cs_admin_activity") || "[]");
}
function saveActivity(a) {
  localStorage.setItem("cs_admin_activity", JSON.stringify(a.slice(0, 100)));
}

function logActivity(type, icon, title, detail) {
  const log = getActivity();
  log.unshift({ type, icon, title, detail, time: new Date().toISOString() });
  saveActivity(log);
  // also update dot if panel open
  renderActivityLog();
}

function renderActivityLog() {
  const filter = document.getElementById("activity-filter")?.value || "";
  let log = getActivity();
  if (filter) log = log.filter((l) => l.type === filter);
  const el = document.getElementById("activity-list");
  if (!el) return;
  if (!log.length) {
    el.innerHTML =
      '<div class="activity-empty">No activity recorded yet.</div>';
    return;
  }
  const colors = {
    export: "rgba(16,217,130,.12)",
    flag: "rgba(255,77,106,.12)",
    refresh: "rgba(245,183,49,.12)",
    alert: "rgba(255,122,69,.12)",
    theme: "rgba(91,79,232,.1)",
    profile: "rgba(91,79,232,.1)",
  };
  el.innerHTML = log
    .map(
      (l) => `
    <div class="activity-item">
      <div class="activity-icon-wrap" style="background:${colors[l.type] || "rgba(91,79,232,.1)"};">${l.icon}</div>
      <div style="flex:1;">
        <div class="activity-title">${l.title}</div>
        <div class="activity-detail">${l.detail}</div>
        <div class="activity-time">${fmtDate(l.time)} · ${fmtTime(l.time)}</div>
      </div>
    </div>`,
    )
    .join("");
}

function clearActivityLog() {
  saveActivity([]);
  renderActivityLog();
  toast("🗑 Activity log cleared");
}

// ══════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════
function getNotifs() {
  return JSON.parse(localStorage.getItem("cs_admin_notifs") || "[]");
}
function saveNotifs(n) {
  localStorage.setItem("cs_admin_notifs", JSON.stringify(n.slice(0, 30)));
}

function addNotif(icon, title, body) {
  if (!getPref("alerts")) return;
  const notifs = getNotifs();
  notifs.unshift({ icon, title, body, time: new Date().toISOString() });
  saveNotifs(notifs);
  renderNotifBadge();
}

function renderNotifBadge() {
  const n = getNotifs();
  const dot = document.getElementById("notif-dot");
  if (dot) dot.classList.toggle("hidden", n.length === 0);
}

function toggleNotifPanel() {
  const panel = document.getElementById("notif-panel");
  if (!panel) return;
  panel.classList.toggle("hidden");
  if (!panel.classList.contains("hidden")) renderNotifList();
  document.addEventListener("click", closeNotifOutside, { once: true });
}
function closeNotifOutside(e) {
  const panel = document.getElementById("notif-panel");
  const btn = document.getElementById("notif-btn");
  if (panel && !panel.contains(e.target) && e.target !== btn)
    panel.classList.add("hidden");
}
function renderNotifList() {
  const notifs = getNotifs();
  const el = document.getElementById("notif-list");
  if (!el) return;
  if (!notifs.length) {
    el.innerHTML = '<p class="notif-empty">No alerts</p>';
    return;
  }
  el.innerHTML = notifs
    .map(
      (n) => `
    <div class="notif-item">
      <span class="notif-item-icon">${n.icon}</span>
      <div><div class="notif-item-title">${n.title}</div>
      <div class="notif-item-body">${n.body}</div>
      <div class="notif-item-time">${fmtDate(n.time)}</div></div>
    </div>`,
    )
    .join("");
}
function clearNotifs() {
  saveNotifs([]);
  document.getElementById("notif-list").innerHTML =
    '<p class="notif-empty">No alerts</p>';
  renderNotifBadge();
}

// ══════════════════════════════════
// GLOBAL SEARCH
// ══════════════════════════════════
function onGlobalSearch(query) {
  const panel = document.getElementById("search-results");
  if (!query.trim()) {
    panel.classList.add("hidden");
    return;
  }
  const q = query.toLowerCase();
  const matches = [];
  VENDORS_DATA.forEach((v) => {
    if (v.name.toLowerCase().includes(q)) {
      const latest = v.history[v.history.length - 1];
      matches.push({
        type: "vendor",
        id: v.id,
        name: v.name,
        sub: latest
          ? `Latest: ${latest.pct}% · ${RANK_CFG[latest.rank].label}`
          : "No assessments",
      });
    }
  });
  // Search assessments
  getAllAssessments().forEach((a) => {
    if (
      (a.vendorName || "").toLowerCase().includes(q) ||
      String(a.pct).includes(q)
    ) {
      if (!matches.find((m) => m.id === a.vendorId)) {
        matches.push({
          type: "assessment",
          id: a.vendorId,
          name: a.vendorName,
          sub: `Score: ${a.pct}% on ${fmtDate(a.date)}`,
        });
      }
    }
  });
  if (!matches.length) {
    panel.innerHTML =
      '<div style="padding:1rem;font-size:.82rem;color:var(--text-3);text-align:center;">No results found</div>';
    panel.classList.remove("hidden");
    return;
  }
  panel.innerHTML = matches
    .slice(0, 8)
    .map(
      (m) => `
    <div class="search-result-item" onclick="openVendorDetail(${m.id});document.getElementById('global-search').value='';document.getElementById('search-results').classList.add('hidden');">
      <span style="font-size:1rem;">${m.type === "vendor" ? "👤" : "📋"}</span>
      <div><div class="sr-name">${m.name}</div><div class="sr-sub">${m.sub}</div></div>
      <span class="rank-pill ${getLatestPerVendor().find((v) => v.vendorId === m.id)?.rank || "D"}" style="margin-left:auto;"></span>
    </div>`,
    )
    .join("");
  panel.classList.remove("hidden");
  document.addEventListener("click", () => panel.classList.add("hidden"), {
    once: true,
  });
}

// ══════════════════════════════════
// BOOT
// ══════════════════════════════════
function bootApp() {
  document.getElementById("app").classList.remove("hidden");

  // Apply saved theme
  const theme = localStorage.getItem("cs_admin_theme") || "dark";
  applyTheme(theme === "dark");

  document.getElementById("topbar-date").textContent =
    new Date().toLocaleDateString("en-US", {
      weekday: "short",
      month: "short",
      day: "numeric",
      year: "numeric",
    });

  // Load profile from PHP data if available
  if (typeof dashboardData !== "undefined" && dashboardData.user) {
    const sbName = document.getElementById("sb-admin-name");
    const sbEmail = document.getElementById("sb-admin-email");
    if (sbName) sbName.textContent = dashboardData.user.full_name;
    if (sbEmail) sbEmail.textContent = dashboardData.user.email;
  }

  // Seed prefs
  const prefAutoRefresh = document.getElementById("pref-autorefresh");
  if (prefAutoRefresh) prefAutoRefresh.checked = getPref("autorefresh");
  const prefAlerts = document.getElementById("pref-alerts");
  if (prefAlerts) prefAlerts.checked = getPref("alerts");

  renderNotifBadge();

  // Load data from server
  loadDashboardData();
}

function initApp() {
  bootApp();
}

function doSignOut() {
  if (confirm("Are you sure you want to sign out?")) {
    window.location.href = "/security/landingpage.php";
  }
}

function checkHighRiskAlerts() {
  const latest = getLatestPerVendor();
  const critical = latest.filter((v) => v.rank === "D");
  const high = latest.filter((v) => v.rank === "C");
  if (critical.length) {
    addNotif(
      "🚨",
      "Critical Risk Vendors",
      `${critical.length} vendor(s) at critical risk: ${critical
        .slice(0, 3)
        .map((v) => v.vendorName)
        .join(", ")}`,
    );
    logActivity(
      "alert",
      "🚨",
      "Critical Risk Alert",
      `${critical.length} vendor(s) flagged as Critical Risk`,
    );
  }
  if (high.length) {
    addNotif(
      "⚠️",
      "High Risk Vendors",
      `${high.length} vendor(s) at high risk need attention`,
    );
  }
}

function refreshData() {
  loadDashboardData();
  toast("🔄 Data refreshed!");
  logActivity(
    "refresh",
    "↻",
    "Data Refreshed",
    "Dashboard data loaded from server",
  );
}

function renderAll() {
  renderStats();
  renderCharts();
  renderAnalytics();
  renderTable();
  renderReportTable();
  renderUsersTable();
  renderReportCharts();
  renderHeatmap();
  renderActivityLog();
  renderProfile();
}

// ══════════════════════════════════
// SIDEBAR
// ══════════════════════════════════
function toggleSidebar() {
  sidebarCollapsed = !sidebarCollapsed;
  document
    .getElementById("sidebar")
    .classList.toggle("collapsed", sidebarCollapsed);
  document
    .getElementById("main")
    .classList.toggle("expanded", sidebarCollapsed);
}

const SECTION_TITLES = {
  dashboard: ["Dashboard Overview", "Vendor cybersecurity risk summary"],
  reports: ["Reports & Export", "Download CSV and PDF reports"],
  users: ["User Management", "View all vendors and their history"],
  heatmap: ["Risk Heatmap", "Visual weakness map across all vendors"],
  activity: ["Activity Log", "History of admin actions and system events"],
  profile: ["Admin Settings", "Account and dashboard preferences"],
};

function switchSection(name) {
  document
    .querySelectorAll(".section-page")
    .forEach((s) => s.classList.remove("active"));
  document
    .querySelectorAll(".sb-item")
    .forEach((i) => i.classList.remove("active"));
  const sec = document.getElementById("sec-" + name);
  const nav = document.getElementById("nav-" + name);
  if (sec) sec.classList.add("active");
  if (nav) nav.classList.add("active");
  const t = SECTION_TITLES[name] || ["Dashboard", ""];
  document.getElementById("topbar-title").textContent = t[0];
  document.getElementById("topbar-sub").textContent = t[1];
  if (name === "heatmap") renderHeatmap();
  if (name === "activity") renderActivityLog();
  if (name === "profile") renderProfile();
}

// ══════════════════════════════════
// HELPERS
// ══════════════════════════════════
function getAllAssessments() {
  const all = [];
  VENDORS_DATA.forEach((v) =>
    v.history.forEach((h) =>
      all.push({ ...h, vendorId: v.id, vendorName: v.name }),
    ),
  );
  return all.sort((a, b) => new Date(b.date) - new Date(a.date));
}
function getLatestPerVendor() {
  return VENDORS_DATA.map((v) => ({
    ...v.history[v.history.length - 1],
    vendorId: v.id,
    vendorName: v.name,
    totalAssessments: v.history.length,
    flagged: v.flagged,
  })).filter((v) => v.pct !== undefined);
}
function getRiskCounts(records) {
  const c = { A: 0, B: 0, C: 0, D: 0 };
  records.forEach((r) => (c[r.rank] = (c[r.rank] || 0) + 1));
  return c;
}

// ══════════════════════════════════
// STATS
// ══════════════════════════════════
function renderStats() {
  const latest = getLatestPerVendor(),
    all = getAllAssessments(),
    counts = getRiskCounts(latest);
  document.getElementById("stats-row").innerHTML = `
    <div class="card stat-card"><div class="stat-icon" style="background:rgba(91,79,232,.15);color:#7B72F0">👥</div><div class="stat-label">Total Vendors</div><div class="stat-val mono">${VENDORS_DATA.length}</div><div class="stat-sub">Registered clients</div></div>
    <div class="card stat-card"><div class="stat-icon" style="background:rgba(16,217,130,.15);color:#10D982">📋</div><div class="stat-label">Assessments</div><div class="stat-val mono">${all.length}</div><div class="stat-sub">Total sessions</div></div>
    <div class="card stat-card"><div class="stat-icon" style="background:rgba(16,217,130,.15);color:#10D982">🟢</div><div class="stat-label">Low Risk (A)</div><div class="stat-val mono" style="color:#10D982">${counts.A}</div><div class="stat-sub">Vendors</div></div>
    <div class="card stat-card"><div class="stat-icon" style="background:rgba(245,183,49,.15);color:#F5B731">🟡</div><div class="stat-label">Moderate (B)</div><div class="stat-val mono" style="color:#F5B731">${counts.B}</div><div class="stat-sub">Vendors</div></div>
    <div class="card stat-card"><div class="stat-icon" style="background:rgba(255,122,69,.15);color:#FF7A45">🟠</div><div class="stat-label">High Risk (C)</div><div class="stat-val mono" style="color:#FF7A45">${counts.C}</div><div class="stat-sub">Vendors</div></div>
    <div class="card stat-card"><div class="stat-icon" style="background:rgba(255,77,106,.15);color:#FF4D6A">🔴</div><div class="stat-label">Critical (D)</div><div class="stat-val mono" style="color:#FF4D6A">${counts.D}</div><div class="stat-sub">Vendors</div></div>`;
}

// ══════════════════════════════════
// CHARTS
// ══════════════════════════════════
const CHART_COLORS = { A: "#10D982", B: "#F5B731", C: "#FF7A45", D: "#FF4D6A" };
const CHART_FAINT = {
  A: "rgba(34,197,94,.15)",
  B: "rgba(245,158,11,.15)",
  C: "rgba(249,115,22,.15)",
  D: "rgba(239,68,68,.15)",
};
const TOOLTIP_CFG = {
  backgroundColor: "#0d1421",
  borderColor: "rgba(255,255,255,.1)",
  borderWidth: 1,
  titleColor: "#dde4f0",
  bodyColor: "#8898b4",
  padding: 10,
};

function makeBarChart(id, counts, ref) {
  const ctx = document.getElementById(id);
  if (!ctx) return ref;
  if (ref) ref.destroy();
  return new Chart(ctx, {
    type: "bar",
    data: {
      labels: ["A — Low", "B — Moderate", "C — High", "D — Critical"],
      datasets: [
        {
          label: "Vendors",
          data: [counts.A, counts.B, counts.C, counts.D],
          backgroundColor: [
            CHART_FAINT.A,
            CHART_FAINT.B,
            CHART_FAINT.C,
            CHART_FAINT.D,
          ],
          borderColor: [
            CHART_COLORS.A,
            CHART_COLORS.B,
            CHART_COLORS.C,
            CHART_COLORS.D,
          ],
          borderWidth: 2,
          borderRadius: 6,
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: TOOLTIP_CFG },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: "rgba(59,139,255,.04)" },
          ticks: { font: { size: 10 }, stepSize: 1, color: "#8898b4" },
        },
        x: {
          grid: { display: false },
          ticks: { font: { size: 10 }, color: "#8898b4" },
        },
      },
    },
  });
}
function makePieChart(id, counts, ref) {
  const ctx = document.getElementById(id);
  if (!ctx) return ref;
  if (ref) ref.destroy();
  const total = Object.values(counts).reduce((a, b) => a + b, 0);
  return new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: ["A — Low", "B — Moderate", "C — High", "D — Critical"],
      datasets: [
        {
          data: [counts.A, counts.B, counts.C, counts.D],
          backgroundColor: [
            CHART_COLORS.A,
            CHART_COLORS.B,
            CHART_COLORS.C,
            CHART_COLORS.D,
          ],
          borderWidth: 2,
          borderColor: "#030508",
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "bottom",
          labels: { font: { size: 10 }, padding: 12, color: "#8898b4" },
        },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const val = ctx.parsed,
                pct = total ? Math.round((val / total) * 100) : 0;
              return ` ${ctx.label}: ${val} (${pct}%)`;
            },
          },
        },
      },
    },
  });
}
function makeLineChart(id, ref) {
  const ctx = document.getElementById(id);
  if (!ctx) return ref;
  if (ref) ref.destroy();
  const all = getAllAssessments(),
    byWeek = {};
  all.forEach((a) => {
    const d = new Date(a.date);
    const week = `${d.getFullYear()}-W${String(Math.ceil((d.getDate() + (new Date(d.getFullYear(), d.getMonth(), 1).getDay() || 7)) / 7)).padStart(2, "0")}/${d.toLocaleDateString("en-US", { month: "short", day: "numeric" })}`;
    if (!byWeek[week]) byWeek[week] = [];
    byWeek[week].push(a.pct);
  });
  const labels = Object.keys(byWeek).sort().slice(-10);
  const data = labels.map((k) =>
    Math.round(byWeek[k].reduce((a, b) => a + b, 0) / byWeek[k].length),
  );
  const shortLabels = labels.map((l) => l.split("/")[1]);
  return new Chart(ctx, {
    type: "line",
    data: {
      labels: shortLabels,
      datasets: [
        {
          label: "Avg Score %",
          data,
          borderColor: "#7B72F0",
          backgroundColor: "rgba(91,79,232,.1)",
          fill: true,
          tension: 0.4,
          pointBackgroundColor: "#7B72F0",
          pointBorderColor: "#0A0F1E",
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 7,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: TOOLTIP_CFG },
      scales: {
        y: {
          min: 0,
          max: 100,
          grid: { color: "rgba(59,139,255,.04)" },
          ticks: {
            font: { size: 10 },
            color: "#8898b4",
            callback: (v) => v + "%",
          },
        },
        x: {
          grid: { display: false },
          ticks: { font: { size: 10 }, color: "#8898b4" },
        },
      },
    },
  });
}
function renderCharts() {
  const latest = getLatestPerVendor(),
    counts = getRiskCounts(latest);
  barChart = makeBarChart("bar-chart", counts, barChart);
  pieChart = makePieChart("pie-chart", counts, pieChart);
  lineChart = makeLineChart("line-chart", lineChart);
}
function renderReportCharts() {
  const latest = getLatestPerVendor(),
    counts = getRiskCounts(latest);
  barChart2 = makeBarChart("bar-chart-2", counts, barChart2);
  pieChart2 = makePieChart("pie-chart-2", counts, pieChart2);
}

// ══════════════════════════════════
// ANALYTICS
// ══════════════════════════════════
function renderAnalytics() {
  const latest = getLatestPerVendor(),
    all = getAllAssessments();
  if (!latest.length) return;
  const avgScore = Math.round(
    latest.reduce((s, r) => s + r.pct, 0) / latest.length,
  );
  const counts = getRiskCounts(latest),
    highRisk = (counts.C || 0) + (counts.D || 0);
  const catTotals = { password: 0, phishing: 0, device: 0, network: 0 },
    catCount = { ...catTotals };
  all.forEach((a) => {
    if (a.catPct)
      Object.keys(catTotals).forEach((k) => {
        catTotals[k] += a.catPct[k] || 0;
        catCount[k]++;
      });
  });
  const catAvg = {};
  Object.keys(catTotals).forEach(
    (k) =>
      (catAvg[k] = catCount[k] ? Math.round(catTotals[k] / catCount[k]) : 0),
  );
  const weakest = Object.entries(catAvg).sort((a, b) => a[1] - b[1])[0];
  const catNames = {
    password: "Password Security",
    phishing: "Phishing Awareness",
    device: "Device Safety",
    network: "Network Safety",
  };
  const catIcons = {
    password: "🔑",
    phishing: "🎣",
    device: "💻",
    network: "📡",
  };
  const sorted = [...latest].sort(
    (a, b) => new Date(a.date) - new Date(b.date),
  );
  const firstHalf = sorted.slice(0, Math.floor(sorted.length / 2)),
    secondHalf = sorted.slice(Math.floor(sorted.length / 2));
  const avg1 = firstHalf.reduce((s, r) => s + r.pct, 0) / firstHalf.length,
    avg2 = secondHalf.reduce((s, r) => s + r.pct, 0) / secondHalf.length;
  const trendDir = avg2 > avg1 ? "↑ Improving" : "↓ Declining",
    trendColor = avg2 > avg1 ? "#10D982" : "#FF4D6A";
  document.getElementById("analytics-grid").innerHTML = `
    <div class="card analytic-card"><div class="analytic-icon">📊</div><div class="analytic-label">Avg Score</div><div class="analytic-val mono">${avgScore}%</div><div class="analytic-sub">Across all vendors</div></div>
    <div class="card analytic-card"><div class="analytic-icon">⚠️</div><div class="analytic-label">High + Critical</div><div class="analytic-val mono" style="color:var(--red)">${highRisk}</div><div class="analytic-sub">Vendors needing action</div></div>
    <div class="card analytic-card"><div class="analytic-icon">${catIcons[weakest[0]]}</div><div class="analytic-label">Weakest Area</div><div class="analytic-val" style="font-size:1rem">${catNames[weakest[0]]}</div><div class="analytic-sub">Avg: ${weakest[1]}% across vendors</div></div>
    <div class="card analytic-card"><div class="analytic-icon">📈</div><div class="analytic-label">Overall Trend</div><div class="analytic-val" style="color:${trendColor}">${trendDir}</div><div class="analytic-sub">Compared to earlier sessions</div></div>`;
}

// ══════════════════════════════════
// RISK HEATMAP
// ══════════════════════════════════
function heatmapColor(pct) {
  // Poor(0)=red, Medium(50)=yellow, Good(100)=green
  if (pct >= 80) return { bg: "rgba(16,217,130,.2)", color: "#10D982" };
  if (pct >= 60) return { bg: "rgba(245,183,49,.2)", color: "#F5B731" };
  if (pct >= 40) return { bg: "rgba(255,122,69,.2)", color: "#FF7A45" };
  return { bg: "rgba(255,77,106,.2)", color: "#FF4D6A" };
}

function renderHeatmap() {
  const sortBy = document.getElementById("heatmap-sort")?.value || "score";
  let latest = getLatestPerVendor();
  if (sortBy === "name")
    latest.sort((a, b) => a.vendorName.localeCompare(b.vendorName));
  else if (sortBy === "score") latest.sort((a, b) => b.pct - a.pct);
  else
    latest.sort(
      (a, b) => (b.catPct?.[sortBy] || 0) - (a.catPct?.[sortBy] || 0),
    );

  const cats = ["password", "phishing", "device", "network"];
  const catLabels = {
    password: "Password",
    phishing: "Phishing",
    device: "Device",
    network: "Network",
  };
  const catIcons = {
    password: "🔑",
    phishing: "🎣",
    device: "💻",
    network: "📡",
  };
  const rows = latest
    .map((v) => {
      const catCells = cats
        .map((k) => {
          const pct = v.catPct?.[k] || 0,
            c = heatmapColor(pct);
          return `<td><div class="hm-cell" style="background:${c.bg};color:${c.color};">${pct}%</div></td>`;
        })
        .join("");
      const overall = heatmapColor(v.pct);
      return `<tr>
      <td><span class="hm-name">${v.vendorName}</span>${v.flagged ? '<span class="hm-flagged" style="margin-left:.5rem;">🚩</span>' : ""}</td>
      <td><div class="hm-cell" style="background:${overall.bg};color:${overall.color};font-weight:800;">${v.pct}%</div></td>
      ${catCells}
      <td><span class="rank-pill ${v.rank}">${v.rank}</span></td>
    </tr>`;
    })
    .join("");

  document.getElementById("heatmap-grid").innerHTML = `
    <table class="heatmap-table">
      <thead><tr><th>Vendor</th><th>Overall</th>${cats.map((k) => `<th>${catLabels[k]}</th>`).join("")}<th>Rank</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>`;
}

// ══════════════════════════════════
// MAIN TABLE (with bulk select)
// ══════════════════════════════════
function getFilteredAssessments(filterId) {
  const filterVal = document.getElementById(filterId)?.value || "";
  const all = getAllAssessments();
  if (!filterVal) return all;
  if (filterVal === "CD")
    return all.filter((r) => r.rank === "C" || r.rank === "D");
  return all.filter((r) => r.rank === filterVal);
}

function renderTable() {
  const rows = getFilteredAssessments("filter-rank");
  const tbody = document.getElementById("table-body");
  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="11" style="text-align:center;padding:2rem;color:var(--text-3)">No records found.</td></tr>`;
    return;
  }
  tbody.innerHTML = rows
    .slice(0, 50)
    .map(
      (r, i) => `
    <tr>
      <td><input type="checkbox" class="tbl-checkbox row-check" data-id="${r.vendorId}-${r.date}" onchange="updateBulkButtons()"></td>
      <td style="color:var(--text-3)">${i + 1}</td>
      <td style="font-weight:600">${r.vendorName}${r.flagged ? '<span class="flag-badge" style="margin-left:.4rem;">🚩</span>' : ""}</td>
      <td class="mono">${r.pct}%</td>
      <td><span class="rank-pill ${r.rank}">${r.rank}</span></td>
      <td class="mono">${r.catPct?.password ?? "—"}%</td>
      <td class="mono">${r.catPct?.phishing ?? "—"}%</td>
      <td class="mono">${r.catPct?.device ?? "—"}%</td>
      <td class="mono">${r.catPct?.network ?? "—"}%</td>
      <td style="color:var(--text-2);font-size:.78rem">${fmtDate(r.date)}</td>
      <td><button class="btn btn-secondary btn-sm" onclick="openVendorDetail(${r.vendorId})">Detail</button></td>
    </tr>`,
    )
    .join("");
}

function resetFilter() {
  document.getElementById("filter-rank").value = "";
  renderTable();
}

function toggleBulkAll(cb) {
  document.querySelectorAll(".row-check").forEach((c) => {
    c.checked = cb.checked;
  });
  updateBulkButtons();
}

function updateBulkButtons() {
  const checked = document.querySelectorAll(".row-check:checked").length;
  const btn = document.getElementById("bulk-action-btn"),
    flagBtn = document.getElementById("bulk-flag-btn");
  if (btn) btn.style.display = checked ? "" : "none";
  if (flagBtn) flagBtn.style.display = checked ? "" : "none";
  if (btn) btn.textContent = `⬇ Export Selected (${checked})`;
  if (flagBtn) flagBtn.textContent = `🚩 Flag Selected (${checked})`;
}

function getCheckedVendorIds() {
  const ids = new Set();
  document.querySelectorAll(".row-check:checked").forEach((c) => {
    ids.add(parseInt(c.dataset.id.split("-")[0]));
  });
  return ids;
}

function bulkExportSelected() {
  const ids = getCheckedVendorIds();
  const all = getAllAssessments().filter((r) => ids.has(r.vendorId));
  if (!all.length) {
    toast("No rows selected.");
    return;
  }
  const headers = [
    "Vendor",
    "Score (%)",
    "Rank",
    "Password %",
    "Phishing %",
    "Device %",
    "Network %",
    "Date",
  ];
  const rows = all.map((r) =>
    [
      `"${r.vendorName}"`,
      r.pct,
      r.rank,
      r.catPct?.password ?? "",
      r.catPct?.phishing ?? "",
      r.catPct?.device ?? "",
      r.catPct?.network ?? "",
      fmtDate(r.date),
    ].join(","),
  );
  const csv = [headers.join(","), ...rows].join("\n");
  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "cybershield_selected_vendors.csv";
  a.click();
  URL.revokeObjectURL(url);
  toast(`📥 Exported ${ids.size} vendor(s)`);
  logActivity(
    "export",
    "⬇",
    "Bulk Export",
    `Exported ${ids.size} vendor(s) via bulk selection`,
  );
}

function bulkFlagSelected() {
  const ids = getCheckedVendorIds();
  ids.forEach((id) => {
    const v = VENDORS_DATA.find((x) => x.id === id);
    if (v) v.flagged = !v.flagged;
  });
  localStorage.setItem("cs_admin_vendors", JSON.stringify(VENDORS_DATA));
  renderTable();
  renderUsersTable();
  renderHeatmap();
  toast(`🚩 Toggled flag on ${ids.size} vendor(s)`);
  logActivity(
    "flag",
    "🚩",
    "Bulk Flag",
    `Toggled flag on ${ids.size} vendor(s)`,
  );
}

// ══════════════════════════════════
// REPORT TABLE
// ══════════════════════════════════
function renderReportTable() {
  const rows = getFilteredAssessments("filter-rank-2");
  const tbody = document.getElementById("report-table-body");
  tbody.innerHTML = rows
    .slice(0, 100)
    .map(
      (r, i) => `
    <tr>
      <td style="color:var(--text-3)">${i + 1}</td>
      <td style="font-weight:600">${r.vendorName}</td>
      <td class="mono">${r.pct}%</td>
      <td><span class="rank-pill ${r.rank}">${r.rank} — ${RANK_CFG[r.rank].label}</span></td>
      <td style="color:var(--text-2);font-size:.78rem">${fmtDate(r.date)}</td>
    </tr>`,
    )
    .join("");
}

// ══════════════════════════════════
// USERS TABLE (with search + flag)
// ══════════════════════════════════
function renderUsersTable() {
  const filterVal = document.getElementById("filter-users")?.value || "";
  const searchVal = (
    document.getElementById("users-search")?.value || ""
  ).toLowerCase();
  let vendors = [...VENDORS_DATA];
  if (filterVal)
    vendors = vendors.filter((v) => {
      const l = v.history[v.history.length - 1];
      return l && l.rank === filterVal;
    });
  if (searchVal)
    vendors = vendors.filter((v) => v.name.toLowerCase().includes(searchVal));
  const tbody = document.getElementById("users-table-body");
  if (!vendors.length) {
    tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--text-3)">No vendors found.</td></tr>`;
    return;
  }
  tbody.innerHTML = vendors
    .map((v, i) => {
      const latest = v.history[v.history.length - 1];
      if (!latest) return "";
      return `<tr>
      <td style="color:var(--text-3)">${i + 1}</td>
      <td style="font-weight:600">${v.name}</td>
      <td class="mono">${latest.pct}%</td>
      <td><span class="rank-pill ${latest.rank}">${latest.rank}</span></td>
      <td class="mono">${v.history.length}</td>
      <td style="color:var(--text-2);font-size:.78rem">${fmtDate(latest.date)}</td>
      <td>${v.flagged ? '<span class="flag-badge">🚩 Flagged</span>' : "—"}</td>
      <td style="display:flex;gap:.4rem;">
        <button class="btn btn-secondary btn-sm" onclick="openVendorDetail(${v.id})">Detail</button>
        <button class="btn btn-secondary btn-sm" onclick="toggleFlag(${v.id})">${v.flagged ? "Unflag" : "🚩 Flag"}</button>
      </td>
    </tr>`;
    })
    .join("");
}

function toggleFlag(id) {
  const v = VENDORS_DATA.find((x) => x.id === id);
  if (!v) return;
  v.flagged = !v.flagged;
  localStorage.setItem("cs_admin_vendors", JSON.stringify(VENDORS_DATA));
  renderUsersTable();
  renderTable();
  renderHeatmap();
  toast(v.flagged ? `🚩 ${v.name} flagged` : `✅ ${v.name} unflagged`);
  logActivity(
    "flag",
    "🚩",
    "Vendor Flag",
    `${v.name} was ${v.flagged ? "flagged" : "unflagged"}`,
  );
}

// ══════════════════════════════════
// VENDOR DETAIL MODAL
// ══════════════════════════════════
function openVendorDetail(vendorId) {
  const v = VENDORS_DATA.find((x) => x.id === vendorId);
  if (!v) return;
  const latest = v.history[v.history.length - 1];
  if (!latest) return;

  document.getElementById("modal-title").textContent =
    v.name + " — Vendor Detail";

  const cats = ["password", "phishing", "device", "network"];
  const catLabels = {
    password: "Password",
    phishing: "Phishing",
    device: "Device",
    network: "Network",
  };
  const catIcons = {
    password: "🔑",
    phishing: "🎣",
    device: "💻",
    network: "📡",
  };

  const catCards = cats
    .map((k) => {
      const pct = latest.catPct?.[k] || 0;
      const c = heatmapColor(pct);
      return `<div class="vendor-cat-card">
      <div class="vendor-cat-label">${catIcons[k]} ${catLabels[k]}</div>
      <div class="vendor-cat-val" style="color:${c.color}">${pct}%</div>
      <div class="vendor-cat-bar"><div class="vendor-cat-fill" style="width:${pct}%;background:${c.color};"></div></div>
    </div>`;
    })
    .join("");

  const historyRows = v.history
    .map(
      (h, i) => `
    <tr>
      <td>${i + 1}</td>
      <td class="mono">${h.pct}%</td>
      <td><span class="rank-pill ${h.rank}">${h.rank}</span></td>
      <td class="mono">${h.catPct?.password ?? "—"}%</td>
      <td class="mono">${h.catPct?.phishing ?? "—"}%</td>
      <td class="mono">${h.catPct?.device ?? "—"}%</td>
      <td class="mono">${h.catPct?.network ?? "—"}%</td>
      <td style="color:var(--text-2);font-size:.75rem">${fmtDate(h.date)}</td>
    </tr>`,
    )
    .join("");

  // Trend mini-chart canvas
  const canvasId = "vendor-trend-" + vendorId;

  document.getElementById("modal-body").innerHTML = `
    <div class="vendor-detail-header">
      <div class="vendor-detail-avatar">${v.name.charAt(0)}</div>
      <div>
        <div class="vendor-detail-name">${v.name}${v.flagged ? ' <span class="flag-badge">🚩 Flagged</span>' : ""}</div>
        <div class="vendor-detail-sub">Latest Score: <strong>${latest.pct}%</strong> &nbsp;·&nbsp; Rank: <span class="rank-pill ${latest.rank}">${latest.rank} — ${RANK_CFG[latest.rank].label}</span> &nbsp;·&nbsp; ${v.history.length} Assessments</div>
      </div>
      <button class="btn btn-secondary btn-sm" style="margin-left:auto;" onclick="toggleFlag(${v.id});closeModalDirect();">${v.flagged ? "Unflag" : "🚩 Flag"}</button>
    </div>
    <div class="vendor-cat-grid">${catCards}</div>
    <div style="margin-bottom:1.25rem;">
      <h4 style="font-size:.88rem;font-weight:700;margin-bottom:.75rem;">📈 Score Trend</h4>
      <div style="position:relative;height:140px;"><canvas id="${canvasId}"></canvas></div>
    </div>
    <h4 style="font-size:.88rem;font-weight:700;margin-bottom:.75rem;">📋 Assessment History</h4>
    <div style="overflow-x:auto;">
      <table class="tbl">
        <thead><tr><th>#</th><th>Score</th><th>Rank</th><th>Password</th><th>Phishing</th><th>Device</th><th>Network</th><th>Date</th></tr></thead>
        <tbody>${historyRows || '<tr><td colspan="8" style="color:var(--text-3);text-align:center;padding:1rem">No history</td></tr>'}</tbody>
      </table>
    </div>`;

  document.getElementById("modal-overlay").classList.remove("hidden");

  // Draw mini trend chart after DOM is ready
  requestAnimationFrame(() => {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    new Chart(ctx, {
      type: "line",
      data: {
        labels: v.history.map((h) => fmtDate(h.date)),
        datasets: [
          {
            data: v.history.map((h) => h.pct),
            borderColor: "#7B72F0",
            backgroundColor: "rgba(91,79,232,.08)",
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: "#7B72F0",
            pointBorderColor: "#0A0F1E",
            pointBorderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            ...TOOLTIP_CFG,
            callbacks: { label: (ctx) => ` ${ctx.parsed.y}%` },
          },
        },
        scales: {
          y: {
            min: 0,
            max: 100,
            grid: { color: "rgba(59,139,255,.04)" },
            ticks: {
              color: "#8898b4",
              font: { size: 9 },
              callback: (v) => v + "%",
            },
          },
          x: {
            grid: { display: false },
            ticks: { color: "#8898b4", font: { size: 9 } },
          },
        },
      },
    });
  });

  logActivity(
    "view",
    "👁",
    "Vendor Viewed",
    `Opened detail page for ${v.name}`,
  );
}

function closeModal(e) {
  if (e.target.id === "modal-overlay") closeModalDirect();
}
function closeModalDirect() {
  document.getElementById("modal-overlay").classList.add("hidden");
}

// ══════════════════════════════════
// EXPORTS
// ══════════════════════════════════
function exportCSV() {
  const all = getAllAssessments();
  if (!all.length) {
    toast("No data to export.");
    return;
  }
  const headers = [
    "Vendor",
    "Score (%)",
    "Rank",
    "Password %",
    "Phishing %",
    "Device %",
    "Network %",
    "Date",
    "Flagged",
  ];
  const rows = all.map((r) => {
    const v = VENDORS_DATA.find((x) => x.id === r.vendorId);
    return [
      `"${r.vendorName}"`,
      r.pct,
      r.rank,
      r.catPct?.password ?? "",
      r.catPct?.phishing ?? "",
      r.catPct?.device ?? "",
      r.catPct?.network ?? "",
      fmtDate(r.date),
      v?.flagged ? "Yes" : "No",
    ].join(",");
  });
  const csv = [headers.join(","), ...rows].join("\n");
  const blob = new Blob([csv], { type: "text/csv" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "cybershield_admin_report.csv";
  a.click();
  URL.revokeObjectURL(url);
  toast("📥 CSV exported!");
  logActivity("export", "📥", "CSV Export", "Full vendor data exported to CSV");
}

function exportPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  const latest = getLatestPerVendor(),
    counts = getRiskCounts(latest);
  const avgScore = latest.length
    ? Math.round(latest.reduce((s, r) => s + r.pct, 0) / latest.length)
    : 0;
  const all = getAllAssessments();
  doc.setFillColor(10, 15, 30);
  doc.rect(0, 0, 210, 40, "F");
  doc.setFillColor(91, 79, 232);
  doc.rect(0, 38, 210, 2, "F");
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(20);
  doc.setFont("helvetica", "bold");
  doc.text("CyberShield", 14, 18);
  doc.setFontSize(10);
  doc.setFont("helvetica", "normal");
  doc.text("Admin Risk Assessment Report", 14, 28);
  doc.setFontSize(9);
  doc.setTextColor(148, 163, 184);
  doc.text(
    `Generated: ${new Date().toLocaleDateString("en-US", { month: "long", day: "numeric", year: "numeric" })}`,
    140,
    28,
  );
  doc.setTextColor(30, 30, 30);
  doc.setFontSize(13);
  doc.setFont("helvetica", "bold");
  doc.text("Executive Summary", 14, 52);
  doc.setFontSize(10);
  doc.setFont("helvetica", "normal");
  doc.setTextColor(80, 80, 80);
  doc.text(`Total Vendors: ${VENDORS_DATA.length}`, 14, 62);
  doc.text(`Total Assessments: ${all.length}`, 14, 70);
  doc.text(`Average Score: ${avgScore}%`, 14, 78);
  const flagged = VENDORS_DATA.filter((v) => v.flagged).length;
  doc.text(`Flagged Vendors: ${flagged}`, 14, 86);
  doc.setFontSize(13);
  doc.setFont("helvetica", "bold");
  doc.setTextColor(30, 30, 30);
  doc.text("Risk Distribution", 14, 102);
  const riskData = [
    { rank: "A", label: "Low Risk", count: counts.A, color: [16, 217, 130] },
    {
      rank: "B",
      label: "Moderate Risk",
      count: counts.B,
      color: [245, 183, 49],
    },
    { rank: "C", label: "High Risk", count: counts.C, color: [255, 122, 69] },
    {
      rank: "D",
      label: "Critical Risk",
      count: counts.D,
      color: [255, 77, 106],
    },
  ];
  riskData.forEach((r, i) => {
    const y = 112 + i * 16,
      total = VENDORS_DATA.length,
      w = total > 0 ? 90 * (r.count / total) : 0;
    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(60, 60, 60);
    doc.text(`Rank ${r.rank} — ${r.label}`, 14, y);
    doc.setFillColor(20, 31, 56);
    doc.rect(80, y - 5, 90, 7, "F");
    doc.setFillColor(...r.color);
    doc.rect(80, y - 5, w, 7, "F");
    doc.setTextColor(30, 30, 30);
    doc.text(
      `${r.count} vendors (${total ? Math.round((r.count / total) * 100) : 0}%)`,
      176,
      y,
    );
  });
  doc.setFontSize(13);
  doc.setFont("helvetica", "bold");
  doc.setTextColor(30, 30, 30);
  doc.text("Vendor Summary (Latest)", 14, 184);
  doc.setFontSize(9);
  doc.setFont("helvetica", "bold");
  doc.setTextColor(100, 100, 100);
  doc.text("Vendor", 14, 194);
  doc.text("Score", 110, 194);
  doc.text("Rank", 140, 194);
  doc.text("Flag", 165, 194);
  doc.text("Date", 178, 194);
  doc.setDrawColor(40, 50, 70);
  doc.line(14, 196, 196, 196);
  latest.slice(0, 12).forEach((v, i) => {
    const y = 203 + i * 10;
    if (y > 275) return;
    const vd = VENDORS_DATA.find((x) => x.id === v.vendorId);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(40, 40, 40);
    doc.text(v.vendorName.substring(0, 28), 14, y);
    doc.text(`${v.pct}%`, 110, y);
    doc.text(v.rank, 140, y);
    doc.text(vd?.flagged ? "🚩" : "—", 165, y);
    doc.text(fmtDate(v.date), 178, y);
    if (i % 2 === 1) {
      doc.setFillColor(20, 28, 48);
      doc.rect(14, y - 5, 182, 9, "F");
    }
  });
  doc.setFillColor(10, 15, 30);
  doc.rect(0, 283, 210, 14, "F");
  doc.setFontSize(8);
  doc.setTextColor(74, 90, 122);
  doc.text("Confidential — CyberShield Admin Report", 14, 291);
  doc.text("Page 1 of 1", 185, 291);
  doc.save("CyberShield_Admin_Report.pdf");
  toast("📄 PDF exported!");
  logActivity(
    "export",
    "📄",
    "PDF Export",
    "Admin summary report exported to PDF",
  );
}

// ══════════════════════════════════
// PROFILE
// ══════════════════════════════════
function renderProfile() {
  const prof = JSON.parse(localStorage.getItem("cs_admin_profile") || "{}");
  const name = prof.name || "Administrator",
    email = "admin@cybershield.com",
    org = prof.org || "";
  const nameEl = document.getElementById("profile-name");
  const emailEl = document.getElementById("profile-email");
  const orgEl = document.getElementById("profile-org");
  if (nameEl) nameEl.value = name;
  if (emailEl) emailEl.value = email;
  if (orgEl) orgEl.value = org;
  const dispEl = document.getElementById("profile-name-display"),
    dispEmail = document.getElementById("profile-email-display");
  if (dispEl) dispEl.textContent = name;
  if (dispEmail) dispEmail.textContent = email;
  const avatarEl = document.getElementById("profile-avatar");
  if (avatarEl) avatarEl.textContent = name.charAt(0).toUpperCase();

  // Pref toggles
  const prefDark = document.getElementById("pref-dark");
  if (prefDark)
    prefDark.checked =
      document.documentElement.getAttribute("data-theme") === "dark";
  const prefAlerts = document.getElementById("pref-alerts");
  if (prefAlerts) {
    prefAlerts.checked = getPref("alerts");
    prefAlerts.onchange = () => savePref("alerts", prefAlerts.checked);
  }
  const prefAR = document.getElementById("pref-autorefresh");
  if (prefAR) {
    prefAR.checked = getPref("autorefresh");
    prefAR.onchange = () => savePref("autorefresh", prefAR.checked);
  }

  // Stats
  const all = getAllAssessments(),
    latest = getLatestPerVendor();
  const flagged = VENDORS_DATA.filter((v) => v.flagged).length;
  const avg = latest.length
    ? Math.round(latest.reduce((s, r) => s + r.pct, 0) / latest.length)
    : 0;
  const el = document.getElementById("profile-stats-grid");
  if (el)
    el.innerHTML = [
      { label: "Total Vendors", val: VENDORS_DATA.length },
      { label: "Assessments", val: all.length },
      { label: "Avg Score", val: avg + "%" },
      { label: "Flagged", val: flagged },
    ]
      .map(
        (s) =>
          `<div class="profile-stat-item"><div class="label">${s.label}</div><div class="val mono">${s.val}</div></div>`,
      )
      .join("");
}

function saveProfile() {
  const name =
    document.getElementById("profile-name").value.trim() || "Administrator";
  const org = document.getElementById("profile-org").value.trim();
  localStorage.setItem("cs_admin_profile", JSON.stringify({ name, org }));
  const sbName = document.getElementById("sb-admin-name");
  if (sbName) sbName.textContent = name;
  const dispEl = document.getElementById("profile-name-display");
  if (dispEl) dispEl.textContent = name;
  const avatarEl = document.getElementById("profile-avatar");
  if (avatarEl) avatarEl.textContent = name.charAt(0).toUpperCase();
  toast("✅ Profile saved!");
  logActivity(
    "profile",
    "⚙️",
    "Profile Updated",
    `Admin name updated to "${name}"`,
  );
}

// ══════════════════════════════════
// TOAST
// ══════════════════════════════════
function toast(msg) {
  const el = document.createElement("div");
  el.className = "toast";
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 2800);
}

// ══════════════════════════════════
// INIT
// ══════════════════════════════════
window.addEventListener("load", () => {
  initApp();
});

// ══════════════════════════════════
// SECTION TITLES UPDATE
// ══════════════════════════════════
Object.assign(SECTION_TITLES, {
  compare: ["Vendor Comparison", "Compare two vendors side by side"],
  forecast: ["Risk Score Forecast", "Predicted next scores based on trends"],
  compliance: [
    "Compliance Checklist",
    "Per-vendor action items and remediation steps",
  ],
  email: ["Send Email Report", "Send simulated assessment reports to vendors"],
});

// ══════════════════════════════════
// EXTENDED renderAll + switchSection
// ══════════════════════════════════
var _origRenderAll = renderAll;
renderAll = function () {
  _origRenderAll();
  populateVendorSelects();
};

var _origSwitchSection = switchSection;
switchSection = function (name) {
  _origSwitchSection(name);
  if (name === "compare") {
    populateVendorSelects();
    renderComparison();
  }
  if (name === "forecast") renderForecast();
  if (name === "compliance") {
    populateVendorSelects();
    renderCompliance();
  }
  if (name === "email") {
    populateVendorSelects();
    updateEmailPreview();
    renderEmailLog();
  }
};

function populateVendorSelects() {
  const selects = [
    "compare-a",
    "compare-b",
    "compliance-vendor",
    "email-vendor",
  ];
  selects.forEach(function (id) {
    const el = document.getElementById(id);
    if (!el) return;
    const current = el.value;
    el.innerHTML = VENDORS_DATA.map(function (v, i) {
      const latest = v.history[v.history.length - 1];
      const score = latest
        ? latest.pct + "% · " + RANK_CFG[latest.rank].label
        : "No data";
      return `<option value="${v.id}">${v.name} — ${score}</option>`;
    }).join("");
    if (current) el.value = current;
  });
  // Default compare-b to second vendor
  const cb = document.getElementById("compare-b");
  if (cb && cb.options.length > 1 && !cb.value) cb.selectedIndex = 1;
}

// ══════════════════════════════════
// VENDOR COMPARISON
// ══════════════════════════════════
function renderComparison() {
  const aId = parseInt(document.getElementById("compare-a")?.value);
  const bId = parseInt(document.getElementById("compare-b")?.value);
  const va = VENDORS_DATA.find((x) => x.id === aId);
  const vb = VENDORS_DATA.find((x) => x.id === bId);
  const el = document.getElementById("comparison-result");
  if (!va || !vb || !el) return;

  const la = va.history[va.history.length - 1];
  const lb = vb.history[vb.history.length - 1];
  if (!la || !lb) {
    el.innerHTML =
      '<p style="color:var(--text-3)">No assessment data available.</p>';
    return;
  }

  const cats = [
    { key: "password", label: "🔑 Password", icon: "🔑" },
    { key: "phishing", label: "🎣 Phishing", icon: "🎣" },
    { key: "device", label: "💻 Device", icon: "💻" },
    { key: "network", label: "📡 Network", icon: "📡" },
  ];

  function colColor(id) {
    const colors = [
      "#7B72F0",
      "#10D982",
      "#F5B731",
      "#FF7A45",
      "#FF4D6A",
      "#9B8FFE",
    ];
    return colors[id % colors.length];
  }

  function catRow(cat) {
    const pa = la.catPct?.[cat.key] || 0;
    const pb = lb.catPct?.[cat.key] || 0;
    const winner = pa > pb ? "A" : pb > pa ? "B" : "=";
    const colorA =
      pa >= 80
        ? "#10D982"
        : pa >= 60
          ? "#F5B731"
          : pa >= 40
            ? "#FF7A45"
            : "#FF4D6A";
    const colorB =
      pb >= 80
        ? "#10D982"
        : pb >= 60
          ? "#F5B731"
          : pb >= 40
            ? "#FF7A45"
            : "#FF4D6A";
    return `
      <div class="compare-cat-row">
        <span class="compare-cat-label">${cat.label}</span>
        <span class="compare-cat-pct" style="color:${colorA}">${pa}%</span>
        <div class="compare-bar-wrap" style="direction:rtl">
          <div class="compare-bar-fill" style="width:${pa}%;background:${colorA};direction:ltr"></div>
        </div>
        <span style="font-size:.8rem;font-weight:700;color:var(--text-3);min-width:1.2rem;text-align:center;">${winner === "A" ? "◀" : winner === "=" ? "▬" : ""}</span>
        <div class="compare-bar-wrap">
          <div class="compare-bar-fill" style="width:${pb}%;background:${colorB}"></div>
        </div>
        <span class="compare-cat-pct" style="color:${colorB}">${pb}%</span>
      </div>`;
  }

  const aCfg = RANK_CFG[la.rank],
    bCfg = RANK_CFG[lb.rank];
  const overallWinner =
    la.pct > lb.pct ? va.name : lb.pct > la.pct ? vb.name : "Tie";

  el.innerHTML = `
    <div class="compare-grid">
      <div class="compare-col">
        <div class="compare-col-header">
          <div class="compare-avatar" style="background:${colColor(aId)}">${va.name.charAt(0)}</div>
          <div>
            <div class="compare-name">${va.name}</div>
            <div class="compare-score-text"><span class="rank-pill ${la.rank}">${la.rank}</span> &nbsp; ${la.pct}% · ${aCfg.label}</div>
          </div>
        </div>
        ${cats
          .map((c) => catRow(c))
          .join("")
          .replace(/compare-cat-row/g, "compare-cat-row a-side")}
      </div>
      <div class="compare-divider">
        <div class="compare-divider-line"></div>
        <div class="compare-divider-label">VS</div>
        <div class="compare-divider-line"></div>
      </div>
      <div class="compare-col">
        <div class="compare-col-header">
          <div class="compare-avatar" style="background:${colColor(bId)}">${vb.name.charAt(0)}</div>
          <div>
            <div class="compare-name">${vb.name}</div>
            <div class="compare-score-text"><span class="rank-pill ${lb.rank}">${lb.rank}</span> &nbsp; ${lb.pct}% · ${bCfg.label}</div>
          </div>
        </div>
        ${cats
          .map((c) => {
            const pa = la.catPct?.[c.key] || 0,
              pb = lb.catPct?.[c.key] || 0;
            const colorA =
              pa >= 80
                ? "#10D982"
                : pa >= 60
                  ? "#F5B731"
                  : pa >= 40
                    ? "#FF7A45"
                    : "#FF4D6A";
            const colorB =
              pb >= 80
                ? "#10D982"
                : pb >= 60
                  ? "#F5B731"
                  : pb >= 40
                    ? "#FF7A45"
                    : "#FF4D6A";
            const winner = pa > pb ? "A" : pb > pa ? "B" : "=";
            return `<div class="compare-cat-row">
            <span class="compare-cat-label">${c.label}</span>
            <span class="compare-cat-pct" style="color:${colorB}">${pb}%</span>
            <div class="compare-bar-wrap"><div class="compare-bar-fill" style="width:${pb}%;background:${colorB}"></div></div>
            <span style="font-size:.8rem;font-weight:700;color:var(--text-3);min-width:1.2rem;text-align:center;">${winner === "B" ? "◀" : winner === "=" ? "▬" : ""}</span>
            <div class="compare-bar-wrap" style="direction:rtl"><div class="compare-bar-fill" style="width:${pa}%;background:${colorA};direction:ltr"></div></div>
            <span class="compare-cat-pct" style="color:${colorA}">${pa}%</span>
          </div>`;
          })
          .join("")}
      </div>
    </div>
    <div style="margin-top:1.25rem;padding:1rem 1.25rem;background:var(--indigo-faint);border:1px solid rgba(91,79,232,.2);border-radius:10px;font-size:.85rem;">
      <strong>Overall Winner:</strong> ${overallWinner === "Tie" ? "🤝 Tie — Both vendors scored equally" : `🏆 ${overallWinner} (${Math.max(la.pct, lb.pct)}%)`}
    </div>`;
}

// ══════════════════════════════════
// RISK SCORE FORECAST
// ══════════════════════════════════
function predictScore(history) {
  if (history.length < 2)
    return { predicted: history[0]?.pct || 50, trend: "flat", delta: 0 };
  // Linear regression on last 3–5 points
  const pts = history.slice(-5).map((h, i) => ({ x: i, y: h.pct }));
  const n = pts.length;
  const sumX = pts.reduce((s, p) => s + p.x, 0);
  const sumY = pts.reduce((s, p) => s + p.y, 0);
  const sumXY = pts.reduce((s, p) => s + p.x * p.y, 0);
  const sumX2 = pts.reduce((s, p) => s + p.x * p.x, 0);
  const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
  const intercept = (sumY - slope * sumX) / n;
  const predicted = Math.round(
    Math.min(100, Math.max(0, intercept + slope * n)),
  );
  const delta = predicted - history[history.length - 1].pct;
  const trend = delta > 2 ? "up" : delta < -2 ? "down" : "flat";
  return { predicted, trend, delta };
}

function renderForecast(filter) {
  filter = filter || "all";
  let vendors = [...VENDORS_DATA].filter((v) => v.history.length >= 2);
  const forecasts = vendors.map((v) => {
    const f = predictScore(v.history);
    const latest = v.history[v.history.length - 1];
    return {
      ...f,
      id: v.id,
      name: v.name,
      current: latest.pct,
      currentRank: latest.rank,
    };
  });

  let filtered = forecasts;
  if (filter === "improving")
    filtered = forecasts.filter((f) => f.trend === "up");
  else if (filter === "declining")
    filtered = forecasts.filter((f) => f.trend === "down");
  else if (filter === "atrisk")
    filtered = forecasts.filter((f) => f.predicted < 40);

  filtered.sort((a, b) => a.predicted - b.predicted);

  const el = document.getElementById("forecast-grid");
  if (!el) return;
  if (!filtered.length) {
    el.innerHTML =
      '<p style="color:var(--text-3);text-align:center;padding:2rem;">No vendors match this filter.</p>';
    return;
  }

  const cards = filtered
    .map((f) => {
      const color =
        f.predicted >= 80
          ? "#10D982"
          : f.predicted >= 60
            ? "#F5B731"
            : f.predicted >= 40
              ? "#FF7A45"
              : "#FF4D6A";
      const trendLabel =
        f.trend === "up"
          ? `↑ +${Math.abs(f.delta)}%`
          : f.trend === "down"
            ? `↓ ${f.delta}%`
            : "→ Stable";
      const arrowIcon =
        f.trend === "up" ? "📈" : f.trend === "down" ? "📉" : "➡️";
      return `<div class="forecast-card">
      <div class="forecast-name">${f.name}</div>
      <div class="forecast-scores">
        <span class="forecast-current">${f.current}%</span>
        <span class="forecast-arrow">${arrowIcon}</span>
        <span class="forecast-predicted" style="color:${color}">${f.predicted}%</span>
        <span class="forecast-trend ${f.trend}" style="margin-left:auto">${trendLabel}</span>
      </div>
      <div style="font-size:.74rem;color:var(--text-3);">Predicted next assessment score</div>
      <div class="forecast-bar-wrap"><div class="forecast-bar-fill" style="width:${f.predicted}%;background:${color}"></div></div>
    </div>`;
    })
    .join("");

  el.innerHTML = `<div class="forecast-grid-inner">${cards}</div>`;
}

function filterForecast(filter, btn) {
  document
    .querySelectorAll(".forecast-filter")
    .forEach((b) => b.classList.remove("active"));
  btn.classList.add("active");
  renderForecast(filter);
}

// ══════════════════════════════════
// COMPLIANCE CHECKLIST
// ══════════════════════════════════
const COMPLIANCE_ITEMS = {
  password: [
    {
      id: "pw1",
      title: "Deploy Password Manager",
      desc: "Mandate use of an approved password manager (e.g. Bitwarden, 1Password) for all staff.",
      priority: "high",
    },
    {
      id: "pw2",
      title: "Enable MFA on All Accounts",
      desc: "Enforce multi-factor authentication on email, VPN, and SaaS platforms.",
      priority: "high",
    },
    {
      id: "pw3",
      title: "Password Policy Review",
      desc: "Update policy to require minimum 16 characters with complexity requirements.",
      priority: "medium",
    },
    {
      id: "pw4",
      title: "Quarterly Password Audit",
      desc: "Schedule automated audits to detect weak or reused credentials.",
      priority: "low",
    },
  ],
  phishing: [
    {
      id: "ph1",
      title: "Phishing Simulation Training",
      desc: "Run monthly simulated phishing campaigns and mandatory awareness training.",
      priority: "high",
    },
    {
      id: "ph2",
      title: "Email Gateway Filtering",
      desc: "Implement advanced email filtering (DMARC, SPF, DKIM) to block spoofed domains.",
      priority: "high",
    },
    {
      id: "ph3",
      title: "Incident Response Procedure",
      desc: "Define clear steps for reporting and responding to suspected phishing attempts.",
      priority: "medium",
    },
  ],
  device: [
    {
      id: "dv1",
      title: "Full-Disk Encryption",
      desc: "Enable BitLocker or FileVault on all company devices within 30 days.",
      priority: "high",
    },
    {
      id: "dv2",
      title: "Patch Management Policy",
      desc: "Establish automated patching with max 7-day window for critical updates.",
      priority: "high",
    },
    {
      id: "dv3",
      title: "Screen Lock Policy",
      desc: "Configure auto-lock after 5 minutes of inactivity on all endpoints.",
      priority: "medium",
    },
    {
      id: "dv4",
      title: "Mobile Device Management",
      desc: "Enroll all mobile devices in MDM solution for remote wipe capability.",
      priority: "low",
    },
  ],
  network: [
    {
      id: "nw1",
      title: "VPN Mandate for Remote Work",
      desc: "Require company-issued VPN for all access to internal systems from external networks.",
      priority: "high",
    },
    {
      id: "nw2",
      title: "Network Segmentation",
      desc: "Implement VLAN segmentation to isolate sensitive systems from general access.",
      priority: "medium",
    },
    {
      id: "nw3",
      title: "Wi-Fi Security Audit",
      desc: "Audit and upgrade wireless protocols to WPA3; disable legacy WEP/WPA connections.",
      priority: "medium",
    },
    {
      id: "nw4",
      title: "USB Policy Enforcement",
      desc: "Block unknown USB devices via endpoint security policy.",
      priority: "low",
    },
  ],
};

function getComplianceState(vendorId) {
  const key = "cs_compliance_" + vendorId;
  return JSON.parse(localStorage.getItem(key) || "{}");
}
function saveComplianceState(vendorId, state) {
  localStorage.setItem("cs_compliance_" + vendorId, JSON.stringify(state));
}

function toggleChecklistItem(vendorId, itemId) {
  const state = getComplianceState(vendorId);
  state[itemId] = !state[itemId];
  saveComplianceState(vendorId, state);
  renderCompliance();
  logActivity(
    "compliance",
    "✅",
    "Compliance Updated",
    "Item " + itemId + " toggled for vendor ID " + vendorId,
  );
}

function renderCompliance() {
  const sel = document.getElementById("compliance-vendor");
  if (!sel) return;
  const vendorId = parseInt(sel.value);
  const v = VENDORS_DATA.find((x) => x.id === vendorId);
  const el = document.getElementById("compliance-content");
  if (!v || !el) return;

  const latest = v.history[v.history.length - 1];
  if (!latest) {
    el.innerHTML =
      '<p style="color:var(--text-3)">No assessment data for this vendor.</p>';
    return;
  }

  const state = getComplianceState(vendorId);
  const catPct = latest.catPct || {};

  // Determine which categories need attention (score < 70)
  const weakCats = Object.entries(catPct)
    .filter(([k, v]) => v < 70)
    .sort((a, b) => a[1] - b[1])
    .map((e) => e[0]);
  const allItems = weakCats.flatMap((cat) => COMPLIANCE_ITEMS[cat] || []);
  const totalItems = allItems.length;
  const doneCount = allItems.filter((item) => state[item.id]).length;
  const pct = totalItems ? Math.round((doneCount / totalItems) * 100) : 100;

  const cfg = RANK_CFG[latest.rank];
  const rankColor = cfg.color;

  let sectionsHtml = "";
  const catLabels = {
    password: "🔑 Password Security",
    phishing: "🎣 Phishing Awareness",
    device: "💻 Device Safety",
    network: "📡 Network Safety",
  };

  if (!weakCats.length) {
    sectionsHtml = `<div style="text-align:center;padding:2rem;color:var(--green);">✅ All categories are at acceptable levels (≥70%). No immediate action required.</div>`;
  } else {
    weakCats.forEach((cat) => {
      const items = COMPLIANCE_ITEMS[cat] || [];
      const rows = items
        .map((item) => {
          const done = state[item.id];
          const pColors = { high: "p-high", medium: "p-medium", low: "p-low" };
          return `<div class="checklist-item${done ? " done" : ""}" onclick="toggleChecklistItem(${vendorId},'${item.id}')">
          <div class="check-box">${done ? "✓" : ""}</div>
          <div style="flex:1">
            <div class="check-title">${item.title}</div>
            <div class="check-desc">${item.desc}</div>
          </div>
          <span class="check-priority ${pColors[item.priority]}">${item.priority.toUpperCase()}</span>
        </div>`;
        })
        .join("");
      sectionsHtml += `<div class="compliance-section">
        <div class="compliance-section-title">${catLabels[cat]} — Avg: ${catPct[cat]}%</div>
        ${rows}
      </div>`;
    });
  }

  el.innerHTML = `
    <div class="compliance-header">
      <div class="compliance-score-badge" style="background:${cfg.faint};color:${rankColor}">${latest.rank}</div>
      <div>
        <div class="compliance-name">${v.name}</div>
        <div class="compliance-sub">Overall Score: ${latest.pct}% · ${cfg.label} · ${weakCats.length} categories need action</div>
      </div>
    </div>
    <div class="compliance-progress">
      <span style="font-size:.82rem;font-weight:600;color:var(--text-2);">Remediation Progress</span>
      <div class="compliance-progress-bar"><div class="compliance-progress-fill" style="width:${pct}%"></div></div>
      <span class="compliance-progress-text">${doneCount}/${totalItems} complete (${pct}%)</span>
    </div>
    ${sectionsHtml}`;
}

// ══════════════════════════════════
// EMAIL REPORT (SIMULATED)
// ══════════════════════════════════
function updateEmailPreview() {
  const vendorId = parseInt(document.getElementById("email-vendor")?.value);
  const toEmail =
    document.getElementById("email-to")?.value || "vendor@company.com";
  const subject =
    document.getElementById("email-subject")?.value ||
    "CyberShield Risk Assessment Report";
  const note = document.getElementById("email-note")?.value || "";
  const el = document.getElementById("email-preview");
  if (!el) return;

  const v = VENDORS_DATA.find((x) => x.id === vendorId);
  if (!v) return;
  const latest = v.history[v.history.length - 1];
  if (!latest) {
    el.innerHTML =
      '<p style="color:var(--text-3)">No assessment data available.</p>';
    return;
  }

  const cfg = RANK_CFG[latest.rank];
  const cats = {
    password: "🔑 Password",
    phishing: "🎣 Phishing",
    device: "💻 Device",
    network: "📡 Network",
  };
  const catRows = Object.entries(cats)
    .map(([k, label]) => {
      const pct = latest.catPct?.[k] || 0;
      const color =
        pct >= 80
          ? "#10D982"
          : pct >= 60
            ? "#F5B731"
            : pct >= 40
              ? "#FF7A45"
              : "#FF4D6A";
      return `<div style="display:flex;justify-content:space-between;font-size:.78rem;margin:.2rem 0;"><span style="color:var(--text-2)">${label}</span><span style="font-weight:700;color:${color}">${pct}%</span></div>`;
    })
    .join("");

  el.innerHTML = `
    <div class="ep-from">From: noreply@cybershield.com &nbsp;|&nbsp; CyberShield Platform</div>
    <div class="ep-to">To: ${toEmail || "—"}</div>
    <div class="ep-subject">${subject}</div>
    <div class="ep-divider"></div>
    <div class="ep-body">
      <p>Dear <strong>${v.name}</strong> Team,</p>
      <p style="margin-top:.6rem;">Your latest <strong>CyberShield Cyber Hygiene Assessment</strong> results are now available. Please review your risk profile below.</p>
      <div class="ep-score-block">
        <div class="ep-rank-badge" style="background:${cfg.faint};color:${cfg.color}">${latest.rank}</div>
        <div>
          <div style="font-weight:700;color:var(--text);">${cfg.label}</div>
          <div style="font-size:.8rem;color:var(--text-2);">Score: ${latest.pct}% &nbsp;·&nbsp; ${latest.score}/${latest.max} pts &nbsp;·&nbsp; ${fmtDate(latest.date)}</div>
        </div>
      </div>
      <div style="margin:.6rem 0;">${catRows}</div>
      ${note ? `<div style="background:var(--navy-2);border-radius:8px;padding:.65rem .85rem;margin-top:.6rem;font-style:italic;color:var(--text-2);font-size:.8rem;">"${note}"</div>` : ""}
      <p style="margin-top:.75rem;font-size:.78rem;color:var(--text-3);">To improve your score, please review the recommendations in your assessment portal. This is an automated report from the CyberShield platform.</p>
    </div>`;
}

function sendEmailReport() {
  const vendorId = parseInt(document.getElementById("email-vendor")?.value);
  const toEmail = (document.getElementById("email-to")?.value || "").trim();
  const subject =
    document.getElementById("email-subject")?.value || "CyberShield Report";
  const note = document.getElementById("email-note")?.value || "";
  const v = VENDORS_DATA.find((x) => x.id === vendorId);
  if (!v) return;
  if (!toEmail) {
    toast("⚠ Please enter a recipient email.");
    return;
  }

  const latest = v.history[v.history.length - 1];
  const sentLog = JSON.parse(localStorage.getItem("cs_email_log") || "[]");
  sentLog.unshift({
    vendorName: v.name,
    to: toEmail,
    subject,
    note,
    score: latest?.pct || "—",
    rank: latest?.rank || "—",
    time: new Date().toISOString(),
  });
  localStorage.setItem("cs_email_log", JSON.stringify(sentLog.slice(0, 50)));

  toast("📧 Report sent to " + toEmail + " (simulated)");
  addNotif(
    "📧",
    "Report Sent",
    `Assessment report for ${v.name} sent to ${toEmail}`,
  );
  logActivity(
    "export",
    "📧",
    "Email Report Sent",
    `Report for ${v.name} → ${toEmail}`,
  );
  renderEmailLog();

  // Clear fields
  const noteEl = document.getElementById("email-note");
  if (noteEl) noteEl.value = "";
  updateEmailPreview();
}

function renderEmailLog() {
  const el = document.getElementById("email-sent-log");
  if (!el) return;
  const log = JSON.parse(localStorage.getItem("cs_email_log") || "[]");
  if (!log.length) {
    el.innerHTML =
      '<p style="color:var(--text-3);font-size:.84rem;">No reports sent yet.</p>';
    return;
  }
  el.innerHTML = log
    .map(
      (l) => `
    <div class="email-log-item">
      <span class="email-log-icon">📧</span>
      <div style="flex:1">
        <div style="font-size:.84rem;font-weight:600;">${l.vendorName} → ${l.to}</div>
        <div style="font-size:.76rem;color:var(--text-2);margin-top:.1rem;">${l.subject}</div>
        <div style="font-size:.7rem;color:var(--text-3);margin-top:.15rem;font-family:'DM Mono',monospace;">${fmtDate(l.time)} · ${new Date(l.time).toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" })}</div>
      </div>
      <span class="rank-pill ${l.rank}">${l.rank}</span>
    </div>`,
    )
    .join("");
}
