(function () {
  'use strict';
  var CFG = window.EFC_CONFIG || {};
  var PRIMARY = CFG.primaryColor || '#1f6feb';
  document.documentElement.style.setProperty('--efc-primary', PRIMARY);

  function detectLang() {
    var htmlLang = (document.documentElement.getAttribute('lang') || '').toLowerCase();
    if (htmlLang.indexOf('kk') === 0 || htmlLang.indexOf('kz') === 0) return 'kk';
    var path = (window.location.pathname || '').toLowerCase();
    if (path.indexOf('/kk') === 0 || path.indexOf('/kz') === 0 || path.indexOf('/kaz') > -1) return 'kk';
    if (CFG.lang === 'kk') return 'kk';
    return 'ru';
  }

  var state = {
    lang: detectLang(),
    open: false,
    qa: [],
    loaded: false,
    messages: []
  };

  function t(ru, kk) { return state.lang === 'kk' ? kk : ru; }

  function normalize(s) {
    if (!s) return '';
    s = String(s).toLowerCase();
    s = s.replace(/ё/g, 'е');
    // keep letters, digits, spaces (unicode)
    s = s.replace(/[^\p{L}\p{N}\s]/gu, ' ');
    s = s.replace(/\s+/g, ' ').trim();
    return s;
  }

  function tokens(s) { return normalize(s).split(' ').filter(Boolean); }

  function levenshtein(a, b) {
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;
    var prev = [];
    for (var j = 0; j <= b.length; j++) prev[j] = j;
    for (var i = 1; i <= a.length; i++) {
      var cur = [i];
      for (var j2 = 1; j2 <= b.length; j2++) {
        var cost = a.charAt(i - 1) === b.charAt(j2 - 1) ? 0 : 1;
        cur[j2] = Math.min(prev[j2] + 1, cur[j2 - 1] + 1, prev[j2 - 1] + cost);
      }
      prev = cur;
    }
    return prev[b.length];
  }

  function scoreQuestion(input, item) {
    var ni = normalize(input);
    var nq = normalize(item.q);
    if (!ni || !nq) return 0;
    if (ni === nq) return 1;
    // substring bonus
    var sub = 0;
    if (ni.length >= 6 && (nq.indexOf(ni) !== -1 || ni.indexOf(nq) !== -1)) sub = 0.88;
    var levRatio = 1 - levenshtein(ni, nq) / Math.max(ni.length, nq.length, 1);
    // jaccard
    var sa = new Set(tokens(input));
    var sb = new Set(tokens(item.q));
    var inter = 0;
    sa.forEach(function (w) { if (sb.has(w)) inter++; });
    var union = sa.size + sb.size - inter;
    var jac = union ? inter / union : 0;
    // also consider answers/categories (helps kk input)
    var extra = 0;
    var fields = [item.a_ru, item.a_kk, item.category];
    fields.forEach(function (f) {
      if (!f) return;
      var nf = normalize(f);
      var jac2 = 0;
      var sf = new Set(tokens(f));
      var inter2 = 0;
      sa.forEach(function (w) { if (sf.has(w)) inter2++; });
      var union2 = sa.size + sf.size - inter2;
      jac2 = union2 ? inter2 / union2 : 0;
      if (jac2 > extra) extra = jac2;
    });
    var s = Math.max(sub, levRatio * 0.85, jac * 0.9, extra * 0.6);
    return s;
  }

  function findAnswer(question) {
    if (!state.qa.length) return null;
    var best = null;
    var bestScore = 0;
    var second = 0;
    state.qa.forEach(function (item) {
      var sc = scoreQuestion(question, item);
      if (sc > bestScore) {
        second = bestScore;
        bestScore = sc;
        best = item;
      } else if (sc > second) {
        second = sc;
      }
    });
    // threshold
    if (!best || bestScore < 0.55) return null;
    // if too close between top two, still return best (exact-ish)
    return { item: best, score: bestScore };
  }

  // ---- DOM ----
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html !== undefined) e.innerHTML = html;
    return e;
  }

  var root = null;
  var panel = null;
  var body = null;
  var chipsWrap = null;

  function build() {
    root = document.getElementById('efc-chatbot-root');
    if (!root) return;
    root.innerHTML = '';
    var widget = el('div', 'efc-widget ' + (CFG.position === 'left' ? 'efc-left' : 'efc-right'));
    widget.style.setProperty('--efc-primary', PRIMARY);

    var toggle = el('button', 'efc-toggle', '💬');
    toggle.setAttribute('aria-label', 'Chat');
    if (CFG.logoUrl) toggle.innerHTML = '<img src="' + CFG.logoUrl + '" alt="" style="width:32px;height:32px;border-radius:50%;">';

    panel = el('div', 'efc-panel' + (state.open ? '' : ' efc-hidden'));
    // header
    var header = el('div', 'efc-header');
    var title = el('span', 'efc-title', CFG.title || 'Чат-бот');
    var langBtn = el('button', 'efc-lang', state.lang.toUpperCase());
    langBtn.addEventListener('click', function () {
      state.lang = state.lang === 'ru' ? 'kk' : 'ru';
      langBtn.textContent = state.lang.toUpperCase();
      addBotMessage(state.lang === 'ru' ? (CFG.greeting && CFG.greeting.ru) : (CFG.greeting && CFG.greeting.kk));
    });
    var closeBtn = el('button', '', '×');
    closeBtn.addEventListener('click', function () { setOpen(false); });
    header.appendChild(title);
    header.appendChild(langBtn);
    header.appendChild(closeBtn);

    body = el('div', 'efc-body');

    var footer = el('div', 'efc-footer');
    chipsWrap = el('div', 'efc-chips');
    footer.appendChild(chipsWrap);
    var inputRow = el('div', 'efc-input-row');
    var input = el('input', 'efc-input');
    input.type = 'text';
    input.placeholder = (CFG.placeholder && CFG.placeholder[state.lang]) || '';
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
    var sendBtn = el('button', 'efc-send', '➤');
    sendBtn.addEventListener('click', send);
    inputRow.appendChild(input);
    inputRow.appendChild(sendBtn);
    footer.appendChild(inputRow);
    var managerBtn = el('button', 'efc-manager', (CFG.managerBtn && CFG.managerBtn[state.lang]) || 'Manager');
    managerBtn.addEventListener('click', openContact);
    footer.appendChild(managerBtn);

    panel.appendChild(header);
    panel.appendChild(body);
    panel.appendChild(footer);

    widget.appendChild(panel);
    widget.appendChild(toggle);
    root.appendChild(widget);

    toggle.addEventListener('click', function () { setOpen(!state.open); });

    // greeting
    addBotMessage((CFG.greeting && CFG.greeting[state.lang]) || '');
    renderChips();
  }

  function setOpen(v) {
    state.open = v;
    if (!panel) return;
    if (v) panel.classList.remove('efc-hidden'); else panel.classList.add('efc-hidden');
  }

  function addMessage(role, text) {
    var msg = el('div', 'efc-msg ' + role);
    var bubble = el('div', 'efc-bubble', escapeHtml(text));
    msg.appendChild(bubble);
    body.appendChild(msg);
    body.scrollTop = body.scrollHeight;
    state.messages.push({ role: role, text: text });
  }
  function addBotMessage(text) { addMessage('bot', text); }
  function addUserMessage(text) { addMessage('user', text); }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function showTyping() {
    var msg = el('div', 'efc-msg bot');
    var bubble = el('div', 'efc-bubble');
    bubble.innerHTML = '<span class="efc-typing"><span></span><span></span><span></span></span>';
    msg.appendChild(bubble);
    body.appendChild(msg);
    body.scrollTop = body.scrollHeight;
    return msg;
  }

  function send() {
    var input = document.querySelector('.efc-input');
    if (!input) return;
    var q = input.value.trim();
    if (!q) return;
    input.value = '';
    addUserMessage(q);
    var typing = showTyping();
    setTimeout(function () {
      typing.remove();
      var res = findAnswer(q);
      if (res) {
        addBotMessage(res.item[state.lang === 'kk' ? 'a_kk' : 'a_ru'] || res.item.a_ru || '');
        logStat(q, 1, res.item.q, res.item.category || '');
      } else {
        addBotMessage((CFG.noAnswer && CFG.noAnswer[state.lang]) || '');
        logStat(q, 0, '', '');
      }
    }, 200);
  }

  function renderChips() {
    if (!chipsWrap) return;
    chipsWrap.innerHTML = '';
    if (!state.qa.length) return;
    var count = CFG.quickCount || 6;
    var items = state.qa.slice(0, count);
    items.forEach(function (it) {
      var chip = el('button', 'efc-chip', escapeHtml(it.q));
      chip.addEventListener('click', function () {
        var input = document.querySelector('.efc-input');
        if (input) { input.value = it.q; send(); }
      });
      chipsWrap.appendChild(chip);
    });
  }

  function logStat(question, matched, matchedQ, category) {
    if (!CFG.ajaxUrl) return;
    var fd = new FormData();
    fd.append('action', 'efc_log');
    fd.append('nonce', CFG.nonce);
    fd.append('lang', state.lang);
    fd.append('question', question);
    fd.append('matched', matched ? 1 : 0);
    fd.append('matched_q', matchedQ);
    fd.append('category', category);
    try { fetch(CFG.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' }); } catch (e) {}
  }

  function openContact() {
    var modal = el('div', 'efc-modal');
    var card = el('div', 'efc-modal-card');
    card.innerHTML = '<h3>' + escapeHtml(t('Связаться с менеджером', 'Менеджерге хабарласу')) + '</h3>';
    var name = el('input', ''); name.placeholder = t('Имя', 'Аты');
    var contact = el('input', ''); contact.placeholder = t('Телефон или email', 'Телефон немесе email');
    var msg = el('textarea', ''); msg.placeholder = t('Сообщение', 'Хабарлама'); msg.rows = 3;
    var actions = el('div', 'efc-modal-actions');
    var cancel = el('button', 'efc-btn', t('Отмена', 'Болдырмау'));
    var submit = el('button', 'efc-btn primary', t('Отправить', 'Жіберу'));
    cancel.addEventListener('click', function () { modal.remove(); });
    submit.addEventListener('click', function () {
      var fd = new FormData();
      fd.append('action', 'efc_contact');
      fd.append('nonce', CFG.nonce);
      fd.append('name', name.value);
      fd.append('contact', contact.value);
      fd.append('message', msg.value);
      fd.append('history', state.messages.map(function (m) { return m.role + ': ' + m.text; }).join('\n'));
      fetch(CFG.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j.success) {
            alert(t('Отправлено. Менеджер свяжется с вами.', 'Жіберілді. Менеджер сізбен байланысады.'));
            modal.remove();
          } else {
            alert((j && j.data && j.data.message) || t('Ошибка отправки.', 'Жіберу қатесі.'));
          }
        })
        .catch(function () { alert(t('Ошибка отправки.', 'Жіберу қатесі.')); });
    });
    actions.appendChild(cancel);
    actions.appendChild(submit);
    card.appendChild(name);
    card.appendChild(contact);
    card.appendChild(msg);
    card.appendChild(actions);
    modal.appendChild(card);
    document.body.appendChild(modal);
  }

  function loadQA() {
    if (!CFG.qaUrl) { state.loaded = true; return Promise.resolve(); }
    return fetch(CFG.qaUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) { state.qa = Array.isArray(data) ? data : []; state.loaded = true; renderChips(); })
      .catch(function () { state.loaded = true; });
  }

  function init() {
    build();
    loadQA();
    if (CFG.autoOpen) setOpen(true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();