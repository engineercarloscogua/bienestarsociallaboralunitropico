document.addEventListener('DOMContentLoaded', () => {
  initAnalytics();
  initSidebar();
  initScrollReveal();
  setActiveNav();
  initPageTransitions();
  initSelfcareGames();
  initTalibriMascot();
  initCommentForm();
});

function initAnalytics() {
  const endpoint = `${window.PORTAL_BASE || ''}/api/analytics.php`;
  const visitorKey = 'unitropicoThVisitorId';
  let visitorId = localStorage.getItem(visitorKey);

  if (!visitorId) {
    const randomPart = window.crypto?.randomUUID
      ? window.crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    visitorId = `v_${randomPart}`;
    localStorage.setItem(visitorKey, visitorId);
  }

  const payload = new URLSearchParams({
    visitor_id: visitorId,
    path: `${window.location.pathname}${window.location.search}`,
    title: document.title.replace(' — Unitrópico', ''),
  });

  if (navigator.sendBeacon) {
    navigator.sendBeacon(endpoint, new Blob([payload.toString()], {
      type: 'application/x-www-form-urlencoded;charset=UTF-8',
    }));
    return;
  }

  fetch(endpoint, {
    method: 'POST',
    body: payload,
    keepalive: true,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
  }).catch(() => {});
}

function initSidebar() {
  const hamburger = document.getElementById('btn-hamburger');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!sidebar) return;

  function openSidebar() {
    sidebar.classList.add('open');
    overlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay?.classList.remove('open');
    document.body.style.overflow = '';
  }

  hamburger?.addEventListener('click', () => {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay?.addEventListener('click', closeSidebar);
}

function setActiveNav() {
  const currentPath = window.location.pathname;
  document.querySelectorAll('.nav-item').forEach((item) => {
    const href = item.getAttribute('href') || '';
    if (href && currentPath.endsWith(href.split('/').pop())) {
      item.classList.add('active');
    }
  });
}

function initPageTransitions() {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion) return;

  document.querySelectorAll('.sidebar-nav .nav-item').forEach((link) => {
    link.addEventListener('click', (event) => {
      const href = link.getAttribute('href');
      if (!href || link.classList.contains('active')) return;

      const targetUrl = new URL(href, window.location.href);
      if (targetUrl.origin !== window.location.origin) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

      event.preventDefault();
      document.querySelectorAll('.nav-item.is-transitioning').forEach((item) => {
        item.classList.remove('is-transitioning');
      });
      link.classList.add('is-transitioning');
      document.body.classList.add('page-leaving');

      window.setTimeout(() => {
        window.location.href = targetUrl.href;
      }, 190);
    });
  });
}

function initScrollReveal() {
  const targets = document.querySelectorAll('.service-card, .mega-card, .stat-card');
  if (!targets.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const delay = entry.target.dataset.delay || 0;
        setTimeout(() => entry.target.classList.add('visible'), Number(delay));
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

  targets.forEach((el, index) => {
    el.dataset.delay = index * 70;
    observer.observe(el);
  });
}

function initSelfcareGames() {
  document.querySelectorAll('[data-selfcare-puzzle]').forEach((puzzle) => {
    const feedback = puzzle.parentElement?.querySelector('[data-puzzle-feedback]');
    const tiles = [...puzzle.querySelectorAll('.selfcare-puzzle-tile')];

    tiles.forEach((tile) => {
      tile.addEventListener('click', () => {
        tile.classList.toggle('active');
        const selected = tiles.filter((item) => item.classList.contains('active')).length;
        if (!feedback) return;
        feedback.textContent = selected
          ? `Tu pausa de hoy tiene ${selected} pieza${selected === 1 ? '' : 's'}. Respira y ponla en práctica.`
          : 'Elige las piezas que necesitas hoy.';
      });
    });
  });

  document.querySelectorAll('[data-riddle-answer]').forEach((button) => {
    button.addEventListener('click', () => {
      const answer = button.dataset.riddleAnswer || '';
      const target = button.parentElement?.querySelector('.selfcare-riddle-answer');
      if (target) target.textContent = answer;
    });
  });

  document.querySelectorAll('[data-word-found]').forEach((button) => {
    button.addEventListener('click', () => {
      button.classList.toggle('found');
      const card = button.closest('.selfcare-word-card');
      const feedback = card?.querySelector('[data-word-feedback]');
      const total = card?.querySelectorAll('[data-word-found]').length || 0;
      const found = card?.querySelectorAll('[data-word-found].found').length || 0;
      if (!feedback) return;
      feedback.textContent = found === total
        ? 'Excelente: encontraste todas las palabras de autocuidado.'
        : `Llevas ${found} de ${total} palabras encontradas.`;
    });
  });
}

function initTalibriMascot() {
  const widget = document.getElementById('talibri-widget');
  const button = document.getElementById('talibri-button');
  const bubble = document.getElementById('talibri-bubble');
  if (!widget || !button || !bubble) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const moods = ['walk', 'stroll', 'fly', 'rest', 'wild', 'bored', 'wave', 'sleepy', 'peek'];
  const messages = [
    'Haz una pausa breve: respira, estira y vuelve con calma.',
    'Un equipo también se cuida escuchando con respeto.',
    'Tomar agua cuenta como autocuidado. Talibrí aprueba.',
    'Antes de responder con afán, prueba tres respiraciones lentas.',
    'Tu bienestar no es un extra: sostiene tu talento.',
    'Reconocer a alguien hoy puede mejorar el clima laboral.',
    'Si la carga pesa mucho, pedir apoyo también es profesional.',
    'Un minuto de pausa puede salvar una hora de tensión.',
    'Muévete un poco: cuello, hombros, manos y mirada lejos.',
    'Talibrí dice: trabajar bien también es cuidarse bien.',
    'Hoy revisa tu energía antes de revisar otra tarea.',
    'Una conversación amable también es prevención.',
    'Tu familia también agradece cuando llegas con un poco más de calma.',
    'Antes de llevarte el estrés a casa, suelta hombros y respira lento.',
    'Cuidar tus vínculos familiares también hace parte del bienestar.',
    'Una llamada pendiente a alguien querido puede recargar el corazón.',
    'Organiza tu tiempo para que el trabajo no se coma tus afectos.',
    'Si hoy fue pesado, conversa en casa sin cargar culpas.',
    'Poner límites sanos protege tu energía laboral y familiar.',
    'El descanso no es premio: es mantenimiento emocional.',
    'Una pausa a tiempo evita una respuesta desde el cansancio.',
    'Si notas irritabilidad frecuente, tu cuerpo está pidiendo cuidado.',
    'La salud mental también se entrena con rutinas pequeñas.',
    'Pedir ayuda no te hace menos capaz; te hace más humano.',
    'La escucha activa baja tensiones antes de que se vuelvan conflicto.',
    'Reconoce tus logros del día, aunque hayan sido pequeños.',
    'No todo debe resolverse con urgencia. Priorizar también cuida.',
    'Un buen clima laboral empieza con saludos, respeto y claridad.',
    'Cuando una carga se siente excesiva, habla antes de saturarte.',
    'Dormir bien también es una estrategia de productividad.',
    'Tu cuerpo habla: tensión, dolor de cabeza o fatiga merecen atención.',
    'Celebra el avance de un compañero. El reconocimiento fortalece equipos.',
    'La empatía no quita firmeza; mejora la forma de resolver.',
    'Hoy puedes practicar una cosa: responder con calma.',
    'Si estás abrumado, divide la tarea en el siguiente paso posible.',
    'Un equipo sano no normaliza el agotamiento permanente.',
    'Cuidar la mente también es apagar pantallas a tiempo.'
  ];

  let lastMessageIndex = -1;
  let hideTimer = null;

  if (!reduceMotion) {
    widget.classList.add('is-entering');
    window.setTimeout(() => widget.classList.remove('is-entering'), 1900);
  }

  function setMood(mood) {
    moods.forEach((item) => widget.classList.remove(item));
    if (!reduceMotion) {
      widget.classList.add(mood);
      window.setTimeout(() => widget.classList.remove(mood), mood === 'stroll' ? 4700 : 3000);
    }
  }

  function showMessage(forceMood) {
    const mood = forceMood || moods[Math.floor(Math.random() * moods.length)];
    let nextMessageIndex = Math.floor(Math.random() * messages.length);
    if (messages.length > 1) {
      while (nextMessageIndex === lastMessageIndex) {
        nextMessageIndex = Math.floor(Math.random() * messages.length);
      }
    }
    lastMessageIndex = nextMessageIndex;
    bubble.textContent = messages[nextMessageIndex];
    bubble.classList.add('show');
    setMood(mood);

    window.clearTimeout(hideTimer);
    hideTimer = window.setTimeout(() => {
      bubble.classList.remove('show');
    }, 5200);
  }

  button.addEventListener('click', () => {
    showMessage(moods[Math.floor(Math.random() * moods.length)]);
  });

  button.addEventListener('mousemove', (event) => {
    if (reduceMotion) return;
    const bounds = button.getBoundingClientRect();
    const x = (event.clientX - bounds.left) / bounds.width - 0.5;
    const y = (event.clientY - bounds.top) / bounds.height - 0.5;
    button.style.setProperty('--talibri-ry', `${x * 16}deg`);
    button.style.setProperty('--talibri-rx', `${y * -12}deg`);
  });

  button.addEventListener('mouseleave', () => {
    button.style.setProperty('--talibri-ry', '0deg');
    button.style.setProperty('--talibri-rx', '0deg');
  });

  window.setTimeout(() => showMessage('stroll'), 1150);
  window.setInterval(() => {
    showMessage();
  }, 120000);
}

function initCommentForm() {
  const form = document.getElementById('comment-form');
  const wall = document.getElementById('comment-wall');
  const status = document.getElementById('comment-form-status');
  if (!form || !wall || !status) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    status.className = 'comment-form-status';
    status.textContent = 'Publicando tu comentario...';

    try {
      const data = await submitComment(form);
      const card = buildCommentCard(data.comment, form.action);
      const empty = wall.querySelector('.comment-empty');
      if (empty) empty.remove();
      wall.prepend(card);
      form.reset();
      const defaultRating = form.querySelector('input[name="rating"][value="5"]');
      const defaultEmoji = form.querySelector('input[name="emoji"][value="💚"]');
      if (defaultRating) defaultRating.checked = true;
      if (defaultEmoji) defaultEmoji.checked = true;
      status.classList.add('success');
      status.textContent = data.message || 'Comentario publicado.';
    } catch (error) {
      status.classList.add('error');
      status.textContent = error.message || 'No se pudo publicar el comentario.';
    }
  });

  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-reply-toggle]');
    if (!toggle) return;

    const card = toggle.closest('.comment-card');
    const replyForm = card?.querySelector('[data-reply-form]');
    if (!replyForm) return;

    replyForm.hidden = !replyForm.hidden;
    toggle.classList.toggle('is-open', !replyForm.hidden);
    if (!replyForm.hidden) {
      const field = replyForm.querySelector('textarea, input[name="message"]');
      if (field) field.focus();
    }
  });

  document.addEventListener('submit', async (event) => {
    const replyForm = event.target.closest('[data-reply-form]');
    if (!replyForm) return;

    event.preventDefault();
    const replyStatus = replyForm.querySelector('[data-reply-status]');
    if (replyStatus) {
      replyStatus.className = 'comment-form-status';
      replyStatus.textContent = 'Publicando respuesta...';
    }

    try {
      const data = await submitComment(replyForm);
      const card = replyForm.closest('.comment-card');
      const replies = card?.querySelector('.comment-replies');
      if (replies) replies.append(buildReplyCard(data.comment));
      replyForm.reset();
      replyForm.hidden = true;
      card?.querySelector('[data-reply-toggle]')?.classList.remove('is-open');
      if (replyStatus) {
        replyStatus.classList.add('success');
        replyStatus.textContent = data.message || 'Respuesta publicada.';
      }
    } catch (error) {
      if (replyStatus) {
        replyStatus.classList.add('error');
        replyStatus.textContent = error.message || 'No se pudo publicar la respuesta.';
      }
    }
  });
}

async function submitComment(form) {
  const response = await fetch(form.action, {
    method: 'POST',
    body: new FormData(form),
    headers: { 'Accept': 'application/json' },
  });
  const data = await response.json();

  if (!response.ok || !data.ok) {
    throw new Error(data.message || 'No se pudo publicar el comentario.');
  }

  return data;
}

function buildCommentCard(comment, actionUrl) {
  const card = document.createElement('article');
  card.className = 'comment-card';
  card.dataset.commentId = comment.id || '';
  const rating = '★'.repeat(Number(comment.rating || 5));
  const dateText = formatCommentDate(comment.created_at);
  card.innerHTML = `
    <div class="comment-card-top">
      <span class="comment-emoji"></span>
      <div class="comment-main">
        <div class="comment-meta">
          <h3></h3>
          <span>@portalTH</span>
          <span aria-hidden="true">·</span>
          <time datetime="${escapeHtml(comment.created_at || '')}">${dateText}</time>
        </div>
        <p></p>
        <div class="comment-score">${rating}</div>
      </div>
    </div>
    <div class="comment-replies"></div>
    <div class="comment-actions">
      <button type="button" class="comment-reply-toggle" data-reply-toggle>Responder</button>
    </div>
    <form class="comment-reply-form" data-reply-form method="post" action="${escapeHtml(actionUrl)}" hidden>
      <input type="hidden" name="parent_id" value="${escapeHtml(comment.id || '')}">
      <input type="hidden" name="rating" value="${Number(comment.rating || 5)}">
      <input type="hidden" name="emoji" value="💬">
      <div class="form-field">
        <label>Tu nombre</label>
        <input name="name" type="text" maxlength="60" placeholder="Nombre o área">
      </div>
      <div class="form-field">
        <label>Respuesta</label>
        <textarea name="message" rows="3" maxlength="600" required placeholder="Responde con respeto y buena energía"></textarea>
      </div>
      <button type="submit" class="btn-primary btn-reply-submit">Enviar respuesta</button>
      <p class="comment-form-status" data-reply-status aria-live="polite"></p>
    </form>
  `;
  card.querySelector('.comment-emoji').textContent = comment.emoji || '💚';
  card.querySelector('h3').textContent = comment.name || 'Visitante';
  card.querySelector('p').textContent = comment.message || '';
  return card;
}

function buildReplyCard(reply) {
  const card = document.createElement('article');
  card.className = 'comment-reply';
  card.innerHTML = `
    <span class="comment-reply-avatar"></span>
    <div class="comment-reply-body">
    <div class="comment-reply-head">
      <strong></strong>
      <span>@respuesta</span>
      <span aria-hidden="true">·</span>
      <time datetime="${escapeHtml(reply.created_at || '')}">${formatCommentDate(reply.created_at)}</time>
    </div>
    <p></p>
    </div>
  `;
  card.querySelector('.comment-reply-avatar').textContent = reply.emoji || '💬';
  card.querySelector('strong').textContent = reply.name || 'Visitante';
  card.querySelector('p').textContent = reply.message || '';
  return card;
}

function formatCommentDate(value) {
  const created = new Date(value || Date.now());
  return created.toLocaleString('es-CO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
