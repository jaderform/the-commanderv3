<?php
/**
 * bots-storage.php
 * ------------------------------------------------------------------
 * Armazenamento de bots em SQLite com RETENCAO INFINITA.
 *
 * Objetivo: guardar bots para sempre (nunca apagar) SEM travar o
 * cloaker, mesmo com milhoes de registros.
 *
 * Como funciona:
 *  - Usa um unico arquivo de banco: data/bots.db
 *  - Busca indexada (instantanea), mesmo com a base enorme
 *  - Escrita segura via transacao (sem corromper em trafego alto)
 *  - WAL mode = varios acessos simultaneos sem travar
 *
 * Se o PHP do servidor NAO tiver SQLite, todas as funcoes retornam
 * null e o sistema cai automaticamente no JSON antigo (sem quebrar).
 *
 * Este arquivo deve ficar na RAIZ (junto de functions.php).
 * ------------------------------------------------------------------
 */

if (!defined('DATA_DIR')) {
    define('DATA_DIR', __DIR__ . '/data/');
}

/**
 * SQLite esta disponivel neste servidor?
 */
if (!function_exists('botStorageAvailable')) {
    function botStorageAvailable() {
        return class_exists('PDO')
            && in_array('sqlite', PDO::getAvailableDrivers(), true);
    }
}

/**
 * Conexao unica (singleton) com o banco de bots.
 * Retorna null se SQLite nao estiver disponivel.
 */
if (!function_exists('botDb')) {
    function botDb() {
        static $pdo = null;
        static $tried = false;

        if ($tried) {
            return $pdo;
        }
        $tried = true;

        if (!botStorageAvailable()) {
            return null;
        }

        try {
            if (!is_dir(DATA_DIR)) {
                @mkdir(DATA_DIR, 0755, true);
            }

            $pdo = new PDO('sqlite:' . DATA_DIR . 'bots.db');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // PRAGMAs para performance + concorrencia segura
            $pdo->exec('PRAGMA journal_mode = WAL;');     // varios acessos sem travar
            $pdo->exec('PRAGMA synchronous = NORMAL;');   // rapido e seguro
            $pdo->exec('PRAGMA busy_timeout = 5000;');    // espera 5s em vez de erro
            $pdo->exec('PRAGMA temp_store = MEMORY;');

            // Tabelas (criadas uma unica vez)
            $pdo->exec('CREATE TABLE IF NOT EXISTS bot_fingerprints (
                fingerprint TEXT PRIMARY KEY,
                reason      TEXT,
                date        TEXT,
                campaign    TEXT,
                ip_masked   TEXT
            );');

            $pdo->exec('CREATE TABLE IF NOT EXISTS bot_ips (
                ip_hash     TEXT PRIMARY KEY,
                count       INTEGER DEFAULT 0,
                first_seen  TEXT,
                last_seen   TEXT,
                last_reason TEXT
            );');

            // Indice por data (acelera estatisticas e ordenacao)
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fp_date ON bot_fingerprints(date);');

        } catch (Exception $e) {
            error_log('[bots-storage] Falha ao abrir SQLite: ' . $e->getMessage());
            $pdo = null;
        }

        return $pdo;
    }
}

/**
 * Verifica se o visitante ja eh um bot conhecido.
 * Retorna o MESMO formato da funcao original isLearnedBot(),
 * ou null se SQLite indisponivel (para cair no fallback JSON).
 */
if (!function_exists('botStorageIsLearnedBot')) {
    function botStorageIsLearnedBot($ip, $userAgent) {
        $db = botDb();
        if (!$db) {
            return null;
        }

        try {
            $fingerprint = md5($ip . '|' . $userAgent);
            $ipHash      = md5($ip);

            // 1. Match exato por fingerprint (IP + UA)
            $stmt = $db->prepare('SELECT reason, date FROM bot_fingerprints WHERE fingerprint = ? LIMIT 1');
            $stmt->execute([$fingerprint]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'isBot'      => true,
                    'reason'     => 'Bot aprendido: ' . ($row['reason'] ?: 'detectado anteriormente'),
                    'learned_at' => $row['date'] ?? ''
                ];
            }

            // 2. IP com 3+ deteccoes
            $stmt = $db->prepare('SELECT count, last_seen FROM bot_ips WHERE ip_hash = ? LIMIT 1');
            $stmt->execute([$ipHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (int) $row['count'] >= 3) {
                return [
                    'isBot'      => true,
                    'reason'     => 'IP bloqueado: multiplas deteccoes (' . $row['count'] . ')',
                    'learned_at' => $row['last_seen'] ?? ''
                ];
            }

            return ['isBot' => false];

        } catch (Exception $e) {
            error_log('[bots-storage] isLearnedBot: ' . $e->getMessage());
            return ['isBot' => false];
        }
    }
}

/**
 * Adiciona/atualiza um bot. Retencao infinita (nunca remove nada).
 * Retorna true/false, ou null se SQLite indisponivel.
 */
if (!function_exists('botStorageAddLearnedBot')) {
    function botStorageAddLearnedBot($ip, $userAgent, $reason, $campaignId = '') {
        $db = botDb();
        if (!$db) {
            return null;
        }

        try {
            $fingerprint = md5($ip . '|' . $userAgent);
            $ipHash      = md5($ip);
            $date        = date('Y-m-d H:i:s');

            $pos      = strrpos($ip, '.');
            $ipMasked = ($pos !== false) ? substr($ip, 0, $pos) . '.xxx' : $ip;

            $db->beginTransaction();

            // Fingerprint: insere se ainda nao existe
            $stmt = $db->prepare('INSERT OR IGNORE INTO bot_fingerprints
                (fingerprint, reason, date, campaign, ip_masked)
                VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$fingerprint, $reason, $date, $campaignId, $ipMasked]);

            // IP: incrementa contador (UPDATE; se nao existir, INSERT)
            $upd = $db->prepare('UPDATE bot_ips
                SET count = count + 1, last_seen = ?, last_reason = ?
                WHERE ip_hash = ?');
            $upd->execute([$date, $reason, $ipHash]);

            if ($upd->rowCount() === 0) {
                $ins = $db->prepare('INSERT OR IGNORE INTO bot_ips
                    (ip_hash, count, first_seen, last_seen, last_reason)
                    VALUES (?, 1, ?, ?, ?)');
                $ins->execute([$ipHash, $date, $date, $reason]);
            }

            $db->commit();
            return true;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[bots-storage] addLearnedBot: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Estatisticas. Retorna o mesmo formato de getLearnedBotsStats(),
 * ou null se SQLite indisponivel.
 */
if (!function_exists('botStorageStats')) {
    function botStorageStats() {
        $db = botDb();
        if (!$db) {
            return null;
        }

        try {
            $fp   = (int) $db->query('SELECT COUNT(*) FROM bot_fingerprints')->fetchColumn();
            $ips  = (int) $db->query('SELECT COUNT(*) FROM bot_ips')->fetchColumn();
            $last = $db->query('SELECT MAX(date) FROM bot_fingerprints')->fetchColumn();

            return [
                'total_fingerprints' => $fp,
                'total_ips'          => $ips,
                'last_updated'       => $last ?: '-'
            ];
        } catch (Exception $e) {
            error_log('[bots-storage] stats: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * Limpa TODOS os bots (apenas via acao manual do admin).
 * Retorna true, ou null se SQLite indisponivel.
 */
if (!function_exists('botStorageClear')) {
    function botStorageClear() {
        $db = botDb();
        if (!$db) {
            return null;
        }

        try {
            $db->exec('DELETE FROM bot_fingerprints');
            $db->exec('DELETE FROM bot_ips');
            return true;
        } catch (Exception $e) {
            error_log('[bots-storage] clear: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Migra os bots do learned_bots.json antigo para o SQLite.
 * Pode ser rodada varias vezes sem duplicar (INSERT OR IGNORE).
 *
 * @return array { ok, fingerprints, ips, error }
 */
if (!function_exists('botStorageMigrateFromJson')) {
    function botStorageMigrateFromJson($jsonFile = null) {
        $db = botDb();
        if (!$db) {
            return ['ok' => false, 'error' => 'SQLite indisponivel neste servidor'];
        }

        $jsonFile = $jsonFile ?: (DATA_DIR . 'learned_bots.json');
        if (!is_file($jsonFile)) {
            return ['ok' => false, 'error' => 'learned_bots.json nao encontrado em ' . $jsonFile];
        }

        $data = json_decode(@file_get_contents($jsonFile), true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'learned_bots.json invalido ou vazio'];
        }

        $fpAdded = 0;
        $ipAdded = 0;

        try {
            $db->beginTransaction();

            $fpStmt = $db->prepare('INSERT OR IGNORE INTO bot_fingerprints
                (fingerprint, reason, date, campaign, ip_masked)
                VALUES (?, ?, ?, ?, ?)');

            foreach (($data['fingerprints'] ?? []) as $fp => $info) {
                $fpStmt->execute([
                    $fp,
                    $info['reason']    ?? '',
                    $info['date']      ?? '',
                    $info['campaign']  ?? '',
                    $info['ip_masked'] ?? ''
                ]);
                $fpAdded += $fpStmt->rowCount();
            }

            $ipStmt = $db->prepare('INSERT OR IGNORE INTO bot_ips
                (ip_hash, count, first_seen, last_seen, last_reason)
                VALUES (?, ?, ?, ?, ?)');

            foreach (($data['ips'] ?? []) as $hash => $info) {
                $ipStmt->execute([
                    $hash,
                    $info['count']       ?? 1,
                    $info['first_seen']  ?? '',
                    $info['last_seen']   ?? '',
                    $info['last_reason'] ?? ''
                ]);
                $ipAdded += $ipStmt->rowCount();
            }

            $db->commit();

            return ['ok' => true, 'fingerprints' => $fpAdded, 'ips' => $ipAdded];

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
