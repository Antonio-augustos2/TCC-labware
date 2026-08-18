# 🎯 Guia Rápido - Migração para MySQL

## ⚡ 5 Passos para Ativar o Banco de Dados

### **Passo 1: Certifique-se que XAMPP está rodando**
- Apache ✅
- MySQL ✅
- phpMyAdmin acessível em `http://localhost/phpmyadmin`

### **Passo 2: Importar o Banco**
1. Abra phpMyAdmin
2. Clique em **"Importar"**
3. Selecione: `c:\xampp\htdocs\tcc\data\recrutamento (1).sql`
4. Clique em **"Executar"**

**✅ Banco criado!**

### **Passo 3: Verificar Conexão (Opcional)**
Acesse: `http://localhost/tcc/admin.php`

Se aparecer a página de login, a conexão está funcionando.

### **Passo 4: Testar as Vagas**
1. Acesse: `http://localhost/tcc/index.php`
2. Role até **"Vagas Abertas"**
3. Verifique se aparecem as vagas do banco (devem estar em HTML, não como código)

### **Passo 5: Testar Admin**
1. Acesse: `http://localhost/tcc/admin.php`
2. Faça login:
   - **Email**: `admin@labware.com`
   - **Senha**: `senha123`
3. Você deve ver o painel administrativo com as vagas

---

## 📁 Arquivos Importantes

| Arquivo | Função |
|---------|--------|
| `config.php` | Configuração de conexão MySQL |
| `db_functions.php` | Funções para CRUD de vagas |
| `api_vagas.php` | API que retorna vagas em JSON |
| `api_candidatura.php` | API que registra candidaturas |
| `admin.php` | Painel administrativo |
| `job.php` | Página de detalhes da vaga |
| `index.php` | Página inicial |
| `script.js` | JavaScript atualizado para usar APIs |

---

## 🔧 Se Algo Não Funcionar

### **Erro de Conexão ao Banco?**
Edite `config.php` e verifique:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Seu usuário MySQL
define('DB_PASS', '');               // Sua senha MySQL
define('DB_NAME', 'recrutamento');   // Nome do banco
```

### **Vagas Não Aparecem?**
1. Verifique se o banco foi importado corretamente
2. No phpMyAdmin, vá para `recrutamento` → `vaga`
3. Verifique se há registros na tabela
4. Recarregue a página (Ctrl+F5)

### **Admin Não Funciona?**
1. Limpe cookies do navegador
2. Acesse em modo incógnito
3. Verifique credenciais: `admin@labware.com` / `senha123`

---

## 📊 Estrutura do Banco

O banco `recrutamento` tem estas tabelas principais:

```
recrutamento/
├── vaga (Vagas abertas)
├── candidato (Candidatos)
├── candidatura (Candidaturas enviadas)
├── empresa (LabWare)
├── rh (Recursos Humanos)
├── entrevista (Entrevistas agendadas)
└── acesso (Histórico de acessos)
```

---

## ✅ Checklist de Ativação

- [ ] XAMPP rodando (Apache + MySQL)
- [ ] Banco `recrutamento` importado
- [ ] `http://localhost/tcc/index.php` mostra as vagas
- [ ] Login admin funciona
- [ ] Formulário de candidatura envia dados
- [ ] Histórico de acessos aparece no admin

---

## 🚀 Próximos Passos

Depois de verificar que tudo funciona:
1. Expandir tabela `vaga` com mais campos (salário, experiência, etc.)
2. Adicionar upload de currículo
3. Implementar notificações por email
4. Criar dashboard de candidaturas

---

## 📞 Suporte

Todos os erros aparecem no console do navegador (F12).
Verifique também os logs do PHP em `c:\xampp\logs\`
