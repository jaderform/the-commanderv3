<?php 
session_start();

// PROTECAO: Verifica se o usuario esta logado e com acesso valido
require_once 'proteger.php';

// Carrega dados do usuário logado para injetar no JavaScript
$arquivo_usuarios = 'usuarios.json';
$license_expiry = '2099-12-31'; // Padrão
$whatsapp_renew = '5547992593339'; // Seu WhatsApp de renovação
$dominio_autorizado = $_SERVER['HTTP_HOST']; // Dominio atual do sistema

if (isset($_SESSION['email']) && file_exists($arquivo_usuarios)) {
    $usuarios = json_decode(file_get_contents($arquivo_usuarios), true);
    $email_atual = $_SESSION['email'];
    
    if (isset($usuarios[$email_atual])) {
        $license_expiry = $usuarios[$email_atual]['expira_em'] ?? '2099-12-31';
    }
}

// Gera token unico para este usuario/sessao (protecao contra copia)
$token_extensao = hash('sha256', session_id() . $dominio_autorizado . date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>
        // Variáveis de licença injetadas pelo servidor
        window.LICENSE_EXPIRY_DATE = '<?php echo $license_expiry; ?>';
        window.WHATSAPP_RENEW = '<?php echo $whatsapp_renew; ?>';
        
        // EXTENSAO TIKTOK SHOP - Funcao global
        function importarDaExtensao(tipo) {
            tipo = tipo || 'principal';
            
            navigator.clipboard.readText().then(function(text) {
                console.log('[Extensao] Clipboard lido, tamanho:', text.length);
                
                try {
                    var data = JSON.parse(text);
                    console.log('[Extensao] JSON parseado:', data);
                    
                    if (data.source === 'tiktok-shop-extractor') {
                        var tipoFinal = tipo === 'recomendacao' ? 'recomendacao' : (data.type || 'principal');
                        processarDadosExtensao(data.data, tipoFinal);
                    } else if (data.product) {
                        processarDadosExtensao(data, tipo);
                    } else {
                        alert('Formato invalido. Use a extensao para copiar o produto primeiro.');
                    }
                } catch (err) {
                    console.error('[Extensao] Erro ao parsear JSON:', err);
                    alert('Nenhum dado valido no clipboard.\n\nPasso 1: Va na pagina do TikTok Shop\nPasso 2: Clique na extensao\nPasso 3: Clique em Copiar\nPasso 4: Volte aqui e clique no botao');
                }
            }).catch(function(err) {
                console.error('[Extensao] Erro ao ler clipboard:', err);
                alert('Nao foi possivel acessar o clipboard.\n\nTente:\n1. Usar HTTPS\n2. Permitir acesso ao clipboard no navegador');
            });
        }
        
        // Define valor de um campo e dispara eventos para o preview atualizar
        function setVal(id, value) {
            var elx = document.getElementById(id);
            if (!elx || value === undefined || value === null || value === '') return;
            elx.value = value;
            elx.dispatchEvent(new Event('input', { bubbles: true }));
            elx.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        // Decide se uma variacao e de TAMANHO (vai para addSize) ou COR (vai para addColor)
        // Regras em ordem de confianca:
        //   1) Se as opcoes tem imagem -> e COR (tamanhos nunca tem imagem no TikTok)
        //   2) Se o nome bate com termos de tamanho -> e TAMANHO
        //   3) Se a maioria dos valores parecem tamanho (P/M/G, 33/34, numeros) -> e TAMANHO
        function ehVariacaoDeTamanho(variation) {
            if (!variation) return false;
            var nome = variation.name || '';
            var opcoes = variation.options || [];

            // 1) Tem imagem em alguma opcao? Entao e COR, nunca tamanho
            var temImagem = opcoes.some(function(o) {
                return o && o.image && String(o.image).trim() !== '';
            });
            if (temImagem) return false;

            // 2) Nome claramente de tamanho
            if (/tamanho|\bsize\b|\btam\b|n[uú]mero|numera[cç]|medida/i.test(nome)) return true;

            // 3) Nome claramente de cor/estampa -> nao e tamanho
            if (/^cor(es)?$|color|colour|estampa|^padr[aã]o$/i.test(nome.trim())) return false;

            // 4) Analise dos VALORES: a maioria parece tamanho?
            if (opcoes.length > 0) {
                var padraoTamanho = /^(PP|P|M|G|GG|XG|XGG|XS|S|L|XL|XXL|XXXL|U|UNICO|[0-9]{1,3}([\/.\-x][0-9]{1,3})?|EU\s?[0-9]+|BR\s?[0-9]+|[0-9]+\s?(cm|mm|ml|m|kg|g|l|polegadas?))$/i;
                var qtdTamanho = 0;
                opcoes.forEach(function(o) {
                    var v = (o && o.name ? String(o.name) : '').trim();
                    if (v && padraoTamanho.test(v)) qtdTamanho++;
                });
                if (qtdTamanho >= Math.ceil(opcoes.length * 0.6)) return true;
            }

            return false;
        }
        
        function processarDadosExtensao(data, tipo) {
            var p = data.product || data;
            console.log('[Extensao] Processando produto:', p);
            console.log('[Extensao] Tipo:', tipo);
            console.log('[Extensao] Imagens:', (p.images || []).length, '| Variacoes:', (p.variations || []).length, '| Reviews:', (p.reviews || []).length);
            
            if (tipo === 'principal') {
                // Titulo
                setVal('product-title', p.title);
                
                // Precos (mantem formato brasileiro com virgula)
                setVal('current-price', limparPrecoExt(p.price));
                setVal('original-price', limparPrecoExt(p.originalPrice));
                
                // Imagem principal
                var mainImg = p.mainImage || (p.images && p.images.length > 0 ? p.images[0] : '');
                setVal('main-image', mainImg);
                
                // Imagens adicionais (todas exceto a principal)
                if (p.images && p.images.length > 1) {
                    setVal('additional-images', p.images.slice(1).join(', '));
                }
                
                // Descricao
                setVal('product-description', p.description);
                
                // Rating (converte virgula para ponto: 4,5 -> 4.5)
                if (p.rating) setVal('product-rating', p.rating.toString().replace(',', '.'));
                
                // Review count (mantem formato com K: 4.4K)
                setVal('review-count', p.reviewCount);
                
                // Vendidos (mantem formato com K: 43.5K)
                var vendidos = p.soldCount || p.salesCount || p.sold || '';
                setVal('sales-count', vendidos.toString());
                
                // Loja
                setVal('store-name', p.shopName);
                setVal('store-logo-url', p.shopLogo);
                
                // ========== VARIACOES (CORES e TAMANHOS SEPARADOS) ==========
                if (p.variations && p.variations.length > 0) {
                    // Limpar containers
                    var colorContainer = document.getElementById('color-options');
                    var sizeContainer = document.getElementById('size-options');
                    if (colorContainer) colorContainer.innerHTML = '';
                    if (sizeContainer) sizeContainer.innerHTML = '';
                    
                    console.log('[v0] VARIACOES RECEBIDAS:', p.variations.length);
                    
                    p.variations.forEach(function(variation, idx) {
                        var isTamanho = ehVariacaoDeTamanho(variation);
                        console.log('[v0] Variacao', idx, ':', variation.name, '| isTamanho:', isTamanho, '| opcoes:', variation.options ? variation.options.length : 0);
                        
                        // Atualizar titulo da variacao no painel
                        if (isTamanho) {
                            setVal('variation2-title', variation.name || 'Tamanhos');
                        } else {
                            setVal('variation1-title', variation.name || 'Cores');
                        }
                        
                        if (variation.options && variation.options.length > 0) {
                            variation.options.forEach(function(opt, optIdx) {
                                if (isTamanho) {
                                    // ========== ADICIONA COMO TAMANHO ==========
                                    console.log('[v0] Adicionando TAMANHO:', opt.name);
                                    
                                    // Tenta usar addSize se existir
                                    if (typeof addSize === 'function') {
                                        addSize(opt.name || '');
                                    } else {
                                        // Fallback: clica no botao "Adicionar Variação 2" e preenche o campo
                                        var addBtn = document.querySelector('[onclick*="addVariation2"], [onclick*="addVariation(2)"], button[id*="add-variation-2"], .add-variation-2-btn');
                                        if (!addBtn) {
                                            // Procura por texto do botao
                                            var allBtns = document.querySelectorAll('button');
                                            for (var b = 0; b < allBtns.length; b++) {
                                                if (allBtns[b].textContent.indexOf('Adicionar Varia') > -1 && allBtns[b].textContent.indexOf('2') > -1) {
                                                    addBtn = allBtns[b];
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if (addBtn) {
                                            addBtn.click();
                                            console.log('[v0] Clicou no botao Adicionar Variacao 2');
                                            
                                            // Aguarda o campo ser criado e preenche
                                            setTimeout(function(tamanhoNome) {
                                                // Pega o ultimo campo de variacao 2 criado
                                                var campos = document.querySelectorAll('[id*="variation2-option"], [name*="variation2"], .variation2-input, input[placeholder*="Tamanho"]');
                                                if (campos.length > 0) {
                                                    var ultimoCampo = campos[campos.length - 1];
                                                    ultimoCampo.value = tamanhoNome;
                                                    ultimoCampo.dispatchEvent(new Event('input', { bubbles: true }));
                                                    ultimoCampo.dispatchEvent(new Event('change', { bubbles: true }));
                                                    console.log('[v0] Preencheu campo variacao 2:', tamanhoNome);
                                                }
                                            }.bind(null, opt.name), 100 * (optIdx + 1));
                                        } else {
                                            console.log('[v0] Nao encontrou botao Adicionar Variacao 2');
                                        }
                                    }
                                } else {
                                    // ========== ADICIONA COMO COR ==========
                                    console.log('[v0] Adicionando COR:', opt.name, opt.image ? '(com img)' : '(sem img)');
                                    if (typeof addColor === 'function') {
                                        addColor({
                                            imageUrl: opt.image || '',
                                            name: opt.name || '',
                                            price: limparPrecoExt(p.price),
                                            checkoutUrl: ''
                                        });
                                    }
                                }
                            });
                        }
                    });
                }
                
                // Avaliacoes
                if (p.reviews && p.reviews.length > 0 && typeof addReview === 'function') {
                    var reviewContainer = document.getElementById('review-items');
                    if (reviewContainer) reviewContainer.innerHTML = '';
                    
                    console.log('[v0] Reviews recebidos:', p.reviews.length);
                    p.reviews.slice(0, 8).forEach(function(review, idx) {
                        console.log('[v0] Review ' + idx + ' images:', review.images);
                        addReview({
                            name: review.author || review.name || 'Cliente ' + (idx + 1),
                            avatarUrl: review.avatar || '',
                            details: review.variant || '',
                            stars: review.rating || review.stars || 5,
                            text: review.text || review.content || '',
                            images: review.images && review.images.length > 0 ? review.images.slice(0, 5) : []
                        });
                    });
                }
                
                mostrarNotificacaoExt('Produto principal importado!', 'success');
                
            } else if (tipo === 'recomendacao') {
                if (typeof addRecommendation === 'function') {
                    
                    // Converter variations para colors e sizes (formato esperado pela funcao addRecommendation)
                    var colors = [];
                    var sizes = [];
                    var variation1Title = 'Cor';
                    var variation2Title = 'Tamanho';
                    
                    if (p.variations && p.variations.length > 0) {
                        p.variations.forEach(function(variation) {
                            // Detecta se e tamanho
                            var isTamanho = /tamanho|size|tam\b|numero|number/i.test(variation.name || '');
                            
                            if (isTamanho) {
                                variation2Title = variation.name || 'Tamanho';
                                if (variation.options && variation.options.length > 0) {
                                    variation.options.forEach(function(opt) {
                                        sizes.push(opt.name || opt);
                                    });
                                }
                            } else {
                                variation1Title = variation.name || 'Cor';
                                if (variation.options && variation.options.length > 0) {
                                    variation.options.forEach(function(opt) {
                                        colors.push({
                                            name: opt.name || '',
                                            imageUrl: opt.image || '',
                                            price: '',
                                            checkoutUrl: ''
                                        });
                                    });
                                }
                            }
                        });
                    }
                    
                    // Formatar reviews - MESMO mapeamento do produto principal (campos da extensao)
                    var formattedReviews = [];
                    if (p.reviews && p.reviews.length > 0) {
                        p.reviews.slice(0, 5).forEach(function(review, idx) {
                            formattedReviews.push({
                                name: review.author || review.name || 'Cliente ' + (idx + 1),
                                avatarUrl: review.avatar || review.avatarUrl || '',
                                details: review.variant || review.details || 'Item: Padrao',
                                stars: review.rating || review.stars || 5,
                                text: review.text || review.content || '',
                                image: review.images && review.images.length > 0 ? review.images[0] : (review.image || '')
                            });
                        });
                    }
                    
                    // Imagens adicionais (galeria)
                    var additionalImages = (p.images && p.images.length > 1) ? p.images.slice(1) : [];
                    
                    // Nota / avaliacoes / vendas
                    var ratingVal = p.rating ? String(p.rating).replace(',', '.') : '4.9';
                    var reviewCountVal = p.reviewCount ? String(p.reviewCount).replace(/[^\d]/g, '') : (formattedReviews.length || '15');
                    var salesCountVal = (p.soldCount || p.salesCount || p.sold || '');
                    salesCountVal = salesCountVal ? String(salesCountVal) : '100';
                    
                    // Envia dados no formato correto para addRecommendation
                    var recData = {
                        productTitle: p.title,
                        currentPrice: limparPrecoExt(p.price),
                        originalPrice: limparPrecoExt(p.originalPrice),
                        mainImage: p.mainImage || (p.images && p.images.length > 0 ? p.images[0] : ''),
                        productDescription: p.description || '',
                        productUrl: 'rec-' + Date.now(),
                        colors: colors,
                        sizes: sizes,
                        variation1Title: variation1Title,
                        variation2Title: variation2Title,
                        reviews: formattedReviews,
                        additionalImages: additionalImages,
                        productRating: ratingVal,
                        reviewCount: reviewCountVal,
                        salesCount: salesCountVal
                    };
                    
                    console.log('[v0] Recomendacao formatada:', recData);
                    console.log('[v0] Colors:', colors.length, 'Sizes:', sizes.length, 'Reviews:', formattedReviews.length);
                    
                    var novoRecItem = addRecommendation(recData);
                    // Chama SEMPRE - a funcao localiza o ultimo item sozinha se nao houver retorno
                    if (typeof injetarCamposExtrasRecomendacao === 'function') {
                        injetarCamposExtrasRecomendacao(novoRecItem, recData);
                    }
                    mostrarNotificacaoExt('Recomendacao adicionada com todos os dados!', 'success');
                } else {
                    alert('Funcao addRecommendation nao encontrada.');
                }
            }
            
            // Atualizar preview
            if (typeof updateOutput === 'function') {
                setTimeout(updateOutput, 200);
            }
        }
        
        function limparPrecoExt(preco) {
            if (!preco) return '';
            // Remove R$ e espacos, mantem o numero em formato brasileiro (virgula)
            return preco.toString()
                .replace(/R\$\s*/g, '')
                .replace(/\s/g, '')
                .trim();
        }
        
        function mostrarNotificacaoExt(mensagem, tipo) {
            var notifAnterior = document.querySelector('.extensao-notificacao');
            if (notifAnterior) notifAnterior.remove();
            
            var cor = tipo === 'success' ? '#25f4ee' : '#fe2c55';
            var icone = tipo === 'success' ? 'OK' : 'X';
            
            var notif = document.createElement('div');
            notif.className = 'extensao-notificacao';
            notif.style.cssText = 'position:fixed;top:20px;right:20px;background:#1a1a1a;border:2px solid '+cor+';color:#fff;padding:16px 24px;border-radius:12px;z-index:99999;font-family:-apple-system,BlinkMacSystemFont,sans-serif;font-size:14px;box-shadow:0 4px 20px rgba(0,0,0,0.5);display:flex;align-items:center;gap:10px;';
            notif.innerHTML = '<span style="color:'+cor+';font-size:18px;font-weight:bold;">'+icone+'</span><span>'+mensagem+'</span>';
            
            document.body.appendChild(notif);
            
            setTimeout(function() { notif.remove(); }, 4000);
        }
        
        // ============ PROTECAO: Extensao personalizada por dominio ============
        // Dominio autorizado injetado pelo servidor
        var DOMINIO_AUTORIZADO = '<?php echo $dominio_autorizado; ?>';
        var TOKEN_EXTENSAO = '<?php echo $token_extensao; ?>';
        
        // Funcao para baixar a extensao TikTok Shop como ZIP (personalizada)
        function baixarExtensaoTikTok() {
            mostrarNotificacaoExt('Gerando extensao personalizada...', 'info');
            
            var arquivos = [
                { nome: 'manifest.json', url: 'extensao-tiktok-shop/manifest.json' },
                { nome: 'content.js', url: 'extensao-tiktok-shop/content.js' },
                { nome: 'inject.js', url: 'extensao-tiktok-shop/inject.js' },
                { nome: 'popup.html', url: 'extensao-tiktok-shop/popup.html' },
                { nome: 'popup.js', url: 'extensao-tiktok-shop/popup.js' },
                { nome: 'styles.css', url: 'extensao-tiktok-shop/styles.css' },
                { nome: 'icons/icon16.png', url: 'extensao-tiktok-shop/icons/icon16.png' },
                { nome: 'icons/icon48.png', url: 'extensao-tiktok-shop/icons/icon48.png' },
                { nome: 'icons/icon128.png', url: 'extensao-tiktok-shop/icons/icon128.png' }
            ];
            
            var zip = new JSZip();
            var promessas = [];
            var cacheBuster = '?v=' + Date.now(); // Forca buscar arquivo atualizado
            
            arquivos.forEach(function(arq) {
                var urlComCache = arq.url + (arq.nome.endsWith('.png') ? '' : cacheBuster);
                var p = fetch(urlComCache)
                    .then(function(resp) {
                        if (!resp.ok) throw new Error('Arquivo nao encontrado: ' + arq.url);
                        return arq.nome.endsWith('.png') ? resp.blob() : resp.text();
                    })
                    .then(function(conteudo) {
                        // ===== PROTECAO: Injeta dominio autorizado nos arquivos JS =====
                        if (arq.nome === 'content.js' || arq.nome === 'popup.js') {
                            // Adiciona verificacao de dominio no inicio do arquivo
                            var protecao = '// EXTENSAO LICENCIADA PARA: ' + DOMINIO_AUTORIZADO + '\n';
                            protecao += 'var _0xDOMINIO="' + DOMINIO_AUTORIZADO + '";';
                            protecao += 'var _0xTOKEN="' + TOKEN_EXTENSAO + '";';
                            conteudo = protecao + '\n' + conteudo;
                        }
                        zip.file(arq.nome, conteudo);
                    });
                promessas.push(p);
            });
            
            Promise.all(promessas)
                .then(function() {
                    return zip.generateAsync({ type: 'blob' });
                })
                .then(function(blob) {
                    // Nome do arquivo inclui parte do dominio para identificacao
                    var nomeDominio = DOMINIO_AUTORIZADO.replace(/[^a-z0-9]/gi, '-').substring(0, 20);
                    saveAs(blob, 'extensao-tiktok-' + nomeDominio + '.zip');
                    mostrarNotificacaoExt('Extensao gerada para: ' + DOMINIO_AUTORIZADO, 'success');
                })
                .catch(function(err) {
                    console.error('Erro ao baixar extensao:', err);
                    mostrarNotificacaoExt('Erro ao baixar. Tente novamente.', 'error');
                });
        }
        
        // Funcao para mostrar/esconder instrucoes
        function toggleInstrucoesExtensao() {
            var instrucoes = document.getElementById('instrucoes-extensao');
            if (instrucoes) {
                instrucoes.style.display = instrucoes.style.display === 'none' ? 'block' : 'none';
            }
        }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor PRO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        .tiktok-message-box { max-width: 1600px; margin: 0 auto 30px auto; background: #fff; border-left: 6px solid #fe2c55; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 20px 25px; display: flex; align-items: center; gap: 20px; font-family: 'Montserrat', sans-serif; position: relative; overflow: hidden; }
        .tiktok-message-box::after { content: ''; position: absolute; top: 0; right: 0; width: 100px; height: 100%; background: linear-gradient(90deg, transparent, rgba(254, 44, 85, 0.03)); pointer-events: none; }
        .tiktok-msg-icon { background: rgba(254, 44, 85, 0.1); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .tiktok-msg-icon svg { width: 24px; height: 24px; color: #fe2c55; fill: currentColor; }
        .tiktok-msg-content { flex: 1; }
        .tiktok-msg-content h3 { margin: 0 0 5px 0; font-size: 16px; font-weight: 700; color: #161823; }
        .tiktok-msg-content p { margin: 0; font-size: 14px; color: #555; line-height: 1.5; }
        @media (max-width: 768px) { .tiktok-message-box { flex-direction: column; align-items: flex-start; gap: 15px; margin: 0 15px 30px 15px; } }
        @media (min-width: 900px) {
            .container {
                display: flex !important;
                flex-direction: row !important;
                align-items: flex-start;
                gap: 30px;
                max-width: 1600px;
                margin: 0 auto;
            }
            .editor-section { flex: 1; min-width: 0; max-width: 55%; }
            .preview-section { flex: 1; width: 45%; position: sticky; top: 20px; display: block !important; }
            #preview-container { height: 85vh !important; min-height: 600px; }
        }
        .gateway-body { padding: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 10px; display: none; }
        .gateway-body.active { display: block; animation: fadeIn 0.3s ease-in-out; }
        .gateway-selector-container select { width: 100%; padding: 12px; font-size: 15px; font-weight: 600; border: 2px solid #cbd5e1; border-radius: 6px; cursor: pointer; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .preview-page-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; }
        .preview-page-tab { flex: 1 1 auto; min-width: 70px; padding: 8px 10px; font-size: 13px; font-weight: 600; color: #555; background: #f1f1f3; border: 1px solid #e2e2e6; border-radius: 8px; cursor: pointer; transition: all 0.15s ease; }
        .preview-page-tab:hover { background: #e8e8ec; }
        .preview-page-tab.active { background: #fe2c55; color: #fff; border-color: #fe2c55; }
        /* Moldura de celular: todas as paginas com a mesma largura mobile */
        #preview-container { display: flex; justify-content: center; align-items: flex-start; background: #e9e9ee; padding: 16px 0; overflow-y: auto; border-radius: 12px; }
        #preview-iframe { width: 390px; max-width: 100%; height: 100%; min-height: 760px; border: none; background: #fff; border-radius: 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.18); overflow: hidden; }
    </style>
</head>
<body>
    <a href="logout.php" class="logout-btn" style="background-color: #000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: 600; float: right;">Sair da Conta</a>
    <div style="clear: both;"></div>

    <h1 style="text-align: center; margin-bottom: 30px; font-weight: 700;">Editor PRO</h1>

    <div class="tiktok-message-box">
        <div class="tiktok-msg-icon">
            <svg viewBox="0 0 24 24">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
            </svg>
        </div>
        <div class="tiktok-msg-content">
            <h3>Novidades no Painel</h3>
            <p>Agora você consegue copiar produtos da shopify com o link da pagina de produto, basta colar o link e vai puxar tudo.  02/03/2026</p>
        </div>
    </div>

    <div class="container">
        <div class="editor-section" id="editor-form">
            <h2>Editor de Conteúdo</h2>

            <div class="project-management-section">
                <h4>Gerenciamento de Projetos</h4>
                <div class="form-group project-controls">
                    <button class="add-btn" onclick="saveProject()" style="background-color: #000; color: white;">Salvar no Navegador</button>
                    <select id="project-list" onchange="loadProject()" style="padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
                        <option value="">Carregar do Navegador</option>
                    </select>
                    <button class="remove-btn" onclick="deleteProject()" style="background-color: #fe2c55; color: white;">Excluir</button>
                    <button class="add-btn" style="background-color: #0c8a00; color: white;" onclick="exportProject()">Exportar (.json)</button>
                    <button class="add-btn" style="background-color: #0060c8; color: white;" onclick="importProject()">Importar</button>
                    <button class="add-btn" style="background-color: #555; color: white;" onclick="newProject()">Novo Projeto</button>
                </div>
                <input type="file" id="import-file-input" accept=".json,.html,.txt" style="display: none;">
            </div>

            <!-- EXTENSAO TIKTOK SHOP EXTRACTOR -->
            <div class="extensao-tiktok-box" style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border:2px solid #25f4ee;border-radius:12px;padding:15px;margin:15px 0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#25f4ee"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                    <span style="color:#fff;font-weight:700;font-size:14px;">Importar da Extensao TikTok Shop</span>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="button" onclick="importarDaExtensao('principal')" style="background:#fe2c55;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">Colar como PRINCIPAL</button>
                    <button type="button" onclick="importarDaExtensao('recomendacao')" style="background:#25f4ee;color:#000;border:none;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">Colar como RECOMENDACAO</button>
                </div>
                <p style="color:#888;font-size:11px;margin:10px 0 0 0;">1. Va no TikTok Shop - 2. Clique na Extensao - 3. Copie - 4. Clique no botao acima</p>
                
                <!-- BOTAO BAIXAR EXTENSAO + INSTRUCOES -->
                <div style="margin-top:15px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.1);">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button type="button" onclick="baixarExtensaoTikTok()" style="background:#00c853;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                            Baixar Extensao (Licenciada)
                        </button>
                        <button type="button" onclick="toggleInstrucoesExtensao()" style="background:transparent;color:#25f4ee;border:1px solid #25f4ee;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
                            Como Instalar?
                        </button>
                    </div>
                    <p style="color:#ff9800;font-size:10px;margin:8px 0 0 0;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        Extensao personalizada para seu dominio. Nao compartilhe!
                    </p>
                    
                    <div id="instrucoes-extensao" style="display:none;margin-top:15px;background:rgba(0,0,0,0.3);border-radius:8px;padding:15px;">
                        <h4 style="color:#25f4ee;margin:0 0 10px 0;font-size:14px;">Como instalar a extensao:</h4>
                        <ol style="color:#ccc;font-size:12px;margin:0;padding-left:20px;line-height:1.8;">
                            <li>Clique em <strong style="color:#00c853;">"Baixar Extensao"</strong> e salve o arquivo ZIP</li>
                            <li>Extraia o ZIP em uma pasta no seu computador</li>
                            <li>Abra o Chrome e va em <strong style="color:#25f4ee;">chrome://extensions</strong></li>
                            <li>Ative o <strong style="color:#25f4ee;">"Modo do desenvolvedor"</strong> no canto superior direito</li>
                            <li>Clique em <strong style="color:#25f4ee;">"Carregar sem compactacao"</strong></li>
                            <li>Selecione a pasta onde extraiu os arquivos</li>
                            <li>Pronto! A extensao aparecera na barra do Chrome</li>
                        </ol>
                        <p style="color:#888;font-size:11px;margin:10px 0 0 0;border-top:1px solid rgba(255,255,255,0.1);padding-top:10px;">
                            <strong>Uso:</strong> Acesse qualquer produto no TikTok Shop, clique na extensao e depois em "Copiar Produto". Volte aqui e clique em "Colar como PRINCIPAL".
                        </p>
                    </div>
                </div>
            </div>
            <!-- FIM EXTENSAO -->

            <div class="tab-buttons">
                <button class="tab-button active" data-tab="basic">Básico</button>
                <button class="tab-button" data-tab="security" style="border-color: #ef4444; color: #ef4444;">Segurança 🔒</button>
                <button class="tab-button" data-tab="marketing" style="border-color: #000; color: #000;">Marketing (Pixel)</button>
                <button class="tab-button" data-tab="payment" style="border-color: #3b82f6; color: #3b82f6;">Pagamento (Gateway)</button>
                <button class="tab-button" data-tab="details">Detalhes</button>
                <button class="tab-button" data-tab="media">Mídia</button>
                <button class="tab-button" data-tab="variations">Variações</button>
                <button class="tab-button" data-tab="reviews">Avaliações</button>
<button class="tab-button" data-tab="recommendations">Recomendações</button>
                <button class="tab-button" data-tab="footer-info">Rodape</button>
            </div>

            <div id="basic" class="tab-content active">
                <div class="form-group" style="background: #d1fae5; padding: 15px; border-radius: 8px; border: 1px solid #10b981; margin-bottom: 20px;">
                    <label for="use-custom-checkout" style="display:flex; align-items:center; gap: 10px; cursor:pointer; font-size: 16px; color: #065f46;">
                        <input type="checkbox" id="use-custom-checkout" checked style="width: 20px; height: 20px;">
                        ATIVAR CHECKOUT PRÓPRIO (checkout.php)
                    </label>
                    <small style="display:block; margin-top:5px; color: #065f46;">
                        <b>Marcado:</b> Usa o sistema interno. <b>Desmarcado:</b> Link externo.
                    </small>
                </div>

                <div class="form-group" style="background: #e0f2fe; padding: 15px; border-radius: 8px; border: 1px solid #7dd3fc; margin-bottom: 20px;">
                    <label style="font-weight:bold; color:#0369a1; display:block; margin-bottom:5px;">Estilo do Checkout</label>
                    <select id="checkout-style-selector" style="width:100%; padding:10px; border-radius:5px; border:1px solid #38bdf8;">
                        <option value="v1">Checkout V1 (Padrão TikTok - Fundo Branco)</option>
                        <option value="v2">Checkout V2 (Novo - Focado em PIX/Mobile)</option>
                    </select>
                </div>

                <div class="form-group" style="background: #fff7ed; padding: 15px; border-radius: 8px; border: 1px solid #fdba74; margin-bottom: 20px;">
                    <label style="font-weight:bold; color:#9a3412; display:block; margin-bottom:5px;">Idioma do Site Gerado</label>
                    <select id="site-language" style="width:100%; padding:10px; border-radius:5px; border:1px solid #fdba74;">
                        <option value="pt" selected>Português (Brasil)</option>
                        <option value="pt-pt">Português (Portugal)</option>
                        <option value="en">Inglês (English)</option>
                        <option value="es">Espanhol (Español)</option>
                        <option value="fr">Francês (Français)</option>
                        <option value="de">Alemão (Deutsch)</option>
                    </select>
                    <p style="font-size:12px; color:#c2410c; margin-top:5px;">Isso traduzirá automaticamente o Carrinho, Checkout, Upsell e Taxa.</p>
                </div>

                <div class="fetch-box">
                    <label class="fetch-box-title">Puxar Dados do Produto De links da Shopify ON</label>
                    <div class="fetch-box-controls">
                        <input type="text" id="main-product-url-input" placeholder="Cole o link do produto da TikTok Shop aqui...">
                        <button class="add-btn" onclick="fetchMainProduct()" style="background-color: #000; color: white;">Puxar Dados</button>
                    </div>
                    <div id="main-product-loading-status" class="fetch-status"></div>
                </div>


                <h3>Informações Principais</h3>
                <div class="form-group"><label>Badge/Tag</label><input type="text" id="product-badge" value="Black Friday"></div>
                <div class="form-group"><label>Título do Produto</label><input type="text" id="product-title" value="Camiseta Oversized"></div>
                <div class="form-group"><label>Preço Padrão</label><input type="text" id="current-price" value="50,31"></div>
                <div class="form-group"><label>Preço Original/"De"</label><input type="text" id="original-price" value="73,22"></div>
                <div class="form-group"><label>Textos de Desconto</label><input type="text" id="discount-text" value="Desconto de R$ 10, Desconto de 10%"></div>
                <div class="form-group"><label>Nota (Ex: 4.8)</label><input type="text" id="product-rating" value="4.8"></div>
                <div class="form-group"><label>Nº Avaliações</label><input type="text" id="review-count" value="387"></div>
                <div class="form-group"><label>Nº Vendas</label><input type="text" id="sales-count" value="4674"></div>
            </div>

            <div id="security" class="tab-content">
                <h3 style="color: #ef4444;">Proteção Contra Clonagem (Anti-Cópia)</h3>
                <div class="form-group" style="background: #fee2e2; padding: 20px; border-radius: 8px; border: 1px solid #f87171; margin-bottom: 20px;">
                    <label for="license-domain" style="color:#7f1d1d; font-weight:bold;">Domínio Autorizado (Sem https:// ou www)</label>
                    <input type="text" id="license-domain" placeholder="Ex: sualoja.com" style="border-color: #fca5a5;">
                    <p style="font-size:12px; color:#991b1b; margin-top:8px;">Se preenchido, o site só abrirá neste domínio.</p>
                </div>
                <div class="form-group" style="background: #f3f4f6; padding: 20px; border-radius: 8px; border: 1px solid #d1d5db;">
                    <label for="anti-copy-protection" style="display:flex; align-items:center; gap: 10px; cursor:pointer; font-size: 16px; color: #1f2937; font-weight: 600;">
                        <input type="checkbox" id="anti-copy-protection" style="width: 20px; height: 20px;"> Ativar Bloqueio de Mouse e Teclado
                    </label>
                </div>
            </div>

            <div id="marketing" class="tab-content">
                <div class="form-group" style="background: #eef2ff; padding: 20px; border-radius: 8px; border: 1px solid #c7d2fe; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="background:#4f46e5; color:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:18px;">V8</div>
                        <div>
                            <h3 style="margin:0; color:#312e81; font-size:16px;">Integração Cloaker V8</h3>
                            <p style="margin:2px 0 0 0; font-size:12px; color:#6366f1;">Sincroniza o rastreamento para Webhook.</p>
                        </div>
                    </div>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e7ff;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#312e81; font-size:15px;">
                            <input type="checkbox" id="use-cloaker-integration" checked style="width: 20px; height: 20px; accent-color: #4f46e5;">
                            ATIVAR RASTREAMENTO DE VENDAS
                        </label>
                        <p style="font-size:12px; color:#4338ca; margin-top:8px; line-height:1.4;">
                            Habilita o envio do <b>Click ID</b> para o gateway. Configure o Webhook no painel do gateway apontando para o seu Cloaker.
                        </p>
                    </div>
                </div>

                <div class="form-group" style="background: #f0f9ff; padding: 20px; border-radius: 8px; border: 1px solid #bae6fd; margin-top: 20px;">
                    <h3 style="margin:0; color:#000;">TikTok Ads (Web + API)</h3>
                    <label for="tiktok-pixel-id" style="font-weight:bold; color:#333; display:block; margin-bottom:5px;">ID do Pixel (Web)</label>
                    <input type="text" id="tiktok-pixel-id" placeholder="Ex: D4IGNJ3C77UDQ79ISPO0">
                    <label for="tiktok-access-token" style="font-weight:bold; color:#333; display:block; margin-top:10px; margin-bottom:5px;">Access Token (Events API)</label>
                    <input type="text" id="tiktok-access-token" placeholder="Cole o token gigante aqui...">
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dbeafe;">
                        <label style="display:flex; align-items:start; gap:10px; cursor:pointer; color:#0284c7; font-weight:600;">
                            <input type="checkbox" id="pixel-trigger-type" checked style="width:20px; height:20px; margin-top:2px;">
                            <div>Disparar Eventos ao Gerar PIX?</div>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="background: #fdf4ff; padding: 20px; border-radius: 8px; border: 1px solid #f0abfc; margin-top: 20px;">
                    <h3 style="margin:0; color:#a21caf;">Webhook (Opcional)</h3>
                    <input type="text" id="custom-webhook-url" placeholder="Ex: https://webhook.site/...">
                </div>

                <div class="form-group" style="background: #fff1f2; padding: 20px; border-radius: 8px; border: 1px solid #fda4af; margin-top: 20px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 10px;">
                        <h3 style="margin:0; color:#be123c;">UPSELL / REDIRECIONAMENTO</h3>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; font-size:14px; color:#be123c; background:white; padding:5px 10px; border-radius:20px; border:1px solid #fda4af;">
                            <input type="checkbox" id="upsell-enable" onchange="toggleUpsellFields()" style="width:16px; height:16px;"> ATIVAR
                        </label>
                    </div>
                    <div id="upsell-fields-container" style="display:none; border-top:1px solid #fecdd3; padding-top:15px; margin-top:10px;">
                        <select id="upsell-mode" onchange="toggleUpsellMode()" style="width:100%; padding:10px; border-radius:5px; border:1px solid #fda4af; margin-bottom:15px;">
                            <option value="auto">⚡ Sistema Automático (Taxa + Barrado)</option>
                            <option value="custom">🔗 Link Externo (Customizado)</option>
                        </select>
                        <div id="custom-url-group" style="display:none;">
                            <label>URL de Destino</label>
                            <input type="text" id="upsell-url">
                        </div>
                        <label>Tempo após fechar aviso (segundos)</label>
                        <input type="number" id="upsell-delay" value="15">
                        <p id="auto-msg" style="font-size:12px; color:#881337; margin-top:10px;">O sistema criará automaticamente as páginas de "Pedido Barrado" e "Taxa".</p>
                    </div>
                </div>
            </div>

            <div id="payment" class="tab-content">
                <h3>Configuração do Gateway</h3>
                <div class="form-group gateway-selector-container">
                    <label>Selecione o Gateway</label>
                    <select id="gateway-selector" onchange="updateGatewayFields()">
                        <option value="plumify">Plumify</option>
                        <option value="fastsoft" selected>FastSoft</option>
                        <option value="payhub">PayHub</option>
                        <option value="cyberhub">CyberHub</option>
                        <option value="freepay">FreePay</option>
                        <option value="kingpay">KingPay</option>
                        <option value="duttyfy">Duttyfy</option>
                        <option value="sourcepay">SourcePay</option>
                        <option value="pagflex">PagFlex</option>
                        <option value="blackpayments">Black Cat</option>
                        <option value="invictus">Invictus Pay</option>
                        <option value="bynet">ByNet</option>
                        <option value="otimize">Otimize Pagamentos</option>
                        <option value="ironpay">IronPay</option>
                        <option value="skalepay">SkalePay</option>
                    </select>
                </div>

                <div id="gw-fields-plumify" class="gateway-body">
                    <div class="form-group">
                        <label style="font-weight:bold;">API Token (Plumify)</label>
                        <input type="text" id="plumify-token" placeholder="Cole seu Token de API aqui...">
                    </div>
                </div>

                <div id="gw-fields-payhub" class="gateway-body">
                    <div class="form-group">
                        <label style="font-weight:bold; color:#000;">Chave Secreta PayHub</label>
                        <input type="text" id="payhub-secret" placeholder="Cole sua chave secreta aqui..." class="form-control">
                        <small style="color:#666;">A PayHub utiliza Basic Auth no formato "x:CHAVE_SECRETA".</small>
                    </div>
                </div>

                <div id="gw-fields-fastsoft" class="gateway-body">
                    <div class="form-group">
                        <label style="font-weight:bold; color:#fe2c55;">Chave Secreta FastSoft (sk_...)</label>
                        <input type="text" id="fastsoft-secret" placeholder="Cole sua sk_880eeb..." class="form-control">
                        <small style="color:#666;">A FastSoft utiliza Basic Auth no formato "x:TOKEN".</small>
                    </div>
                </div>

                <div id="gw-fields-cyberhub" class="gateway-body"><div class="form-group"><input type="text" id="cyberhub-public" placeholder="Public Key"></div><div class="form-group"><input type="text" id="cyberhub-secret" placeholder="Secret Key"></div></div>
                <div id="gw-fields-freepay" class="gateway-body"><div class="form-group"><input type="text" id="freepay-secret" placeholder="Public Key"></div><div class="form-group"><input type="text" id="freepay-company" placeholder="Secret Key"></div></div>
                <div id="gw-fields-kingpay" class="gateway-body"><div class="form-group"><input type="text" id="kingpay-secret" placeholder="Secret Key"></div></div>
                <div id="gw-fields-sourcepay" class="gateway-body"><div class="form-group"><input type="text" id="sourcepay-public" placeholder="Public Key"></div><div class="form-group"><input type="text" id="sourcepay-secret" placeholder="Secret Key"></div></div>
                <div id="gw-fields-pagflex" class="gateway-body"><div class="form-group"><input type="text" id="pagflex-secret" placeholder="Secret Key"></div><div class="form-group"><input type="text" id="pagflex-company" placeholder="Company ID"></div></div>
                <div id="gw-fields-blackpayments" class="gateway-body"><div class="form-group"><input type="text" id="black-public" placeholder="Public Key"></div><div class="form-group"><input type="text" id="black-secret" placeholder="Secret Key"></div></div>
                <div id="gw-fields-invictus" class="gateway-body"><div class="form-group"><input type="text" id="invictus-token" placeholder="API Token"></div><div class="form-group"><input type="text" id="invictus-hash" placeholder="Hash da Oferta"></div></div>
                <div id="gw-fields-duttyfy" class="gateway-body"><div class="form-group"><input type="text" id="duttyfy-secret" placeholder="Client Secret"></div></div>
                <div id="gw-fields-bynet" class="gateway-body">
                    <div class="form-group">
                        <label style="font-weight:bold; color:#0ea5e9;">API Key (TechByNet)</label>
                        <input type="text" id="bynet-apikey" placeholder="Cole sua API Key aqui... (ex: fc2e9260-49b5-...)" class="form-control">
                        <small style="color:#666;">Utilize a API Key fornecida pela TechByNet.</small>
                    </div>
                </div>

                <div id="gw-fields-otimize" class="gateway-body">
                    <div class="form-group">
                        <label style="font-weight:bold; color:#10b981;">Chave Secreta Otimize Pagamentos</label>
                        <input type="text" id="otimize-secret" placeholder="Cole sua chave secreta aqui..." class="form-control">
                        <small style="color:#666;">A Otimize utiliza Basic Auth no formato "SECRET_KEY:x". Encontre suas chaves em Configurações → Credenciais de API.</small>
                    </div>
                </div>

                <div id="gw-fields-ironpay" class="gateway-body">
                    <div class="form-group">
                        <label style="font-weight:bold; color:#ef4444;">API Token (IronPay)</label>
                        <input type="text" id="ironpay-token" placeholder="Cole seu token de API IronPay aqui..." class="form-control">
                        <small style="color:#666;">Token de API da IronPay. Encontre em: Dashboard → Configurações → Credenciais de API.</small>
                    </div>
                </div>

                <div id="gw-fields-skalepay" class="gateway-body">
                    <div class="form-group">
                        <label style="font-weight:bold; color:#3b82f6;">Secret Key (SkalePay)</label>
                        <input type="text" id="skalepay-secret" placeholder="Cole sua Secret Key aqui..." class="form-control">
                        <small style="color:#666;">Secret Key da SkalePay. Autenticação via Basic Auth com SECRET_KEY:x.</small>
                    </div>
                </div>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
                    <h3 style="color: #dc2626;">Configuracao de Cartao de Credito</h3>
                    <div class="form-group" style="background: #fef2f2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca;">
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #991b1b;"><strong>Funcionalidade:</strong> Ao finalizar pagamento com cartao, exibe "Pagamento Recusado" e oferece desconto no PIX.</p>
                        <label style="font-weight:bold; color:#dc2626;">Desconto apos cartao recusado (%)</label>
                        <input type="number" id="card-decline-discount" value="15" min="1" max="50" class="form-control" style="width: 100px;">
                        <small style="color:#666;">Porcentagem de desconto oferecida no PIX apos cartao ser recusado (ex: 15 = 15% OFF).</small>
                    </div>
                </div>
            </div>

            <div id="details" class="tab-content">
                <h3>Informações da Loja</h3>
                <div class="form-group"><label>Nome da Loja</label><input type="text" id="store-name" value="Loja"></div>
                <div class="form-group"><label>URL da Logo</label><input type="text" id="store-logo-url" value=""></div>
                <div class="form-group"><label>Vendas da Loja</label><input type="text" id="store-sales" value="1698"></div>

                <h3 style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;">Configuracoes da Pagina Loja (loja.html)</h3>
                <div class="form-group" style="background: #fef3c7; padding: 12px; border-radius: 8px; border: 1px solid #fcd34d; margin-bottom: 15px;">
                    <p style="margin: 0; font-size: 13px; color: #92400e;"><strong>Nota:</strong> Os produtos que aparecem na loja.html sao os produtos de <strong>Recomendacoes</strong> adicionados na aba correspondente.</p>
                </div>

                <h4 style="margin-top: 20px; color: #374151;">Banners de Cupom</h4>
                <div class="form-group"><label>Titulo Cupom 1</label><input type="text" id="coupon-title" value="Cupom de frete gratis" placeholder="Ex: Cupom de frete gratis"></div>
                <div class="form-group"><label>Subtitulo Cupom 1</label><input type="text" id="coupon-sub" value="Sem gasto minimo" placeholder="Ex: Sem gasto minimo"></div>
                <div class="form-group"><label>Titulo Cupom 2 (Desconto)</label><input type="text" id="discount-title" value="Ate 85% OFF" placeholder="Ex: Ate 85% OFF"></div>
                <div class="form-group"><label>Subtitulo Cupom 2</label><input type="text" id="discount-sub" value="Em produtos selecionados" placeholder="Ex: Em produtos selecionados"></div>

                <h4 style="margin-top: 20px; color: #374151;">Categorias (Aba Categorias)</h4>
                <p style="font-size: 12px; color: #6b7280; margin-bottom: 10px;">Adicione categorias no formato JSON. Deixe vazio para gerar automaticamente baseado nos produtos.</p>
                <div class="form-group">
                    <label>Categorias (JSON)</label>
                    <textarea id="store-categories" rows="6" placeholder='[{"name": "Eletronicos", "image": "https://...", "count": 5}, {"name": "Roupas", "image": "https://...", "count": 3}]'></textarea>
                </div>
            </div>

            <div id="media" class="tab-content">
                <h3>Imagens e Descrição</h3>
                <div class="form-group"><label>URL Imagem Principal</label><input type="text" id="main-image"></div>
                <div class="form-group"><label>URLs Adicionais</label><textarea id="additional-images"></textarea></div>
                <div class="form-group"><label>URLs Imagens Descrição</label><textarea id="description-images"></textarea></div>
                <div class="form-group"><label>Descrição Longa</label><textarea id="product-description"></textarea></div>
            </div>

            <div id="variations" class="tab-content">
                <h3>Configuração de Variações</h3>
                <div class="form-group">
                    <label>Título Variação 1</label>
                    <input type="text" id="variation1-title" value="Cores">
                </div>
                <div id="color-options"></div>
                <button class="add-btn" onclick="addColor(); updateOutputDebounced();" style="background-color: #000; color: white;">
                    Adicionar Variação 1
                </button>

                <div class="form-group" style="margin-top: 25px;">
                    <label>Título Variação 2</label>
                    <input type="text" id="variation2-title" value="Tamanhos">
                </div>
                <div id="size-options"></div>
                <button class="add-btn" onclick="addSize(); updateOutputDebounced();" style="background-color: #000; color: white;">
                    Adicionar Variação 2
                </button>
            </div>

            <div id="reviews" class="tab-content">
                <h3>Avaliações</h3>

                <div class="form-group" style="background:#f8fafc; padding:14px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:16px;">
                    <label style="font-weight:700; display:block; margin-bottom:8px;">Modelos internos por categoria</label>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <select id="review-preset-category" style="flex:1; min-width:220px; padding:10px; border:1px solid #d1d5db; border-radius:6px;">
                            <option value="">Selecione a categoria</option>
                            <option value="eletronico">Eletrônico</option>
                            <option value="cozinha">Cozinha</option>
                            <option value="sapato">Sapato</option>
                            <option value="vestuario">Vestuário</option>
                            <option value="beleza">Beleza</option>
                            <option value="utilidades">Utilidades</option>
                            <option value="infantil">Infantil</option>
                        </select>

                        <button type="button" class="add-btn" onclick="loadInternalReviewModels()" style="background:#111; color:#fff;">
                            Preencher modelos
                        </button>

                        <button type="button" class="add-btn" onclick="clearInternalReviewModels()" style="background:#6b7280; color:#fff;">
                            Limpar modelos
                        </button>
                    </div>

                    <small style="display:block; margin-top:8px; color:#6b7280;">
                        Os botões abaixo preenchem os cards com modelos internos editáveis. Revise e adapte antes de usar.
                    </small>
                </div>

                <div class="form-group" style="margin-bottom:16px;">
                    <label style="font-weight:700; display:block; margin-bottom:8px;">Modelos carregados</label>
                    <textarea id="review-drafts" rows="10" placeholder="Os modelos internos da categoria vão aparecer aqui..." style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:6px; resize:vertical;"></textarea>
                </div>

                <div id="review-items"></div>
                <button class="add-btn" onclick="addReview(); setupLiveUpdateListeners(el('review-items').lastElementChild); updateOutputDebounced();" style="background-color: #000; color: white;">Adicionar Avaliação</button>
            </div>

            <div id="recommendations" class="tab-content">
                <h3>Configuração de Recomendações</h3>

                <div class="form-group" style="background: #fffbeb; padding: 15px; border-radius: 8px; border: 1px solid #fcd34d; margin-bottom: 20px;">
                    <h4 style="margin-top:0; color: #b45309;">Adicionar Tamanhos em Massa</h4>
                    <p style="font-size: 12px; color: #b45309; margin-bottom: 10px;">Isso irá apagar os tamanhos atuais das recomendações abaixo e adicionar os novos.</p>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <label style="font-weight: bold; font-size: 12px;">Título da Variação</label>
                            <input type="text" id="bulk-rec-title" value="Tamanho" placeholder="Ex: Tamanho" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        </div>
                        <div style="flex: 3;">
                            <label style="font-weight: bold; font-size: 12px;">Opções (separadas por vírgula)</label>
                            <input type="text" id="bulk-rec-values" value="PP, P, M, G, GG, XG, XGG, XGGG" placeholder="Ex: 34, 36, 38, 40..." style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                        </div>
                    </div>
                    <button class="add-btn" onclick="applyBulkRecSizes()" style="background-color: #b45309; color: white; width: 100%;">Adicionar em todas as variantes</button>
                </div>

                <div class="form-group" style="background: #fdf4ff; padding: 15px; border-radius: 8px; border: 1px solid #e879f9; margin-bottom: 20px;">
                    <h4 style="margin-top:0; color: #a21caf;">TikTok Shop - Adicionar Produto (HTML)</h4>
                    <p style="font-size: 12px; color: #86198f; margin-bottom: 10px;">Cole o HTML de <strong>UM produto</strong> do TikTok Shop e clique em extrair. Repita para cada produto que deseja adicionar como recomendacao.</p>
                    <textarea id="tiktok-rec-html-input" rows="4" placeholder="Cole aqui o HTML copiado de UM produto do TikTok Shop..." style="width: 100%; padding: 10px; border: 1px solid #e879f9; border-radius: 6px; margin-bottom: 10px; font-size: 12px;"></textarea>
                    <button class="add-btn" onclick="extractRecommendationsFromTikTok()" style="background-color: #a21caf; color: white; width: 100%;">Extrair e Adicionar Produto</button>
                    <p id="tiktok-rec-html-status" style="margin-top: 8px; font-size: 12px; color: #6b7280;"></p>
                </div>

                <div class="form-group"><label>Desconto Padrão (%)</label><input type="number" id="recommendation-discount" value="40"></div>
                <div class="fetch-box">
                    <div class="fetch-box-controls">
                        <input type="text" id="rec-product-url-input" placeholder="Link do produto...">
                        <button class="add-btn" onclick="fetchProductForRecommendation()" style="background-color: #000; color: white;">Puxar</button>
                    </div>
                    <div id="rec-loading-status" class="fetch-status"></div>
                </div>
                <div id="recommendation-items"></div>
            </div>

            <div id="footer-info" class="tab-content">
                <h3>Informações do Rodapé</h3>
                <div class="form-group" style="background: #f0f9ff; padding: 15px; border-radius: 8px; border: 1px solid #bae6fd; margin-bottom: 20px;">
                    <label style="display:flex; align-items:center; gap: 10px; cursor:pointer; font-size: 16px; color: #0284c7; font-weight: bold;">
                        <input type="checkbox" id="enable-footer" checked style="width: 20px; height: 20px;">
                        EXIBIR RODAPÉ NO SITE
                    </label>
                </div>
                <div class="form-group"><label>URL Política Privacidade</label><input type="text" id="footer-link-privacy"></div>
                <div class="form-group"><label>URL Trocas</label><input type="text" id="footer-link-exchanges"></div>
                <div class="form-group"><label>URL Envio</label><input type="text" id="footer-link-shipping"></div>
                <div class="form-group"><label>URL Termos</label><input type="text" id="footer-link-terms"></div>
                <div class="form-group"><label>Email</label><input type="text" id="footer-email"></div>
                <div class="form-group"><label>WhatsApp</label><input type="text" id="footer-whatsapp"></div>
                <div class="form-group"><label>Endereço</label><input type="text" id="footer-address"></div>
                <div class="form-group"><label>CNPJ</label><input type="text" id="footer-cnpj"></div>
                <div class="form-group"><label>Razão Social</label><input type="text" id="footer-company-name"></div>
                <div class="form-group"><label>Texto Segurança</label><input type="text" id="footer-security-text"></div>
                <div class="form-group"><label>URL Selos</label><input type="text" id="footer-security-seals-img"></div>
                <div class="form-group"><label>Ano</label><input type="text" id="footer-copyright-year"></div>
                <div class="form-group"><label>Texto Copyright</label><input type="text" id="footer-copyright-text"></div>
            </div>
        </div>

        <div class="preview-section">
            <h2>Prévia do Site</h2>
            <div class="preview-controls">
                <button class="add-btn" style="background-color: #fe2c55; width: 100%; color: white; font-size: 16px; font-weight: 700;" onclick="downloadCompleteSite()">BAIXAR SITE COMPLETO (.ZIP)</button>
            </div>
            <div class="preview-page-tabs" id="preview-page-tabs">
                <button type="button" class="preview-page-tab active" data-page="produto" onclick="showPreviewPage('produto')">Produto</button>
                <button type="button" class="preview-page-tab" data-page="loja" onclick="showPreviewPage('loja')">Loja</button>
                <button type="button" class="preview-page-tab" data-page="carrinho" onclick="showPreviewPage('carrinho')">Carrinho</button>
                <button type="button" class="preview-page-tab" data-page="checkout" onclick="showPreviewPage('checkout')">Checkout</button>
                <button type="button" class="preview-page-tab" data-page="chat" onclick="showPreviewPage('chat')">Chat</button>
            </div>
            <div id="preview-container" class="view-container active">
                <iframe id="preview-iframe" srcdoc="<p>Edite algo para ver a prévia ao vivo.</p>"></iframe>
            </div>
        </div>
    </div>

    <script src="parser.js?v=<?php echo @filemtime('parser.js'); ?>"></script>
    <script src="script.js?v=<?php echo @filemtime('script.js'); ?>"></script>
    <script>
        function updateGatewayFields() {
            const selector = document.getElementById('gateway-selector');
            const selected = selector.value;
            document.querySelectorAll('.gateway-body').forEach(el => el.classList.remove('active'));
            const target = document.getElementById('gw-fields-' + selected);
            if (target) target.classList.add('active');
        }

        function toggleUpsellFields() {
            const isChecked = document.getElementById('upsell-enable').checked;
            document.getElementById('upsell-fields-container').style.display = isChecked ? 'block' : 'none';
        }

        function toggleUpsellMode() {
            const mode = document.getElementById('upsell-mode').value;
            document.getElementById('custom-url-group').style.display = (mode === 'custom') ? 'block' : 'none';
            document.getElementById('auto-msg').style.display = (mode === 'auto') ? 'block' : 'none';
        }

        function applyBulkRecSizes() {
            const title = document.getElementById('bulk-rec-title').value || "Tamanho";
            const valuesStr = document.getElementById('bulk-rec-values').value;

            if (!valuesStr || valuesStr.trim() === "") {
                alert("Por favor, digite os tamanhos separados por vírgula.");
                return;
            }

            const values = valuesStr.split(',').map(s => s.trim()).filter(Boolean);
            const recItems = document.querySelectorAll('#recommendation-items .item-editor');

            if (recItems.length === 0) {
                alert("Adicione produtos na lista de recomendações antes de aplicar.");
                return;
            }

            if (!confirm(`Isso irá alterar os tamanhos de ${recItems.length} recomendações. Deseja continuar?`)) return;

            recItems.forEach(item => {
                const titleInput = item.querySelector('.rec-variation2-title');
                if (titleInput) titleInput.value = title;

                const sizeContainer = item.querySelector('.rec-size-options');
                if (sizeContainer) {
                    sizeContainer.innerHTML = '';
                    values.forEach(val => {
                        const optionDiv = document.createElement("div");
                        optionDiv.className = "option-item";
                        optionDiv.style.cssText = "display: flex; gap: 8px; margin-bottom: 8px; align-items: center;";
                        optionDiv.innerHTML = `
                            <input type="text" class="rec-size-name" placeholder="Tamanho" value="${val}" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <button class="remove-btn" onclick="this.parentElement.remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">X</button>
                        `;
                        sizeContainer.appendChild(optionDiv);
                    });
                }
            });

            if (typeof setupLiveUpdateListeners === 'function') setupLiveUpdateListeners(document.getElementById('recommendation-items'));
            if (typeof updateOutputDebounced === 'function') updateOutputDebounced();
            alert("Tamanhos aplicados em todas as recomendações!");
        }

        window.reviewDraftPresets = window.reviewDraftPresets || {
            eletronico: [
                "Fiquei surpreso com a qualidade! A imagem é muito nítida, o som é limpo e a bateria aguenta bem o tranco do dia a dia. O desempenho não deixa a desejar em nada.",
                "A entrega foi super rápida e veio muito bem embalado, com plástico bolha e tudo. Minha primeira impressão foi excelente, dá para ver que é um produto premium.",
                "Muito fácil de configurar e usar, não tive nenhuma dificuldade. O produto é exatamente como no anúncio, sem surpresas negativas. Recomendo demais!",
                "Pelo preço que paguei, o custo-benefício é imbatível. O acabamento é muito bem feito, parece ser bem resistente e durável. Valeu cada centavo."
            ],
            cozinha: [
                "Mudou minha rotina! Muito prático para usar no dia a dia e, o melhor de tudo, é super fácil de limpar. Não fico mais sem aqui em casa.",
                "O material é de primeira linha. O acabamento é impecável e dá para sentir que é um produto bem resistente, que vai durar muitos anos na cozinha.",
                "O tamanho é ideal, nem muito grande nem muito pequeno. A capacidade atendeu perfeitamente às necessidades da minha família. Nota dez!",
                "Chegou antes do prazo e a embalagem estava bem reforçada, o que me deixou tranquilo. Veio tudo certinho, sem nenhum arranhão."
            ],
            sapato: [
                "Extremamente confortável! O acabamento interno é macio e ele se ajusta perfeitamente ao pé, não aperta em lugar nenhum. Dá para usar o dia todo.",
                "Pedi o meu número de costume e serviu como uma luva. O tamanho corresponde exatamente à tabela, o que facilita muito na hora da compra.",
                "O material é excelente e a costura é muito bem feita. Ao vivo ele é ainda mais bonito que nas fotos, fiquei impressionado com a qualidade do design.",
                "O prazo de entrega foi respeitado e chegou super rápido. O rastreio funcionou bem e o produto veio bem protegido na caixa original."
            ],
            vestuario: [
                "O tecido é uma delícia, muito macio e com um caimento perfeito no corpo. Dá para ver que é uma peça de qualidade, com boa durabilidade.",
                "Fiquei com medo de ser diferente, mas a peça é idêntica às fotos do anúncio. A cor é vibrante e o modelo valoriza muito o visual.",
                "Acabamento impecável, sem fios soltos ou defeitos. Além de linda, a roupa é muito confortável para usar em qualquer ocasião.",
                "O tamanho ficou ótimo seguindo as medidas do site. A entrega foi bem ágil e chegou antes do que eu esperava. Com certeza comprarei mais vezes."
            ],
            beleza: [
                "A textura é maravilhosa e o rendimento é ótimo. Tem uma fragrância suave muito gostosa e a sensação na pele após o uso é de hidratação total.",
                "A embalagem é linda e muito prática, dá até gosto de deixar exposta na penteadeira. A apresentação do produto como um todo é de muito bom gosto.",
                "Muito simples de aplicar, espalha super bem e não deixa resíduos pegajosos. Em poucos minutos você já sente a diferença na aplicação.",
                "Estou usando há alguns dias e o resultado é visível. Minha pele/cabelo está com outra vida, muito mais saudável e com um brilho natural incrível."
            ],
            utilidades: [
                "É aquele tipo de item que a gente não sabe como viveu sem antes. Traz muita praticidade e realmente facilita as tarefas chatas do dia a dia.",
                "Achei o material bem robusto. O acabamento é simples mas muito funcional, e a resistência dele me surpreendeu positivamente.",
                "Cumpre exatamente o que promete no anúncio. É um produto honesto, eficiente e que resolve o problema de forma rápida.",
                "Tudo certo com a entrega. Chegou bem rápido e a embalagem garantiu que o produto chegasse intacto, sem nenhuma avaria."
            ],
            infantil: [
                "O material é muito seguro e o toque é bem suave, ideal para crianças. O conforto parece ser prioridade aqui, meu filho adorou usar.",
                "Dá para perceber o cuidado no acabamento, tudo muito bem reforçado e com cores vivas. A qualidade é superior a muitos que já vi por aí.",
                "O tamanho ficou perfeito e a criança se adaptou super rápido ao uso. É funcional e ao mesmo tempo diverte, o que é ótimo.",
                "Chegou dentro do prazo e a apresentação do pacote foi um diferencial, veio tudo muito caprichado e pronto para presente se fosse o caso."
            ]
        };

        function loadInternalReviewModels() {
            const select = document.getElementById('review-preset-category');
            const textarea = document.getElementById('review-drafts');
            const container = document.getElementById('review-items');

            if (!select || !textarea || !container || typeof addReview !== 'function') return;

            const category = select.value;
            const items = reviewDraftPresets[category];

            if (!category || !items) {
                textarea.value = '';
                return;
            }

            textarea.value = items.map((item, index) => `${index + 1}. ${item}`).join('\n');
            container.innerHTML = '';

            items.forEach((item, index) => {
                addReview({
                    name: `Modelo interno ${index + 1}`,
                    avatarUrl: '',
                    details: 'Rascunho para adaptação',
                    stars: '',
                    text: item,
                    image: ''
                });
            });

            if (typeof setupLiveUpdateListeners === 'function') setupLiveUpdateListeners(container);
            if (typeof updateOutputDebounced === 'function') updateOutputDebounced();
        }

        function clearInternalReviewModels() {
            const textarea = document.getElementById('review-drafts');
            const select = document.getElementById('review-preset-category');
            const container = document.getElementById('review-items');

            if (textarea) textarea.value = '';
            if (select) select.value = '';
            if (container) container.innerHTML = '';
            if (typeof updateOutputDebounced === 'function') updateOutputDebounced();
        }

        function loadReviewDrafts() {
            loadInternalReviewModels();
        }

        function clearReviewDrafts() {
            clearInternalReviewModels();
        }

        document.addEventListener('DOMContentLoaded', () => updateGatewayFields());
    </script>

    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['email'])) {
        $arquivo_user_check = 'usuarios.json';

        if (file_exists($arquivo_user_check)) {
            $todos_users = json_decode(file_get_contents($arquivo_user_check), true);
            $meu_email = $_SESSION['email'];

            if (
                isset($todos_users[$meu_email]) &&
                isset($todos_users[$meu_email]['utm_cloaker_ativo']) &&
                $todos_users[$meu_email]['utm_cloaker_ativo'] === true
            ) {
                $link_destino = "/COMMANDER/";
                ?>
                <style>
                    .btn-float-utm {
                        position: fixed;
                        bottom: 30px;
                        right: 30px;
                        background: linear-gradient(45deg, #121212, #1a1a1a);
                        color: #25f4ee;
                        padding: 15px 25px;
                        border-radius: 50px;
                        text-decoration: none;
                        font-family: 'Montserrat', sans-serif;
                        font-weight: 800;
                        font-size: 13px;
                        letter-spacing: 0.5px;
                        box-shadow: 0 8px 25px rgba(37, 244, 238, 0.25);
                        border: 2px solid #25f4ee;
                        z-index: 9999;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        transition: all 0.3s ease;
                        animation: pulse-border 3s infinite;
                    }

                    .btn-float-utm:hover {
                        transform: scale(1.05) translateY(-3px);
                        color: #121212;
                        background: #25f4ee;
                        box-shadow: 0 10px 30px rgba(37, 244, 238, 0.5);
                        border-color: #25f4ee;
                    }

                    .btn-float-utm span {
                        font-size: 18px;
                    }

                    @keyframes pulse-border {
                        0% { box-shadow: 0 0 0 0 rgba(37, 244, 238, 0.6); }
                        70% { box-shadow: 0 0 0 15px rgba(37, 244, 238, 0); }
                        100% { box-shadow: 0 0 0 0 rgba(37, 244, 238, 0); }
                    }
                </style>

                <a href="<?php echo $link_destino; ?>" target="_blank" class="btn-float-utm">
                    <span>🔗</span> IR PARA UTM E CLOAKER
                </a>
                <?php
            }
        }
    }
    ?>
</body>
</html>
