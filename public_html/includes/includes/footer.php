<footer class="ddc-footer">
  <div class="container ddc-footer-bar">
    <a class="ddc-footer-brand" href="/" aria-label="Doces do Casal - Início">
      <img
        class="ddc-footer-logo"
        src="<?=e($config['app']['logo'])?>"
        alt="Doces do Casal"
        width="52"
        height="52"
        loading="lazy"
        decoding="async"
      >
    </a>

    <div class="ddc-footer-content">
      <span class="ddc-footer-tagline">Feito com amor. Feito a dois.</span>
      <span class="ddc-footer-sep" aria-hidden="true">·</span>
      <span>© <?=date('Y')?> Doces do Casal</span>
      <span class="ddc-footer-sep" aria-hidden="true">·</span>

      <nav class="ddc-footer-links" aria-label="Links do rodapé">
        <a href="/politica-de-privacidade.php">Privacidade</a>
        <a href="/politica-de-cookies.php">Cookies</a>
        <a href="/lgpd.php">LGPD</a>
        <a href="/termos-de-uso.php">Termos de Uso</a>
        <button type="button" data-ddc-cookie-settings>Cookies</button>
      </nav>

      <span class="ddc-footer-sep" aria-hidden="true">·</span>
      <span class="ddc-footer-dev">Desenvolvido pela <a href="https://kinsmanst.com" target="_blank" rel="noopener noreferrer">Kinsman Safe Tech</a></span>
    </div>
  </div>
</footer>

<div class="toast" id="toast"></div>

<script src="/assets/js/app.js" defer></script>

<?php include __DIR__.'/site_extras.php'; ?>

<style>
.ddc-footer{
  padding:0;
  margin:0;
}

.ddc-footer-bar{
  min-height:64px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:14px;
  padding:8px 20px;
}

.ddc-footer-brand{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  flex:0 0 auto;
}

.ddc-footer-logo{
  width:48px;
  height:48px;
  object-fit:contain;
  display:block;
}

.ddc-footer-content{
  display:flex;
  align-items:center;
  justify-content:center;
  flex-wrap:wrap;
  gap:7px;
  font-size:13px;
  line-height:1.25;
  text-align:center;
}

.ddc-footer-links{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  flex-wrap:wrap;
  gap:10px;
}

.ddc-footer a,
.ddc-footer-links button{
  color:inherit;
  font:inherit;
  text-decoration:none;
  transition:opacity .2s ease;
}

.ddc-footer-links button{
  appearance:none;
  border:0;
  padding:0;
  margin:0;
  background:transparent;
  cursor:pointer;
}

.ddc-footer a:hover,
.ddc-footer-links button:hover{
  opacity:.7;
  text-decoration:underline;
}

.ddc-footer-dev a{
  font-weight:700;
}

@media (max-width:900px){
  .ddc-footer-bar{
    min-height:72px;
    padding:10px 14px;
    gap:8px;
  }

  .ddc-footer-logo{
    width:40px;
    height:40px;
  }

  .ddc-footer-content{
    font-size:12px;
    gap:5px 7px;
  }

  .ddc-footer-links{
    gap:7px;
  }
}

@media (max-width:560px){
  .ddc-footer-bar{
    flex-direction:column;
    gap:5px;
    padding:9px 12px 12px;
  }

  .ddc-footer-logo{
    width:38px;
    height:38px;
  }

  .ddc-footer-content{
    max-width:100%;
  }

  .ddc-footer-sep{
    display:none;
  }
}
</style>

</body>
</html>
