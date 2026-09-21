<?php
declare(strict_types=1);

$configFile = __DIR__ . '/../app/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    exit(
        'Configuração ausente. Copie app/config.example.php para app/config.php '
        . 'e informe as configurações necessárias.'
    );
}

$config = require $configFile;

date_default_timezone_set(
    $config['app']['timezone']
    ?? 'America/Sao_Paulo'
);


/* =========================================================
   SESSÃO
   ========================================================= */

if (session_status() !== PHP_SESSION_ACTIVE) {

    session_name(
        $config['app']['session_name']
        ?? 'docesdocasal_session'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (
            !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
        ),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


/* =========================================================
   BANCO DE DADOS
   ========================================================= */

function db(): PDO
{
    static $pdo;

    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['db'];

    $dsn =
        "mysql:host={$db['host']};"
        . "dbname={$db['name']};"
        . "charset={$db['charset']}";

    $pdo = new PDO(
        $dsn,
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false,
        ]
    );

    return $pdo;
}


/* =========================================================
   SEGURANÇA / HTML
   ========================================================= */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   CSRF
   ========================================================= */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION['csrf'];
}


function csrf_field(): string
{
    return
        '<input type="hidden" name="csrf" value="'
        . e(csrf_token())
        . '">';
}


function verify_csrf(): void
{
    $token =
        $_POST['csrf']
        ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf'] ?? '',
            $token
        )
    ) {

        http_response_code(419);

        exit(
            'Sessão expirada. Atualize a página e tente novamente.'
        );
    }
}


/* =========================================================
   USUÁRIO / LOGIN
   ========================================================= */

function current_user(): ?array
{
    return $_SESSION['user']
        ?? null;
}


function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' =>
            (int)$user['id'],

        'nome' =>
            $user['nome'],

        'telefone' =>
            $user['telefone'],

        'email' =>
            $user['email']
            ?? null,

        'tipo' =>
            $user['tipo'],
    ];
}


function logout_user(): void
{
    $_SESSION = [];

    if (
        ini_get(
            'session.use_cookies'
        )
    ) {

        $p =
            session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    session_destroy();
}



function safe_next_url(?string $next, string $fallback = '/cliente/'): string
{
    $next = trim((string)$next);

    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
        return $fallback;
    }

    $parts = parse_url($next);

    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    $path = $parts['path'] ?? '/';

    if ($path === '' || $path[0] !== '/') {
        return $fallback;
    }

    if (!empty($parts['query'])) {
        $path .= '?' . $parts['query'];
    }

    return $path;
}


function require_login(): array
{
    $u = current_user();

    if (!$u) {

        $next = safe_next_url(
            $_SERVER['REQUEST_URI'] ?? '/cliente/',
            '/cliente/'
        );

        header(
            'Location: /auth/login.php?next='
            . urlencode($next)
        );

        exit;
    }

    return $u;
}


function require_admin(): array
{
    $u =
        require_login();

    if (
        !in_array(
            $u['tipo'],
            [
                'admin',
                'operador'
            ],
            true
        )
    ) {

        http_response_code(403);

        exit(
            'Acesso negado.'
        );
    }

    return $u;
}


function is_admin(): bool
{
    $u =
        current_user();

    return
        $u
        && in_array(
            $u['tipo'],
            [
                'admin',
                'operador'
            ],
            true
        );
}


/* =========================================================
   FLASH
   ========================================================= */

function flash(
    string $key,
    ?string $value = null
): ?string {

    if ($value !== null) {

        $_SESSION['flash'][$key] =
            $value;

        return null;
    }

    $msg =
        $_SESSION['flash'][$key]
        ?? null;

    unset(
        $_SESSION['flash'][$key]
    );

    return $msg;
}


/* =========================================================
   FORMATAÇÃO
   ========================================================= */

function money($v): string
{
    return
        'R$ '
        . number_format(
            (float)$v,
            2,
            ',',
            '.'
        );
}


/* =========================================================
   CONFIGURAÇÕES DO SISTEMA
   ========================================================= */

function setting(
    string $key,
    $default = ''
) {

    static $cache = [];

    if (
        array_key_exists(
            $key,
            $cache
        )
    ) {

        return $cache[$key];
    }

    try {

        $st =
            db()->prepare(
                "SELECT valor
                 FROM configuracoes
                 WHERE chave = ?
                 LIMIT 1"
            );

        $st->execute([
            $key
        ]);

        $row =
            $st->fetch();

        return
            $cache[$key] =
            $row
                ? $row['valor']
                : $default;

    } catch (Throwable $e) {

        return $default;
    }
}


/* =========================================================
   AUDITORIA
   ========================================================= */

function audit(
    string $acao,
    ?string $entidade = null,
    ?int $id = null,
    ?string $detalhes = null
): void {

    try {

        $u =
            current_user();

        $st =
            db()->prepare(
                "INSERT INTO auditoria
                (
                    usuario_id,
                    acao,
                    entidade,
                    entidade_id,
                    detalhes,
                    ip
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );

        $st->execute([
            $u['id']
                ?? null,

            $acao,

            $entidade,

            $id,

            $detalhes,

            $_SERVER['REMOTE_ADDR']
                ?? null
        ]);

    } catch (Throwable $e) {
        /*
         * Auditoria não pode derrubar a aplicação.
         */
    }
}


/* =========================================================
   POLÍTICA DE SENHA
   =========================================================
   Requisitos:
   - mínimo 8 caracteres;
   - uma letra maiúscula;
   - uma letra minúscula;
   - um número;
   - um caractere especial.
   ========================================================= */

function senha_forte(
    string $senha
): bool {

    if (
        strlen($senha) < 8
    ) {
        return false;
    }

    if (
        !preg_match(
            '/[A-Z]/',
            $senha
        )
    ) {
        return false;
    }

    if (
        !preg_match(
            '/[a-z]/',
            $senha
        )
    ) {
        return false;
    }

    if (
        !preg_match(
            '/[0-9]/',
            $senha
        )
    ) {
        return false;
    }

    if (
        !preg_match(
            '/[^A-Za-z0-9]/',
            $senha
        )
    ) {
        return false;
    }

    return true;
}


function senha_forte_mensagem(): string
{
    return
        'A senha deve ter pelo menos 8 caracteres, '
        . 'incluindo letra maiúscula, letra minúscula, '
        . 'número e caractere especial.';
}


/* =========================================================
   CLOUDFLARE TURNSTILE
   ========================================================= */

function turnstile_site_key(): string
{
    global $config;

    return
        trim(
            (string)(
                $config['turnstile']['site_key']
                ?? ''
            )
        );
}


function turnstile_secret_key(): string
{
    global $config;

    return
        trim(
            (string)(
                $config['turnstile']['secret_key']
                ?? ''
            )
        );
}


/*
 * Confirma que o Turnstile foi configurado.
 */
function turnstile_configurado(): bool
{
    $site =
        turnstile_site_key();

    $secret =
        turnstile_secret_key();

    if (
        $site === ''
        || $secret === ''
    ) {
        return false;
    }

    if (
        str_contains(
            $site,
            'COLOQUE_'
        )
        || str_contains(
            $secret,
            'COLOQUE_'
        )
    ) {
        return false;
    }

    return true;
}


/*
 * Validação server-side obrigatória do Turnstile.
 *
 * O formulário envia cf-turnstile-response.
 * O servidor valida esse token diretamente com a Cloudflare.
 */
function validar_turnstile(
    string $token
): bool {

    if (
        !turnstile_configurado()
        || $token === ''
    ) {
        return false;
    }

    $payload = [
        'secret' =>
            turnstile_secret_key(),

        'response' =>
            $token,

        'remoteip' =>
            $_SERVER['REMOTE_ADDR']
            ?? '',
    ];

    $endpoint =
        'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /*
     * Caminho preferencial: cURL.
     */
    if (
        function_exists(
            'curl_init'
        )
    ) {

        $ch =
            curl_init(
                $endpoint
            );

        curl_setopt_array(
            $ch,
            [
                CURLOPT_POST =>
                    true,

                CURLOPT_POSTFIELDS =>
                    http_build_query(
                        $payload
                    ),

                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_TIMEOUT =>
                    10,

                CURLOPT_CONNECTTIMEOUT =>
                    5,

                CURLOPT_HTTPHEADER =>
                    [
                        'Content-Type: application/x-www-form-urlencoded'
                    ],
            ]
        );

        $response =
            curl_exec(
                $ch
            );

        $httpCode =
            (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        curl_close(
            $ch
        );

        if (
            $response === false
            || $httpCode < 200
            || $httpCode >= 300
        ) {
            return false;
        }

    } else {

        /*
         * Fallback para hospedagens sem extensão cURL.
         */
        $context =
            stream_context_create(
                [
                    'http' => [
                        'method' =>
                            'POST',

                        'header' =>
                            "Content-Type: application/x-www-form-urlencoded\r\n",

                        'content' =>
                            http_build_query(
                                $payload
                            ),

                        'timeout' =>
                            10,

                        'ignore_errors' =>
                            true,
                    ],
                ]
            );

        $response =
            @file_get_contents(
                $endpoint,
                false,
                $context
            );

        if (
            $response === false
        ) {
            return false;
        }
    }

    $result =
        json_decode(
            $response,
            true
        );

    return
        is_array($result)
        && !empty(
            $result['success']
        );
}



/* =========================================================
   E-MAIL / SMTP
   ========================================================= */
function enviar_email_smtp(string $destino, string $assunto, string $html, string $texto = ''): bool
{
    global $config;
    $m = $config['mail'] ?? [];
    $host = trim((string)($m['host'] ?? ''));
    $port = (int)($m['port'] ?? 465);
    $user = trim((string)($m['username'] ?? ''));
    $pass = (string)($m['password'] ?? '');
    $from = trim((string)($m['from_email'] ?? $user));
    $fromName = trim((string)($m['from_name'] ?? 'Doces do Casal'));
    if ($host === '' || $user === '' || $pass === '' || str_contains($pass, 'COLOQUE_')) return false;

    $socket = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) return false;
    stream_set_timeout($socket, 15);

    $read = static function () use ($socket): string {
        $out = '';
        while (($line = fgets($socket, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $out;
    };
    $cmd = static function (string $command, array $ok) use ($socket, $read): bool {
        fwrite($socket, $command . "\r\n");
        $r = $read();
        return in_array((int)substr($r, 0, 3), $ok, true);
    };

    $banner = $read();
    if ((int)substr($banner, 0, 3) !== 220) { fclose($socket); return false; }
    $ehlo = $_SERVER['SERVER_NAME'] ?? 'docesdocasal.kinsmanst.com';
    if (!$cmd('EHLO ' . $ehlo, [250]) || !$cmd('AUTH LOGIN', [334]) ||
        !$cmd(base64_encode($user), [334]) || !$cmd(base64_encode($pass), [235]) ||
        !$cmd('MAIL FROM:<' . $from . '>', [250]) || !$cmd('RCPT TO:<' . $destino . '>', [250,251]) ||
        !$cmd('DATA', [354])) { fclose($socket); return false; }

    $boundary = 'b_' . bin2hex(random_bytes(12));
    $subject = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $name = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $texto = $texto !== '' ? $texto : strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $html));
    $headers = [
        'From: ' . $name . ' <' . $from . '>', 'To: <' . $destino . '>', 'Subject: ' . $subject,
        'MIME-Version: 1.0', 'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
    ];
    $body = implode("\r\n", $headers) . "\r\n\r\n"
        . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto)) . "\r\n--" . $boundary
        . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n--" . $boundary . "--\r\n";
    $body = preg_replace('/(?m)^\./', '..', $body) ?? $body;
    fwrite($socket, $body . "\r\n.\r\n");
    $result = $read();
    $ok = (int)substr($result, 0, 3) === 250;
    $cmd('QUIT', [221]);
    fclose($socket);
    return $ok;
}


/* =========================================================
   IMAGENS / UPLOAD OTIMIZADO
   ========================================================= */

/**
 * Retorna a raiz pública do projeto.
 */
function app_root_path(): string
{
    return dirname(__DIR__);
}

/**
 * Apaga uma imagem gerenciada pelo sistema e sua miniatura, se existir.
 */
function remover_imagem_upload(?string $url): void
{
    if (!$url || !str_starts_with($url, '/uploads/')) {
        return;
    }

    $root = app_root_path();
    $arquivo = $root . $url;
    $info = pathinfo($arquivo);

    $arquivos = [$arquivo];

    if (!empty($info['dirname']) && !empty($info['filename'])) {
        $base = $info['dirname'] . '/' . $info['filename'];
        $arquivos[] = $base . '.webp';
        $arquivos[] = $base . '_thumb.webp';

        if (!empty($info['extension'])) {
            $arquivos[] = $base . '_thumb.' . $info['extension'];
        }
    }

    foreach (array_unique($arquivos) as $alvo) {
        if (is_file($alvo)) {
            @unlink($alvo);
        }
    }
}

/**
 * Retorna a miniatura de uma imagem quando ela existe.
 * Imagens antigas continuam funcionando usando o arquivo principal.
 */
function imagem_otimizada_url(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '' || !str_starts_with($url, '/uploads/')) {
        return $url;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $url;
    }

    $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return $url;
    }

    $webpPath = preg_replace('/\.(?:jpe?g|png)$/i', '.webp', $path);
    if (!is_string($webpPath)) {
        return $url;
    }

    $fs = dirname(__DIR__) . $webpPath;
    return is_file($fs) ? $webpPath : $url;
}

function imagem_thumbnail_url(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '' || !str_starts_with($url, '/uploads/')) {
        return $url;
    }

    $path = app_root_path() . $url;
    $info = pathinfo($path);

    if (empty($info['dirname']) || empty($info['filename']) || empty($info['extension'])) {
        return $url;
    }

    $dirUrl = str_replace('\\', '/', dirname($url));

    $thumbWebp = $info['dirname'] . '/' . $info['filename'] . '_thumb.webp';
    if (is_file($thumbWebp)) {
        return rtrim($dirUrl, '/') . '/' . $info['filename'] . '_thumb.webp';
    }

    $thumbPath = $info['dirname'] . '/' . $info['filename'] . '_thumb.' . $info['extension'];
    if (is_file($thumbPath)) {
        return rtrim($dirUrl, '/') . '/' . $info['filename'] . '_thumb.' . $info['extension'];
    }

    return imagem_otimizada_url($url);
}

/**
 * Corrige orientação de JPEGs tirados por celular quando EXIF estiver disponível.
 */
function corrigir_orientacao_jpeg($imagem, string $arquivo)
{
    if (!function_exists('exif_read_data')) {
        return $imagem;
    }

    try {
        $exif = @exif_read_data($arquivo);
        $orientacao = (int)($exif['Orientation'] ?? 1);

        if ($orientacao === 3) {
            $rotacionada = imagerotate($imagem, 180, 0);
        } elseif ($orientacao === 6) {
            $rotacionada = imagerotate($imagem, -90, 0);
        } elseif ($orientacao === 8) {
            $rotacionada = imagerotate($imagem, 90, 0);
        } else {
            return $imagem;
        }

        if ($rotacionada !== false) {
            imagedestroy($imagem);
            return $rotacionada;
        }
    } catch (Throwable $e) {
        // A imagem continua válida mesmo se o EXIF não puder ser lido.
    }

    return $imagem;
}

/**
 * Redimensiona uma imagem GD sem ampliar arquivos menores.
 */
function redimensionar_imagem_gd($origem, int $maxLargura, int $maxAltura)
{
    $largura = imagesx($origem);
    $altura = imagesy($origem);

    if ($largura <= 0 || $altura <= 0) {
        throw new RuntimeException('Não foi possível identificar as dimensões da imagem.');
    }

    $escala = min(
        1,
        $maxLargura / $largura,
        $maxAltura / $altura
    );

    $novaLargura = max(1, (int)round($largura * $escala));
    $novaAltura = max(1, (int)round($altura * $escala));

    $destino = imagecreatetruecolor($novaLargura, $novaAltura);
    if ($destino === false) {
        throw new RuntimeException('Não foi possível processar a imagem.');
    }

    imagealphablending($destino, false);
    imagesavealpha($destino, true);
    $transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
    imagefilledrectangle($destino, 0, 0, $novaLargura, $novaAltura, $transparente);

    imagecopyresampled(
        $destino,
        $origem,
        0,
        0,
        0,
        0,
        $novaLargura,
        $novaAltura,
        $largura,
        $altura
    );

    return $destino;
}

/**
 * Salva uploads de imagem de forma otimizada.
 *
 * - valida MIME real e limite de 5 MB;
 * - limita a imagem principal a 1600x1600 por padrão;
 * - cria miniatura de 360x360 para o Admin;
 * - converte para WEBP com qualidade 82 quando GD/WEBP estiver disponível;
 * - em hospedagens sem GD/WEBP, mantém o upload original como fallback;
 * - remove a imagem anterior somente após a nova ser salva com sucesso.
 */
function salvar_imagem_otimizada(
    array $file,
    string $folder,
    ?string $atual = null,
    int $maxLargura = 1600,
    int $maxAltura = 1600,
    int $qualidade = 82
): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $atual;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar a imagem.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 5 MB.');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload de imagem inválido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($permitidos[$mime]) || !@getimagesize($file['tmp_name'])) {
        throw new RuntimeException('Use uma imagem JPG, PNG ou WEBP válida.');
    }

    $folder = trim($folder, '/');
    if ($folder === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $folder)) {
        throw new RuntimeException('Pasta de upload inválida.');
    }

    $dir = app_root_path() . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
    }

    $base = $folder . '_' . bin2hex(random_bytes(12));

    $gdDisponivel = function_exists('imagecreatetruecolor');
    $webpDisponivel = $gdDisponivel && function_exists('imagewebp');

    if ($webpDisponivel) {
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $origem = @imagecreatefromjpeg($file['tmp_name']);
            if ($origem !== false) {
                $origem = corrigir_orientacao_jpeg($origem, $file['tmp_name']);
            }
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $origem = @imagecreatefrompng($file['tmp_name']);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $origem = @imagecreatefromwebp($file['tmp_name']);
        } else {
            $origem = false;
        }

        if ($origem !== false) {
            $principal = redimensionar_imagem_gd($origem, $maxLargura, $maxAltura);
            $thumb = redimensionar_imagem_gd($origem, 360, 360);

            $nome = $base . '.webp';
            $nomeThumb = $base . '_thumb.webp';
            $destino = $dir . '/' . $nome;
            $destinoThumb = $dir . '/' . $nomeThumb;

            $okPrincipal = @imagewebp($principal, $destino, $qualidade);
            $okThumb = @imagewebp($thumb, $destinoThumb, 78);

            imagedestroy($principal);
            imagedestroy($thumb);
            imagedestroy($origem);

            if ($okPrincipal && $okThumb && is_file($destino) && is_file($destinoThumb)) {
                remover_imagem_upload($atual);
                return '/uploads/' . $folder . '/' . $nome;
            }

            @unlink($destino);
            @unlink($destinoThumb);
        }
    }

    // Fallback seguro: preserva o formato original quando GD/WEBP não estiver disponível.
    $ext = $permitidos[$mime];
    $nome = $base . '.' . $ext;
    $destino = $dir . '/' . $nome;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar a imagem.');
    }

    remover_imagem_upload($atual);
    return '/uploads/' . $folder . '/' . $nome;
}


/* =========================================================
   PIX BR CODE / EMV
   =========================================================
   Gera localmente o payload Pix Copia e Cola.

   Observação:
   - não consulta banco/gateway;
   - não confirma pagamento automaticamente;
   - o valor é vinculado ao total calculado no servidor.
   ========================================================= */

function pix_emv(string $id, string $valor): string
{
    return $id
        . str_pad(
            (string)strlen($valor),
            2,
            '0',
            STR_PAD_LEFT
        )
        . $valor;
}


function pix_normalizar_texto(
    string $texto,
    int $max
): string {

    $texto =
        trim(
            mb_strtoupper(
                $texto,
                'UTF-8'
            )
        );

    if (
        function_exists(
            'iconv'
        )
    ) {

        $convertido =
            @iconv(
                'UTF-8',
                'ASCII//TRANSLIT//IGNORE',
                $texto
            );

        if (
            $convertido !== false
        ) {
            $texto =
                $convertido;
        }
    }

    $texto =
        preg_replace(
            '/[^A-Z0-9 .\-]/',
            '',
            $texto
        )
        ?? '';

    return
        substr(
            $texto,
            0,
            $max
        );
}


function pix_crc16(
    string $payload
): string {

    $polinomio =
        0x1021;

    $resultado =
        0xFFFF;

    $length =
        strlen(
            $payload
        );

    for (
        $offset = 0;
        $offset < $length;
        $offset++
    ) {

        $resultado ^=
            ord(
                $payload[$offset]
            ) << 8;

        for (
            $bitwise = 0;
            $bitwise < 8;
            $bitwise++
        ) {

            if (
                ($resultado <<= 1)
                & 0x10000
            ) {

                $resultado ^=
                    $polinomio;
            }

            $resultado &=
                0xFFFF;
        }
    }

    return
        strtoupper(
            str_pad(
                dechex(
                    $resultado
                ),
                4,
                '0',
                STR_PAD_LEFT
            )
        );
}


function pix_copia_cola(
    float $valor,
    string $txid = '***'
): string {

    global $config;

    $chave =
        trim(
            (string)(
                $config['pix']['chave']
                ?? ''
            )
        );

    if (
        $chave === ''
        || str_contains(
            $chave,
            'COLOQUE_'
        )
    ) {

        throw new RuntimeException(
            'A chave Pix ainda não foi configurada.'
        );
    }

    $nome =
        pix_normalizar_texto(
            (string)(
                $config['pix']['nome']
                ?? 'DOCES DO CASAL'
            ),
            25
        );

    $cidade =
        pix_normalizar_texto(
            (string)(
                $config['pix']['cidade']
                ?? 'CONTAGEM'
            ),
            15
        );

    $txid =
        pix_normalizar_texto(
            $txid,
            25
        );

    if (
        $txid === ''
    ) {
        $txid = '***';
    }

    $merchantAccount =
        pix_emv(
            '00',
            'BR.GOV.BCB.PIX'
        )
        . pix_emv(
            '01',
            $chave
        );

    $additionalData =
        pix_emv(
            '05',
            $txid
        );

    $payload =
        pix_emv(
            '00',
            '01'
        )
        . pix_emv(
            '26',
            $merchantAccount
        )
        . pix_emv(
            '52',
            '0000'
        )
        . pix_emv(
            '53',
            '986'
        )
        . pix_emv(
            '54',
            number_format(
                $valor,
                2,
                '.',
                ''
            )
        )
        . pix_emv(
            '58',
            'BR'
        )
        . pix_emv(
            '59',
            $nome
        )
        . pix_emv(
            '60',
            $cidade
        )
        . pix_emv(
            '62',
            $additionalData
        )
        . '6304';

    return
        $payload
        . pix_crc16(
            $payload
        );
}

/* =========================================================
   ESTOQUE DE PRODUTO PRONTO
   ========================================================= */

/**
 * Devolve ao estoque os produtos prontos de um pedido cancelado.
 * Deve ser chamada dentro de uma transacao com o pedido bloqueado.
 * A observacao da movimentacao funciona como trava idempotente para
 * impedir devolucao duplicada.
 */
function devolver_estoque_pedido(int $pedidoId, ?int $usuarioId = null): void
{
    $marcador = 'Devolucao pedido #' . $pedidoId;

    $jaDevolvido = db()->prepare("
        SELECT 1
        FROM estoque_movimentacoes
        WHERE referencia_tipo = 'pedido_cancelado'
          AND observacao = ?
        LIMIT 1
    ");
    $jaDevolvido->execute([$marcador]);

    if ($jaDevolvido->fetchColumn()) {
        return;
    }

    $st = db()->prepare("
        SELECT
            pi.produto_id,
            SUM(pi.quantidade) AS quantidade,
            ie.id AS item_estoque_id,
            ie.custo_medio
        FROM pedido_itens pi
        JOIN itens_estoque ie
          ON ie.produto_id = pi.produto_id
         AND ie.tipo = 'produto_pronto'
         AND ie.ativo = 1
        WHERE pi.pedido_id = ?
        GROUP BY pi.produto_id, ie.id, ie.custo_medio
    ");
    $st->execute([$pedidoId]);

    foreach ($st->fetchAll() as $item) {
        $quantidade = (float)$item['quantidade'];

        db()->prepare("
            UPDATE itens_estoque
            SET estoque_atual = estoque_atual + ?
            WHERE id = ?
        ")->execute([
            $quantidade,
            $item['item_estoque_id']
        ]);

        db()->prepare("
            INSERT INTO estoque_movimentacoes
            (
                item_estoque_id,
                tipo,
                quantidade,
                custo_unitario,
                referencia_tipo,
                observacao,
                usuario_id
            )
            VALUES
            (
                ?,
                'entrada',
                ?,
                ?,
                'pedido_cancelado',
                ?,
                ?
            )
        ")->execute([
            $item['item_estoque_id'],
            $quantidade,
            $item['custo_medio'] ?? 0,
            $marcador,
            $usuarioId
        ]);
    }
}
