# BueDoc Facturação para E-Commerce

Plugin oficial de integração entre o **WooCommerce** e a plataforma **BueDoc** para emissão automatizada de facturas e facturas-recibo certificadas pela **AGT (Administração Geral Tributária de Angola)**.

---

## 🌟 Principais Funcionalidades

- **Conformidade Total com a AGT (Angola):** Emissão de documentos fiscais numerados sequencialmente, assinados com RSA-SHA1 em cadeia e com geração de QR Code fiscal certificado.
- **Séries Exclusivas de API (`isApi: true`):** Utiliza séries reservadas para integrações, evitando qualquer colapso ou conflito de numeração com os documentos emitidos pela interface web do BueDoc.
- **Emissão Automática:** Gera a factura automaticamente quando a encomenda atinge os estados definidos (por exemplo, *A processar* ou *Concluída*).
- **Tipos de Documento Flexíveis:** Suporta Factura-Recibo (**FR**) para vendas pagas no acto (recomendado para e-commerce) e Factura (**FT**) para vendas a prazo ou com pagamento diferido.
- **Gestão de NIF no Checkout:**
  - Adiciona o campo NIF na finalização de compra (configurável como obrigatório, opcional ou oculto).
  - Validação em tempo real do contribuinte perante a AGT com feedback imediato.
  - Fallback automático para Consumidor Final (`999999999`) quando omitido.
- **Regras Fiscais & Isenções AGT:** Suporte completo às taxas de IVA (14%, 7%, 5%, 0%) e mapeamento de todos os motivos de isenção oficiais da AGT (códigos M00 a M99, conforme CIVA e especificação DS-120).
- **Tratamento de Portes e Encargos:** Portes de envio e taxas adicionais são discriminados nas linhas fiscais com a respectiva tributação ou isenção de IVA.
- **Notas de Crédito (NC):** Emissão facilitada de Nota de Crédito com referência ao documento de origem caso haja reembolso da encomenda.
- **Painel de Gestão na Encomenda:** Meta Box dedicada na encomenda com badge de estado na AGT, QR Code fiscal, botão de descarga directa do PDF oficial e actualização de estado.
- **Acções em Massa e Coluna Dedicada:** Coluna "Factura BueDoc" na lista de encomendas do WooCommerce e acção para emitir facturas em massa.
- **Área de Cliente & Emails:**
  - Anexo automático do PDF oficial aos emails enviados ao cliente.
  - Link seguro de download na página "A Minha Conta > Encomendas".
- **Compatível com HPOS:** Totalmente compatível com o sistema moderno de tabelas de encomendas de alto desempenho do WooCommerce (High-Performance Order Storage).

---

## 📋 Requisitos do Sistema

- **WordPress:** 6.0 ou superior
- **WooCommerce:** 8.0 ou superior (testado até 9.5+)
- **PHP:** 7.4 até 8.4 (extensões `curl` e `json` activas)
- Conta activa no [BueDoc](https://doc.buegood.com) com plano com acesso à API habilitado e chave privada AGT configurada.

---

## 🚀 Instalação

### Opção 1: Via Painel do WordPress (Ficheiro ZIP)
1. Crie um arquivo ZIP da pasta `buedoc-woocommerce`.
2. No painel de administração do WordPress, aceda a **Plugins > Adicionar Novo**.
3. Clique em **Carregar Plugin** no topo da página.
4. Seleccione o ficheiro `buedoc-woocommerce.zip` e clique em **Instalar Agora**.
5. Clique em **Activar Plugin**.

### Opção 2: Manual via FTP / Servidor
1. Copie a pasta `buedoc-woocommerce` para a pasta de plugins do seu WordPress: `/wp-content/plugins/`.
2. Aceda ao painel do WordPress em **Plugins > Plugins Instalados**.
3. Localize **BueDoc Facturação para WooCommerce** e clique em **Activar**.

---

## ⚙️ Configuração Passo a Passo

1. **Obter a Chave de API no BueDoc:**
   - Aceda à sua conta no [BueDoc](https://doc.buegood.com).
   - No menu lateral, aceda a **Definições da Conta / Empresa > Chaves de API**.
   - Crie uma nova Chave de API (ex.: nome *"Loja WooCommerce"*) e copie o token gerado (`bd_live_...`).
   - Certifique-se de que a sua **Chave Privada da AGT** já foi inserida no menu **Dados Fiscais** do BueDoc.

2. **Configurar o Plugin no WooCommerce:**
   - No WordPress, aceda a **WooCommerce > Definições** e clique na aba **BueDoc Facturação** (ou utilize o atalho no menu **WooCommerce > BueDoc Facturação**).
   - Defina o **Ambiente da API** (Produção ou Homologação / Sandbox).
   - Cole a sua **Chave de API BueDoc**.
   - Clique no botão **Testar Ligação à API BueDoc**. O sistema verificará as credenciais, o plano contratado, a quota mensal disponível e a série de API reservada.

3. **Definições de Emissão:**
   - **Emissão Automática:** Mantenha activo para emissão autónoma sem intervenção manual.
   - **Estados para Emissão:** Seleccione os estados em que a factura deve ser emitida (por padrão: *A processar* e *Concluída*).
   - **Tipo de Documento Fiscal:** Seleccione **FR (Factura-Recibo)** para vendas pagas na hora ou **FT (Factura)** para compras a crédito/prazo.
   - **Meio de Pagamento Padrão:** Indique a designação a constar no documento (ex.: *Multicaixa*, *Transferência Bancária*).

4. **Regras Fiscais de IVA:**
   - Indique a **Taxa de IVA Padrão** (14% no Regime Geral, ou 0% nos Regimes Simplificado e de Exclusão).
   - Caso utilize taxa 0%, escolha o **Motivo de Isenção Padrão** (ex.: *M10* para bens da cesta básica, *M04* para Regime de Exclusão, etc.).
   - Configure a tributação para portes de envio.

5. **NIF e Checkout:**
   - Escolha se o campo NIF é **Opcional**, **Obrigatório** ou **Oculto**.
   - Active a **Validação em Tempo Real** para validar na AGT enquanto o cliente digita.

6. **Guardar Alterações:**
   - Clique em **Guardar alterações** no final da página.

---

## 📦 Gestão Diária de Encomendas

### No Detalhe da Encomenda (Meta Box "Factura BueDoc")
Ao abrir qualquer encomenda no painel do WooCommerce:
- **Se a factura já foi emitida:** É apresentado o número do documento (ex.: `FR API2026/1`), data, total em Kwanzas, selo com o estado na AGT (`COMMUNICATED`, `ACCEPTED`, etc.), miniatura do QR Code fiscal, botão para descarregar o PDF e botão para actualizar o estado.
- **Se a encomenda for reembolsada:** Aparece o botão **Emitir Nota de Crédito (NC)** para emitir o documento rectificativo com referência ao documento original.
- **Se a factura ainda não tiver sido emitida:** É possível seleccionar o tipo de documento e emitir manualmente com um clique.

### Na Lista de Encomendas
- A coluna **Factura BueDoc** exibe o número e estado de cada documento emitido com link directo para o PDF.
- A acção em massa **Emitir Facturas no BueDoc** permite seleccionar várias encomendas em simultâneo e emitir as respectivas facturas com um só clique.

---

## 🛠️ Suporte & Depuração

- Caso ocorra algum erro na emissão, uma nota é automaticamente registada no histórico de notas da encomenda descrevendo o motivo retornado pela API ou pela AGT.
- Active a opção **Registo de Logs (Debug)** nas definições para gravar os detalhes de comunicação em **WooCommerce > Estado > Registos (Logs)** sob o contexto `buedoc-woocommerce`.
- Documentação interactiva da API: [https://doc.buegood.com/api/docs](https://doc.buegood.com/api/docs)
- Suporte técnico: [geral@buegood.com](mailto:geral@buegood.com)

---

## 📄 Licença

Desenvolvido por **BueGood Tecnologias**. Distribuído sob a licença GPLv2 ou superior.
