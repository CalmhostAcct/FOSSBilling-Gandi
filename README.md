
# **Gandi Registrar Adapter for FOSSBilling**

This is a third-party registrar integration for **Gandi.net**, built using the standard adapter structure used by FOSSBilling.
It is intended as a starting point for developers who need Gandi API support.

> ⚠️ **IMPORTANT WARNING**
> This module is **untested**.
> It **may or may not work** out of the box.
> It has **not** been validated against the real Gandi API and will likely require debugging, adjustments, and field verification.
> Use at your own risk.

---

## 📂 Installation

1. Download the file **Gandi.php** (the registrar adapter).
2. Place it inside the FOSSBilling installation at:

```
library/Registrar/Adapter/Gandi.php
```

3. Log into the Admin Panel.
4. Navigate to:
   **Settings → Domain Registrars**
5. You should now see **Gandi** appear as a selectable registrar.
6. Enter your Gandi **API Key** and save.

> The API key must be a valid Gandi production or test/sandbox key.

---

## ⚙️ Features Implemented

### ✔ Included in this adapter

* Domain availability checks
* Domain registration
* Domain transfer (with EPP code)
* Domain renewal
* Domain WHOIS/details retrieval
* Nameserver updates
* Contact updates
* Privacy protection toggle
* Domain locking/unlocking
* Authinfo (EPP) retrieval

### ✖ Not included / unsupported

* Domain deletion (Gandi does not support deletion via API)
* Full error mapping
* Advanced or TLD-specific attributes
* Automated handling of registry restrictions

---

## 🧪 Testing

Because this adapter has **not been tested**, you should:

* Use a Gandi **test/sandbox API key** first
* Enable FOSSBilling debugging/logging
* Attempt operations only in a non-production environment
* Check FOSSBilling logs under:
  `data/log/application.log`

Please report any issues, unexpected responses, or corrections needed for the API endpoints.

---

## 🛠 Notes for Developers

This adapter follows the same architectural and structural patterns seen in:

* `Registrar_Adapter_Custom`
* `Registrar_Adapter_Namecheap`

It uses:

* FOSSBilling’s `Registrar_AdapterAbstract`
* Symfony HTTP client via `$this->getHttpClient()`
* JSON API calls with:

  ```
  Authorization: Apikey YOUR_API_KEY
  ```

The file is self-contained and can be modified freely to match additional Gandi API capabilities.

---

## 📄 License

This adapter is provided without any warranty.
You may modify and redistribute it under the same licensing terms used by FOSSBilling adapters.

Just tell me!
