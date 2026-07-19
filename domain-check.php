<?php
/**
 * domain-check.php
 * ------------------------------------------------------------------
 * Endpoint de autorizacao de SSL on-demand (usado pelo Caddy).
 *
 * O Caddy, ao receber a PRIMEIRA visita de um dominio novo, pergunta a
 * este endpoint se pode emitir o certificado SSL para ele. So respondemos
 * 200 (OK) se o dominio pertencer a alguma campanha cadastrada. Isso evita
 * que qualquer um aponte um dominio para o servidor e consuma certificados.
 *
 * Caddy chama:  GET /domain-check?domain=exemplo.com
 * Resposta:     200 -> pode emitir  |  404 -> negar
 * ------------------------------------------------------------------
 */

header('Content-Type: text/plain; charset=utf-8');

/** Normaliza dominio (mesma regra do index.php). */
function dcNormalizeDomain($domain) {
    $d = strtolower(trim((string) $domain));
    $d = preg_replace('#^https?://#', '', $d);
    $d = preg_replace('#/.*$#', '', $d);
    $d = preg_replace('/:\d+$/', '', $d);
    $d = preg_replace('/^www\./', '', $d);
    $d = preg_replace('/[^a-z0-9.\-]/', '', $d);
    return $d;
}

$domain = dcNormalizeDomain($_GET['domain'] ?? '');

if ($domain === '') {
    http_response_code(400);
    echo 'missing domain';
    exit;
}

$campaignsFile = __DIR__ . '/data/campaigns.json';
if (!is_file($campaignsFile)) {
    http_response_code(404);
    echo 'no campaigns';
    exit;
}

$all = json_decode((string) @file_get_contents($campaignsFile), true);
if (!is_array($all)) {
    http_response_code(404);
    echo 'invalid data';
    exit;
}

foreach ($all as $c) {
    if (dcNormalizeDomain($c['domain'] ?? '') === $domain) {
        http_response_code(200);
        echo 'ok';
        exit;
    }
}

// Tambem autoriza o proprio dominio do painel (caso rode no mesmo servidor)
$panelDomain = dcNormalizeDomain(getenv('COMMANDER_PANEL_DOMAIN') ?: '');
if ($panelDomain !== '' && $panelDomain === $domain) {
    http_response_code(200);
    echo 'ok';
    exit;
}

http_response_code(404);
echo 'not allowed';
