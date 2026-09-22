const $ = (s) => document.querySelector(s);
const money = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
let cart = JSON.parse(localStorage.getItem('bdc_cart') || '[]');

function toast(msg) {
  const e = $('#toast');
  if (!e) return;
  e.textContent = msg;
  e.classList.add('show');
  clearTimeout(window.__t);
  window.__t = setTimeout(() => e.classList.remove('show'), 2200);
}

function save() {
  localStorage.setItem('bdc_cart', JSON.stringify(cart));
  renderCart();
}

function maxStock(item) {
  const stock = Number(item.stock);
  return Number.isFinite(stock) && stock >= 0 ? stock : null;
}

function add(id, name, price, stock = null) {
  const limit = stock === '' || stock === null ? null : Number(stock);
  const i = cart.find((x) => x.id === id);

  if (Number.isFinite(limit) && limit <= 0) {
    toast('Produto esgotado no momento');
    return;
  }

  if (i) {
    i.stock = Number.isFinite(limit) ? limit : i.stock;

    if (maxStock(i) !== null && i.qty >= maxStock(i)) {
      toast('Somente ' + maxStock(i) + ' unidade(s) disponíveis');
      return;
    }

    i.qty++;
  } else {
    cart.push({
      id,
      name,
      price: Number(price),
      qty: 1,
      stock: Number.isFinite(limit) ? limit : null
    });
  }

  save();
  toast('Adicionado ao carrinho');
}

function qty(id, d) {
  const i = cart.find((x) => x.id === id);
  if (!i) return;

  const next = i.qty + d;

  if (next <= 0) {
    cart = cart.filter((x) => x.id !== id);
    save();
    return;
  }

  if (maxStock(i) !== null && next > maxStock(i)) {
    toast('Somente ' + maxStock(i) + ' unidade(s) disponíveis');
    return;
  }

  i.qty = next;
  save();
}

function total() {
  return cart.reduce((s, i) => s + i.price * i.qty, 0);
}

function renderCart() {
  const c = $('#cartCount');
  if (c) c.textContent = cart.reduce((s, i) => s + i.qty, 0);

  const body = $('#cartItems');
  if (body) {
    body.innerHTML = cart.length
      ? cart.map((i) => {
          const limit = maxStock(i);
          const stockText = limit !== null
            ? `<small class="cart-stock">Disponível: ${limit}</small>`
            : '';

          return `<div class="cart-item"><div><strong>${i.name}</strong><div>${money(i.price)}</div>${stockText}</div><div class="qty"><button onclick="qty(${i.id},-1)">−</button><strong>${i.qty}</strong><button onclick="qty(${i.id},1)">+</button></div></div>`;
        }).join('')
      : '<p>Seu carrinho está vazio.</p>';
  }

  const t = $('#cartTotal');
  if (t) t.textContent = money(total());
}

document.querySelectorAll('[data-add]').forEach((b) => {
  b.addEventListener('click', () => {
    add(
      Number(b.dataset.add),
      b.dataset.name,
      Number(b.dataset.price),
      b.dataset.stock ?? null
    );
  });
});

const drawer = $('#cartDrawer');
const back = $('#backdrop');

$('#cartBtn')?.addEventListener('click', () => {
  drawer?.classList.add('open');
  back?.classList.add('show');
});

$('#closeCart')?.addEventListener('click', () => {
  drawer?.classList.remove('open');
  back?.classList.remove('show');
});

back?.addEventListener('click', () => {
  drawer?.classList.remove('open');
  back?.classList.remove('show');
});

$('#checkoutBtn')?.addEventListener('click', () => {
  if (!cart.length) return toast('Adicione um brownie primeiro');
  localStorage.setItem('bdc_cart', JSON.stringify(cart));
  location.href = '/cliente/checkout.php';
});

renderCart();
