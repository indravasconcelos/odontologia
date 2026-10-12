/* =========================================================
   INTERAÇÕES — Dr. Maycon Gomes
   Vanilla JS · sem dependências
   ========================================================= */
(function () {
  'use strict';

  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------- Header no scroll ---------------- */
  var header = document.querySelector('.site-header');

  function onScrollHeader() {
    if (!header) return;
    header.classList.toggle('is-scrolled', window.scrollY > 30);
  }

  onScrollHeader();
  window.addEventListener('scroll', onScrollHeader, { passive: true });

  /* ---------------- Menu mobile ---------------- */
  var navToggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('nav');

  function setNav(open) {
    document.body.classList.toggle('nav-open', open);
    if (navToggle) {
      navToggle.setAttribute('aria-expanded', String(open));
      navToggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    }
  }

  if (navToggle) {
    navToggle.addEventListener('click', function () {
      setNav(!document.body.classList.contains('nav-open'));
    });
  }

  if (nav) {
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setNav(false);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('nav-open')) setNav(false);
  });

  /* ---------------- Scroll spy ---------------- */
  var navLinks = Array.prototype.slice.call(document.querySelectorAll('.nav-list a[href^="#"]'));
  var spiedSections = navLinks
    .map(function (link) { return document.querySelector(link.getAttribute('href')); })
    .filter(Boolean);

  if ('IntersectionObserver' in window && spiedSections.length) {
    var spyObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        navLinks.forEach(function (link) {
          link.classList.toggle('is-active', link.getAttribute('href') === '#' + entry.target.id);
        });
      });
    }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

    spiedSections.forEach(function (section) { spyObserver.observe(section); });
  }

  /* ---------------- Reveal on scroll ---------------- */
  var revealItems = Array.prototype.slice.call(document.querySelectorAll('.reveal'));

  if (prefersReducedMotion || !('IntersectionObserver' in window)) {
    revealItems.forEach(function (el) { el.classList.add('is-visible'); });
  } else {
    var revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    revealItems.forEach(function (el) { revealObserver.observe(el); });
  }

  /* ---------------- FAQ / Acordeão ---------------- */
  var accTriggers = Array.prototype.slice.call(document.querySelectorAll('.acc-trigger'));

  accTriggers.forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      var item = trigger.closest('.acc-item');
      var panel = item.querySelector('.acc-panel');
      var isOpen = trigger.getAttribute('aria-expanded') === 'true';

      // fecha os demais (comportamento de acordeão)
      accTriggers.forEach(function (other) {
        if (other === trigger) return;
        other.setAttribute('aria-expanded', 'false');
        var otherItem = other.closest('.acc-item');
        otherItem.classList.remove('is-open');
        otherItem.querySelector('.acc-panel').hidden = true;
      });

      trigger.setAttribute('aria-expanded', String(!isOpen));
      item.classList.toggle('is-open', !isOpen);
      panel.hidden = isOpen;
    });
  });

  /* ---------------- Lightbox ---------------- */
  var lightbox = document.getElementById('lightbox');

  if (lightbox) {
    var lbImg = lightbox.querySelector('.lightbox-img');
    var lbCaption = lightbox.querySelector('.lightbox-caption');
    var lbCounter = lightbox.querySelector('.lb-counter');
    var lbPrev = lightbox.querySelector('.lb-prev');
    var lbNext = lightbox.querySelector('.lb-next');
    var currentGroup = [];
    var currentIndex = 0;
    var lastFocused = null;

    function collectGroup(group) {
      return Array.prototype.slice.call(
        document.querySelectorAll('[data-lightbox="' + group + '"]')
      );
    }

    function render() {
      var item = currentGroup[currentIndex];
      if (!item) return;
      var src = item.getAttribute('data-src');
      var caption = item.getAttribute('data-caption') || '';
      var innerImg = item.querySelector('img');

      lbImg.src = src;
      lbImg.alt = (innerImg && innerImg.alt) || caption;
      lbCaption.textContent = caption;
      // sem legenda a figura nao fica com uma linha vazia embaixo
      lbCaption.hidden = !caption;
      lbCounter.textContent = (currentIndex + 1) + ' / ' + currentGroup.length;

      var multi = currentGroup.length > 1;
      lbPrev.hidden = !multi;
      lbNext.hidden = !multi;
      lbCounter.hidden = !multi;
    }

    function openLightbox(item) {
      var group = item.getAttribute('data-lightbox');
      currentGroup = collectGroup(group);
      currentIndex = currentGroup.indexOf(item);
      if (currentIndex < 0) currentIndex = 0;

      lastFocused = document.activeElement;
      lightbox.hidden = false;
      document.body.classList.add('lightbox-open');
      render();
      lightbox.querySelector('.lb-close').focus();
    }

    function closeLightbox() {
      lightbox.hidden = true;
      document.body.classList.remove('lightbox-open');
      lbImg.src = '';
      if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }

    function step(delta) {
      if (!currentGroup.length) return;
      currentIndex = (currentIndex + delta + currentGroup.length) % currentGroup.length;
      render();
    }

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-lightbox]');
      if (trigger) {
        e.preventDefault();
        openLightbox(trigger);
        return;
      }
      if (e.target.closest('[data-lb-close]')) closeLightbox();
      if (e.target.closest('.lb-prev')) step(-1);
      if (e.target.closest('.lb-next')) step(1);
    });

    document.addEventListener('keydown', function (e) {
      if (lightbox.hidden) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') step(-1);
      if (e.key === 'ArrowRight') step(1);

      // foco preso dentro do lightbox
      if (e.key === 'Tab') {
        var focusables = Array.prototype.slice.call(
          lightbox.querySelectorAll('button:not([hidden])')
        ).filter(function (el) { return el.offsetParent !== null; });
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });

    // ativação por teclado nos itens da galeria (figures com tabindex)
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var item = e.target.closest && e.target.closest('[data-lightbox]');
      if (!item || item.tagName === 'BUTTON' || item.tagName === 'A') return;
      e.preventDefault();
      openLightbox(item);
    });
  }

  /* ---------------- Vídeo ---------------- */
  var videoFrame = document.getElementById('video-frame');
  var VIDEO_SRC = 'assets/video_horizontal.mp4';
  var VIDEO_POSTER = 'assets/capa-video.jpg';

  if (videoFrame) {
    function buildPlayButton() {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'video-play';
      btn.setAttribute('aria-label', 'Reproduzir vídeo');
      btn.innerHTML =
        '<span class="video-play-icon" aria-hidden="true">' +
        '<svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor"><path d="M8 5.5v13l11-6.5-11-6.5Z"/></svg>' +
        '</span>' +
        '<span class="video-play-label">Assistir vídeo</span>';
      return btn;
    }

    function showUnavailable() {
      if (videoFrame.querySelector('.video-unavailable')) return;

      var box = document.createElement('div');
      box.className = 'video-unavailable';
      box.innerHTML =
        '<strong>O vídeo estará disponível em breve</strong>' +
        '<p>Assim que o arquivo for adicionado, ele será reproduzido automaticamente nesta área.</p>' +
        '<code>assets/video_horizontal.mp4</code>';
      videoFrame.appendChild(box);

      var poster = videoFrame.querySelector('.video-poster');
      if (poster) poster.style.opacity = '0.35';

      var retry = buildPlayButton();
      retry.setAttribute('aria-label', 'Tentar novamente');
      retry.querySelector('.video-play-label').textContent = 'Tentar novamente';
      retry.addEventListener('click', function () {
        box.remove();
        retry.remove();
        var p = videoFrame.querySelector('.video-poster');
        if (p) p.style.opacity = '';
        startVideo();
      });
      videoFrame.appendChild(retry);
    }

    function startVideo() {
      var oldBtn = videoFrame.querySelector('.video-play');
      if (oldBtn) oldBtn.remove();

      var video = document.createElement('video');
      video.setAttribute('controls', '');
      video.setAttribute('playsinline', '');
      video.setAttribute('preload', 'auto');
      video.setAttribute('aria-label', 'Vídeo: Dr. Maycon explica por que não adiar o tratamento');
      video.src = VIDEO_SRC;
      video.poster = VIDEO_POSTER;

      var failed = false;

      function fail() {
        if (failed) return;
        failed = true;
        video.remove();
        showUnavailable();
      }

      video.addEventListener('error', fail);
      video.addEventListener('stalled', function () { /* deixa o timeout decidir */ });

      window.setTimeout(function () {
        if (!failed && (video.error || video.networkState === 3 /* NO_SOURCE */)) fail();
      }, 4000);

      videoFrame.appendChild(video);

      var playPromise = video.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(function () { /* usuário pode usar os controles */ });
      }
    }

    var initialPlayBtn = videoFrame.querySelector('.video-play');
    if (initialPlayBtn) initialPlayBtn.addEventListener('click', startVideo);
  }

  /* ---------------- Vídeo do consultório ---------------- */
  var localFrame = document.getElementById('local-video-frame');

  if (localFrame) {
    var localVideo = localFrame.querySelector('.local-video-el');
    var localPlay = localFrame.querySelector('.video-play');

    if (localVideo && localPlay) {
      localPlay.addEventListener('click', function () {
        localPlay.remove();
        localVideo.setAttribute('controls', '');

        var tentativa = localVideo.play();
        if (tentativa && typeof tentativa.catch === 'function') {
          tentativa.catch(function () {
            // o navegador recusou o play automatico: os controles ja
            // estao na tela e a pessoa consegue dar play no proprio player
            localVideo.setAttribute('controls', '');
          });
        }
      });

      localVideo.addEventListener('error', function () {
        if (localFrame.querySelector('.video-unavailable')) return;

        var caixa = document.createElement('div');
        caixa.className = 'video-unavailable';
        caixa.innerHTML =
          '<strong>O vídeo estará disponível em breve</strong>' +
          '<p>Assim que o arquivo for adicionado, ele será reproduzido aqui.</p>' +
          '<code>assets/video-fiame.mp4</code>';
        localFrame.appendChild(caixa);
      });
    }
  }

  /* ---------------- Tratamentos: filtro por categoria ---------------- */
  var tratTabs = Array.prototype.slice.call(document.querySelectorAll('.trat-tab'));
  if (tratTabs.length) {
    var tratCards = Array.prototype.slice.call(document.querySelectorAll('#procedimentos .proc-card'));
    var tratGrid = document.getElementById('procedimentos');
    var tratSteps = document.querySelector('.trat-steps');

    // 'todos' e um estado interno, sem botao na tela: aparece quando
    // a pessoa desmarca a categoria que estava escolhida
    var TODOS = 'todos';
    var filtroAtual = TODOS;

    function applyTratFilter(filter) {
      var showSteps = filter === 'passos';
      if (tratGrid) tratGrid.hidden = showSteps;
      if (tratSteps) tratSteps.hidden = !showSteps;

      tratCards.forEach(function (card) {
        card.hidden = showSteps || !(filter === TODOS || card.getAttribute('data-cat') === filter);
      });

      tratTabs.forEach(function (tab, index) {
        var active = tab.getAttribute('data-filter') === filter;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        // sem filtro escolhido, a primeira aba continua focavel pelo teclado
        tab.tabIndex = (active || (filter === TODOS && index === 0)) ? 0 : -1;
      });

      filtroAtual = filter;
    }

    function alternarFiltro(filter) {
      // clicar na categoria ja escolhida volta a mostrar todos
      applyTratFilter(filtroAtual === filter ? TODOS : filter);
    }

    tratTabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () {
        alternarFiltro(tab.getAttribute('data-filter'));
      });

      tab.addEventListener('keydown', function (e) {
        var next = e.key === 'ArrowRight' ? index + 1 : e.key === 'ArrowLeft' ? index - 1 : null;
        if (next === null) return;
        e.preventDefault();
        var target = tratTabs[(next + tratTabs.length) % tratTabs.length];
        target.focus();
        applyTratFilter(target.getAttribute('data-filter'));
      });
    });

    // a secao abre com a primeira categoria marcada, para ficar
    // claro que existe filtro. Clicar nela de novo mostra todos.
    applyTratFilter(tratTabs[0].getAttribute('data-filter'));
  }

  /* ---------------- Galeria antes e depois: carrossel ---------------- */
  var casesTrack = document.getElementById('casos-track');

  if (casesTrack) {
    var caseCards = Array.prototype.slice.call(casesTrack.querySelectorAll('.case-card'));
    var caseDots = Array.prototype.slice.call(document.querySelectorAll('.cases-dot'));
    var casePrev = document.querySelector('[data-cases="prev"]');
    var caseNext = document.querySelector('[data-cases="next"]');
    var casesTimer = null;

    // a posição é medida na tela, não por cálculo de largura: assim o
    // carrossel continua certo quando as fotos mudam de tamanho no
    // celular e quando existe preenchimento na pista
    function casesIndex() {
      var base = casesTrack.getBoundingClientRect().left;
      var melhor = 0;
      var menorDistancia = Infinity;

      caseCards.forEach(function (card, n) {
        var distancia = Math.abs(card.getBoundingClientRect().left - base);
        if (distancia < menorDistancia) {
          menorDistancia = distancia;
          melhor = n;
        }
      });

      return melhor;
    }

    function casesGo(i) {
      var card = caseCards[i];
      if (!card) return;

      var deslocamento = card.getBoundingClientRect().left - casesTrack.getBoundingClientRect().left;
      casesTrack.scrollBy({
        left: deslocamento,
        behavior: prefersReducedMotion ? 'auto' : 'smooth'
      });
    }

    function casesUpdate() {
      var atual = casesIndex();

      caseDots.forEach(function (dot, n) {
        dot.classList.toggle('is-active', n === atual);
      });

      var fim = casesTrack.scrollWidth - casesTrack.clientWidth;
      if (casePrev) casePrev.disabled = casesTrack.scrollLeft <= 2;
      if (caseNext) caseNext.disabled = casesTrack.scrollLeft >= fim - 2;
    }

    if (casePrev) {
      casePrev.addEventListener('click', function () { casesGo(casesIndex() - 1); });
    }

    if (caseNext) {
      caseNext.addEventListener('click', function () { casesGo(casesIndex() + 1); });
    }

    caseDots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        casesGo(parseInt(dot.getAttribute('data-cases-go'), 10) || 0);
      });
    });

    // arrastar com o dedo ou rolar atualiza o ponto ativo e o estado
    // dos botões
    casesTrack.addEventListener('scroll', function () {
      window.clearTimeout(casesTimer);
      casesTimer = window.setTimeout(casesUpdate, 80);
    }, { passive: true });

    casesTrack.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); casesGo(casesIndex() + 1); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); casesGo(casesIndex() - 1); }
    });

    window.addEventListener('resize', casesUpdate);
    casesUpdate();
  }

  /* ---------------- Depoimentos: fita automática (direita -> esquerda) ---- */
  var depTrack = document.querySelector('.dep-track');

  if (depTrack) {
    var depCards = Array.prototype.slice.call(depTrack.querySelectorAll('.dep-card'));

    // duas cópias idênticas: assim -50% do transform cai exatamente no
    // ponto de partida e o loop volta sem dar "pulo"
    depCards.forEach(function (card) {
      var clone = card.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      depTrack.appendChild(clone);
    });

    // velocidade fixa em px/s — a duração acompanha o tamanho da fita
    function depVelocidade() {
      var metade = depTrack.scrollWidth / 2;
      depTrack.style.animationDuration = Math.round(metade / 60) + 's';
    }

    depVelocidade();
    window.addEventListener('resize', depVelocidade);

    // fora da tela a animação para, para não gastar render à toa
    if ('IntersectionObserver' in window) {
      var depObserver = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (entrada) {
          depTrack.classList.toggle('is-off', !entrada.isIntersecting);
        });
      }, { threshold: 0 });

      depObserver.observe(depTrack);
    }
  }

  /* ---------------- Mapa 3D (satélite inclinado) ----------------
     A biblioteca só é carregada quando a localização se aproxima da
     tela; se algo falhar, o mapa volta para o iframe do Google. */
  var mapBox = document.getElementById('map-canvas');
  var MAPA_CENTRO = [-63.0410824, -9.9066373];
  var mapa3D = null;
  var mapaTempo = null;

  function mapaFallback() {
    if (!mapBox || mapBox.querySelector('iframe')) return;

    if (mapaTempo) {
      window.clearTimeout(mapaTempo);
      mapaTempo = null;
    }

    // solta a instância antes de trocar o conteúdo, senão sobra um
    // canvas/WebGL órfão dentro do contêiner
    if (mapa3D) {
      try { mapa3D.remove(); } catch (erro) { /* já tinha sido removida */ }
      mapa3D = null;
    }

    mapBox.innerHTML =
      '<iframe src="https://www.google.com/maps?q=Rua+Salvador,+2076,+Ariquemes+-+RO,+76870-416&output=embed"' +
      ' title="Mapa: Rua Salvador, 2076 — Ariquemes - RO" loading="lazy"' +
      ' referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
  }

  function iniciarMapa3D() {
    if (!window.maplibregl) { mapaFallback(); return; }

    var errosTile = 0;
    var dadosChegaram = false;

    try {
      mapa3D = new window.maplibregl.Map({
        container: 'map-canvas',
        style: {
          version: 8,
          sources: {
            satelite: {
              type: 'raster',
              tiles: [
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
              ],
              tileSize: 256,
              // O Esri não tem imagem em nível 18 nesta região e devolve o
              // quadro cinza "Map data not yet available". Limitando a 17,
              // o MapLibre amplia o tile correto e o mapa aparece.
              maxzoom: 17,
              attribution: 'Esri, Maxar, Earthstar Geographics'
            }
          },
          layers: [
            { id: 'fundo', type: 'background', paint: { 'background-color': '#0b1b2b' } },
            { id: 'satelite', type: 'raster', source: 'satelite' }
          ]
        },
        center: MAPA_CENTRO,
        zoom: 16.6,
        pitch: 58,
        bearing: -20,
        cooperativeGestures: true,
        attributionControl: false
      });

      // O Esri não tem SLA: se os tiles começarem a falhar, em vez de deixar
      // a caixa escura vazia, caímos no iframe do Google.
      mapa3D.on('error', function (e) {
        var msg = e && e.error && e.error.message ? String(e.error.message) : '';
        if (/style/i.test(msg)) { mapaFallback(); return; }

        if (e && e.sourceId || /tile|fetch|network|http|cors/i.test(msg)) {
          errosTile += 1;
          if (errosTile >= 8) mapaFallback();
        }
      });

      mapa3D.on('sourcedata', function (e) {
        if (e && e.sourceId === 'satelite') dadosChegaram = true;
      });

      new window.maplibregl.Marker({ color: '#14d6c4' })
        .setLngLat(MAPA_CENTRO)
        .addTo(mapa3D);

      mapa3D.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), 'top-left');
    } catch (erro) {
      mapaFallback();
      return;
    }

    // rede travada, resposta que não chega ou tile que volta em branco:
    // depois de 8 s sem sinal de fonte, muda para o Google
    mapaTempo = window.setTimeout(function () {
      if (errosTile > 0 || !dadosChegaram) mapaFallback();
    }, 8000);
  }

  function carregarMapa3D() {
    if (!mapBox || mapBox.getAttribute('data-mapa') === '1') return;
    mapBox.setAttribute('data-mapa', '1');

    var folha = document.createElement('link');
    folha.rel = 'stylesheet';
    folha.href = 'assets/vendor/maplibre-gl.css';
    document.head.appendChild(folha);

    var script = document.createElement('script');
    script.src = 'assets/vendor/maplibre-gl.js';
    script.onload = iniciarMapa3D;
    script.onerror = mapaFallback;
    document.body.appendChild(script);
  }

  if (mapBox && 'IntersectionObserver' in window) {
    var mapaObserver = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        obs.disconnect();
        carregarMapa3D();
      });
    }, { rootMargin: '250px' });
    mapaObserver.observe(mapBox);
  } else {
    mapaFallback();
  }

  /* ---------------- Ano do rodapé ---------------- */
  var yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = String(new Date().getFullYear());
})();
