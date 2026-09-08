# 📋 Configuração do CRON para Análise Assíncrona de Currículos

## 📝 Visão Geral

O sistema foi refatorado para processar candidaturas de forma **assíncrona**:

1. **Instantaneamente**: Usuário submete formulário → Candidatura é registrada → Resposta imediata
2. **Posteriormente (CRON)**: A cada 5-15 minutos, um job automático analisa os currículos com Gemini

---

## 🔧 Alterações Realizadas

### 1. **api_candidatura.php** (Modificado)
- ✅ Valida dados e arquivo
- ✅ Grava candidatura no banco com `status = 'Pendente Análise'`
- ✅ Salva arquivo em disco
- ✅ **Retorna resposta imediata** (sem esperar Gemini)
- ❌ Não chama mais Gemini API

### 2. **db_functions.php** (Novas Funções)
```php
ensureCandidaturaStatusColumn($conn)      // Garante coluna de status
getCandidaturasPendentes($conn, $limit)   // Busca candidaturas pendentes
updateCandidaturaStatus($conn, $id, $status) // Atualiza status
getCandidaturaComDetalhes($conn, $id)     // Busca dados completos
```

### 3. **gemini_service.php** (Nova Função)
```php
analizarComApi($conn, $caminhoArquivo, $descricaoVaga, $idCandidatura)
```
- Função executada pelo CRON
- Processa análise com Gemini
- Atualiza banco de dados com resultado
- Marca como 'Análise Completa' ou 'Análise Erro'

### 4. **cron_analisar.php** (Novo Arquivo)
- Script para ser executado pelos CRON jobs
- Busca candidaturas pendentes
- Processa até 10 por vez
- Retorna JSON com status de cada processamento
- Valida token de segurança

---

## 🔐 Configuração de Segurança

### Definir Token no config.php

Adicione ao seu `config.php`:

```php
// Token de segurança para CRON - mude isso!
define('CRON_TOKEN', 'seu_token_super_secreto_aqui_mude_em_producao');
```

**⚠️ IMPORTANTE**: Use um token bem aleatório, seguro e compatível com URL
(somente letras, números, `-` e `_`). Exemplo:
```
CRON_TOKEN = 'k7j-P2qL9m_zX5bN-wY3T-rQ8eF'
```

---

## ⏰ Configurar o CRON

### Opção 1: Via cPanel (Recomendado)

1. Acesse seu **cPanel**
2. Procure por **"Cron Jobs"** (ou **Agendador de Tarefas**)
3. Clique em **"Add New Cron Job"**
4. Preencha assim:

```
Common Settings: Every 5 minutes
Command: wget -O - http://seu-dominio.com/cron_analisar.php?token=seu_token_secreto > /dev/null 2>&1
```

Ou alternativamente com `curl`:
```
Command: curl -s http://seu-dominio.com/cron_analisar.php?token=seu_token_secreto > /dev/null 2>&1
```

**Salve** e teste.

---

### Opção 2: Via SSH (Terminal do Servidor)

1. Conecte via SSH ao seu servidor
2. Edite o crontab:
```bash
crontab -e
```

3. Adicione a linha (para executar a cada 5 minutos):
```bash
*/5 * * * * wget -O - http://seu-dominio.com/cron_analisar.php?token=seu_token_secreto > /dev/null 2>&1
```

Ou com `curl`:
```bash
*/5 * * * * curl -s http://seu-dominio.com/cron_analisar.php?token=seu_token_secreto > /dev/null 2>&1
```

4. Salve (geralmente Ctrl+X, depois Y, depois Enter)

---

### Expressão de Tempo CRON

Você pode alterar a frequência:

| Frequência | Expressão CRON |
|-----------|----------------|
| A cada minuto | `* * * * *` |
| A cada 5 minutos | `*/5 * * * *` |
| A cada 10 minutos | `*/10 * * * *` |
| A cada 15 minutos | `*/15 * * * *` |
| A cada hora | `0 * * * *` |
| A cada 2 horas | `0 */2 * * *` |
| Diariamente às 2 AM | `0 2 * * *` |

**Recomendação**: Use `*/5` (a cada 5 minutos) ou `*/10` para melhor balance entre responsividade e carga do servidor.

---

## 🧪 Testar a Configuração

### 1. Testar Manualmente (no navegador)

Acesse:
```
http://seu-dominio.com/cron_analisar.php?token=seu_token_secreto
```

Você deve receber um JSON como resposta:
```json
{
  "success": true,
  "message": "Processamento de análises concluído",
  "processadas": 2,
  "sucesso": 2,
  "erros": 0,
  "detalhes": [...],
  "timestamp": "2026-09-05 14:30:45"
}
```

### 2. Verificar Logs

Verifique os logs do PHP para ver o status:
```bash
tail -f /var/log/php-errors.log
# ou
tail -f /home/seu_usuario/public_html/error_log
```

Procure por linhas com `[CRON]` para ver o processamento.

---

## 📊 Estados das Candidaturas

| Status | Significado |
|--------|-----------|
| `Pendente Análise` | Aguardando processamento pelo CRON |
| `Análise Completa` | Análise concluída com sucesso |
| `Análise Erro` | Houve erro durante a análise |

**Visualizar no banco:**
```sql
SELECT id_candidatura, status, assertividade, feedback 
FROM candidatura 
WHERE status != 'Pendente Análise' 
ORDER BY id_candidatura DESC;
```

---

## 🔍 Monitorar Candidaturas Pendentes

**No banco de dados:**
```sql
SELECT COUNT(*) as pendentes_analise 
FROM candidatura 
WHERE status = 'Pendente Análise';
```

**Via PHP:**
```php
$pendentes = getCandidaturasPendentes($conn, 999); // Sem limite
echo count($pendentes) . " candidaturas aguardando análise\n";
```

---

## ⚠️ Troubleshooting

### Problema: CRON não está executando

**Soluções:**
1. Verifique se o token está correto no comando CRON
2. Teste manualmente a URL no navegador
3. Verifique se `cron_analisar.php` existe no diretório raiz
4. Veja os logs do cron: `grep CRON /var/log/syslog` (em Linux)

### Problema: "Token inválido"

**Solutção:**
- Certifique-se de que o token no `config.php` é **idêntico** ao token na URL do CRON
- Não há espaços extras ou caracteres diferentes

### Problema: "Arquivo não encontrado"

**Solução:**
- Verifique se o caminho relativo do arquivo está correto em `candidatura.arquivo_path`
- Garanta que o diretório `uploads/candidatos/` existe e tem permissões de leitura

### Problema: Gemini API falha regularmente

**Soluções:**
1. Verifique se `GEMINI_API_KEY` está configurada corretamente
2. Verifique se a quota de API do Gemini não foi excedida
3. Analise os logs em `data/api_errors.json`
4. Aumente o tempo entre execuções do CRON se houver muitas candidaturas

---

## 📈 Otimizações Futuras

1. **Fila de Prioridade**: Adicionar coluna `prioridade` em `candidatura`
2. **Rate Limiting**: Limitar requisições à API Gemini por intervalo
3. **Notificações**: Enviar email quando análise estiver completa
4. **Dashboard**: Criar página para monitorar status do CRON
5. **Retry Auto**: Tentar novamente se falhar (com backoff exponencial)

---

## 📚 Referências

- [Manual CRON do cPanel](https://docs.cpanel.net/cpanel/automation/cron-jobs/)
- [Expressões CRON](https://crontab.guru/)
- [API Gemini do Google](https://ai.google.dev/)

---

**Data**: 2026-09-05  
**Versão**: 1.0
