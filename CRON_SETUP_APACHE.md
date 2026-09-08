# ⏰ Configurar CRON - Passo a Passo Completo

## 🖥️ Ambiente: Windows XAMPP (Desenvolvimento)

Se está usando XAMPP localmente, você precisa usar **Task Scheduler** do Windows.

### Passo 1: Definir Token em config.php

1. Abra `config.php`
2. Procure pelas constantes de banco de dados
3. **Adicione no final do arquivo** (antes do `?>` se tiver):

```php
// Token de segurança para CRON - altere para algo seguro!
define('CRON_TOKEN', 'seu_token_muito_secreto_123456789');
```

**Exemplo completo:**
```php
<?php
// Configurações do banco
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'recrutamento');

// ... outras configurações ...

// Token CRON (NOVO)
define('CRON_TOKEN', 'seu_token_muito_secreto_123456789');
?>
```

---

### Passo 2: Configurar Task Scheduler (Windows)

#### Via Interface Gráfica:

1. **Abra o Task Scheduler:**
   - Pressione `Win + R`
   - Digite: `taskschd.msc`
   - Pressione Enter

2. **Criar Nova Tarefa:**
   - Painel esquerdo: Clique em **"Task Scheduler Library"**
   - Painel direito: Clique em **"Create Basic Task..."**

3. **Preencha os Dados:**
   - **Name:** `CronAnalisarCandidatos`
   - **Description:** `Processa análise de currículos com Gemini a cada 5 minutos`
   - Clique **Next**

4. **Trigger (Agendamento):**
   - Selecione: **"Repeat a task"**
   - Clique **Next**
   - Defina: 
     - ⏱️ Frequência: `Daily`
     - 🕐 Hora de início: `00:00:00`
   - Clique **Next**

5. **Action (O que executar):**
   - Selecione: **"Start a program"**
   - Clique **Next**

6. **Configure Executável:**
   - No campo **"Program/script"**, coloque o caminho do `curl` ou `wget`:
   
   **Opção A - Usando curl (recomendado):**
   ```
   C:\xampp\php\curl.exe
   ```
   
   **Opção B - Usando wget:**
   ```
   C:\xampp\contrib\wget\wget.exe
   ```

7. **Configure Argumentos:**
   - No campo **"Add arguments (optional)"**, coloque:
   
   ```
   -s http://localhost/TCC-labware-main/cron_analisar.php?token=seu_token_muito_secreto_123456789 -o nul
   ```
   
   **OU se usar wget:**
   ```
   -O - http://localhost/TCC-labware-main/cron_analisar.php?token=seu_token_muito_secreto_123456789
   ```

8. **Configure Working Directory:**
   - No campo **"Start in (optional)"**, coloque:
   ```
   C:\xampp\htdocs\TCC-labware-main
   ```

9. **Clique Finish**

10. **Configurar Repetição a Cada 5 Minutos:**
    - Duplo-clique na tarefa criada
    - Abra a aba **"Triggers"**
    - Clique no gatilho e depois em **"Edit"**
    - Marque: ✓ **"Repeat task every:"** e coloque `5 minutes`
    - Clique **OK**

---

### Passo 3: Testar (Windows)

1. **Teste Manual no Navegador:**
   ```
   http://localhost/TCC-labware-main/cron_analisar.php?token=seu_token_muito_secreto_123456789
   ```

2. **Você deve ver JSON:**
   ```json
   {
     "success": true,
     "message": "Nenhuma candidatura pendente",
     "timestamp": "2026-09-05 14:30:45"
   }
   ```

3. **Execute a Tarefa Manualmente:**
   - No Task Scheduler, clique direito na tarefa
   - Selecione **"Run"**
   - Verifique o log dedicado do cron em:
   ```
   C:\xampp\htdocs\TCC-labware-main\data\cron_analisar.log
   ```
   - O arquivo registra início, autenticação, candidaturas processadas, erros e resumo final.

---

---

## 🐧 Ambiente: Linux com Apache (Produção)

### Passo 1: Definir Token em config.php

Idêntico ao Windows:

```php
define('CRON_TOKEN', 'seu_token_muito_secreto_123456789');
```

---

### Passo 2: Acessar via SSH

1. **Conecte ao servidor via SSH:**
   ```bash
   ssh usuario@seu-dominio.com
   ```

2. **Ou via putty/terminal:**
   ```bash
   ssh usuario@192.168.0.100
   ```

---

### Passo 3: Editar Crontab

1. **Abra o editor de cron:**
   ```bash
   crontab -e
   ```

2. **Escolha um editor** (se aparecer um prompt):
   - `1` para nano (mais fácil)
   - `2` para vim

3. **Vá para o final do arquivo** (com Ctrl+End ou End)

4. **Adicione esta linha:**

   ```bash
   */5 * * * * curl -s http://seu-dominio.com/cron_analisar.php?token=seu_token_muito_secreto_123456789 > /dev/null 2>&1
   ```

   **Ou se preferir wget:**
   ```bash
   */5 * * * * wget -O - http://seu-dominio.com/cron_analisar.php?token=seu_token_muito_secreto_123456789 > /dev/null 2>&1
   ```

5. **Salve e saia:**
   - Se for nano: `Ctrl + X` → `Y` → `Enter`
   - Se for vim: `Esc` → `:wq` → `Enter`

---

### Passo 4: Verificar se foi Adicionado

```bash
crontab -l
```

Você deve ver a linha que acabou de adicionar.

---

### Passo 5: Testar (Linux)

1. **Execute manualmente:**
   ```bash
   curl -s http://seu-dominio.com/cron_analisar.php?token=seu_token_muito_secreto_123456789
   ```

2. **Você deve receber JSON:**
   ```json
   {
     "success": true,
     "message": "Nenhuma candidatura pendente"
   }
   ```

3. **Verifique logs do cron:**
   ```bash
   sudo tail -f /var/log/syslog | grep CRON
   ```
   
   Ou:
   ```bash
   sudo journalctl -u cron -f
   ```

---

### Passo 6: Monitorar Execução (Linux)

**Ver quando o CRON foi executado:**
```bash
grep CRON /var/log/syslog | tail -20
```

**Ver logs de erro do seu CRON:**
```bash
tail -f ~/public_html/error_log
```

Procure por linhas com `[CRON]`

---

---

## ⏰ Entender a Sintaxe CRON

```
┌───────────┬─────────────────────────────────────┐
│ Campo     │ Significado                         │
├───────────┼─────────────────────────────────────┤
│ minute    │ 0-59                                │
│ hour      │ 0-23                                │
│ day       │ 1-31                                │
│ month     │ 1-12                                │
│ weekday   │ 0-6 (0=domingo)                     │
└───────────┴─────────────────────────────────────┘

Exemplos:
*/5 * * * *     ← A cada 5 minutos
0 * * * *       ← A cada hora (00 minutos)
0 */2 * * *     ← A cada 2 horas
0 0 * * *       ← Diariamente à meia-noite
0 2 * * *       ← Diariamente às 2 da manhã
0 0 * * 1       ← Toda segunda-feira à meia-noite
```

**Para sua necessidade (a cada 5 minutos):**
```
*/5 * * * *
```

---

---

## 🔐 Segurança: Proteger Token em Produção

### Problema: Token na URL é visível em logs

```bash
logs do apache mostram: cron_analisar.php?token=seu_token
```

### Solução 1: Usar uma senha HTTP (Basic Auth)

1. **Crie arquivo `.htaccess`** na raiz do projeto:
   ```apache
   <FilesMatch "^cron_analisar.php$">
       Require all denied
       Allow from 127.0.0.1
   </FilesMatch>
   ```

2. **Assim só localhost pode acessar!**

3. **Altere o CRON para executar localmente:**
   ```bash
   */5 * * * * localhost curl -s http://localhost/cron_analisar.php?token=token > /dev/null 2>&1
   ```

### Solução 2: Usar variável de ambiente

1. **No SSH, defina a variável:**
   ```bash
   export CRON_TOKEN="seu_token_secreto"
   crontab -e
   ```

2. **Use no CRON:**
   ```bash
   */5 * * * * curl -s "http://localhost/cron_analisar.php?token=${CRON_TOKEN}" > /dev/null 2>&1
   ```

### Solução 3: Remover token da URL (Recomendado)

Modifique `cron_analisar.php` para validar via IP ao invés de token:

```php
// Se vier de localhost, permite sem token
if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === 'localhost') {
    // Valida automaticamente
} else {
    // Requer token
}
```

---

---

## 📊 Verificação de Status

### No Banco de Dados

```sql
-- Candidaturas aguardando análise
SELECT COUNT(*) as pendentes 
FROM candidatura 
WHERE status = 'Pendente Análise';

-- Candidaturas analisadas com sucesso
SELECT COUNT(*) as analisadas 
FROM candidatura 
WHERE status = 'Análise Completa';

-- Últimas 5 com erro
SELECT id_candidatura, candidato_nome, error_message 
FROM candidatura 
WHERE status = 'Análise Erro' 
LIMIT 5;
```

### Via Logs do PHP

**Windows (XAMPP):**
```
C:\xampp\apache\logs\error.log
C:\xampp\php\logs\php_error_log
```

**Linux:**
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f ~/public_html/error_log
```

**Procure por `[CRON]`:**
```bash
grep "[CRON]" /var/log/apache2/error.log
```

---

---

## ✅ Checklist Final

### Windows XAMPP
- [ ] Token definido em `config.php`
- [ ] Task criada no Task Scheduler
- [ ] Gatilho configurado para 5 minutos
- [ ] Teste manual funciona (navegador)
- [ ] Task executa manualmente (direito > Run)
- [ ] Logs mostram execução com `[CRON]`

### Linux com cPanelProdução
- [ ] Token definido em `config.php`
- [ ] CRON adicionado via `crontab -e`
- [ ] `crontab -l` mostra a linha
- [ ] Teste manual com `curl` funciona
- [ ] `grep CRON /var/log/syslog` mostra execuções
- [ ] Banco de dados mostra candidaturas processadas

---

---

## 🆘 Troubleshooting

### Problema: CRON não executa (Windows)
**Solução:**
1. Task Scheduler > Histórico > Procure por erros
2. Verifique se curl.exe existe em `C:\xampp\php\`
3. Execute manualmente a tarefa

### Problema: HTTP 401 - Unauthorized
**Solução:**
1. Verifique se o token em `config.php` é idêntico ao da URL
2. Não há espaços extras?
3. Teste sem token temporariamente para debug

### Problema: "Nenhuma candidatura pendente" sempre
**Solução:**
1. Submeta uma candidatura via formulário
2. Verifique no banco: `SELECT * FROM candidatura LIMIT 1;`
3. Status é `'Pendente Análise'`?

### Problema: Sem logs do CRON (Linux)
**Solução:**
1. Verifique permissões: `chmod 755 cron_analisar.php`
2. Teste manualmente: `curl -v http://seu-dominio.com/...`
3. Verifique conexão à API Gemini
4. Aumente verbosidade no cron: `2>&1 >> /tmp/mycron.log`

---

**Data:** 2026-09-05  
**Versão:** 2.0 - Com Apache
