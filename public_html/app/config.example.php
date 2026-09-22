<?php

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'SEU_BANCO_DE_DADOS',
        'user' => 'SEU_USUARIO_DO_BANCO',
        'pass' => 'SUA_SENHA_DO_BANCO',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'url' => 'https://docesdocasal.kinsmanst.com',
        'name' => 'Doces do Casal',
        'timezone' => 'America/Sao_Paulo',
        'session_name' => 'doces_do_casal_session',
        'logo' => '/assets/img/SELO_PNG_SF_DDC.png',
    ],

    'turnstile' => [
        'site_key' => 'SUA_SITE_KEY',
        'secret_key' => 'SUA_SECRET_KEY',
    ],

    'pix' => [
        'chave' => 'SUA_CHAVE_PIX',
        'nome' => 'NOME_DO_RECEBEDOR',
        'cidade' => 'CIDADE',
    ],

    'mail' => [
        'host' => 'smtp.seu-provedor.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'SEU_USUARIO_SMTP',
        'password' => 'SUA_SENHA_SMTP',
        'from_email' => 'SEU_EMAIL_DE_ENVIO',
        'from_name' => 'Doces do Casal',
    ],
];
