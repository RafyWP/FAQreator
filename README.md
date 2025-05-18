# FAQreator

**FAQreator** é um plugin WordPress que gera automaticamente perguntas e respostas frequentes (FAQs) para seus posts usando a API da OpenAI. Ideal para criadores de conteúdo que desejam melhorar a compreensão do público e o SEO de suas páginas.

## 📦 Funcionalidades

- Geração automática de FAQs com base no título e resumo do post.
- Integração com o modelo de linguagem da OpenAI.
- Relacionamento dinâmico entre posts e FAQs.
- Suporte a campos personalizados via ACF (Advanced Custom Fields).
- Interface de administração para configurar a API, modelo, parâmetros e mensagens de erro.
- Shortcode para exibir FAQs no frontend.
- Suporte a internacionalização com domínio `faqreator`.

## 🔧 Requisitos

- WordPress 5.2 ou superior  
- PHP 7.2 ou superior  
- Uma chave da API OpenAI válida

## 🚀 Instalação

1. Faça upload do plugin para a pasta `/wp-content/plugins/faqreator` ou instale diretamente via painel do WordPress.
2. Ative o plugin.
3. Vá em **Configurações > FAQreator** e preencha os campos obrigatórios:
   - Chave da API OpenAI
   - Token de autenticação
   - Tipo de post a ser analisado
   - Tipo de post para as perguntas
   - Quantidade de FAQs a gerar
   - Modelo, temperatura, tokens, timeout
   - Mensagens de erro personalizadas

## 🧠 Como funciona

Ao acessar a rota REST `/wp-json/faqreator/v1/generate-faqs/` com `post_id` e `token` válidos, o plugin coleta o título e o resumo (ou os primeiros 400 caracteres) do post e envia para a OpenAI.

A resposta com as perguntas e respostas é salva como posts do tipo definido (ex: `question`), vinculados ao post original (ex: `post`) por um campo relacional.

## 🧾 Shortcode

Use o shortcode abaixo dentro de qualquer post singular para exibir as FAQs associadas:

[faqreator]

## 🔁 Gatilho manual (via código)

Você pode acionar a geração de FAQs manualmente:

do_action( 'faqreator_generate_faq_event', $post_id, 'seu_token' );

## 🛡 Segurança

As requisições são protegidas por um token de autenticação definido na tela de configurações. Sem o token correto, o acesso à rota de geração será negado (`401 Unauthorized`).

## 🗣 Tradução

O plugin está pronto para tradução e utiliza as funções `__()` e `esc_html__()` com o domínio de texto `faqreator`.

## ✍️ Autor

**Rafy**  
https://rafy.site

## 📜 Licença

GPL v2 ou posterior  
http://www.gnu.org/licenses/gpl-2.0.txt
