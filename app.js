const API_BASE = 'api/';

const EMOJIS = {
  Electronics:   '&#128241;',
  'ID / Card':   '&#129706;',
  Keys:          '&#128273;',
  'Bag / Wallet':'&#128092;',
  Clothing:      '&#128084;',
  Stationery:    '&#9999;',
  Other:         '&#128230;'
};

// ======== HTTP helpers ========

async function apiGet(endpoint, params = {}) {
  const qs  = new URLSearchParams(params).toString();
  const url = API_BASE + endpoint + (qs ? '?' + qs : '');
  const res = await fetch(url, { credentials: 'include' });
  return res.json();
}

async function apiPost(endpoint, params = {}, body = {}) {
  const qs  = new URLSearchParams(params).toString();
  const url = API_BASE + endpoint + (qs ? '?' + qs : '');
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return res.json();
}

// ======== Session / User ========

let _cachedUser = null;

function getUser() { return _cachedUser; }

async function initUser() {
  try {
    const data  = await apiGet('auth.php', { action: 'check' });
    _cachedUser = data.loggedIn ? data.user : null;
  } catch { _cachedUser = null; }
}

async function logout() {
  await apiPost('auth.php', { action: 'logout' });
  _cachedUser = null;
  window.location.href = 'index.html';
}

// ======== Posts ========

async function getPosts(filters = {}) {
  const data = await apiGet('posts.php', { action: 'list', ...filters });
  return data.ok ? data.posts : [];
}

// ======== Claims ========

async function submitClaim(postId, message) {
  return apiPost('claims.php', { action: 'submit', post_id: postId }, { message });
}
async function getIncomingClaims() {
  const d = await apiGet('claims.php', { action: 'incoming' });
  return d.ok ? d.claims : [];
}
async function getOutgoingClaims() {
  const d = await apiGet('claims.php', { action: 'outgoing' });
  return d.ok ? d.claims : [];
}
async function getClaimsForPost(postId) {
  const d = await apiGet('claims.php', { action: 'for_post', post_id: postId });
  return d.ok ? d.claims : [];
}
async function acceptClaim(claimId)  { return apiPost('claims.php', { action: 'accept', claim_id: claimId }); }
async function rejectClaim(claimId)  { return apiPost('claims.php', { action: 'reject', claim_id: claimId }); }
async function getClaimBadge() {
  const d = await apiGet('claims.php', { action: 'badge' });
  return d.ok ? d : { claims: 0, contacts: 0, total: 0 };
}

// ======== Contacts ========

async function sendContact(postId, message_note, contact_info) {
  return apiPost('contacts.php', { action: 'send', post_id: postId }, { message_note, contact_info });
}
async function getContactInbox() {
  const d = await apiGet('contacts.php', { action: 'inbox' });
  return d.ok ? d.contacts : [];
}
async function getContactSent() {
  const d = await apiGet('contacts.php', { action: 'sent' });
  return d.ok ? d.contacts : [];
}
async function markContactRead(contactId) {
  return apiPost('contacts.php', { action: 'read', contact_id: contactId });
}

// ======== Nav badge ========

async function loadNavBadge() {
  const user = getUser();
  if (!user) return;
  try {
    const b = await getClaimBadge();
    const el = document.getElementById('navBadge');
    if (el) {
      el.textContent = b.total;
      el.style.display = b.total > 0 ? 'inline-flex' : 'none';
    }
  } catch {}
}

// ======== Nav HTML ========

function getNavHTML(activePage) {
  const user = getUser();

  const guestActions = `
    <button class="btn btn-outline-white btn-sm" onclick="window.location.href='login.html'">Log In</button>
    <button class="btn btn-white btn-sm" onclick="window.location.href='signup.html'">Sign Up</button>
  `;

  const userActions = user ? `
    <button class="btn btn-accent btn-sm" onclick="window.location.href='newpost.html'">+ New Post</button>
    <div class="user-menu">
      <div class="user-chip" onclick="toggleUserMenu()" id="userChip">
        <span id="navUserInitials">${user.initials}</span>
        <span id="navUserName">${user.name.split(' ')[0]}</span>
        <span class="notif-badge" id="navBadge" style="display:none">0</span>
      </div>
      <div class="user-menu-dropdown" id="userMenuDropdown">
        <a href="myposts.html">&#128238; My Posts</a>
        <a href="inbox.html">&#9993; Inbox <span class="notif-badge" id="navBadge2" style="display:none;background:var(--accent);color:white;font-size:10px;padding:1px 5px;border-radius:10px;margin-left:4px;">0</span></a>
        <div class="menu-divider"></div>
        <button onclick="logout()">Log Out</button>
      </div>
    </div>
  ` : '';

  return `
    <nav>
      <div class="nav-inner">
        <a class="nav-brand" href="index.html">
         <img src="logo.png" class="nav-logo" style="object-fit:contain;padding:4px; background:none;">
          <div>
            <div class="nav-title">FTMK Lost &amp; Found</div>
            <div class="nav-subtitle">Faculty of ICT in UTeM</div>
          </div>
        </a>
        <div class="nav-actions" id="navActions" style="${user ? 'display:none' : ''}">
          ${guestActions}
        </div>
        <div class="nav-actions" id="navLoggedIn" style="${user ? '' : 'display:none'}">
          ${userActions}
        </div>
      </div>
    </nav>
  `;
}

function toggleUserMenu() {
  document.getElementById('userMenuDropdown')?.classList.toggle('open');
}
document.addEventListener('click', e => {
  const dd = document.getElementById('userMenuDropdown');
  if (dd && !e.target.closest('.user-menu')) dd.classList.remove('open');
});

// ======== Toast ========

let _toastTimer;
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent  = msg;
  t.className    = type ? `show toast-${type}` : 'show';
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => t.classList.remove('show'), 2800);
}

// ======== Helpers ========

function formatDate(d) {
  if (!d) return '';
  return new Date(d).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
}

function formatDateTime(d) {
  if (!d) return '';
  return new Date(d).toLocaleString('en-GB', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

function claimStatusBadge(status) {
  const map = {
    pending:  '<span class="badge badge-pending">&#9679; Pending</span>',
    accepted: '<span class="badge badge-accepted">Accepted</span>',
    rejected: '<span class="badge badge-rejected">&#10005; Rejected</span>',
  };
  return map[status] || status;
}

// Modal helpers (shared dengan semua page)
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    closeModal(e.target.id);
  }
});
