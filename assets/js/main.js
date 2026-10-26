document.addEventListener('DOMContentLoaded', () => {
  initAnalytics();
  initHeroNetwork();
  initHeroCarousel();
  initSidebar();
  initPortalTools();
  initActiveBreak();
  initScrollReveal();
  setActiveNav();
  initPageTransitions();
  initSelfcareGames();
  initTurnstileWidgets();
  initCommentForm();
});

function initHeroCarousel() {
  const carousel = document.querySelector('[data-hero-carousel]');
  if (!carousel) return;

  const slides = [...carousel.querySelectorAll('[data-hero-slide]')];
  const dots = [...carousel.querySelectorAll('[data-hero-dot]')];
  const previousButton = carousel.querySelector('[data-hero-prev]');
  const nextButton = carousel.querySelector('[data-hero-next]');
  const status = carousel.querySelector('[data-hero-status]');
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const interval = Math.max(4000, Number(carousel.dataset.interval) || 6500);
  if (slides.length < 2) return;

  let current = 0;
  let timer = null;
  let pointerStart = null;

  const show = (nextIndex, announce = false) => {
    const normalized = (nextIndex + slides.length) % slides.length;
    if (normalized === current) return;

    slides[current].classList.remove('is-active');
    slides[current].classList.add('is-leaving');
    dots[current]?.classList.remove('is-active');
    dots[current]?.setAttribute('aria-selected', 'false');

    current = normalized;
    slides[current].classList.remove('is-leaving');
    slides[current].classList.add('is-active');
    dots[current]?.classList.add('is-active');
    dots[current]?.setAttribute('aria-selected', 'true');

    window.setTimeout(() => {
      slides.forEach((slide, index) => {
        if (index !== current) slide.classList.remove('is-leaving');
      });
    }, prefersReducedMotion ? 0 : 1300);

    if (announce && status) status.textContent = `Imagen ${current + 1} de ${slides.length}`;
  };

  const stop = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
  };

  const start = () => {
    stop();
    if (prefersReducedMotion || document.hidden) return;
    timer = window.setInterval(() => show(current + 1), interval);
  };

  previousButton?.addEventListener('click', () => {
    show(current - 1, true);
    start();
  });
  nextButton?.addEventListener('click', () => {
    show(current + 1, true);
    start();
  });
  dots.forEach((dot) => {
    dot.addEventListener('click', () => {
      show(Number(dot.dataset.heroDot), true);
      start();
    });
  });

  carousel.addEventListener('mouseenter', stop);
  carousel.addEventListener('mouseleave', start);
  carousel.addEventListener('focusin', stop);
  carousel.addEventListener('focusout', (event) => {
    if (!carousel.contains(event.relatedTarget)) start();
  });
  carousel.addEventListener('pointerdown', (event) => {
    pointerStart = { x: event.clientX, y: event.clientY };
  }, { passive: true });
  carousel.addEventListener('pointerup', (event) => {
    if (!pointerStart) return;
    const deltaX = event.clientX - pointerStart.x;
    const deltaY = event.clientY - pointerStart.y;
    pointerStart = null;
    if (Math.abs(deltaX) < 55 || Math.abs(deltaX) < Math.abs(deltaY)) return;
    show(current + (deltaX < 0 ? 1 : -1), true);
    start();
  }, { passive: true });
  document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());

  start();
}

function initPortalTools() {
  const triggers = [...document.querySelectorAll('[data-portal-tool]')];
  const panels = [...document.querySelectorAll('[data-portal-panel]')];
  const backdrop = document.querySelector('[data-portal-tool-backdrop]');
  if (!triggers.length || !panels.length || !backdrop) return;

  let activePanel = null;
  let returnFocus = null;

  const getFocusable = (panel) => [...panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];

  function closeTools(restoreFocus = true) {
    if (!activePanel) return;
    const closedName = activePanel.dataset.portalPanel;
    activePanel.hidden = true;
    backdrop.hidden = true;
    document.body.classList.remove('portal-tool-open');
    triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));
    const focusTarget = returnFocus;
    activePanel = null;
    returnFocus = null;
    document.dispatchEvent(new CustomEvent('portal-tool:closed', { detail: { name: closedName } }));
    if (restoreFocus && focusTarget) focusTarget.focus();
  }

  function openTool(name, trigger) {
    const panel = panels.find((item) => item.dataset.portalPanel === name);
    if (!panel) return;
    if (activePanel === panel) {
      closeTools();
      return;
    }
    closeTools(false);
    activePanel = panel;
    returnFocus = trigger;
    panel.hidden = false;
    backdrop.hidden = false;
    document.body.classList.add('portal-tool-open');
    triggers.forEach((item) => item.setAttribute('aria-expanded', String(item === trigger)));
    document.dispatchEvent(new CustomEvent('portal-tool:opened', { detail: { name } }));
    window.requestAnimationFrame(() => {
      const preferred = panel.querySelector('input') || getFocusable(panel)[0];
      preferred?.focus();
    });
  }

  triggers.forEach((trigger) => {
    trigger.addEventListener('click', () => openTool(trigger.dataset.portalTool, trigger));
  });
  backdrop.addEventListener('click', () => closeTools());
  document.querySelectorAll('[data-portal-close]').forEach((button) => button.addEventListener('click', () => closeTools()));

  document.addEventListener('keydown', (event) => {
    if (!activePanel) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeTools();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = getFocusable(activePanel);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  initPortalSearch();
  initAccessibilityPreferences();
}

function initActiveBreak() {
  const panel = document.querySelector('[data-portal-panel="active-break"]');
  const trigger = document.querySelector('[data-portal-tool="active-break"]');
  if (!panel || !trigger) return;

  const views = [...panel.querySelectorAll('[data-break-view]')];
  const startButton = panel.querySelector('[data-break-start]');
  const toggleButton = panel.querySelector('[data-break-toggle]');
  const nextButton = panel.querySelector('[data-break-next]');
  const finishButton = panel.querySelector('[data-break-finish]');
  const againButton = panel.querySelector('[data-break-again]');
  const reminderSelect = panel.querySelector('[data-break-reminder]');
  const stepLabel = panel.querySelector('[data-break-step]');
  const clock = panel.querySelector('[data-break-clock]');
  const progress = panel.querySelector('.portal-break-progress');
  const progressBar = panel.querySelector('[data-break-progress]');
  const category = panel.querySelector('[data-break-category]');
  const title = panel.querySelector('[data-break-title]');
  const instruction = panel.querySelector('[data-break-instruction]');
  const exerciseImage = panel.querySelector('[data-break-image]');
  const daily = panel.querySelector('[data-break-daily]');

  const reminderKey = 'portal_active_break_reminder_v1';
  const historyKey = 'portal_active_break_history_v1';
  const exercises = [
    {
      category: 'Respiración',
      title: 'Respira con calma',
      instruction: 'Adopta una postura cómoda. Inhala lentamente por la nariz y exhala sin forzar el ritmo.',
      image: 'assets/uploads/pausa-respiracion.png',
    },
    {
      category: 'Cuello y hombros',
      title: 'Libera la tensión',
      instruction: 'Baja los hombros, llévalos suavemente hacia atrás y realiza movimientos lentos, sin rebotes.',
      image: 'assets/uploads/pausa-hombros.png',
    },
    {
      category: 'Manos y muñecas',
      title: 'Descansa tus manos',
      instruction: 'Abre y cierra las manos. Después, mueve cada muñeca lentamente en ambos sentidos.',
      image: 'assets/uploads/pausa-munecas.png',
    },
    {
      category: 'Cambio de postura',
      title: 'Activa todo el cuerpo',
      instruction: 'Si puedes, ponte de pie. Estira brazos y piernas y camina unos pasos a un ritmo cómodo.',
      image: 'assets/uploads/pausa-estiramiento.png',
    },
    {
      category: 'Descanso visual',
      title: 'Mira a la distancia',
      instruction: 'Aparta la mirada de la pantalla, parpadea con naturalidad y observa un punto lejano.',
      image: 'assets/uploads/pausa-descanso-visual.png',
    },
  ];

  let current = 0;
  let durations = [];
  let remaining = 0;
  let totalSeconds = 180;
  let elapsed = 0;
  let timer = null;
  let running = false;
  let reminderTimer = null;

  const showView = (name) => {
    views.forEach((view) => { view.hidden = view.dataset.breakView !== name; });
  };

  const formatTime = (seconds) => {
    const safeSeconds = Math.max(0, seconds);
    return `${String(Math.floor(safeSeconds / 60)).padStart(2, '0')}:${String(safeSeconds % 60).padStart(2, '0')}`;
  };

  const render = () => {
    const exercise = exercises[current];
    const percent = Math.min(100, Math.round((elapsed / totalSeconds) * 100));
    stepLabel.textContent = `Ejercicio ${current + 1} de ${exercises.length}`;
    clock.textContent = formatTime(remaining);
    category.textContent = exercise.category;
    title.textContent = exercise.title;
    instruction.textContent = exercise.instruction;
    if (exerciseImage) exerciseImage.src = `${window.PORTAL_BASE || ''}/${exercise.image}`;
    progressBar.style.width = `${percent}%`;
    progress.setAttribute('aria-valuenow', String(percent));
    toggleButton.textContent = running ? '⏸ Pausar' : '▶ Continuar';
  };

  const stopTimer = () => {
    if (timer) window.clearInterval(timer);
    timer = null;
  };

  const countToday = () => {
    const today = new Date().toISOString().slice(0, 10);
    let history = {};
    try {
      history = JSON.parse(window.localStorage.getItem(historyKey) || '{}');
    } catch (_) {
      history = {};
    }
    history[today] = Number(history[today] || 0) + 1;
    try {
      window.localStorage.setItem(historyKey, JSON.stringify(history));
    } catch (_) {
      // La pausa funciona aunque el navegador impida guardar el contador.
    }
    return history[today];
  };

  const saveReminder = (minutes, due) => {
    try {
      window.localStorage.setItem(reminderKey, JSON.stringify({ minutes, due }));
    } catch (_) {
      // El recordatorio permanece activo durante la visita actual.
    }
  };

  const markReminderDue = () => {
    trigger.classList.add('is-break-due');
    trigger.title = 'Es momento de realizar una pausa activa';
  };

  const scheduleReminder = (minutes, due = 0) => {
    if (reminderTimer) window.clearTimeout(reminderTimer);
    reminderTimer = null;
    trigger.classList.remove('is-break-due');
    trigger.removeAttribute('title');
    if (!minutes) {
      saveReminder(0, 0);
      return;
    }

    const target = due || (Date.now() + minutes * 60 * 1000);
    saveReminder(minutes, target);
    const delay = target - Date.now();
    if (delay <= 0) {
      markReminderDue();
      return;
    }
    reminderTimer = window.setTimeout(markReminderDue, Math.min(delay, 2147483647));
  };

  const completeBreak = () => {
    stopTimer();
    running = false;
    progressBar.style.width = '100%';
    progress.setAttribute('aria-valuenow', '100');
    const completed = countToday();
    daily.textContent = `Hoy completaste ${completed} pausa${completed === 1 ? '' : 's'} activa${completed === 1 ? '' : 's'} en este navegador.`;
    showView('complete');
    const reminderMinutes = Number(reminderSelect.value || 0);
    if (reminderMinutes) scheduleReminder(reminderMinutes);
  };

  const goToExercise = (index) => {
    if (index >= exercises.length) {
      completeBreak();
      return;
    }
    current = Math.max(0, index);
    elapsed = durations.slice(0, current).reduce((sum, duration) => sum + duration, 0);
    remaining = durations[current];
    render();
  };

  const startTimer = () => {
    stopTimer();
    running = true;
    render();
    timer = window.setInterval(() => {
      if (!running) return;
      remaining -= 1;
      elapsed += 1;
      if (remaining <= 0) {
        goToExercise(current + 1);
        return;
      }
      render();
    }, 1000);
  };

  const startBreak = () => {
    totalSeconds = Number(panel.querySelector('input[name="portal-break-duration"]:checked')?.value || 180);
    const baseDuration = Math.floor(totalSeconds / exercises.length);
    durations = exercises.map(() => baseDuration);
    durations[durations.length - 1] += totalSeconds - (baseDuration * exercises.length);
    current = 0;
    elapsed = 0;
    remaining = durations[0];
    trigger.classList.remove('is-break-due');
    showView('session');
    startTimer();
  };

  startButton?.addEventListener('click', startBreak);
  toggleButton?.addEventListener('click', () => {
    running = !running;
    if (running) startTimer();
    else {
      stopTimer();
      render();
    }
  });
  nextButton?.addEventListener('click', () => goToExercise(current + 1));
  finishButton?.addEventListener('click', () => {
    stopTimer();
    running = false;
    showView('welcome');
  });
  againButton?.addEventListener('click', () => showView('welcome'));
  reminderSelect?.addEventListener('change', () => scheduleReminder(Number(reminderSelect.value || 0)));

  document.addEventListener('portal-tool:closed', (event) => {
    if (event.detail?.name !== 'active-break' || !running) return;
    running = false;
    stopTimer();
    render();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden && running) {
      running = false;
      stopTimer();
      render();
    }
  });

  try {
    const saved = JSON.parse(window.localStorage.getItem(reminderKey) || '{}');
    const minutes = [60, 90, 120].includes(Number(saved.minutes)) ? Number(saved.minutes) : 0;
    reminderSelect.value = String(minutes);
    if (minutes) scheduleReminder(minutes, Number(saved.due || 0));
  } catch (_) {
    reminderSelect.value = '0';
  }
}

function initPortalSearch() {
  const input = document.getElementById('portal-search-input');
  const results = document.getElementById('portal-search-results');
  const index = Array.isArray(window.PORTAL_SEARCH_INDEX) ? window.PORTAL_SEARCH_INDEX : [];
  if (!input || !results) return;

  const normalize = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('es')
    .trim();

  const renderMessage = (message) => {
    results.replaceChildren();
    const paragraph = document.createElement('p');
    paragraph.className = 'portal-search-message';
    paragraph.textContent = message;
    results.appendChild(paragraph);
  };

  const renderResults = () => {
    const query = normalize(input.value);
    if (query.length < 2) {
      renderMessage('Escribe al menos dos letras para iniciar la búsqueda.');
      return;
    }

    const terms = query.split(/\s+/).filter(Boolean);
    const matches = index
      .map((item) => {
        const title = normalize(item.title);
        const haystack = normalize(`${item.title} ${item.description} ${item.category}`);
        if (!terms.every((term) => haystack.includes(term))) return null;
        let score = title === query ? 100 : (title.startsWith(query) ? 60 : 20);
        terms.forEach((term) => { if (title.includes(term)) score += 10; });
        return { item, score };
      })
      .filter(Boolean)
      .sort((a, b) => b.score - a.score)
      .slice(0, 12);

    results.replaceChildren();
    if (!matches.length) {
      renderMessage('No encontramos resultados. Prueba con otra palabra o tema.');
      return;
    }

    const summary = document.createElement('p');
    summary.className = 'portal-search-count';
    summary.textContent = `${matches.length} resultado${matches.length === 1 ? '' : 's'}`;
    results.appendChild(summary);

    matches.forEach(({ item }) => {
      const link = document.createElement('a');
      link.className = 'portal-search-result';
      link.href = item.url;
      if (/^https?:\/\//i.test(item.url) && !item.url.startsWith(window.location.origin)) {
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
      }

      const category = document.createElement('span');
      category.className = 'portal-search-result-category';
      category.textContent = item.category || 'Contenido';
      const title = document.createElement('strong');
      title.textContent = item.title;
      const description = document.createElement('span');
      description.className = 'portal-search-result-description';
      description.textContent = item.description || 'Abrir contenido';

      link.append(category, title, description);
      results.appendChild(link);
    });
  };

  input.addEventListener('input', renderResults);
  renderMessage('Escribe al menos dos letras para iniciar la búsqueda.');
}

function initAccessibilityPreferences() {
  const storageKey = 'portal_accessibility_v1';
  const defaults = { size: 'normal', contrast: false, underline: false, grayscale: false };
  let preferences = { ...defaults };

  try {
    preferences = { ...defaults, ...JSON.parse(window.localStorage.getItem(storageKey) || '{}') };
  } catch (_) {
    preferences = { ...defaults };
  }

  const apply = () => {
    const allowedSizes = ['small', 'normal', 'large'];
    if (!allowedSizes.includes(preferences.size)) preferences.size = 'normal';

    document.body.classList.toggle('a11y-text-small', preferences.size === 'small');
    document.body.classList.toggle('a11y-text-large', preferences.size === 'large');
    document.body.classList.toggle('a11y-high-contrast', Boolean(preferences.contrast));
    document.body.classList.toggle('a11y-underline-links', Boolean(preferences.underline));
    document.body.classList.toggle('a11y-grayscale', Boolean(preferences.grayscale));

    document.querySelectorAll('[data-a11y-size]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.a11ySize === preferences.size));
    });
    document.querySelectorAll('[data-a11y-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', String(Boolean(preferences[button.dataset.a11yToggle])));
    });

    try {
      window.localStorage.setItem(storageKey, JSON.stringify(preferences));
    } catch (_) {
      // La preferencia sigue activa durante la visita si el navegador bloquea el almacenamiento.
    }
  };

  document.querySelectorAll('[data-a11y-size]').forEach((button) => {
    button.addEventListener('click', () => {
      preferences.size = button.dataset.a11ySize;
      apply();
    });
  });
  document.querySelectorAll('[data-a11y-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const key = button.dataset.a11yToggle;
      preferences[key] = !preferences[key];
      apply();
    });
  });
  document.querySelector('[data-a11y-reset]')?.addEventListener('click', () => {
    preferences = { ...defaults };
    apply();
  });

  apply();
}

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
    hamburger?.setAttribute('aria-expanded', 'true');
    hamburger?.setAttribute('aria-label', 'Cerrar menú principal');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay?.classList.remove('open');
    hamburger?.setAttribute('aria-expanded', 'false');
    hamburger?.setAttribute('aria-label', 'Abrir menú principal');
    document.body.style.overflow = '';
  }

  hamburger?.addEventListener('click', () => {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay?.addEventListener('click', closeSidebar);
  sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeSidebar();
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) closeSidebar();
  });
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

  document.querySelectorAll('.portal-main-nav .nav-item').forEach((link) => {
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
    const form = slot.closest('form');
    const submitButton = form?.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    const widgetId = window.turnstile.render(slot, {
      sitekey: slot.dataset.turnstileSitekey || siteKey,
      action: slot.dataset.turnstileAction || 'comment',
      theme: 'light',
      language: 'es',
      callback: () => {
        if (submitButton) submitButton.disabled = false;
      },
      'expired-callback': () => {
        if (submitButton) submitButton.disabled = true;
      },
      'error-callback': () => {
        if (submitButton) submitButton.disabled = true;
      },
    });
    slot.dataset.turnstileWidgetId = String(widgetId);
  });
}

function resetTurnstileWidget(form) {
  const slot = form.querySelector('.turnstile-slot[data-turnstile-widget-id]');
  if (!slot || !window.turnstile) return;
  const submitButton = form.querySelector('button[type="submit"]');
  if (submitButton) submitButton.disabled = true;
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
  const turnstileSlot = form.querySelector('.turnstile-slot');
  const turnstileResponse = form.querySelector('input[name="cf-turnstile-response"]');
  if (turnstileSlot && !turnstileResponse?.value) {
    throw new Error('Espera a que finalice la verificación anti-bots antes de enviar.');
  }

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
