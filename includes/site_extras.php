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

    $ddcChatUser = current_user();
    $ddcChatUserName = '';
    if ($ddcChatUser && !empty($ddcChatUser['nome'])) {
        $ddcChatUserName = trim(explode(' ', trim((string)$ddcChatUser['nome']))[0] ?? '');
    }
?>
<link rel="stylesheet" href="/assets/css/site-extras.css?v=<?=@filemtime(__DIR__.'/../assets/css/site-extras.css')?>">

<!-- ATENDIMENTO INTERATIVO -->
<div id="ddcChatWidget" class="ddc-chat-widget">
    <div
        id="ddcChatTeaser"
        class="ddc-chat-teaser"
        role="status"
        aria-live="polite"
        aria-hidden="true"
    >
        <button
            id="ddcChatTeaserClose"
            class="ddc-chat-teaser-close"
            type="button"
            aria-label="Fechar mensagem"
        >×</button>

        <button
            id="ddcChatTeaserOpen"
            class="ddc-chat-teaser-content"
            type="button"
        >
            <span class="ddc-chat-teaser-avatar" aria-hidden="true">
                <svg viewBox="0 0 32 32" role="img">
                    <path d="M16 27s-10-5.7-10-13.1C6 9.5 8.8 7 12.1 7c1.8 0 3.2.8 3.9 2 0.7-1.2 2.1-2 3.9-2C23.2 7 26 9.5 26 13.9 26 21.3 16 27 16 27Z" fill="currentColor"/>
                </svg>
            </span>
            <span>
                <strong>Oi! Posso te ajudar?</strong>
                <small>Escolha seu doce, faça uma encomenda ou fale com a gente.</small>
            </span>
        </button>
    </div>

    <button
        id="ddcChatLauncher"
        class="ddc-chat-launcher"
        type="button"
        aria-label="Abrir atendimento Doces do Casal"
        aria-expanded="false"
        aria-controls="ddcChat"
    >
        <span class="ddc-chat-launcher-icon ddc-chat-icon-chat" aria-hidden="true">
            <svg viewBox="0 0 32 32">
                <path d="M7.5 7.5h17a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-8.6l-5.7 4v-4H7.5a3 3 0 0 1-3-3v-9a3 3 0 0 1 3-3Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
                <path d="M11 15.2c1.3-2.1 3.4-2.1 5 0 1.6-2.1 3.7-2.1 5 0 1.7 2.8-5 6.1-5 6.1s-6.7-3.3-5-6.1Z" fill="currentColor"/>
            </svg>
        </span>
        <span class="ddc-chat-launcher-icon ddc-chat-icon-close" aria-hidden="true">
            <svg viewBox="0 0 32 32">
                <path d="M9 9l14 14M23 9 9 23" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
            </svg>
        </span>
        <span class="ddc-chat-unread" aria-hidden="true"></span>
    </button>

    <section
        id="ddcChat"
        class="ddc-chat"
        data-whatsapp="<?=e($ddcWhatsapp)?>"
        data-logged-in="<?=$ddcChatUser ? '1' : '0'?>"
        data-user-name="<?=e($ddcChatUserName)?>"
        aria-label="Atendimento Doces do Casal"
        aria-hidden="true"
    >
        <div class="ddc-chat-head">
            <div class="ddc-chat-brand">
                <span class="ddc-chat-avatar" aria-hidden="true">
                    <svg viewBox="0 0 32 32">
                        <path d="M16 27s-10-5.7-10-13.1C6 9.5 8.8 7 12.1 7c1.8 0 3.2.8 3.9 2 0.7-1.2 2.1-2 3.9-2C23.2 7 26 9.5 26 13.9 26 21.3 16 27 16 27Z" fill="currentColor"/>
                    </svg>
                </span>
                <div>
                    <strong>Doces do Casal</strong>
                    <small><span class="ddc-chat-online-dot"></span> Atendimento online</small>
                </div>
            </div>

            <button id="ddcChatClose" class="ddc-chat-close" type="button" aria-label="Fechar atendimento">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div id="ddcChatBody" class="ddc-chat-body" aria-live="polite"></div>

        <div class="ddc-chat-input">
            <input
                id="ddcChatInput"
                type="text"
                maxlength="250"
                placeholder="Digite sua dúvida..."
                autocomplete="off"
                aria-label="Digite sua dúvida"
            >
            <button id="ddcChatSend" class="ddc-chat-send" type="button" aria-label="Enviar mensagem">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 4l17 8-17 8 3-8-3-8Zm3 8h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <div class="ddc-chat-footnote">
            Atendimento guiado • respostas rápidas
        </div>
    </section>
</div>

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
