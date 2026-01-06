// qcm_app/frontend/app.js
// Minimal frontend V1: authenticate via middleware (/api/me), fetch exams and show leaderboard

const API_BASE = '/qcm/middleware/index.php';
const LOGIN_ENDPOINT = API_BASE.replace('/middleware/index.php', '/public/index.php') + '?action=login';

function el(q) { return document.querySelector(q); }

async function fetchExams() {
  const container = el('#exams-list');
  try {
    const res = await fetch(API_BASE + '/api/exams');
    const json = await res.json();
    if (!res.ok) throw new Error(json?.data?.message || 'Erreur API');
    renderExams(json.data);
  } catch (err) {
    container.innerHTML = '<p class="error">Impossible de charger les examens: ' + escapeHtml(err.message) + '</p>';
  }
}

function renderExams(exams) {
  const container = el('#exams-list');
  if (!exams || exams.length === 0) {
    container.innerHTML = '<p>Aucun examen trouvé.</p>';
    return;
  }
  const ul = document.createElement('ul');
  exams.forEach(e => {
    const li = document.createElement('li');
    li.innerHTML = `<strong>${escapeHtml(e.titre || '—')}</strong> (${e.nb_questions ?? '—'} questions) <br><small>${escapeHtml(e.description || '')}</small>`;

    // Show admin challenges button if applicable: for now provide a button that asks for a challenge id
    const btn = document.createElement('button');
    btn.textContent = 'Voir leaderboard (admin challenge)';
    btn.addEventListener('click', () => {
      const challengeId = prompt('Entrez l\'ID du admin_challenge à afficher (ex: 1)');
      if (!challengeId) return;
      fetchLeaderboard(challengeId, e.titre || 'Challenge');
    });

    li.appendChild(document.createElement('br'));
    li.appendChild(btn);
    ul.appendChild(li);
  });
  container.innerHTML = '';
  container.appendChild(ul);
}

async function fetchLeaderboard(challengeId, title) {
  const section = el('#leaderboard');
  const tbody = el('#leaderboard-table tbody');
  const titleDiv = el('#leaderboard-title');
  titleDiv.textContent = 'Challenge: ' + title + ' (ID: ' + challengeId + ')';
  tbody.innerHTML = '<tr><td colspan="5">Chargement…</td></tr>';
  section.classList.remove('hidden');
  try {
    const res = await fetch(`${API_BASE}/api/admin-challenges/${encodeURIComponent(challengeId)}/leaderboard`);
    const json = await res.json();
    if (!res.ok) throw new Error(json?.data?.message || 'Erreur API');
    renderLeaderboardRows(json.data);
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="5" class="error">Erreur: ${escapeHtml(err.message)}</td></tr>`;
  }
}

function renderLeaderboardRows(rows) {
  const tbody = el('#leaderboard-table tbody');
  tbody.innerHTML = '';
  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5">Aucune entrée</td></tr>';
    return;
  }
  rows.forEach((r, idx) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${idx+1}</td><td>${escapeHtml(r.user_identifier || 'Anonyme')}</td><td>${r.percentage ?? '—'}</td><td>${formatSeconds(r.time_spent_seconds)}</td><td>${r.is_forced_submit ? '⚠' : ''}</td>`;
    tbody.appendChild(tr);
  });
}

function formatSeconds(s) {
  if (!s && s !== 0) return '—';
  s = Number(s);
  const h = Math.floor(s/3600); const m = Math.floor((s%3600)/60); const sec = s%60;
  return `${h>0?h+':':''}${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
}

function escapeHtml(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Init
async function fetchMe() {
  try {
    const res = await fetch(API_BASE + '/api/me');
    const json = await res.json();
    if (!res.ok) throw new Error(json?.data?.message || 'Erreur API');
    const data = json.data;
    if (!data || !data.authenticated) {
      renderLoginForm();
      return;
    }
    renderMenus(data.user || {});
    // show exams section
    el('#exams').classList.remove('hidden');
    el('#exams').classList.add('visible');
    el('#exams-list').innerHTML = 'Chargement des examens…';
    await fetchExams();
  } catch (err) {
    // If middleware is unreachable show a friendly error
    el('#auth').innerHTML = '<p class="error">Erreur lors de la vérification de session: ' + escapeHtml(err.message) + '</p>';
  }
}

function renderLoginForm() {
  const auth = el('#auth');
  // Render a JS-handled login form that calls the middleware POST /api/login
  auth.innerHTML = `
    <h2>Connexion</h2>
    <form id="loginForm">
      <label>Utilisateur: <input name="username" required></label><br>
      <label>Mot de passe: <input type="password" name="password" required></label><br>
      <button type="submit">Se connecter</button>
    </form>
    <div id="loginError" class="error"></div>
  `;
  const form = document.getElementById('loginForm');
  const errDiv = document.getElementById('loginError');
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    errDiv.textContent = '';
    const fm = new FormData(form);
    const payload = { username: fm.get('username'), password: fm.get('password') };
    try {
      const res = await fetch(`${API_BASE}/api/login`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload), credentials: 'same-origin'
      });
      const j = await res.json();
      if (!res.ok) {
        const msg = j?.data?.message || (j?.data?.message === undefined ? j?.data : 'Erreur');
        throw new Error(msg);
      }
      // success: refresh session info and UI
      await fetchMe();
    } catch (e) {
      errDiv.textContent = e.message || 'Erreur lors de la connexion';
    }
  });
}

function renderMenus(user) {
  const menu = el('#menu');
  const uname = escapeHtml(user.display_name || user.username || 'Utilisateur');
  const roles = Array.isArray(user.roles) ? user.roles : [];
  const isAdmin = roles.indexOf('admin') !== -1;
  let html = `<p>Bienvenue ${uname} — </p>`;
  html += '<a href="#" id="link-home">Accueil</a> | <a href="#" id="link-history">Mon historique</a>';
  if (isAdmin) {
    html += ' | <a href="#" id="link-admin-exams">Menu d\'édition</a> | <a href="#" id="link-admin-users">Gestion utilisateurs</a>';
  }
  html += ' | <a href="#" id="link-logout">Se déconnecter</a>';
  menu.innerHTML = html;
  menu.classList.remove('hidden');
  // attach logout
  const logoutLink = document.getElementById('link-logout');
  if (logoutLink) {
    logoutLink.addEventListener('click', async (ev) => {
      ev.preventDefault();
      try {
        await fetch(`${API_BASE}/api/logout`, { method: 'POST', credentials: 'same-origin' });
      } catch (e) {
        // ignore
      }
      // Refresh UI
      fetchMe();
    });
  }
}

// Start by checking session
fetchMe();
