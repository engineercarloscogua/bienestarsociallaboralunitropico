document.addEventListener('DOMContentLoaded', () => {
  initAnalytics();
  initHeroNetwork();
  initSidebar();
  initScrollReveal();
  setActiveNav();
  initPageTransitions();
  initSelfcareGames();
  initTalibriMascot();
  initTurnstileWidgets();
  initCommentForm();
});

function initHeroNetwork() {
  const canvas = document.getElementById('hero-network');
  const hero = canvas?.closest('.hero');
  if (!canvas || !hero || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const context = canvas.getContext('2d');
  if (!context) return;

  const particles = [];
  const pointer = { x: 0, y: 0, active: false };
  let width = 0;
  let height = 0;
  let pixelRatio = 1;
  let animationFrame = 0;
  let isVisible = true;

  const randomParticle = () => ({
    x: Math.random() * width,
    y: Math.random() * height,
    vx: (Math.random() - 0.5) * 0.18,
    vy: (Math.random() - 0.5) * 0.18,
    radius: 1.1 + Math.random() * 1.35,
    phase: Math.random() * Math.PI * 2,
    gold: Math.random() > 0.82,
  });

  const resize = () => {
    const bounds = hero.getBoundingClientRect();
    width = Math.max(1, Math.round(bounds.width));
    height = Math.max(1, Math.round(bounds.height));
    pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(width * pixelRatio);
    canvas.height = Math.round(height * pixelRatio);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);

    const desiredCount = Math.max(20, Math.min(52, Math.round(width / 30)));
    while (particles.length < desiredCount) particles.push(randomParticle());
    particles.length = desiredCount;
    particles.forEach((particle) => {
      particle.x = Math.min(particle.x, width);
      particle.y = Math.min(particle.y, height);
    });
  };

  const draw = (time) => {
    context.clearRect(0, 0, width, height);
    const connectionDistance = Math.min(132, Math.max(94, width / 11));

    particles.forEach((particle, index) => {
      particle.x += particle.vx;
      particle.y += particle.vy;

      if (particle.x < -8) particle.x = width + 8;
      if (particle.x > width + 8) particle.x = -8;
      if (particle.y < -8) particle.y = height + 8;
      if (particle.y > height + 8) particle.y = -8;

      if (pointer.active) {
        const dx = pointer.x - particle.x;
        const dy = pointer.y - particle.y;
        const distance = Math.hypot(dx, dy);
        if (distance > 0 && distance < 150) {
          const attraction = (1 - distance / 150) * 0.012;
          particle.x += dx * attraction;
          particle.y += dy * attraction;
        }
      }

      for (let nextIndex = index + 1; nextIndex < particles.length; nextIndex += 1) {
        const next = particles[nextIndex];
        const distance = Math.hypot(next.x - particle.x, next.y - particle.y);
        if (distance >= connectionDistance) continue;

        const opacity = (1 - distance / connectionDistance) * 0.3;
        const gradient = context.createLinearGradient(particle.x, particle.y, next.x, next.y);
        gradient.addColorStop(0, `rgba(211, 255, 246, ${opacity})`);
        gradient.addColorStop(1, `rgba(243, 223, 145, ${opacity * 0.72})`);
        context.beginPath();
        context.moveTo(particle.x, particle.y);
        context.lineTo(next.x, next.y);
        context.strokeStyle = gradient;
        context.lineWidth = 0.7;
        context.stroke();
      }

      const pulse = 0.72 + Math.sin(time * 0.0012 + particle.phase) * 0.28;
      context.beginPath();
      context.arc(particle.x, particle.y, particle.radius * pulse, 0, Math.PI * 2);
      context.fillStyle = particle.gold
        ? `rgba(255, 231, 145, ${0.55 + pulse * 0.35})`
        : `rgba(225, 255, 249, ${0.45 + pulse * 0.4})`;
      context.shadowBlur = particle.gold ? 9 : 7;
      context.shadowColor = particle.gold ? 'rgba(243, 223, 145, 0.7)' : 'rgba(139, 255, 232, 0.62)';
      context.fill();
      context.shadowBlur = 0;
    });

    if (isVisible && document.visibilityState === 'visible') {
      animationFrame = window.requestAnimationFrame(draw);
    }
  };

  const resume = () => {
    window.cancelAnimationFrame(animationFrame);
    if (isVisible && document.visibilityState === 'visible') {
      animationFrame = window.requestAnimationFrame(draw);
    }
  };

  hero.addEventListener('pointermove', (event) => {
    const bounds = hero.getBoundingClientRect();
    pointer.x = event.clientX - bounds.left;
    pointer.y = event.clientY - bounds.top;
    pointer.active = true;
  }, { passive: true });
  hero.addEventListener('pointerleave', () => { pointer.active = false; }, { passive: true });
  document.addEventListener('visibilitychange', resume);

  const resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(hero);

  const visibilityObserver = new IntersectionObserver(([entry]) => {
    isVisible = entry.isIntersecting;
    resume();
  }, { threshold: 0.05 });
  visibilityObserver.observe(hero);

  resize();
  resume();
}

function initAnalytics() {
  const endpoint = `${window.PORTAL_BASE || ''}/api/analytics.php`;
  const requestToken = window.PORTAL_ANALYTICS_TOKEN || '';
  if (!requestToken || navigator.webdriver) return;

  let interacted = false;
  let sent = false;
  let visibleMilliseconds = 0;
  let visibleSince = document.visibilityState === 'visible' ? performance.now() : null;

  const updateVisibleTime = () => {
    if (visibleSince === null) return;
    visibleMilliseconds += performance.now() - visibleSince;
    visibleSince = null;
  };

  const markInteraction = () => {
    interacted = true;
  };

  ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach((eventName) => {
    window.addEventListener(eventName, markInteraction, { once: true, passive: true });
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      visibleSince = performance.now();
    } else {
      updateVisibleTime();
    }
  });

  const timer = window.setInterval(() => {
    const currentVisible = visibleMilliseconds
      + (visibleSince === null ? 0 : performance.now() - visibleSince);
    if (sent || !interacted || currentVisible < 10000) return;

    sent = true;
    window.clearInterval(timer);
    const payload = new URLSearchParams({
      request_token: requestToken,
      interaction: '1',
      visible_seconds: String(Math.floor(currentVisible / 1000)),
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
  }, 1000);
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

function initTurnstileWidgets(root = document) {
  const siteKey = window.PORTAL_TURNSTILE_SITE_KEY || '';
  if (!siteKey || !window.turnstile) return;

  root.querySelectorAll('.turnstile-slot').forEach((slot) => {
    if (slot.dataset.turnstileWidgetId || slot.closest('[hidden]')) return;
    const widgetId = window.turnstile.render(slot, {
      sitekey: slot.dataset.turnstileSitekey || siteKey,
      action: slot.dataset.turnstileAction || 'comment',
      theme: 'light',
      language: 'es',
    });
    slot.dataset.turnstileWidgetId = String(widgetId);
  });
}

function resetTurnstileWidget(form) {
  const slot = form.querySelector('.turnstile-slot[data-turnstile-widget-id]');
  if (!slot || !window.turnstile) return;
  window.turnstile.reset(slot.dataset.turnstileWidgetId);
}

function refreshPublicRequestToken(form, token) {
  const field = form.querySelector('input[name="request_token"]');
  if (field && token) field.value = token;
}

document.addEventListener('turnstile-ready', () => initTurnstileWidgets());

function initCommentForm() {
  const form = document.getElementById('comment-form');
  const wall = document.getElementById('comment-wall');
  const status = document.getElementById('comment-form-status');
  if (!form || !wall || !status) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    status.className = 'comment-form-status';
    status.textContent = 'Verificando y enviando tu comentario...';

    try {
      const data = await submitComment(form);
      form.reset();
      refreshPublicRequestToken(form, data.request_token);
      resetTurnstileWidget(form);
      const defaultRating = form.querySelector('input[name="rating"][value="5"]');
      const defaultEmoji = form.querySelector('input[name="emoji"][value="💚"]');
      if (defaultRating) defaultRating.checked = true;
      if (defaultEmoji) defaultEmoji.checked = true;
      status.classList.add('success');
      status.textContent = data.message || 'Comentario recibido y enviado a revisión.';
    } catch (error) {
      resetTurnstileWidget(form);
      status.classList.add('error');
      status.textContent = error.message || 'No se pudo enviar el comentario.';
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
      initTurnstileWidgets(replyForm);
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
      replyStatus.textContent = 'Enviando respuesta a revisión...';
    }

    try {
      const data = await submitComment(replyForm);
      replyForm.reset();
      refreshPublicRequestToken(replyForm, data.request_token);
      resetTurnstileWidget(replyForm);
      if (replyStatus) {
        replyStatus.classList.add('success');
        replyStatus.textContent = data.message || 'Respuesta recibida y enviada a revisión.';
      }
    } catch (error) {
      resetTurnstileWidget(replyForm);
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
    throw new Error(data.message || 'No se pudo enviar el comentario.');
  }

  return data;
}
