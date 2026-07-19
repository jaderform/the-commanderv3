<?php
// Inicia a sessão
session_start();

// Nossos arquivos de controle
$arquivo_sessoes = 'sessoes_ativas.json';
$arquivo_strikes = 'contador_strikes.json';
$limite_strikes = 3; // O usuário será bloqueado no 3º strike

// --- LISTA DE USUÁRIOS AUTORIZADOS ---
// Todos estão "liberados" por padrão, com "bloqueado" => false
$usuarios_autorizados = [
    "adminjd@gmail.com" => [
        "senha" => "Jader0596##@", 
        "expira_em" => "2099-12-31", // Admin
        "bloqueado" => false // Seu controle manual
    ],
    "samuel.tardim0@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => true
    ],
    "membrosvip2025@gmail.com" => [
        "senha" => "Membrosvip20253", 
        "expira_em" => "2025-12-02",
        "bloqueado" => false
    ],
    "danypassos197@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => true
    ],
    "lamacalf7@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => true
    ],
    "estoudeolho321@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => true
    ],
    "luizahelena@tutamail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-11-29",
        "bloqueado" => false
    ],
    "adrianlazaroba.03@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-11-29",
        "bloqueado" => false
    ],
    "contingenciacx4@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-11-30",
        "bloqueado" => false
    ],
    "acesso4892@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-11-30",
        "bloqueado" => true
    ],
    "acesso4893@gmail.com" => [
        "senha" => "Alterar0596###", 
        "expira_em" => "2025-12-10",
        "bloqueado" => false
    ],
    "Cowstoremp@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-11-10",
        "bloqueado" => true
    ],
    "andretelees@hotmail.com" => [
        "senha" => "Andre0596#", 
        "expira_em" => "2025-12-12",
        "bloqueado" => false
    ],
    "acesso4882@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => false
    ],
    "acesso4899@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => false
    ],
    "sotestefofo1@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => false
    ],
    "cristiancavalcante200@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => false
    ],
     "acesso4859@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => false
    ],
    "carteldacontigencia@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-13",
        "bloqueado" => false
    ],
        "lcfreitas1225@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-14",
        "bloqueado" => false
    ],
    "descontinhosbrasileiro@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-14",
        "bloqueado" => false
    ],
    "acesso8348@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-02",
        "bloqueado" => false
    ],
    "igorfrmota1@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-21",
        "bloqueado" => false
    ],
    "samuca@gmail.com" => [
        "senha" => "Samucaca0596#", 
        "expira_em" => "2025-12-21",
        "bloqueado" => true
    ],
    "newmickey2025@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-21",
        "bloqueado" => false
    ],
    "jefeg111@gmail.com" => [
        "senha" => "Alterar0596#", 
        "expira_em" => "2025-12-21",
        "bloqueado" => false
    ],
    "projectsixinseven@proton.me" => [
        "senha" => "Project0596#", 
        "expira_em" => "2025-12-26",
        "bloqueado" => false
    ],
    "yltwo@hotmail.com" => [
        "senha" => "Yltwo0596#", 
        "expira_em" => "2025-12-26",
        "bloqueado" => false
    ],
    "agenciahermes1@gmail.com" => [
        "senha" => "Hermes0596#", 
        "expira_em" => "2025-12-27",
        "bloqueado" => false
    ],
    "juliocezarfelixdesousa@gmail.com" => [
        "senha" => "Senha0596#", 
        "expira_em" => "2025-12-27",
        "bloqueado" => false
    ],
     "allamcorrea10@gmail.com" => [
        "senha" => "Senha0596#", 
        "expira_em" => "2025-12-27",
        "bloqueado" => false
    ],
    "mastercontingencia@gmail.com" => [
        "senha" => "Senha0596#", 
        "expira_em" => "2025-12-27",
        "bloqueado" => false
    ],
    "cliente24@gmail.com" => [
        "senha" => "Cliente2423", 
        "expira_em" => "2025-12-02",
        "bloqueado" => true //bloqueado
    ]
];
// -----------------------------------------

// Pega os dados do formulário
$email_digitado = $_POST['email'];
$senha_digitada = $_POST['senha'];

// Carrega os arquivos de controle
$sessoes = json_decode(file_get_contents($arquivo_sessoes), true);
if (!$sessoes) $sessoes = [];

$strikes = json_decode(file_get_contents($arquivo_strikes), true);
if (!$strikes) $strikes = [];

// Pega o contador de strikes do usuário (ou 0 se não existir)
$contagem_atual = isset($strikes[$email_digitado]) ? $strikes[$email_digitado] : 0;


// --- LÓGICA DE LOGIN ---

// 1. Verifica se o e-mail existe na lista
if (isset($usuarios_autorizados[$email_digitado])) {
    
    $usuario = $usuarios_autorizados[$email_digitado];

    // --- NOVA LÓGICA DE BLOQUEIO (MANUAL + AUTOMÁTICO) ---
    
    // 2. VERIFICA O BLOQUEIO MANUAL (O "CADEADO")
    if (isset($usuario['bloqueado']) && $usuario['bloqueado'] === true) {
        header('Location: login.php?erro=4'); // Erro 4 = Bloqueado
        exit();
    }
    
    // 3. VERIFICA O BLOQUEIO AUTOMÁTICO (O "VIGIA")
    if ($contagem_atual >= $limite_strikes) {
        header('Location: login.php?erro=4'); // Erro 4 = Bloqueado
        exit();
    }
    // --- FIM DA LÓGICA DE BLOQUEIO ---


    // 4. Se não está bloqueado, verifica se a SENHA BATE
    if ($senha_digitada == $usuario['senha']) {
        
        // 5. Se a senha bate, verifica se o ACESSO NÃO EXPIROU
        $hoje = date('Y-m-d');
        $data_expiracao = $usuario['expira_em'];
        
        if ($hoje <= $data_expiracao) {
            
            // --- LÓGICA DE STRIKE ---
            // Verifica se este login vai "chutar" outra sessão
            if (isset($sessoes[$email_digitado])) {
                // SIM, é um strike!
                $contagem_atual++; // Adiciona +1
                $strikes[$email_digitado] = $contagem_atual; // Salva a nova contagem
                file_put_contents($arquivo_strikes, json_encode($strikes)); // Salva no arquivo
            }
            
            // --- CÓDIGO PARA SESSÃO ÚNICA (Como antes) ---
            $token_de_acesso = bin2hex(random_bytes(16));
            
            $_SESSION['logado'] = true;
            $_SESSION['email'] = $email_digitado;
            $_SESSION['token_de_acesso'] = $token_de_acesso; 

            $sessoes[$email_digitado] = $token_de_acesso;
            file_put_contents($arquivo_sessoes, json_encode($sessoes));
            
            // Redireciona para a página principal (o painel)
            header('Location: index.php');
            exit();
            
        } else {
            // Acesso expirado
            header('Location: login.php?erro=2');
            exit();
        }
    } else {
        // Senha errada
        header('Location: login.php?erro=1');
        exit();
    }
} else {
    // E-mail não existe
    header('Location: login.php?erro=1');
    exit();
}
?>