<?php
/**
 * COMMANDER - Motor de Cloaking por Dominio (estilo White Rabbit)
 *
 * Chamado automaticamente pelo index.php quando o trafego chega por um
 * DOMINIO DE CAMPANHA apontado para o COMMANDER (via CNAME ou registro A).
 * Localiza a campanha pelo dominio e executa o cloaking direto no servidor,
 * sem precisar baixar/instalar tracker manualmente.
 *
 * Fluxo:
 *  1. index.php detecta que o Host e um dominio de campanha e define
 *     $GLOBALS['__CLOAK_CAMPAIGN'].
 *  2. Este motor carrega o "live tracker" gerado para a campanha
 *     (pasta /live/{slug}.php) e o executa.
 *  3. O live tracker consulta a api.php e serve a Safe Page ou a Offer Page.
 */

$campaign = $GLOBALS['__CLOAK_CAMPAIGN'] ?? null;

if (!is_array($campaign)) {
    http_response_code(404);
    exit;
}

$slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $campaign['slug'] ?? '');
$liveFile = __DIR__ . '/live/' . $slug . '.php';

// Caminho principal: executa o tracker gerado para esta campanha.
if ($slug !== '' && is_file($liveFile)) {
    require $liveFile;
    exit;
}

// Fallback seguro: se o tracker ainda nao foi gerado, envia para a Safe Page.
// (Para gerar, basta salvar a campanha novamente no painel.)
$white = trim($campaign['white_url'] ?? '');
if (filter_var($white, FILTER_VALIDATE_URL)) {
    header('Location: ' . $white, true, 302);
    exit;
}

http_response_code(404);
echo 'Campanha nao configurada.';
exit;
