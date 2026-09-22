<?php
require_once __DIR__.'/includes/bootstrap.php';
$title = 'Política de Privacidade | Doces do Casal';
$emailPrivacidade = trim((string)setting('email_privacidade', ''));
include __DIR__.'/includes/header.php';
?>

<section class="ddc-page-hero">
    <div class="container">
        <span class="eyebrow">PRIVACIDADE E PROTEÇÃO DE DADOS</span>
        <h1>Política de Privacidade</h1>
        <p>Transparência sobre como a Doces do Casal trata dados pessoais usados no atendimento, pedidos, encomendas e funcionamento do site.</p>
    </div>
</section>

<section class="section" style="padding-top:12px">
<div class="container">
<article class="ddc-legal-card">
    <p><strong>Última atualização:</strong> 27 de agosto de 2026</p>

    <h2>1. Quem somos</h2>
    <p>
        A Doces do Casal atua na produção e comercialização de doces artesanais.
        Esta Política explica como os dados pessoais podem ser tratados durante o uso do site,
        realização de pedidos, participação no programa de fidelidade e envio de encomendas.
    </p>

    <h2>2. Dados que podemos tratar</h2>
    <p>Conforme a funcionalidade utilizada, podemos tratar:</p>
    <ul>
        <li>nome, telefone, e-mail e dados da conta;</li>
        <li>endereço e informações necessárias para entrega;</li>
        <li>pedidos, itens adquiridos, histórico de status e informações de pagamento;</li>
        <li>pontos e movimentações do programa de fidelidade;</li>
        <li>dados informados em solicitações de encomenda, como data, quantidades e observações;</li>
        <li>dados técnicos e de segurança, como endereço IP, sessão, navegador e registros de auditoria;</li>
        <li>preferências de cookies e, quando autorizado e configurado, dados analíticos ou de marketing.</li>
    </ul>

    <h2>3. Para que usamos esses dados</h2>
    <ul>
        <li>criar e administrar sua conta;</li>
        <li>receber, preparar, entregar e acompanhar pedidos;</li>
        <li>gerar e conferir pagamentos, inclusive Pix;</li>
        <li>administrar o programa de fidelidade;</li>
        <li>analisar e responder solicitações de encomenda;</li>
        <li>prestar atendimento e prevenir fraude ou uso indevido;</li>
        <li>cumprir obrigações legais e exercer direitos;</li>
        <li>medir e melhorar o site quando houver consentimento para recursos opcionais.</li>
    </ul>

    <h2>4. Bases legais</h2>
    <p>
        O tratamento poderá ocorrer, conforme o caso, para execução de contrato ou procedimentos preliminares,
        cumprimento de obrigação legal ou regulatória, exercício regular de direitos, legítimo interesse e consentimento.
    </p>

    <h2>5. Pagamentos</h2>
    <p>
        O site pode gerar informações para pagamento via Pix. Não solicitamos dados completos de cartão no fluxo atual.
        A confirmação e a conciliação do pagamento podem envolver a instituição financeira utilizada na transação.
    </p>

    <h2>6. Cookies e armazenamento local</h2>
    <p>
        Recursos essenciais podem ser utilizados para sessão, segurança, carrinho e preferências.
        Recursos analíticos e de marketing somente devem ser ativados conforme sua escolha e quando tais ferramentas estiverem configuradas.
        Consulte a <a href="/politica-de-cookies.php">Política de Cookies</a>.
    </p>

    <h2>7. Compartilhamento</h2>
    <p>
        Não comercializamos dados pessoais. Informações podem ser tratadas por fornecedores necessários à operação,
        como hospedagem, segurança, serviços de endereço, atendimento e instituições financeiras, sempre conforme a finalidade aplicável.
    </p>

    <h2>8. Retenção e segurança</h2>
    <p>
        Os dados são mantidos pelo tempo necessário para cumprir as finalidades informadas, obrigações legais e exercício de direitos.
        Adotamos medidas técnicas e administrativas compatíveis com a operação para reduzir riscos de acesso, alteração, perda ou divulgação indevida.
    </p>

    <h2>9. Direitos do titular</h2>
    <p>
        Nos termos da LGPD, o titular pode solicitar, quando aplicável, confirmação de tratamento, acesso, correção,
        anonimização, bloqueio, eliminação, portabilidade, informações sobre compartilhamento e revogação do consentimento.
    </p>

    <h2>10. Como falar sobre privacidade</h2>
    <p>
        Você pode utilizar os canais de atendimento disponibilizados no site para solicitações relacionadas a dados pessoais.
        <?php if ($emailPrivacidade): ?>
            O canal cadastrado para privacidade é
            <a href="mailto:<?=e($emailPrivacidade)?>"><?=e($emailPrivacidade)?></a>.
        <?php endif; ?>
    </p>

    <h2>11. Atualizações</h2>
    <p>
        Esta Política poderá ser atualizada para refletir mudanças legais, técnicas ou operacionais.
        A versão vigente permanecerá disponível nesta página.
    </p>

    <div class="ddc-legal-note">
        <strong>Documentos relacionados:</strong>
        <a href="/politica-de-cookies.php">Política de Cookies</a> ·
        <a href="/lgpd.php">LGPD</a> ·
        <a href="/termos-de-uso.php">Termos de Uso</a>
    </div>
</article>
</div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
