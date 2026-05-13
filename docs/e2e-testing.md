# End-to-End Testing Guide

Cum testezi plugin-ul Zapis for WooCommerce într-un mediu local cu WordPress + WooCommerce real, conectat la Zapis.

---

## Prerechizite

- Docker rulează (`docker ps` întoarce ceva fără eroare)
- Node + npm instalate
- `wp-env` global: `npm install -g @wordpress/env`
- Un cont Zapis cu cel puțin o ofertă publicată (Status: `published`)
- API key Zapis (vezi mai jos)

## 1. Pornire mediu local

```bash
cd /home/dev/projects/zapis-woocommerce
wp-env start                    # pornește WP + WC în Docker (~3 min prima dată)
```

Acces:
- **Site:** http://localhost:8888
- **Admin:** http://localhost:8888/wp-admin
- **User:** `admin`
- **Parolă:** `password`

Pentru a opri:
```bash
wp-env stop
```

Pentru reset complet (șterge DB local, repornește de la zero):
```bash
wp-env destroy
wp-env start
```

## 2. Activare plugin

1. Intră în http://localhost:8888/wp-admin
2. WooCommerce este deja activ (vine cu `.wp-env.json`)
3. **Plugins → Installed Plugins** → activează **Zapis for WooCommerce**
4. Verifică: în sidebar apare meniul **Zapis Contracts** (icon: clipboard)

## 3. Setup Zapis (tenant side)

Pe contul tău Zapis (din browser):

### 3.1 Generează API key
1. **Settings → Integrations → API keys** → **Generate new key**
2. Numele: `WooCommerce local` (pentru a-l recunoaște)
3. Copiază imediat valoarea (`zapis_…`) — apare o singură dată!

### 3.2 Adaugă webhook endpoint
1. **Settings → Integrations → Webhook endpoints → Add endpoint**
2. URL: `http://host.docker.internal:8888/?zapis_webhook=1`
   - `host.docker.internal` permite containerului Zapis (dacă-l rulezi local) să acceseze WP-ul din container WP
   - Dacă Zapis rulează direct pe host (nu în Docker), folosește `http://localhost:8888/?zapis_webhook=1`
   - Dacă testezi cu Zapis production, folosește **ngrok** (vezi secțiunea Troubleshooting)
3. Event: `contract.signed`
4. Copiază secret-ul afișat

### 3.3 Identifică offer UUID
1. **Dashboard → Offers** → alege o ofertă cu status `Published`
2. Click **Share** → secțiunea **UUID ofertă (pentru integrări API)** → buton copy

## 4. Configurare plugin în WordPress

1. **Zapis Contracts** (sidebar admin)
2. Completează:
   - **API Key:** valoarea de la 3.1
   - **Default Offer UUID:** valoarea de la 3.3
   - **Webhook Secret:** valoarea de la 3.2
3. **Save Changes**

Verifică: nu apar erori sub câmpuri (sanitizers-ii ar respinge format invalid).

## 5. Creează un produs WC

1. **Products → Add New**
2. Title: `Test Product`
3. Product data → **Simple product**
4. **Regular price:** `100`
5. (Opțional) **General tab → Zapis Offer UUID** → lasă gol pentru a folosi default-ul
6. **Publish**

## 6. Setează plată test

WooCommerce → **Settings → Payments** → activează **Cash on Delivery** (cel mai simplu pentru test, fără gateway extern).

## 7. Plasează o comandă din frontend

1. Logout din admin (sau folosește incognito)
2. http://localhost:8888 → adaugă `Test Product` în coș
3. **Checkout** → completează billing (folosește un email pe care îl poți accesa pentru emailuri)
4. Plată **Cash on Delivery** → **Place Order**
5. Notă: la `Cash on Delivery`, WooCommerce trece order direct la `processing`, nu la `payment_complete` automat. Vezi nota la pct 9 pentru workaround.

## 8. Checklist E2E

Verifică în ordine:

- [ ] **Order received page** afișează un block galben/indigo cu titlul „One last step — sign your contract" și buton `Sign contract now`
- [ ] **Email cumpărător** (verifică wp-env mail catcher la `http://localhost:8025` dacă MailHog e configurat, sau verifică logs) conține link de semnare
- [ ] **Admin → WooCommerce → Orders → [comanda nouă]** are meta box **Zapis Contract** (în coloana din dreapta) cu:
  - Status: galben **Pending signature**
  - Submission UUID (cod monospace)
  - Signing link (deschide în tab nou)
  - Buton **Resend signing email to customer**
- [ ] **Order notes** (coloana dreaptă, sub meta box) conține notă **„Zapis: contract sent for signing. URL: …"**
- [ ] Click pe signing link → ajunge pe pagina Zapis de semnare cu datele pre-completate (nume, email, telefon)
- [ ] **Semnează contractul** pe Zapis (cu mouse-ul sau touch)
- [ ] **Pe Zapis: Settings → Integrations → Webhook deliveries** → vezi delivery cu status 200, payload conține `external_order_id` egal cu order ID WC
- [ ] **Reîmprospătează order WC** în admin → status devine `Completed`, meta box arată:
  - Status: verde **Signed**
  - Link **Signed PDF → Download →**
- [ ] **Order notes** are notă nouă **„Contract signed via Zapis. Order completed automatically."**

## 9. Cazuri speciale

### Plată instant (nu Cash on Delivery)
WC declanșează `woocommerce_payment_complete` doar la gateway-uri online. Pentru `Cash on Delivery`, trebuie să schimbi manual statusul:
- **Admin → comanda → status: Completed** sau apelează tu hook-ul direct via `do_action('woocommerce_payment_complete', $order_id)`

Pentru flow realist instant, instalează **WooCommerce Stripe Gateway** + folosește Stripe test key.

### Webhook nu ajunge la WP local
**Cauză:** Zapis production e pe internet, WP-ul tău e pe localhost.

**Soluție: ngrok**
```bash
# Instalează ngrok dacă nu îl ai
brew install ngrok          # macOS
sudo snap install ngrok     # Ubuntu

# Expune portul wp-env
ngrok http 8888
```
ngrok afișează un URL public `https://xxxx.ngrok-free.app`. Pune în Zapis webhook URL `https://xxxx.ngrok-free.app/?zapis_webhook=1`.

### Port 8888 ocupat
```bash
WP_ENV_PORT=8899 wp-env start
# Acces: http://localhost:8899
```

### Resetare DB
```bash
wp-env clean all       # șterge DB, păstrează imaginile
wp-env start
```

### Vezi logs WP (debug.log)
```bash
wp-env logs            # toate logs
wp-env logs --watch    # live tail
```
Sau direct: `~/wp-env/{instance}/wordpress/wp-content/debug.log`.

### Rulează WP-CLI
```bash
wp-env run cli wp plugin list
wp-env run cli wp post list --post_type=shop_order
wp-env run cli wp eval 'echo get_option("zapis_wc_api_key");'
```

## 10. Inspectează ce salvează plugin-ul

```bash
# meta keys pe un order (înlocuiește 123 cu ID-ul tău)
wp-env run cli wp post meta list 123 --orderby=meta_key | grep zapis

# options plugin
wp-env run cli wp option list --search='zapis_wc_*'
```

Expected: ar trebui să vezi `_zapis_submission_uuid`, `_zapis_signing_url`, `_zapis_expires_at`, `_zapis_contract_status`, eventual `_zapis_pdf_url` după semnătură.

## 11. Rulare teste unitare (NU în Docker)

```bash
cd /home/dev/projects/zapis-woocommerce
composer install                 # prima dată
composer test                    # 74 tests
composer test-coverage           # cu coverage report
```

Testele rulează în PHP direct (cu Brain Monkey), nu în WP — sunt rapide (~0.3 sec).

## 12. Cum opresc tot

```bash
cd /home/dev/projects/zapis-woocommerce
wp-env stop                      # oprește containere, păstrează volume

# Sau șterge tot complet
wp-env destroy                   # șterge containere + volume; data dispare
```

---

## Debugging quick reference

| Simptom | Cauză probabilă | Fix |
|---|---|---|
| `Zapis Contracts` meniu nu apare | WooCommerce nu e activ | Activează WooCommerce întâi |
| API key respins la save | Nu începe cu `zapis_` | Verifică key-ul, regenerează în Zapis |
| Offer UUID respins | Format invalid (nu 36 chars cu dash-uri) | Re-copiază din Zapis Share modal |
| Order n-are submission_uuid | Plugin n-a apucat să facă POST | Verifică WP debug.log; verifică că `woocommerce_payment_complete` se declanșează (Cash on Delivery nu îl declanșează — vezi pct 9) |
| Order rămâne `processing` după semnare | Webhook n-a ajuns la WP | Verifică în Zapis: Webhook deliveries (status, response body); folosește ngrok |
| `Webhook secret missing` (503) | Setting webhook secret e gol | Salvează webhook secret în Zapis Contracts settings |
| `Invalid signature` (401) | Webhook secret diferă între WP și Zapis | Re-copiază secret-ul din Zapis în WP |

---

## Bug reporting

Reportează orice problemă la: https://github.com/EkanMagnia/zapis-woocommerce/issues

Include:
- Versiune WP, WC, PHP
- Versiune plugin (vezi pe pagina Plugins)
- Output `wp-env logs` din timpul reproducerii
- Pași exacți de reproducere
