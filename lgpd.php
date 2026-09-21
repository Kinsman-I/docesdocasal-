<?php
require_once __DIR__.'/includes/bootstrap.php';
$title = 'LGPD e Proteção de Dados | Doces do Casal';
$emailPrivacidade = trim((string)setting('email_privacidade', ''));
include __DIR__.'/includes/header.php';
?>

<section class="ddc-page-hero">
    <div class="container">
        <span class="eyebrow">TRANSPARÊNCIA E SEGURANÇA</span>
        <h1>LGPD e Proteção de Dados</h1>
        <p>Conheça os princípios adotados pela Doces do Casal no tratamento de dados pessoais.</p>
    </div>
</section>

<section class="section" style="padding-top:12px">
<div class="container">
<article class="ddc-legal-card">
    <h2>Nosso compromisso</h2>
    <p>
        Buscamos tratar dados pessoais de forma transparente, segura, adequada às finalidades informadas e limitada ao necessário para a operação.
    </p>

    <h2>Como tratamos dados</h2>
    <ul>
        <li>coletamos informações necessárias para conta, pedidos, entrega, fidelidade, atendimento e encomendas;</li>
        <li>utilizamos dados de navegação opcionais conforme as preferências de cookies;</li>
        <li>não comercializamos dados pessoais;</li>
        <li>limitamos o acesso aos dados a quem precisa deles para executar as atividades aplicáveis;</li>
        <li>mantemos registros necessários para segurança, auditoria e exercício de direitos.</li>
    </ul>

    <h2>Direitos do titular</h2>
    <p>
        Você pode exercer os direitos previstos na Lei nº 13.709/2018, observadas as condições legais aplicáveis,
        incluindo confirmação de tratamento, acesso, correção, eliminação ou anonimização quando cabível,
        informações sobre compartilhamento e revogação do consentimento.
    </p>

    <h2>Solicitações</h2>
    <p>
        Utilize os canais de atendimento disponibilizados no site.
        <?php if ($emailPrivacidade): ?>
            Para privacidade, também está cadastrado o e-mail
            <a href="mailto:<?=e($emailPrivacidade)?>"><?=e($emailPrivacidade)?></a>.
        <?php endif; ?>
    </p>

    <h2>Documentos relacionados</h2>
    <ul>
        <li><a href="/politica-de-privacidade.php">Política de Privacidade</a></li>
        <li><a href="/politica-de-cookies.php">Política de Cookies</a></li>
        <li><a href="/termos-de-uso.php">Termos de Uso</a></li>
    </ul>
</article>
</div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
