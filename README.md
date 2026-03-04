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
