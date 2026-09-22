<?php
/*
 * Inclua este arquivo uma unica vez no footer publico, antes de </body>:
 * <?php include __DIR__.'/site_extras.php'; ?>
 */

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$ddcAdminArea = str_starts_with($uri, '/admin/');

if (!$ddcAdminArea):

    $ddcWhatsapp = preg_replace(
        '/\D+/',
        '',
        (string)setting('whatsapp_numero', '')
    );
?>
<link rel="stylesheet" href="/assets/css/site-extras.css">

<!-- CHATBOT GUIADO -->
<button
    id="ddcChatLauncher"
    class="ddc-chat-launcher"
    type="button"
    aria-label="Abrir atendimento"
    title="Precisa de ajuda?"
>💬</button>

<section
    id="ddcChat"
    class="ddc-chat"
    data-whatsapp="<?=e($ddcWhatsapp)?>"
    aria-label="Atendimento Doces do Casal"
>
    <div class="ddc-chat-head">
        <div>
            <strong>Doces do Casal</strong>
            <small>Assistente virtual</small>
        </div>
        <button id="ddcChatClose" class="ddc-chat-close" type="button" aria-label="Fechar">×</button>
    </div>

    <div id="ddcChatBody" class="ddc-chat-body"></div>

    <div class="ddc-chat-input">
        <input
            id="ddcChatInput"
            type="text"
            maxlength="250"
            placeholder="Digite sua dúvida..."
            autocomplete="off"
        >
        <button id="ddcChatSend" class="ddc-chat-send" type="button">Enviar</button>
    </div>
</section>


<!-- BANNER DE COOKIES -->
<div id="ddcCookieBanner" class="ddc-cookie-banner" role="region" aria-label="Aviso de cookies">
    <div class="ddc-cookie-inner">
        <div class="ddc-cookie-copy">
            <h3>Sua privacidade importa</h3>
            <p>
                Usamos recursos essenciais para o funcionamento do site e,
                com sua autorização, recursos analíticos e de marketing.
                Consulte nossa <a href="/politica-de-cookies.php"><u>Política de Cookies</u></a>.
            </p>
        </div>

        <div class="ddc-cookie-actions">
            <button class="ddc-cookie-btn primary" type="button" data-ddc-cookie-accept>
                Aceitar todos
            </button>
            <button class="ddc-cookie-btn" type="button" data-ddc-cookie-reject>
                Recusar opcionais
            </button>
            <button class="ddc-cookie-btn" type="button" data-ddc-cookie-settings>
                Preferências
            </button>
        </div>
    </div>
</div>

<div id="ddcCookieModal" class="ddc-cookie-modal" role="dialog" aria-modal="true" aria-label="Preferências de cookies">
    <div class="ddc-cookie-panel">
        <div style="display:flex;justify-content:space-between;gap:15px;align-items:flex-start">
            <div>
                <h2>Preferências de cookies</h2>
                <p>Escolha quais categorias opcionais podem ser utilizadas.</p>
            </div>
            <button class="ddc-cookie-btn" type="button" data-ddc-cookie-close>Fechar</button>
        </div>

        <div class="ddc-cookie-option">
            <div>
                <h3>Essenciais</h3>
                <p>Necessários para login, segurança, carrinho, preferências e funcionamento do site.</p>
            </div>
            <strong>Sempre ativos</strong>
        </div>

        <div class="ddc-cookie-option">
            <div>
                <h3>Analíticos</h3>
                <p>Permitem medir uso e desempenho quando ferramentas de análise estiverem configuradas.</p>
            </div>
            <label class="ddc-cookie-switch">
                <input id="ddcCookieAnalytics" type="checkbox" aria-label="Permitir analíticos">
            </label>
        </div>

        <div class="ddc-cookie-option">
            <div>
                <h3>Marketing</h3>
                <p>Permitem medir campanhas e publicidade quando essas ferramentas estiverem configuradas.</p>
            </div>
            <label class="ddc-cookie-switch">
                <input id="ddcCookieMarketing" type="checkbox" aria-label="Permitir marketing">
            </label>
        </div>

        <div class="ddc-cookie-foot">
            <button class="ddc-cookie-btn" type="button" data-ddc-cookie-reject>
                Recusar opcionais
            </button>
            <button class="ddc-cookie-btn primary" type="button" data-ddc-cookie-save>
                Salvar preferências
            </button>
        </div>
    </div>
</div>

<script>
window.dataLayer = window.dataLayer || [];
window.gtag = window.gtag || function(){window.dataLayer.push(arguments);};
window.gtag('consent','default',{
    analytics_storage:'denied',
    ad_storage:'denied',
    ad_user_data:'denied',
    ad_personalization:'denied',
    functionality_storage:'granted',
    security_storage:'granted',
    wait_for_update:500
});
</script>
<script src="/assets/js/cookies.js" defer></script>
<script src="/assets/js/chatbot.js" defer></script>
<?php endif; ?>
