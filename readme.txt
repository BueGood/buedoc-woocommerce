=== BueDoc Facturação para WooCommerce ===
Contributors: ravelinodecastro, buegood
Donate link: https://doc.buegood.com
Tags: woocommerce, invoicing, agt, angola, billing, invoice, factura
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Emissão automática de facturas e facturas-recibo certificadas pela AGT em Angola através da API BueDoc para lojas WooCommerce.

== Description ==

O **BueDoc Facturação para WooCommerce** é a solução oficial de integração para emissão automatizada de documentos fiscais certificados pela **AGT (Administração Geral Tributária de Angola)** em lojas WooCommerce.

Com este plugin, a sua loja emite facturas (**FT**) e facturas-recibo (**FR**) em total conformidade fiscal angolana, com numeração sequencial em série exclusiva para API, assinatura digital RSA-SHA1 em cadeia, códigos JWS RS256 e geração de QR Code fiscal oficial.

### Principais Vantagens

* **Certificado pela AGT (Angola):** Cumpre todos os requisitos do Regime Jurídico das Facturas e da especificação técnica AGT DS-120.
* **Séries Exclusivas de API (`isApi: true`):** A numeração dos documentos gerados pela loja virtual é isolada da interface web do BueDoc, evitando conflitos ou quebras na sequência cronológica fiscal.
* **Emissão 100% Automática:** Emite o documento fiscal assim que a encomenda é paga ou atinge os estados definidos (*A processar* ou *Concluída*).
* **Campo NIF no Checkout:** Adiciona o campo NIF com validação em tempo real na base de dados de contribuintes da AGT.
* **Regras de IVA e Isenções Oficiais:** Suporte nativo a todas as taxas de IVA (14%, 7%, 5%, 0%) e códigos de isenção oficiais da AGT (**M00** a **M99**).
* **Portes e Taxas Discriminados:** Os custos de envio e taxas adicionais são discriminados nas linhas fiscais do documento com a respectiva tributação ou isenção.
* **Notas de Crédito (NC):** Emita Notas de Crédito rectificativas com referência à factura de origem em caso de reembolso da encomenda.
* **Área de Cliente e Emails:** O cliente recebe o PDF oficial anexado ao email da encomenda e pode descarregá-lo a qualquer momento na sua conta.
* **Compatibilidade HPOS:** Compatível com o armazenamento de encomendas de alto desempenho (High-Performance Order Storage) do WooCommerce.

== Third-Party Service Disclosure ==

Este plugin conecta-se e depende do serviço externo **BueDoc API**, uma plataforma de facturação electrónica em nuvem (SaaS) desenvolvida e operada pela **BueGood Tecnologias**:

* **Fornecedor do Serviço:** BueGood Tecnologias (https://buegood.com)
* **Finalidade:** Assinatura digital RSA-SHA1 de documentos fiscais, geração de QR Code certificado, atribuição de sequência fiscal cronológica e comunicação telemática com os servidores da AGT em Angola.
* **Dados Transmitidos:** No momento da emissão do documento fiscal, são enviados para a API do BueDoc os dados da encomenda: NIF do cliente, nome, morada de facturação, email, telefone e linhas dos artigos (designação, quantidade, preço unitário e taxa de IVA). Na validação em tempo real no checkout, apenas o NIF digitado é consultado.
* **Termos de Serviço:** https://doc.buegood.com/termos
* **Política de Privacidade:** https://doc.buegood.com/privacidade

== Installation ==

### Instalação Automática via Painel
1. No painel de administração do WordPress, aceda a **Plugins > Adicionar Novo**.
2. Pesquise por `BueDoc Facturação para WooCommerce`.
3. Clique em **Instalar Agora** e, de seguida, em **Activar**.

### Instalação Manual
1. Descarregue o ficheiro ZIP do plugin.
2. No painel do WordPress, vá a **Plugins > Adicionar Novo > Carregar Plugin**.
3. Seleccione o ficheiro `buedoc-woocommerce.zip` e clique em **Instalar Agora**.
4. Active o plugin através do ecrã de plugins.

### Configuração Inicial
1. Aceda a **WooCommerce > BueDoc Facturação** (ou **WooCommerce > Definições > BueDoc Facturação**).
2. Seleccione o ambiente desejado (**Produção** ou **Homologação / Sandbox**).
3. Insira a sua **Chave de API BueDoc** (obtida no painel BueDoc em *Definições > Chaves de API*).
4. Clique no botão **Testar Ligação à API BueDoc** para validar as credenciais e verificar a quota mensal do seu plano.
5. Ajuste as opções de emissão automática, taxas de IVA padrão e requisitos do NIF no checkout.
6. Clique em **Guardar alterações**.

== Frequently Asked Questions ==

= O que é necessário para utilizar o plugin? =
É necessária uma loja WordPress com o plugin WooCommerce activo, e uma conta na plataforma BueDoc (https://doc.buegood.com) com um plano com acesso à API e a Chave Privada da AGT configurada.

= Onde obtenho a minha Chave de API BueDoc? =
No painel do BueDoc, aceda a **Definições da Conta / Empresa > Chaves de API** e crie uma nova chave para a sua loja.

= Como funciona a validação do NIF no checkout? =
Se a opção "Validação em Tempo Real" estiver activa, o plugin verifica se o NIF inserido pelo cliente corresponde a um contribuinte activo perante a AGT. Se o cliente não indicar NIF e o campo for opcional, o documento é emitido com o NIF convencional de Consumidor Final `999999999`.

= Posso emitir facturas manualmente? =
Sim. Em qualquer encomenda no painel do WooCommerce, a Meta Box "Factura BueDoc" disponibiliza um botão para emitir o documento fiscal manualmente (escolhendo entre FR e FT), além de permitir emitir documentos em massa na lista de encomendas.

= O cliente tem acesso ao PDF da factura? =
Sim. O PDF oficial gerado pela AGT/BueDoc é anexado automaticamente aos emails de encomenda concluída enviados ao cliente e fica disponível para descarga na área "A Minha Conta > Encomendas".

= O plugin é compatível com o WooCommerce HPOS? =
Sim. O plugin declara compatibilidade total com o High-Performance Order Storage (HPOS / Custom Order Tables) do WooCommerce.

== Screenshots ==

1. Painel de configurações da integração BueDoc no WooCommerce.
2. Teste de ligação à API com visualização de plano e quota mensal.
3. Campo de NIF no checkout com validação de contribuinte em tempo real.
4. Meta Box de detalhes fiscais, QR Code e descarga de PDF no ecrã da encomenda.
5. Coluna de estado e número de documento na listagem de encomendas.

== Changelog ==

= 1.0.0 =
* Lançamento inicial oficial.
* Emissão automática de Facturas (FT) e Facturas-Recibo (FR) certificadas pela AGT.
* Compatibilidade com séries exclusivas de API (`isApi: true`).
* Campo NIF no checkout com validação em tempo real perante a AGT.
* Suporte completo a todas as taxas de IVA e motivos de isenção AGT (M00 a M99).
* Anexo automático de PDF em emails do WooCommerce e na área "A Minha Conta".
* Emissão de Notas de Crédito (NC) para encomendas reembolsadas.
* Compatibilidade nativa com High-Performance Order Storage (HPOS).

== Upgrade Notice ==

= 1.0.0 =
Versão inicial de lançamento.
