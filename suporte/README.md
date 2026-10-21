# Painel de Suporte - Gerenciador de Tarefas (multi-empresa)

Projeto **separado** do sistema principal (deploy independente - pode
ficar numa pasta/subdomínio diferente, até num servidor diferente).
Serve só pra equipe de suporte gerenciar quais empresas têm acesso ao
sistema principal.

Os dois projetos não compartilham nenhum arquivo entre si, mas
conectam nos **mesmos bancos de dados**: o banco mestre (lista de
empresas) e, na hora de cadastrar uma empresa nova, o banco molde e a
pasta onde ficam os bancos de cada empresa.


## 1. Requisitos

Os mesmos do projeto principal: PHP 8+, extensão `pdo_firebird`,
Firebird 3.0+.


## 2. Instalação

**Passo 1 — Banco mestre.** Se você ainda não criou, rode
`database/mestre_schema.sql` (está no projeto principal) num banco
novo, `MESTRE.FDB`. Esse é o mesmo banco que o login do sistema
principal consulta pra resolver o código da empresa.

**Passo 2 — Banco molde.** Crie um banco Firebird vazio e rode nele o
`database/schema.sql` do projeto principal (o mesmo script usado para
criar o banco de uma empresa comum) - sem cadastrar usuário nenhum
além do de exemplo que o script já cria. Esse arquivo (`MODELO.FDB`)
nunca é usado diretamente; ele só é **copiado** pra cada empresa nova
que você cadastrar por aqui.

**Passo 3 — Pasta dos bancos de empresa.** Crie uma pasta com
permissão de escrita para o usuário do PHP, onde os bancos das
empresas vão ser criados. É a mesma pasta que cada empresa, uma vez
cadastrada, vai usar no dia a dia.

⚠️ **Importante:** essa pasta (e o `MESTRE.FDB`/`MODELO.FDB` dos
passos 1 e 2) **não deve ficar dentro de `C:\xampp\htdocs`** — essa é
a pasta que o Apache serve na internet/rede, e um arquivo `.fdb` ali
seria, na pior hipótese, baixável direto pelo navegador. Coloque fora
dela, por exemplo:

```
C:\dados\MESTRE.FDB
C:\dados\MODELO.FDB
C:\dados\bancos_empresas\      ← esta é a pasta do Passo 3
```

O Firebird não se importa onde o arquivo está no disco (só precisa do
caminho completo); quem se importa é o Apache, que só enxerga o que
está dentro de `htdocs`.

**Passo 4 — Configuração.** Edite `config/conexao.php` e ajuste:
- `DB_PATH_MESTRE` → mesmo caminho do MESTRE.FDB usado no projeto principal
- `DB_PATH_MODELO` → caminho do banco molde do passo 2
- `PASTA_BANCOS_EMPRESAS` → pasta do passo 3
- `URL_SISTEMA_PRINCIPAL` → endereço do sistema principal (ex.:
  `http://localhost/gerenciador-tarefas/`). É usado pra montar o
  **link de acesso** de cada empresa (`.../?empresa=CODIGO`), que abre
  o login já com o código preenchido

**Passo 5 — Publicar.** Copie a pasta inteira pro seu servidor, como
uma pasta **irmã** do projeto principal (não uma dentro da outra). No
XAMPP, por exemplo, com o sistema principal em
`C:\xampp\htdocs\gerenciador-tarefas`:

```
C:\xampp\htdocs\
├── gerenciador-tarefas\      (sistema principal)
└── suporte-admin\             (este painel)
```

O acesso fica em `http://localhost/suporte-admin/` (o Apache já serve
o `index.html` automaticamente). Se um dia isso for pra um servidor
acessível pela internet, vale considerar restringir mais o acesso
(nome de pasta menos óbvio, regra de IP no Apache, ou só via VPN) - é
uma ferramenta administrativa, não precisa estar aberta pro mundo.


## 3. Acesso inicial

```
E-mail: suporte@sistema.com
Senha:  123456
```

Esse usuário já vem cadastrado pelo `mestre_schema.sql`. Troque a
senha assim que possível (o cadastro de novos usuários de suporte, por
enquanto, é feito direto no banco - não tem tela pra isso ainda).


## 4. Como funciona o cadastro de empresa

Ao clicar em "Cadastrar Empresa" e preencher nome da empresa, nome e
e-mail do responsável e uma senha inicial, o sistema:

1. Gera um código de 6 caracteres (sem `O`/`0`/`I`/`1`, pra evitar
   confusão visual), garantindo que não repete nenhum já existente.
2. Copia o banco molde (`MODELO.FDB`) pra um arquivo novo, nomeado com
   esse código, dentro da pasta configurada.
3. Cria o primeiro usuário (o responsável) já dentro desse banco novo.
4. Registra a empresa no banco mestre, vinculando o código ao caminho
   desse banco.

O código gerado aparece na tela pra você repassar ao cliente - é isso
que ele vai digitar no login do sistema principal, junto com o e-mail
e a senha que você cadastrou aqui.

**Inativar** uma empresa não apaga nada - só impede login: o
`api/login.php` do projeto principal só aceita códigos de empresas com
`ATIVA = 1`.
