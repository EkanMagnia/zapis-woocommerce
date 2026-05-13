# Zapis for WooCommerce

> Cere clientului să semneze electronic un contract după plată, direct din WooCommerce.

Plugin oficial care conectează magazinul tău WooCommerce cu [Zapis](https://zapis.app) — platforma de contracte electronice. După plată, clientul este redirecționat să semneze contractul; pe semnătură, comanda se finalizează automat prin webhook.

---

## De ce ai nevoie de el

Dacă magazinul tău vinde:

- **Cursuri online / mentoring / coaching** — acord didactic + politică de refund (obligatoriu în UE)
- **Abonamente premium** sau **servicii recurente** — contract de prestări servicii
- **Produse high-ticket** (mobilă custom, instalații, echipamente scumpe) — termeni de livrare/garanție specifici
- **Servicii cu date sensibile** — acord GDPR articol 28

…atunci ai nevoie de o semnătură electronică validă pe contractul aferent fiecărei comenzi. Acest plugin elimină complet PDF-urile pe email și schimbul manual.

## Flux complet

1. Clientul plătește în WooCommerce.
2. La `woocommerce_payment_complete`, plugin-ul trimite datele comenzii la Zapis prin API și primește un link de semnare.
3. Pe pagina **Order Received** apare un buton clar **„Sign contract now"**. Email-ul WC către client conține și el link-ul.
4. Clientul semnează pe Zapis în ~30 secunde.
5. Zapis trimite webhook `contract.signed` către `https://magazinul-tau.ro/?zapis_webhook=1`.
6. Plugin-ul validează HMAC, marchează comanda ca `completed` și salvează link-ul PDF.
7. Tu vezi în admin status contract + PDF semnat pe order edit.

---

## Cerințe

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 7.4+
- Un cont activ pe [Zapis](https://zapis.app) cu cel puțin o ofertă publicată

## Instalare

### Din ZIP (recomandat momentan)

1. Descarcă ultima versiune din [Releases](https://github.com/EkanMagnia/zapis-woocommerce/releases) (`zapis-woocommerce-x.y.z.zip`).
2. WP Admin → **Plugins → Add New → Upload Plugin** → selectează ZIP → **Install** → **Activate**.

### Din sursă (developers)

```bash
cd wp-content/plugins/
git clone git@github.com:EkanMagnia/zapis-woocommerce.git
cd zapis-woocommerce
composer install --no-dev
```

WP Admin → Plugins → activează **Zapis for WooCommerce**.

## Setup

### 1. Pe Zapis

1. Intră în **Zapis Dashboard → Settings → Magazin** și bifează „Am magazin online".
2. Selectează platforma **WooCommerce** și URL-ul magazinului.
3. **Settings → Integrations → API keys** → generează o cheie API nouă pentru WordPress. Copiază valoarea (începe cu `zapis_…`) — apare o singură dată.
4. **Settings → Integrations → Webhook endpoints** → adaugă endpoint cu URL `https://magazinul-tau.ro/?zapis_webhook=1` și eveniment `contract.signed`. Copiază secret-ul afișat.
5. Publică oferta-șablon care va fi folosită ca template implicit pentru comenzi. În modalul **Partajare** copiază **UUID-ul ofertei**.

### 2. În WordPress

1. WP Admin → **Zapis Contracts** (meniu nou în sidebar).
2. Lipește **API Key**, **Default Offer UUID** și **Webhook Secret** din pașii anteriori.
3. **Save**.
4. (Opțional) **Products → editează un produs → câmp „Zapis Offer UUID"** pentru a folosi un contract diferit pe acel produs.

Gata. Următoarea comandă plătită va fi trimisă automat la Zapis.

---

## Configurare avansată

### Per-product offer override

Edit Product → tab **General** → câmp **Zapis Offer UUID**. Lasă gol pentru a folosi default-ul.

### Custom Zapis base URL

Pentru staging sau instanțe self-hosted: **Zapis Contracts** → **Avansat** → **Zapis Base URL**. Default: `https://zapis.app`.

### Idempotency

Fiecare comandă primește header `Idempotency-Key: wc_order_{order_id}`. Retry-urile (WC sau webhook gateway) nu creează submission-uri duplicate.

### Order meta keys

Plugin-ul scrie aceste meta keys pe order după success:

| Key | Description |
|---|---|
| `_zapis_submission_uuid` | UUID submission Zapis |
| `_zapis_signing_url` | URL semnat pentru semnătură |
| `_zapis_expires_at` | Iso 8601 când expiră link-ul |
| `_zapis_contract_status` | `pending` / `signed` / `cancelled` |
| `_zapis_pdf_url` | URL semnat pentru download PDF (după semnătură) |

---

## Workflow în admin

| Pas | Ce se întâmplă |
|---|---|
| Comanda plătită | Plugin POST la `/api/v1/offers/{uuid}/direct-sign` cu order data; salvează submission_uuid și signing URL; adaugă order note. |
| Order edit (Pending) | Meta box „Zapis Contract" arată status galben + buton **Resend signing email**. |
| Webhook `contract.signed` | Plugin validează HMAC, marchează `_zapis_contract_status=signed`, schimbă status order la `completed`, salvează `pdf_url`. |
| Order edit (Signed) | Meta box arată status verde + link **Download PDF**. |

## Securitate

- API key e stocat ca WP option (admin-only access).
- Webhook secret folosit pentru HMAC-SHA256 verification cu `hash_equals` (constant-time).
- Toate request-urile sortate explicit pe `manage_woocommerce` capability.
- Plugin-ul nu blochează checkout-ul dacă Zapis e indisponibil — adaugă order note cu eroarea, comanda continuă.

## Dezvoltare

### Local test environment

```bash
# Pornește WordPress + WooCommerce într-un container Docker
wp-env start

# Site: http://localhost:8888
# Admin: http://localhost:8888/wp-admin (admin / password)
```

### Rulează testele

```bash
composer install
composer test
```

74 tests TDD acoperă API client, order handler, settings, webhook receiver, admin meta box, product meta.

### Structura plugin

```
zapis-woocommerce/
├── zapis-woocommerce.php       # entry point, metadata WP
├── includes/
│   ├── Plugin.php              # bootstrap + WC dependency check
│   ├── Settings.php            # WP admin settings page
│   ├── ApiClient.php           # client Zapis API + HMAC verify
│   ├── OrderHandler.php        # hook woocommerce_payment_complete
│   ├── ThankYouHandler.php     # CTA pe order-received + email link
│   ├── WebhookReceiver.php     # endpoint /?zapis_webhook=1
│   ├── AdminMetaBox.php        # meta box pe order edit + resend
│   ├── ProductMeta.php         # câmp UUID per product
│   ├── Http/
│   │   ├── HttpClientInterface.php
│   │   ├── WordPressHttpClient.php
│   │   └── HttpResponse.php
│   └── Exceptions/
│       ├── ApiException.php
│       ├── AuthenticationException.php
│       ├── NotFoundException.php
│       └── ValidationException.php
├── views/
│   └── admin/settings-page.php
├── languages/
│   └── zapis-woocommerce.pot
└── tests/
    └── Unit/                   # 74 tests cu Brain Monkey + Mockery
```

## Licență

GPL v2 sau mai nou. Liber de folosit, modificat, distribuit.

## Suport

- 🐛 Bug-uri: [GitHub Issues](https://github.com/EkanMagnia/zapis-woocommerce/issues)
- 📧 Suport tenant: suport@zapis.app
- 📚 Documentație Zapis: [docs.zapis.app](https://zapis.app/docs)
