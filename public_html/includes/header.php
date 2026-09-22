<?php
require_once __DIR__.'/bootstrap.php';

$title = $title ?? 'Doces do Casal';
$u = current_user();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#4b2418">

<title><?=e($title)?></title>

<link
  rel="icon"
  type="image/png"
  href="/assets/img/SELO_PNG_SF_DDC.png"
>

<link
  rel="stylesheet"
  href="/assets/css/style.css?v=<?=@filemtime(__DIR__.'/../assets/css/style.css')?>"
>

<style>

/* =========================================================
   MENU DO USUÁRIO - DESKTOP
   ========================================================= */

.user-menu{
  position:relative;
}

.user-menu-toggle{
  display:inline-flex;
  align-items:center;
  gap:6px;
}

.user-menu-toggle::after{
  content:"";
  width:0;
  height:0;

  border-left:4px solid transparent;
  border-right:4px solid transparent;
  border-top:5px solid currentColor;
}

.user-menu-dropdown{
  position:absolute;
  right:0;
  top:calc(100% + 8px);

  z-index:60;

  min-width:150px;

  padding:8px;

  border:1px solid rgba(75,36,24,.14);
  border-radius:14px;

  background:#fff;

  box-shadow:
    0 16px 35px
    rgba(75,36,24,.14);

  opacity:0;
  visibility:hidden;

  transform:translateY(-4px);

  transition:.16s ease;
}

.user-menu:hover .user-menu-dropdown,
.user-menu:focus-within .user-menu-dropdown{
  opacity:1;
  visibility:visible;

  transform:translateY(0);
}

.user-menu-dropdown a{
  display:block;

  padding:10px 12px;

  border-radius:10px;

  color:#4b2418;

  text-decoration:none;

  font-weight:700;

  white-space:nowrap;
}

.user-menu-dropdown a:hover,
.user-menu-dropdown a:focus{
  background:#fff4ee;
}


/* =========================================================
   MENU MOBILE
   ========================================================= */

.mobile-menu-wrap{
  display:none;
  position:relative;
}

.mobile-menu-toggle{
  width:44px;
  height:44px;

  display:grid;
  place-items:center;

  padding:0;

  border:1px solid var(--line);
  border-radius:13px;

  background:#fff;

  color:var(--brown);

  cursor:pointer;

  font-size:25px;
  font-weight:900;

  line-height:1;
}

.mobile-menu-toggle:hover{
  background:var(--cream);
}

.mobile-menu-toggle:focus-visible{
  outline:3px solid rgba(107,50,33,.14);
  outline-offset:2px;
}

.mobile-menu-dropdown{
  position:absolute;

  top:calc(100% + 10px);
  right:0;

  z-index:90;

  width:min(
    270px,
    calc(100vw - 28px)
  );

  padding:8px;

  border:1px solid var(--line);
  border-radius:18px;

  background:#fff;

  box-shadow:
    0 20px 50px
    rgba(50,23,15,.16);

  opacity:0;
  visibility:hidden;

  transform:
    translateY(-6px)
    scale(.98);

  transform-origin:top right;

  transition:
    opacity .16s ease,
    transform .16s ease,
    visibility .16s ease;
}

.mobile-menu-dropdown.open{
  opacity:1;
  visibility:visible;

  transform:
    translateY(0)
    scale(1);
}

.mobile-menu-dropdown a{
  display:flex;
  align-items:center;

  min-height:44px;

  padding:10px 12px;

  border-radius:12px;

  color:var(--ink);

  font-weight:750;

  text-decoration:none;
}

.mobile-menu-dropdown a:hover,
.mobile-menu-dropdown a:focus{
  background:var(--cream);

  color:var(--brown);
}

.mobile-menu-separator{
  height:1px;

  margin:6px 5px;

  background:var(--line);
}

.mobile-menu-account{
  padding:7px 12px 5px;

  color:var(--muted);

  font-size:12px;
  font-weight:700;
}


/* =========================================================
   RESPONSIVO
   ========================================================= */

@media(max-width:900px){

  .nav-links{
    display:none;
  }

  .nav{
    position:relative;

    min-height:76px;

    gap:9px;
  }

  .brand{
    flex:0 0 auto;
  }

  .brand-logo{
    max-height:64px;
    width:auto;
    object-fit:contain;
  }

  .nav-actions{
    margin-left:auto;

    display:flex;
    align-items:center;

    gap:8px;
  }

  .nav-actions > .user-menu,
  .nav-actions > a.ghost{
    display:none;
  }

  .mobile-menu-wrap{
    display:block;

    margin-right:10px;
  }

  .cart-btn{
    white-space:nowrap;
  }

}


@media(max-width:620px){

  .nav{
    min-height:80px;

    gap:7px;
  }

  .brand-logo{
    max-height:72px;
    width:auto;
    object-fit:contain;
  }

  .cart-btn{
    padding:10px 12px;

    border-radius:12px;

    font-size:13px;
  }

  .cart-btn span{
    min-width:20px;
    height:20px;

    margin-left:4px;

    font-size:12px;
  }

  .mobile-menu-toggle{
    width:40px;
    height:40px;

    font-size:23px;
  }

  .mobile-menu-dropdown{
    right:0;
  }

}


@media(max-width:390px){

  .nav{
    min-height:76px;
  }

  .brand-logo{
    max-height:66px;
    width:auto;
    object-fit:contain;
  }

  .cart-btn{
    padding:9px 10px;

    font-size:12px;
  }

  .mobile-menu-wrap{
    margin-right:8px;
  }

}

</style>
</head>

<body>

<header class="topbar">

  <div class="container nav">


    <!-- LOGO -->

    <a
      class="brand"
      href="/"
      aria-label="Ir para o início"
    >

      <img
        class="brand-logo"
        src="<?=e($config['app']['logo'])?>"
        alt="Doces do Casal"
        width="800"
        height="800"
        decoding="async"
      >

    </a>


    <!-- MENU DESKTOP -->

    <nav
      class="nav-links"
      aria-label="Menu principal"
    >

      <a href="/#inicio">
        Início
      </a>

      <a href="/#cardapio">
        Cardápio
      </a>

      <a href="/encomendas.php">
        Encomendas
      </a>

      <a href="/#nossa-historia">
        Nossa História
      </a>

      <?php if(is_admin()): ?>

        <a href="/admin/">
          Admin
        </a>

      <?php endif; ?>

    </nav>


    <!-- AÇÕES -->

    <div class="nav-actions">


      <!-- USUÁRIO - DESKTOP -->

      <?php if($u): ?>

        <div class="user-menu">

          <button
            class="btn ghost user-menu-toggle"
            type="button"
          >

            <?=e(
              explode(
                ' ',
                trim($u['nome'])
              )[0]
            )?>

          </button>


          <div class="user-menu-dropdown">

            <a href="/cliente/">
              Minha conta
            </a>

            <a href="/auth/logout.php">
              Sair
            </a>

          </div>

        </div>

      <?php else: ?>

        <a
          class="btn ghost"
          href="/auth/login.php"
        >
          Entrar
        </a>

      <?php endif; ?>


      <!-- CARRINHO -->

      <button
        class="btn cart-btn"
        id="cartBtn"
        type="button"
      >

        Carrinho

        <span id="cartCount">
          0
        </span>

      </button>


      <!-- 3 PONTINHOS - MOBILE -->

      <div class="mobile-menu-wrap">

        <button
          id="mobileMenuToggle"
          class="mobile-menu-toggle"
          type="button"
          aria-label="Abrir menu"
          aria-expanded="false"
          aria-controls="mobileMenuDropdown"
        >
          ⋮
        </button>


        <nav
          id="mobileMenuDropdown"
          class="mobile-menu-dropdown"
          aria-label="Menu mobile"
        >

          <?php if($u): ?>

            <div class="mobile-menu-account">

              Olá,
              <?=e(
                explode(
                  ' ',
                  trim($u['nome'])
                )[0]
              )?>

            </div>

          <?php endif; ?>


          <a href="/#inicio">
            Início
          </a>

          <a href="/#cardapio">
            Cardápio
          </a>

          <a href="/encomendas.php">
            Encomendas
          </a>

          <a href="/#nossa-historia">
            Nossa História
          </a>


          <div class="mobile-menu-separator"></div>


          <?php if($u): ?>

            <a href="/cliente/">
              Minha conta
            </a>


            <?php if(is_admin()): ?>

              <a href="/admin/">
                Administração
              </a>

            <?php endif; ?>


            <a href="/auth/logout.php">
              Sair
            </a>


          <?php else: ?>

            <a href="/auth/login.php">
              Entrar
            </a>

            <a href="/auth/register.php">
              Criar conta
            </a>

          <?php endif; ?>

        </nav>

      </div>

    </div>

  </div>

</header>


<script>

(function(){

  const toggle =
    document.getElementById(
      'mobileMenuToggle'
    );

  const menu =
    document.getElementById(
      'mobileMenuDropdown'
    );


  if(
    !toggle
    ||
    !menu
  ){
    return;
  }


  function fecharMenu(){

    menu.classList.remove(
      'open'
    );

    toggle.setAttribute(
      'aria-expanded',
      'false'
    );

    toggle.setAttribute(
      'aria-label',
      'Abrir menu'
    );

  }


  function abrirMenu(){

    menu.classList.add(
      'open'
    );

    toggle.setAttribute(
      'aria-expanded',
      'true'
    );

    toggle.setAttribute(
      'aria-label',
      'Fechar menu'
    );

  }


  toggle.addEventListener(
    'click',
    function(event){

      event.stopPropagation();

      menu.classList.contains(
        'open'
      )
        ? fecharMenu()
        : abrirMenu();

    }
  );


  menu.querySelectorAll(
    'a'
  ).forEach(
    function(link){

      link.addEventListener(
        'click',
        fecharMenu
      );

    }
  );


  document.addEventListener(
    'click',
    function(event){

      if(
        !menu.contains(event.target)
        &&
        !toggle.contains(event.target)
      ){
        fecharMenu();
      }

    }
  );


  document.addEventListener(
    'keydown',
    function(event){

      if(
        event.key
        ===
        'Escape'
      ){

        fecharMenu();

        toggle.focus();

      }

    }
  );


  window.addEventListener(
    'resize',
    function(){

      if(
        window.innerWidth
        >
        900
      ){
        fecharMenu();
      }

    }
  );

})();

</script>
