-- =========================================================
-- DOCES DO CASAL - ENCOMENDAS
-- Execute uma vez no banco do site.
-- =========================================================

CREATE TABLE IF NOT EXISTS encomendas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NULL,

    nome VARCHAR(160) NOT NULL,
    telefone VARCHAR(30) NOT NULL,
    email VARCHAR(190) NULL,

    data_evento DATE NOT NULL,
    tipo_evento VARCHAR(80) NULL,

    tipo_entrega ENUM('retirada','entrega') NOT NULL DEFAULT 'retirada',
    cidade VARCHAR(120) NULL,
    bairro VARCHAR(120) NULL,

    observacao TEXT NULL,
    quantidade_total INT UNSIGNED NOT NULL DEFAULT 0,

    status ENUM(
        'nova',
        'em_analise',
        'orcamento_enviado',
        'aprovada',
        'recusada',
        'concluida',
        'cancelada'
    ) NOT NULL DEFAULT 'nova',

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL,

    INDEX idx_encomenda_usuario (usuario_id),
    INDEX idx_encomenda_data (data_evento),
    INDEX idx_encomenda_status (status)
);

CREATE TABLE IF NOT EXISTS encomenda_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encomenda_id BIGINT UNSIGNED NOT NULL,
    produto_id BIGINT UNSIGNED NULL,
    produto_nome VARCHAR(190) NOT NULL,
    quantidade INT UNSIGNED NOT NULL,

    INDEX idx_encomenda_item_encomenda (encomenda_id),
    INDEX idx_encomenda_item_produto (produto_id)
);

-- Opcional: se sua tabela configuracoes tiver chave unica,
-- voce pode cadastrar o WhatsApp por ela.
-- Troque 5531999999999 pelo numero real com DDI + DDD.
--
-- INSERT INTO configuracoes (chave, valor)
-- VALUES ('whatsapp_numero', '5531999999999')
-- ON DUPLICATE KEY UPDATE valor = VALUES(valor);
--
-- E-mail de privacidade, se desejar exibir um canal direto:
--
-- INSERT INTO configuracoes (chave, valor)
-- VALUES ('email_privacidade', 'seuemail@seudominio.com')
-- ON DUPLICATE KEY UPDATE valor = VALUES(valor);
