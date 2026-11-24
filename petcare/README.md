# PetCare — API REST em PHP

Trabalho 1 — API REST em PHP | Prof. Jr | ADS

## Integrantes

- Rafael Inglês Vieira

## Sobre

API para cadastro e consulta de tutores e pets de uma clínica veterinária. Consultas são públicas; alterações exigem autenticação Bearer. Desenvolvida em PHP puro, MySQL e PDO, sem frameworks.

## Como executar (XAMPP)

1. Instale o XAMPP com PHP 7.4 ou superior, extensão pdo_mysql e Apache com mod_rewrite e AllowOverride All habilitados.
2. Coloque a pasta `petcare` diretamente em `htdocs` (no macOS, normalmente `/Applications/XAMPP/xamppfiles/htdocs/petcare`; no Windows, `C:\xampp\htdocs\petcare`). Se clonar um repositório que contém a pasta petcare, mantenha apenas um nível de pasta no htdocs.
3. Inicie Apache e MySQL pelo painel do XAMPP.
4. Abra `http://localhost/phpmyadmin/`, selecione Importar e execute `banco.sql`. Atenção: o script apaga e recria o banco petcare, incluindo os dados anteriores.
5. A conexão padrão está em `funcoes.php`: localhost, porta 3306, usuário root e senha vazia. Ajuste se necessário.
6. Acesse `http://localhost/petcare/tutores` para conferir os dados iniciais.
7. Abra `requisicoes.http` no VS Code com a extensão REST Client (Humao). Execute as requisições de cima para baixo usando Send Request. O login e as criações nomeadas fornecem automaticamente os valores de token e IDs para as requisições seguintes. Os comentários indicam os status esperados. Reimporte o SQL se precisar reiniciar os testes do zero.

## Usuário de teste

- E-mail: `professor@petcare.com`
- Senha: `PetCare123!`

A senha foi gerada com `password_hash()` e somente seu hash está no SQL. Esta conta é exclusiva para demonstração local.

## Rotas

| Método | Rota | Token | Sucesso |
|---|---|---|---|
| POST | /login | Não | 200 |
| POST | /logout | Sim | 204 |
| GET | /tutores | Não | 200 |
| GET | /tutores/{id} | Não | 200 |
| GET | /tutores/{id}/pets | Não | 200 |
| POST | /tutores | Sim | 201 |
| PUT | /tutores/{id} | Sim | 200 |
| DELETE | /tutores/{id} | Sim | 204 |
| GET | /pets?especie=gato | Não | 200 |
| GET | /pets/{id} | Não | 200 |
| POST | /pets | Sim | 201 |
| PUT | /pets/{id} | Sim | 200 |
| DELETE | /pets/{id} | Sim | 204 |

O filtro especie é opcional. PUT recebe todos os campos do cadastro; raça pode ser nula. As listas retornam total e a coleção correspondente. Erros usam `detail`, contendo mensagem ou lista de erros de validação. Respostas 204 não têm corpo.

## Segurança

O login verifica a senha com password_verify e cria um token aleatório de 64 caracteres com bin2hex(random_bytes(32)). O token dura duas horas e deve ser enviado no cabeçalho Authorization: Bearer. A função exigirToken verifica existência e validade antes das operações protegidas. Logout remove o token. Todas as consultas usam PDO com prepared statements. A API foi pensada para execução acadêmica local; em produção seria necessário HTTPS e credenciais próprias.

Para testar a expiração sem esperar duas horas, faça um novo login e, no phpMyAdmin, execute `UPDATE tokens SET expira_em = DATE_SUB(NOW(), INTERVAL 1 SECOND);` no banco petcare. Uma operação protegida com esse token deve retornar 401. Faça novo login para continuar.

## Regra de exclusão de tutores

Escolhemos ON DELETE RESTRICT: um tutor com pets cadastrados não pode ser excluído (HTTP 409). Isso evita a perda acidental do cadastro dos animais. Para excluir o tutor, primeiro é necessário transferir os pets para outro tutor ou removê-los explicitamente. A chave estrangeira garante essa regra no próprio banco.

## Arquivos

- `index.php`: roteador manual e operações da API.
- `funcoes.php`: conexão PDO, respostas, autenticação e validações.
- `.htaccess`: redirecionamento e repasse do cabeçalho Authorization.
- `banco.sql`: estrutura e dados iniciais (5 tutores, 10 pets e 1 usuário).
- `requisicoes.http`: testes de sucesso e erro.
- `README.md`: documentação.
