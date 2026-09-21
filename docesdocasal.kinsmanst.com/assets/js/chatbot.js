(function(){
  const launcher = document.getElementById('ddcChatLauncher');
  const panel = document.getElementById('ddcChat');
  const close = document.getElementById('ddcChatClose');
  const body = document.getElementById('ddcChatBody');
  const input = document.getElementById('ddcChatInput');
  const send = document.getElementById('ddcChatSend');
  const teaser = document.getElementById('ddcChatTeaser');
  const teaserOpen = document.getElementById('ddcChatTeaserOpen');
  const teaserClose = document.getElementById('ddcChatTeaserClose');

  if (!launcher || !panel || !body) return;

  const whatsapp = (panel.dataset.whatsapp || '').replace(/\D/g,'');
  const loggedIn = panel.dataset.loggedIn === '1';
  const userName = (panel.dataset.userName || '').trim();

  let teaserTimer = null;
  let typingTimer = null;
  let started = false;

  const scrollBottom = () => {
    requestAnimationFrame(() => {
      body.scrollTop = body.scrollHeight;
    });
  };

  const addMessage = (text, type='bot') => {
    const row = document.createElement('div');
    row.className = 'ddc-message-row ' + type;

    if (type === 'bot') {
      const avatar = document.createElement('span');
      avatar.className = 'ddc-message-avatar';
      avatar.setAttribute('aria-hidden','true');
      avatar.innerHTML = '<svg viewBox="0 0 32 32"><path d="M16 27s-10-5.7-10-13.1C6 9.5 8.8 7 12.1 7c1.8 0 3.2.8 3.9 2 0.7-1.2 2.1-2 3.9-2C23.2 7 26 9.5 26 13.9 26 21.3 16 27 16 27Z" fill="currentColor"/></svg>';
      row.appendChild(avatar);
    }

    const el = document.createElement('div');
    el.className = 'ddc-message ' + type;
    el.textContent = text;
    row.appendChild(el);

    body.appendChild(row);
    scrollBottom();
  };

  const addOptions = (items) => {
    const wrap = document.createElement('div');
    wrap.className = 'ddc-chat-options';

    items.forEach(item => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'ddc-chat-option';
      b.innerHTML = '<span class="ddc-chat-option-icon" aria-hidden="true">' + (item.icon || '•') + '</span><span>' + item.label + '</span>';
      b.addEventListener('click', item.action);
      wrap.appendChild(b);
    });

    body.appendChild(wrap);
    scrollBottom();
  };

  const addTyping = (callback) => {
    const typing = document.createElement('div');
    typing.className = 'ddc-chat-typing';
    typing.setAttribute('aria-label','Digitando');
    typing.innerHTML = '<span></span><span></span><span></span>';
    body.appendChild(typing);
    scrollBottom();

    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      typing.remove();
      callback();
    }, 420);
  };

  const go = (url) => {
    window.location.href = url;
  };

  const openWhatsapp = (message) => {
    if (!whatsapp) {
      addTyping(() => addMessage('Nosso WhatsApp ainda não está configurado no site. Você pode usar Encomendas ou sua área de cliente.'));
      return;
    }
    window.open(
      'https://wa.me/' + whatsapp + '?text=' + encodeURIComponent(message),
      '_blank',
      'noopener'
    );
  };

  const mainOptions = () => {
    addOptions([
      {icon:'🍫', label:'Ver cardápio', action:()=>go('/#cardapio')},
      {icon:'🎁', label:'Fazer uma encomenda', action:()=>go('/encomendas.php')},
      {icon:'📦', label: loggedIn ? 'Meus pedidos' : 'Entrar e ver pedidos', action:()=>go('/cliente/')},
      ...(whatsapp ? [{icon:'↗', label:'Falar no WhatsApp', action:()=>openWhatsapp('Olá! Vim pelo site da Doces do Casal e gostaria de atendimento.')}]:[])
    ]);
  };

  const startConversation = () => {
    if (started) return;
    started = true;

    const hello = userName
      ? 'Oi, ' + userName + '! Que bom ter você por aqui. Como posso te ajudar hoje?'
      : 'Oi! Seja bem-vindo ao Doces do Casal. Como podemos adoçar seu dia?';

    addMessage(hello);
    mainOptions();
  };

  const respond = (raw) => {
    const t = (raw || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g,'');

    addTyping(() => {
      if (/encomenda|evento|festa|aniversario|casamento|quantidade grande|cento/.test(t)) {
        addMessage('Para datas especiais e quantidades maiores, use nossa área de Encomendas. Se você ainda não estiver logado, o site leva você ao login e depois retorna para o formulário.');
        addOptions([{icon:'🎁',label:'Fazer encomenda',action:()=>go('/encomendas.php')}]);
        return;
      }

      if (/sabor|cardapio|brownie|produto|doce|copo/.test(t)) {
        addMessage('Claro! No cardápio você encontra os doces disponíveis e pode adicionar direto ao carrinho.');
        addOptions([{icon:'🍫',label:'Abrir cardápio',action:()=>go('/#cardapio')}]);
        return;
      }

      if (/pedido|status|acompanhar|pagamento|pix/.test(t)) {
        addMessage(loggedIn
          ? 'Você já está conectado. Posso abrir sua área para acompanhar os pedidos.'
          : 'Entre na sua conta para acompanhar pedidos e informações de pagamento.');
        addOptions([{icon:'📦',label:loggedIn?'Abrir meus pedidos':'Entrar na conta',action:()=>go('/cliente/')}]);
        return;
      }

      if (/ponto|fidelidade|recompensa|gratis/.test(t)) {
        addMessage('Seus pontos e recompensas ficam na sua área de cliente.');
        addOptions([{icon:'★',label:'Ver fidelidade',action:()=>go('/cliente/')}]);
        return;
      }

      if (/entrega|retirada|endereco|cep/.test(t)) {
        addMessage('No pedido você pode escolher retirada ou entrega conforme as opções disponíveis. Para encomendas, informe os dados no formulário para análise.');
        return;
      }

      if (/whatsapp|atendente|pessoa|humano|falar|contato/.test(t)) {
        addMessage(whatsapp ? 'Claro! Vou abrir nosso WhatsApp para você.' : 'O WhatsApp ainda não está configurado no site.');
        if (whatsapp) {
          addOptions([{icon:'↗',label:'Abrir WhatsApp',action:()=>openWhatsapp('Olá! Vim pelo site da Doces do Casal e gostaria de falar com o atendimento.')}]);
        }
        return;
      }

      addMessage('Posso ajudar você com cardápio, encomendas, pedidos, entrega, fidelidade ou atendimento.');
      mainOptions();
    });
  };

  const hideTeaser = (remember=false) => {
    if (!teaser) return;
    teaser.classList.remove('show');
    teaser.setAttribute('aria-hidden','true');
    if (remember) {
      try { sessionStorage.setItem('ddc_chat_teaser_closed','1'); } catch(e) {}
    }
  };

  const showTeaser = () => {
    if (!teaser || panel.classList.contains('open')) return;
    let closed = false;
    try { closed = sessionStorage.getItem('ddc_chat_teaser_closed') === '1'; } catch(e) {}
    if (closed) return;

    teaser.classList.add('show');
    teaser.setAttribute('aria-hidden','false');
  };

  const openChat = () => {
    hideTeaser();
    panel.classList.add('open');
    panel.setAttribute('aria-hidden','false');
    launcher.classList.add('active');
    launcher.setAttribute('aria-expanded','true');
    startConversation();
    setTimeout(() => input?.focus(), 120);
  };

  const closeChat = () => {
    panel.classList.remove('open');
    panel.setAttribute('aria-hidden','true');
    launcher.classList.remove('active');
    launcher.setAttribute('aria-expanded','false');
  };

  launcher.addEventListener('click', () => {
    panel.classList.contains('open') ? closeChat() : openChat();
  });

  close?.addEventListener('click', closeChat);
  teaserOpen?.addEventListener('click', openChat);
  teaserClose?.addEventListener('click', (e) => {
    e.stopPropagation();
    hideTeaser(true);
  });

  const submit = () => {
    const value = (input?.value || '').trim();
    if (!value) return;
    addMessage(value,'user');
    input.value = '';
    respond(value);
  };

  send?.addEventListener('click', submit);
  input?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      submit();
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && panel.classList.contains('open')) {
      closeChat();
      launcher.focus();
    }
  });

  teaserTimer = setTimeout(showTeaser, 3800);

  window.addEventListener('pagehide', () => {
    clearTimeout(teaserTimer);
    clearTimeout(typingTimer);
  }, {once:true});
})();