/* =========================================================
   CHAT — assistente do consultório
   Vanilla JS, sem dependências. Não altera main.js.
   ========================================================= */
(function () {
  'use strict';

  var WHATSAPP = 'https://wa.me/5569999880560';

  var raiz = document.createElement('div');
  raiz.id = 'chat-assistente';
  document.body.appendChild(raiz);

  raiz.innerHTML =
    '<button type="button" class="chat-fab" id="chat-fab" aria-label="Abrir atendimento" aria-expanded="false">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.9-.9L3 20.5l1.5-4.9A8.4 8.4 0 1 1 21 11.5Z"/>' +
        '<path d="M8.6 9.3h.01M12 9.3h.01M15.4 9.3h.01" stroke-width="2.6"/>' +
      '</svg>' +
      '<span>Atendimento</span>' +
    '</button>' +

    '<section class="chat-panel" id="chat-panel" hidden aria-label="Atendimento por chat">' +
      '<header class="chat-topo">' +
        '<span class="chat-avatar" aria-hidden="true">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
            '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>' +
          '</svg>' +
        '</span>' +
        '<span class="chat-topo-texto">' +
          '<strong>Atendimento</strong>' +
          '<span><i class="chat-status-dot" aria-hidden="true"></i>Responde agora</span>' +
        '</span>' +
        '<button type="button" class="chat-fechar" id="chat-fechar" aria-label="Fechar atendimento">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
            '<path d="M6 6l12 12M18 6L6 18"/>' +
          '</svg>' +
        '</button>' +
      '</header>' +

      '<div class="chat-mensagens" id="chat-mensagens" role="log" aria-live="polite" aria-atomic="false"></div>' +

      '<form class="chat-form" id="chat-form">' +
        '<label for="chat-campo" class="chat-aviso" style="position:absolute;left:-9999px">Escreva sua mensagem</label>' +
        '<textarea class="chat-campo" id="chat-campo" rows="1" placeholder="Escreva sua mensagem…" autocomplete="off" maxlength="600"></textarea>' +
        '<button type="submit" class="chat-enviar" id="chat-enviar" aria-label="Enviar mensagem" disabled>' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M4.5 12h15M12.5 5l7 7-7 7"/>' +
          '</svg>' +
        '</button>' +
      '</form>' +
    '</section>';

  var fab = raiz.querySelector('#chat-fab');
  var painel = raiz.querySelector('#chat-panel');
  var caixa = raiz.querySelector('#chat-mensagens');
  var form = raiz.querySelector('#chat-form');
  var campo = raiz.querySelector('#chat-campo');
  var enviar = raiz.querySelector('#chat-enviar');
  var fechar = raiz.querySelector('#chat-fechar');

  var ocupado = false;
  var token = '';

  /* ---------------------------------------------------------
     token do chat: precisa vir de uma pagina do servidor
     --------------------------------------------------------- */
  function descobrirToken(callback) {
    if (token) {
      callback(token);
      return;
    }
    // sessao nova: recarrega uma pagina leve do servidor
    fetch('auth/sessao.php', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (dados) {
        token = dados && dados.token ? dados.token : '';
        callback(token);
      })
      .catch(function () { callback(''); });
  }

  function rolar() {
    caixa.scrollTop = caixa.scrollHeight;
  }

  function adicionar(texto, de, opcoes) {
    opcoes = opcoes || {};

    var el = document.createElement('div');
    el.className = 'chat-msg chat-msg--' + de;

    if (de === 'erro') {
      el.textContent = texto;
      caixa.appendChild(el);
      rolar();
      return el;
    }

    // texto simples, sem HTML vindo do servidor
    el.textContent = texto;

    if (opcoes.whatsapp) {
      var link = document.createElement('a');
      link.className = 'chat-atalho';
      link.href = WHATSAPP;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = 'Falar no WhatsApp';
      el.appendChild(link);
    }

    caixa.appendChild(el);
    rolar();
    return el;
  }

  function mostrarDigitando() {
    var el = document.createElement('div');
    el.className = 'chat-msg chat-msg--agente chat-digitando';
    el.innerHTML = '<span></span><span></span><span></span>';
    caixa.appendChild(el);
    rolar();
    return el;
  }

  function saudacao() {
    return 'Olá 😊 Eu sou a assistente do consultório do Dr. Maycon Gomes. '
      + 'Ainda fica alguma dúvida, ou você quer marcar uma avaliação?';
  }

  /* ---------------------------------------------------------
     abrir e fechar
     --------------------------------------------------------- */
  function abrir(pergunta) {
    painel.hidden = false;
    painel.classList.remove('is-closing');
    fab.hidden = true;
    fab.setAttribute('aria-expanded', 'true');

    if (caixa.childElementCount === 0) {
      adicionar(saudacao(), 'agente');
    }

    if (pergunta) {
      window.setTimeout(function () {
        enviarMensagem(pergunta);
        campo.focus();
      }, 420);
    } else {
      campo.focus();
    }
  }

  function fechar() {
    painel.classList.add('is-closing');
    window.setTimeout(function () {
      painel.hidden = true;
      painel.classList.remove('is-closing');
      fab.hidden = false;
      fab.setAttribute('aria-expanded', 'false');
      fab.focus();
    }, 200);
  }

  /* ---------------------------------------------------------
     envio
     --------------------------------------------------------- */
  function enviarMensagem(texto) {
    var mensagem = String(texto || '').trim();
    if (!mensagem || ocupado) {
      return;
    }

    adicionar(mensagem, 'visitante');
    ocupado = true;
    campo.value = '';
    campo.style.height = 'auto';
    atualizarBotao();

    var digitando = mostrarDigitando();

    descobrirToken(function (tk) {
      if (!tk) {
        digitando.remove();
        ocupado = false;
        atualizarBotao();
        adicionar('Não consegui abrir o atendimento agora. Tente de novo em instantes.', 'erro');
        return;
      }

      fetch('api/chat.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Chat-Token': tk
        },
        body: JSON.stringify({ mensagem: mensagem })
      })
        .then(function (r) {
          if (r.status === 419) { return Promise.reject(new Error('sessao')); }
          return r.json();
        })
        .then(function (dados) {
          digitando.remove();
          ocupado = false;
          atualizarBotao();

          if (!dados || !dados.resposta) {
            adicionar('Não consegui responder agora. Pode tentar de novo?', 'erro');
            return;
          }

          var resposta = dados.resposta || '';

          // o atalho do WhatsApp aparece quando a assistente
          // encaminha a conversa para o atendimento humano
          var encaminha = /Prefiro te encaminhar|encaminhar para o Dr\. Maycon|falar direto/i.test(resposta);

          adicionar(resposta, 'agente', { whatsapp: encaminha });

          campo.focus();
        })
        .catch(function () {
          digitando.remove();
          ocupado = false;
          atualizarBotao();
          adicionar('Ops, deu uma falha na conexão. Tente novamente — ou fale com a gente no '
            + 'WhatsApp (69) 99988-0560.', 'erro');
        });
    });
  }

  function atualizarBotao() {
    enviar.disabled = ocupado || campo.value.trim() === '';
  }

  /* ---------------------------------------------------------
     eventos
     --------------------------------------------------------- */
  fab.addEventListener('click', function () { abrir(''); });
  fechar.addEventListener('click', fechar);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    enviarMensagem(campo.value);
  });

  campo.addEventListener('input', function () {
    campo.style.height = 'auto';
    campo.style.height = Math.min(campo.scrollHeight, 96) + 'px';
    atualizarBotao();
  });

  campo.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      enviarMensagem(campo.value);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !painel.hidden) {
      fechar();
    }
  });

  /* ---------------------------------------------------------
     botoes do site que passam a abrir o chat
     data-chat-pergunta  = pergunta inicial
     --------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    var alvo = e.target.closest('[data-chat-pergunta]');
    if (!alvo) {
      return;
    }
    e.preventDefault();
    abrir(alvo.getAttribute('data-chat-pergunta') || '');
  });

  ajustar();
  window.addEventListener('resize', ajustar);

  function ajustar() {
    if (window.innerWidth < 560) {
      fab.querySelector('span').textContent = '';
    } else {
      fab.querySelector('span').textContent = 'Atendimento';
    }
  }
})();