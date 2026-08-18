# 🗄️ Implementação MySQL - LabWare

## 📋 Resumo da Migração

O projeto foi migrado de **JSON** para **MySQL** com phpMyAdmin. Todos os dados de vagas e acessos agora são gerenciados através de um banco de dados relacional.

---

## 🚀 Passo a Passo para Implementar

### 1️⃣ **Importar o banco de dados**

1. Acesse o **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Clique em **Importar** no menu superior
3. Selecione o arquivo: `data/recrutamento (1).sql`
4. Clique em **Executar**

A estrutura completa do banco será criada com as tabelas:
- `vaga` - Vagas abertas
- `candidato` - Candidatos
- `candidatura` - Relacionamento entre candidatos e vagas
- `empresa` - Empresa (LabWare)
- `rh` - Recursos Humanos
- `entrevista` - Entrevistas agendadas

---

### 2️⃣ **Configurar a Conexão**

O arquivo `config.php` contém as credenciais do banco:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'recrutamento');
```

**Se você usar diferentes credenciais, edite este arquivo.**

---

## 📁 Arquivos Criados

### **config.php**
- Configuração de conexão com MySQL
- Charset UTF-8 para suporte a caracteres especiais

### **db_functions.php**
- Funções auxiliares para gerenciar vagas:
  - `getAllVagas()` - Obter todas as vagas abertas
  - `getVagaById()` - Obter vaga por ID
  - `createVaga()` - Criar nova vaga
  - `updateVaga()` - Atualizar vaga existente
  - `deleteVaga()` - Deletar vaga
  - `registerAccess()` - Registrar acesso à vaga
  - `getAccessLog()` - Obter histórico de acessos
  - `registerCandidatura()` - Registrar candidatura

### **api_vagas.php**
- API REST que retorna as vagas em JSON
- Usado pelo `script.js` para carregar as vagas dinamicamente
- Endpoint: `http://localhost/tcc/api_vagas.php`

---

## 📝 Arquivos Modificados

### **admin.php**
- ✅ Substituído JSON por MySQL
- ✅ Funções de CRUD de vagas agora usam banco de dados
- ✅ Histórico de acessos salvo no banco

### **job.php**
- ✅ Carrega vagas do banco de dados
- ✅ Registra acessos no banco em vez de JSON

### **script.js**
- ✅ Atualizado para chamar `api_vagas.php` em vez de `data/jobs.json`

---

## 🔄 Fluxo de Dados

```
┌─────────────────────────────────┐
│   Frontend (index.php)           │
│   - Carrega vagas via AJAX      │
│   - Exibe formulário de candidatura
└────────────┬────────────────────┘
             │
             ├─→ api_vagas.php (GET vagas)
             │
             └─→ job.php (Detalhes da vaga)
                 - Registra acesso no banco
                 
┌─────────────────────────────────┐
│   Admin (admin.php)              │
│   - CRUD de vagas                │
│   - Visualiza histórico de acessos
│   - Gerencia candidaturas        │
└────────────┬────────────────────┘
             │
             └─→ MySQL (recrutamento)
                 - Tabelas: vaga, candidato, candidatura, acesso
```

---

## 🎯 Funcionalidades Implementadas

### **Gerenciar Vagas**
- ✅ Criar nova vaga
- ✅ Editar vaga existente
- ✅ Deletar vaga
- ✅ Buscar vagas por título ou descrição
- ✅ Listar todas as vagas

### **Registrar Candidaturas**
- ✅ Adicionar candidato
- ✅ Vincular candidato à vaga
- ✅ Status da candidatura

### **Monitorar Acessos**
- ✅ Registrar quando um candidato acessa uma vaga
- ✅ Histórico de acessos com data/hora
- ✅ Últimos 20 acessos no painel admin

---

## 🔐 Segurança

- ✅ Escape de strings com `real_escape_string()`
- ✅ Validação de tipos com `FILTER_VALIDATE_INT`
- ✅ Preparação para Prepared Statements (próxima melhoria)
- ✅ Sessão segura para admin

---

## 📊 Estrutura das Tabelas

### **vaga**
```
id_vaga (INT, PK, AUTO_INCREMENT)
titulo (VARCHAR 150)
descricao (TEXT)
status (VARCHAR 50) - 'Aberta' ou 'Fechada'
id_empresa (INT, FK)
id_rh_responsavel (INT, FK)
```

### **candidato**
```
id_candidato (INT, PK, AUTO_INCREMENT)
nome (VARCHAR 100)
email (VARCHAR 150)
```

### **candidatura**
```
id_candidatura (INT, PK, AUTO_INCREMENT)
id_candidato (INT, FK)
id_vaga (INT, FK)
status (VARCHAR 50) - 'Pendente', 'Em análise', 'Aprovado', 'Rejeitado'
```

### **acesso** (criada automaticamente)
```
id_acesso (INT, PK, AUTO_INCREMENT)
id_vaga (INT, FK)
titulo_vaga (VARCHAR 150)
data_acesso (TIMESTAMP)
```

---

## 🧪 Testando a Implementação

1. Acesse `http://localhost/tcc/index.php`
2. Verifique se as vagas aparecem na seção "Vagas Abertas"
3. Clique em uma vaga para ver os detalhes
4. Acesse `http://localhost/tcc/admin.php`
5. Faça login: `admin@labware.com` / `senha123`
6. Gerenciar vagas e visualizar histórico de acessos

---

## 📱 Endpoints da API

### **GET /api_vagas.php**
Retorna todas as vagas em formato JSON:

```json
[
  {
    "id": 1,
    "title": "Desenvolvedor Full Stack",
    "type": "Desenvolvedor • Remoto",
    "location": "Remoto",
    "description": "Desenvolvimento de sistemas..."
  }
]
```

---

## 🚀 Próximas Melhorias Sugeridas

- [ ] Implementar Prepared Statements para maior segurança
- [ ] Adicionar validação de email
- [ ] Upload de currículo para candidatos
- [ ] Painel de gerencimento de candidaturas
- [ ] Notificações por email
- [ ] Relatórios de candidaturas
- [ ] Autenticação com múltiplos usuários RH
- [ ] API REST completa com autenticação JWT

---

## ✅ Verificação Final

Após importar o banco e testar, você terá:
- ✅ Banco de dados MySQL funcional
- ✅ Sistema de vagas conectado ao banco
- ✅ Histórico de acessos registrado
- ✅ Painel administrativo operacional
- ✅ Candidaturas armazenadas no banco
