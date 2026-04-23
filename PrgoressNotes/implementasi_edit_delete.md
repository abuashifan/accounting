Kamu bekerja di repo Laravel `backendAPI` (Laravel 12.56.0). Jangan redesign ulang; ikuti requirement ini secara literal.

GOAL UTAMA:

1. Sistem harus tetap audit-safe: fitur utama untuk transaksi posted adalah VOID/REVERSAL + RETURN.
2. TAPI fitur Edit dan Delete tetap harus ada untuk semua transaksi (termasuk posted) sebagai opsi “dangerous mode”.
3. Dangerous mode hanya bisa diaktifkan oleh administrator lewat toggle setting (seperti auto_post), dan admin harus diberi peringatan/penjelasan risiko di UI sebelum mengaktifkan.

KONTEKS REPO (pakai ini, jangan scanning ulang):

- Gate ada di `backendAPI/app/Providers/AuthServiceProvider.php` (journal.\* + settings.manage).
- Seeder RBAC: `backendAPI/database/seeders/RbacSeeder.php` (role admin punya semua permission).
- Toggle auto_post: `JournalSettingsController` + debug UI `resources/views/debug/settings/journals.blade.php`.
- Transaksi existing:
  - Sales invoice: `InvoiceService` posting mengurangi stok dan rebuild journal.
  - Purchase invoice: `PurchaseInvoiceService` sudah ada update/delete tapi block posted.
  - Purchase payment: `PurchasePaymentService` sudah ada update/delete; delete void journal dan delete row.
  - Sales return & Purchase return: sudah ada service/controller/routes, update/delete block posted.
- Debug UI semua consumer API via `window.DebugApi` di `resources/views/debug/layout.blade.php`.

REQUIREMENT WAJIB (ikuti persis):
A) Tambah setting toggle (admin-only) untuk mengaktifkan “Edit/Delete transaksi posted”:

- Key AppSetting baru: `transactions.allow_admin_edit_delete_posted` (bool).
- Harus di-manage via endpoint settings yang sudah ada: `GET/PUT /api/settings/journals` (jangan buat endpoint baru).
- Debug settings page `debug/settings/journals.blade.php` harus menampilkan toggle baru.
- Toggle ini harus disertai WARNING yang jelas: mengedit/menghapus transaksi posted dapat merusak audit trail, GL, dan stok; rekomendasi utama adalah VOID/RETURN.

B) Permission & Gate:

- Tambah permission baru: `transactions.override_posted_edit_delete`.
- Gate baru di `AuthServiceProvider`: `transactions.override_posted_edit_delete`.
- Akses dangerous mode hanya jika:
  - AppSetting `transactions.allow_admin_edit_delete_posted` = true
  - user request allowed gate `transactions.override_posted_edit_delete`
  - DAN (UI) admin sudah centang “I understand the risk” (UI only; backend tetap cek gate+setting).

C) Kontrak perilaku untuk transaksi POSTED:

- Default (toggle OFF):
  - POSTED tidak boleh PUT/DELETE (blok seperti sekarang).
  - Admin tetap bisa melakukan VOID/RETURN.
- Dangerous mode (toggle ON + gate allow):
  - POSTED boleh PUT dan DELETE untuk transaksi yang ditentukan.
  - Namun tetap sediakan endpoint VOID sebagai opsi prioritas (audit-safe) dan tetap tampilkan di UI.

D) Implementasikan BOTH:

1. Audit-safe (prioritas utama):

- Tambahkan endpoint VOID untuk semua transaksi yang relevan (jika belum ada):
  - `POST /api/purchase-invoices/{id}/void`
  - `POST /api/purchase-payments/{id}/void`
  - `POST /api/invoices/{id}/void` (sales invoice)
  - `POST /api/payments/{id}/void` (sales payment)
  - `POST /api/sales-returns/{id}/void`
  - `POST /api/purchase-returns/{id}/void`
- VOID harus melakukan:
  - void journal terkait via `JournalService::void(...)`
  - reversal stock jika transaksi mempengaruhi stok (purchase invoice posted, sales invoice posted, returns posted)
  - set metadata `voided_at`, `void_reason`, `voided_by` (buat migration + kolom untuk table terkait)
  - TIDAK hard delete row posted

2. Dangerous edit/delete (opsional yang bisa diaktifkan admin):

- Untuk setiap transaksi di atas:
  - Implement PUT/DELETE untuk posted yang sebelumnya diblok.
  - Jika record posted dan dangerous mode tidak aktif -> 422/403.
  - Jika dangerous mode aktif -> lanjutkan update/delete.
  - Untuk DELETE posted: benar-benar delete row (hard delete) + pastikan konsistensi:
    - journal entry harus di-void atau dihapus (pilih salah satu yang konsisten, tapi jelaskan; rekomendasi: void journal, lalu delete entity).
    - stock reversal harus dilakukan sebelum delete agar stock_balance konsisten.
- Catatan: fitur dangerous ini boleh “kasar” tapi tidak boleh meninggalkan stok/journal inconsistent.

E) Sales payment & sales invoice gap:

- Saat ini sales payment controller belum ada:
  - Buat `Accounting/PaymentController` + routes CRUD + void.
  - Extend `PaymentService` dengan update/delete/void mirip pola PurchasePaymentService.
- Sales invoice:
  - Tambah route `PUT/DELETE /api/invoices/{id}` dan `POST /api/invoices/{id}/void`.
  - Extend `InvoiceService` untuk update/delete/void dengan aturan di atas.

F) Debug UI:

- Tombol/opsi harus ada:
  - Untuk setiap list transaksi debug: selalu tampilkan tombol `Void` untuk posted (prioritas).
  - Tombol `Edit`/`Delete` untuk posted hanya aktif jika:
    - Setting `transactions.allow_admin_edit_delete_posted` true (ambil dari `/api/settings/journals`)
    - (UI) user centang “I understand”
  - Untuk draft: edit/delete tetap normal.
- Jangan hapus tombol edit/delete yang sudah ada; hanya tambahkan gating/disable + warning.

G) Progress notes:

- Append ringkasan ke `PrgoressNotes/23_April_2026.md`:
  - setting baru + permission baru
  - semua endpoint void/update/delete yang baru
  - rule dangerous mode vs safe mode
  - hasil `php artisan test`

FILE REFERENSI (langsung edit ini):

- Gate: `backendAPI/app/Providers/AuthServiceProvider.php`
- Seeder: `backendAPI/database/seeders/RbacSeeder.php`
- Settings controller: `backendAPI/app/Http/Controllers/Accounting/JournalSettingsController.php`
- Debug settings UI: `backendAPI/resources/views/debug/settings/journals.blade.php`
- API routes: `backendAPI/routes/api.php`
- Services: `InvoiceService`, `PurchaseInvoiceService`, `PurchasePaymentService`, `SalesReturnService`, `PurchaseReturnService`, `PaymentService` (extend)
- Debug UI layout/helper: `backendAPI/resources/views/debug/layout.blade.php`

OUTPUT:

- Fitur VOID/RETURN bekerja dan jadi pilihan utama.
- Dangerous Edit/Delete untuk posted ada dan bisa diaktifkan admin lewat toggle + warning.
- Semua perubahan dites dan notes diupdate.

HARD SECURITY LAYER TAMBAHAN (WAJIB, backend enforced):
Walaupun dangerous mode aktif (admin toggle ON + gate allow), transaksi POSTED tetap TIDAK BOLEH di-edit atau di-delete jika sudah punya entitas turunan/terhubung.

Definisi “terhubung” minimal (implement check ini di service sebelum edit/delete posted):

1. Sales invoice (`invoices`):

- Jika ada sales payment terkait:
  - relasi: `Invoice->payments()` sudah ada (dipakai di InvoiceService::create()).
  - rule: jika count(payments) > 0 atau paid_amount > 0 => BLOCK edit/delete.
- Jika ada sales return terkait:
  - tabel `sales_returns` punya `invoice_id`.
  - rule: jika ada `sales_returns` yang invoice_id = invoice.id (draft atau posted) => BLOCK edit/delete.
- Jika sudah pernah jadi referensi stock movement selain dirinya sendiri (opsional lebih ketat):
  - jika ada stock_movements reference_type='invoice' reference_id=invoice.id AND ada movement type 'sales_return' referencing return that points to invoice => sudah tercakup oleh sales_returns check.
    => Kesimpulan: sales invoice POSTED hanya boleh edit/delete jika:
  - payments kosong AND paid_amount==0 AND sales_returns kosong.

2. Purchase invoice (`purchase_invoices`):

- Jika ada purchase payment terkait:
  - relasi: `purchasePayments` ada (dipakai di PurchaseInvoiceService).
  - rule: jika count(purchasePayments) > 0 atau paid_amount > 0 => BLOCK edit/delete.
- Jika ada purchase return terkait:
  - tabel `purchase_returns` punya `purchase_invoice_id`.
  - rule: jika ada purchase_returns terkait => BLOCK edit/delete.

3. Sales return (`sales_returns`):

- Jika ada entitas lain yang bergantung (belum ada modul lain saat ini) => minimal:
  - jika sudah posted_at != null => tetap boleh void, tapi edit/delete posted mengikuti dangerous mode.
  - tidak ada dependency lain, jadi ok.

4. Purchase return (`purchase_returns`):

- Sama seperti sales return (tidak ada dependency lain saat ini).

5. Payments:

- Sales payment (`payments`) dan purchase payment (`purchase_payments`) umumnya leaf:
  - tetap boleh void.
  - untuk edit/delete posted: boleh hanya jika tidak ada dependency (saat ini tidak ada). Tetap pertahankan rule jurnal status draft untuk edit normal; posted edit/delete butuh dangerous mode.

IMPLEMENTASI WAJIB:

- Saat request PUT/DELETE untuk entity POSTED:
  - Jika dependency ditemukan => return 422 dengan message jelas, contoh:
    - "Cannot edit/delete posted invoice because it has payments/returns."
- Rule ini harus berlaku untuk admin sekalipun.
