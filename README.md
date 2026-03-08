# Desafio Técnico

## Desenvolvimento de Plugin de Likes para WordPress

### Objetivo

Desenvolver um plugin para WordPress, que implemente um sistema de votação (Like/Dislike) em posts, com persistência de dados e listagem de ranking.

### Requisitos

#### Sistema de Votação

Implementar um mecanismo de Like e Dislike para posts:

- A interface de votação deve ser exibida automaticamente nos posts.
- A interação não pode provocar recarregamento de página.
- A pontuação deve refletir o estado atual do voto.
- A modelagem da pontuação e o fluxo de atualização ficam a critério do candidato, desde que consistentes.

#### Regra de Voto por Visitante

Cada visitante deve poder manter apenas um estado de voto por post.

O mecanismo de controle de voto é de responsabilidade do candidato (ex.: armazenamento local, estratégia de identificação, etc.).

A solução deve garantir coerência entre o estado do visitante e a pontuação persistida.

#### Persistência

A pontuação deve ser armazenada associada ao post.

A estratégia de armazenamento deve ser nativa do WordPress e adequada ao contexto.

#### Bloco (Gutenberg)

Implementar um bloco nativo para o editor de blocos:

- O bloco deve permitir inserir, em qualquer post, uma listagem de posts ordenados por pontuação.
- A ordenação deve refletir os dados persistidos pelo sistema de votação.
- A definição da estrutura visual e possíveis configurações do bloco fica a critério do candidato.

# ----------------

# SOLUÇÃO APLICADA 

## Criação de um plugin específico para o sistema de votação em cada post
### o plugin tem a funcionalidade de adicionar um sistema de votação que consiste em dois botões de Like e Dislike.
### Após ativado o script do plugin irá exibir dos botões de 

### INSTALAÇÃO: https://jam.dev/c/1e39d5ce-65e6-482c-9138-d9108ec7995e
### VALIDAÇÃO DE LIKES EM: https://jam.dev/c/585e9df1-5c0a-4e0f-b5f6-77edab82cf53
### VALIDAÇÃO DE RANKING EM: https://jam.dev/c/49b794b5-b8ee-4419-9964-ee7bdc2d3411

# INSTALACÃO

## Para instalar o plugin, necessário seguir os passos abaixo:
### - Para instalar o plugin em outros sistemas WP, será necessário zipar o arquivo wp-likes-plugin.php no formato .ZIP
### - Ao acessar o painel de ADM do WP vá em Plugin > Adicionar novo > Enviar plugin e faça upload do arquivo .zip clique em > INSTALAR AGORA e depois > ATIVAR PLUGIN


# FUNCIONALIDADES

### Botoes de Like e Dislike que são add diretamente nos posts, 
### A votação é via Ajax para que seja transparente ao usuario,
### Função Toggle (para dar e remover likes ao clicar no oposto), 
### Controle via cookie por visitante, 
### A persistencia dos dados foram realizados na tabela tabela wp_postmeta com a função post_meta,  
### Ranking dentro do Editor, bloco gutenberg.



