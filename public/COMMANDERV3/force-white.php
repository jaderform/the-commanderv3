<?php
/**
 * =========================================================
 * FORCE WHITE - Lista manual de bots -> White Page
 * =========================================================
 * Le o arquivo data/force_white.txt e verifica se o IP do
 * visitante esta na lista (IP exato ou faixa CIDR).
 *
 * Use isForcedWhite($ip) no fluxo de cloaking (api.php).
 * =========================================================
 */

if (!function_exists('forceWhiteFilePath')) {
    /**
     * Caminho do arquivo da lista. Usa DATA_DIR se existir,
     * senao cai para a pasta data/ ao lado deste arquivo.
     */
    function forceWhiteFilePath() {
        if (defined('DATA_DIR')) {
            return rtrim(DATA_DIR, '/') . '/force_white.txt';
        }
        return __DIR__ . '/data/force_white.txt';
    }
}

if (!function_exists('loadForceWhiteList')) {
    /**
     * Carrega e cacheia a lista (IPs exatos + faixas CIDR).
     * Cache em memoria por request (static) para nao reler o arquivo.
     *
     * @return array{ips: array<string,bool>, cidrs: array<int,string>}
     */
    function loadForceWhiteList() {
        static $cache = null;

        // Permite forcar releitura apos add/remove no mesmo request
        if (!empty($GLOBALS['__force_white_dirty'])) {
            $cache = null;
            $GLOBALS['__force_white_dirty'] = false;
        }

        if ($cache !== null) {
            return $cache;
        }

        $cache = ['ips' => [], 'cidrs' => []];
        $file = forceWhiteFilePath();

        if (!is_file($file)) {
            return $cache;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $cache;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignora comentarios e linhas vazias
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Remove comentario no fim da linha (ex: "1.2.3.4 # google")
            if (($hash = strpos($line, '#')) !== false) {
                $line = trim(substr($line, 0, $hash));
            }
            if ($line === '') {
                continue;
            }

            if (strpos($line, '/') !== false) {
                // Faixa CIDR
                $cache['cidrs'][] = $line;
            } else {
                // IP exato (chave para lookup O(1))
                $cache['ips'][$line] = true;
            }
        }

        return $cache;
    }
}

if (!function_exists('ipInCidr')) {
    /**
     * Verifica se um IPv4 esta dentro de uma faixa CIDR.
     */
    function ipInCidr($ip, $cidr) {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        list($subnet, $bits) = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);

        // Apenas IPv4 valido
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        if ($bits < 0 || $bits > 32) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }

        $mask        = -1 << (32 - $bits);
        $subnetLong &= $mask;

        return ($ipLong & $mask) === $subnetLong;
    }
}

if (!function_exists('isForcedWhite')) {
    /**
     * Retorna true se o IP deve ir DIRETO para a white page.
     *
     * @param string $ip IP do visitante
     * @return bool
     */
    function isForcedWhite($ip) {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return false;
        }

        $list = loadForceWhiteList();

        // 1. Match exato (rapido)
        if (isset($list['ips'][$ip])) {
            return true;
        }

        // 2. Faixas CIDR (somente IPv4)
        if (!empty($list['cidrs']) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach ($list['cidrs'] as $cidr) {
                if (ipInCidr($ip, $cidr)) {
                    return true;
                }
            }
        }

        return false;
    }
}

/* =========================================================
 * GESTAO DA LISTA (usado pelo painel index.php)
 * =======================================================*/

if (!function_exists('forceWhiteResetCache')) {
    /**
     * Limpa o cache estatico apos uma alteracao no arquivo.
     * Como o cache usa "static" dentro de loadForceWhiteList(),
     * precisamos relê-lo via uma flag global.
     */
    function forceWhiteResetCache() {
        $GLOBALS['__force_white_dirty'] = true;
    }
}

if (!function_exists('getForceWhiteEntries')) {
    /**
     * Retorna a lista completa (1 entrada por linha), na ordem do arquivo,
     * ignorando comentarios e linhas vazias.
     *
     * @return array<int,string>
     */
    function getForceWhiteEntries() {
        $file = forceWhiteFilePath();
        if (!is_file($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (($hash = strpos($line, '#')) !== false) {
                $line = trim(substr($line, 0, $hash));
            }
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('isValidForceWhiteEntry')) {
    /**
     * Valida se a entrada e um IPv4, IPv6 ou faixa CIDR valida.
     */
    function isValidForceWhiteEntry($entry) {
        $entry = trim((string) $entry);
        if ($entry === '') {
            return false;
        }

        // Faixa CIDR
        if (strpos($entry, '/') !== false) {
            list($subnet, $bits) = array_pad(explode('/', $entry, 2), 2, '');
            if (!filter_var($subnet, FILTER_VALIDATE_IP)) {
                return false;
            }
            if (!is_numeric($bits)) {
                return false;
            }
            $bits = (int) $bits;
            $isV6 = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            return $isV6 ? ($bits >= 0 && $bits <= 128) : ($bits >= 0 && $bits <= 32);
        }

        // IP exato (v4 ou v6)
        return (bool) filter_var($entry, FILTER_VALIDATE_IP);
    }
}

if (!function_exists('addForceWhiteEntry')) {
    /**
     * Adiciona uma ou varias entradas (separadas por virgula, espaco
     * ou quebra de linha). Retorna quantas foram realmente adicionadas.
     *
     * @param string $raw
     * @return array{added:int, invalid:array<int,string>}
     */
    function addForceWhiteEntry($raw) {
        $file    = forceWhiteFilePath();
        $dir     = dirname($file);
        $invalid = [];
        $added   = 0;

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $existing = getForceWhiteEntries();
        $existingMap = array_flip($existing);

        // Aceita virgula, espaco, ; ou nova linha como separador
        $parts = preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

        $toAppend = [];
        foreach ($parts as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (!isValidForceWhiteEntry($entry)) {
                $invalid[] = $entry;
                continue;
            }
            if (isset($existingMap[$entry])) {
                continue; // ja existe
            }
            $existingMap[$entry] = true;
            $toAppend[] = $entry;
            $added++;
        }

        if (!empty($toAppend)) {
            $needsNl = is_file($file) && filesize($file) > 0
                && substr(file_get_contents($file), -1) !== "\n";
            $data = ($needsNl ? "\n" : '') . implode("\n", $toAppend) . "\n";
            file_put_contents($file, $data, FILE_APPEND | LOCK_EX);
            forceWhiteResetCache();
        }

        return ['added' => $added, 'invalid' => $invalid];
    }
}

if (!function_exists('removeForceWhiteEntry')) {
    /**
     * Remove uma entrada exata da lista. Reescreve o arquivo
     * preservando os comentarios do cabecalho.
     *
     * @param string $entry
     * @return bool true se removeu
     */
    function removeForceWhiteEntry($entry) {
        $entry = trim((string) $entry);
        $file  = forceWhiteFilePath();
        if ($entry === '' || !is_file($file)) {
            return false;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }

        $removed = false;
        $out = [];
        foreach ($lines as $line) {
            $clean = trim($line);
            // Mantem comentarios e linhas vazias
            if ($clean === '' || $clean[0] === '#') {
                $out[] = $line;
                continue;
            }
            // Compara ignorando comentario inline
            $value = $clean;
            if (($hash = strpos($value, '#')) !== false) {
                $value = trim(substr($value, 0, $hash));
            }
            if ($value === $entry) {
                $removed = true;
                continue; // pula (remove)
            }
            $out[] = $line;
        }

        if ($removed) {
            file_put_contents($file, implode("\n", $out) . "\n", LOCK_EX);
            forceWhiteResetCache();
        }

        return $removed;
    }
}
