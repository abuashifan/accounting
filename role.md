# 🧾 ACCOUNTING ENGINE RULES (FINAL DESIGN)

## 1. Core Principle

- Sistem akuntansi bersifat **fleksibel**, bukan strict-lock
- Transaksi **boleh diedit**, tergantung:
  - status periode
  - role & permission user

---

## 2. Accounting Period Control

- Setiap transaksi terhubung ke `accounting_periods`
- Period memiliki status:

```text
OPEN   → transaksi bisa diedit
CLOSED → transaksi dibatasi
```

### Rule:

- Jika period = CLOSED:
  - ❌ user biasa tidak boleh edit
  - ✅ admin bisa edit (jika punya permission)

---

## 3. Role & Permission System

Setiap user memiliki role dan permission granular

### Contoh permission:

```text
journal.create
journal.update
journal.delete
journal.post
journal.edit_locked   (admin override)
```

### Rule:

- Semua aksi harus melalui pengecekan permission
- Tidak ada hardcode role (semua berbasis permission)

---

## 4. Journal Behavior

### Status Journal:

```text
active → normal transaksi
void   → dibatalkan (tidak dihapus)
```

### Rule:

- ❌ Tidak boleh hard delete
- ✅ Gunakan status `void`

---

## 5. Edit Transaction Rule

### Logic utama:

```text
IF period = OPEN
   → boleh edit (sesuai permission)

IF period = CLOSED
   → hanya user dengan permission "journal.edit_locked" yang boleh edit
```

---

## 6. Audit Trail (MANDATORY)

Semua perubahan data HARUS dicatat

### audit_logs:

- user_id
- action (create/update/delete)
- table_name
- record_id
- old_values (JSON)
- new_values (JSON)
- reason (optional)
- timestamp

### Rule:

- ❌ Tidak boleh ada perubahan tanpa audit log
- ✅ Semua edit wajib tercatat

---

## 7. Data Integrity Rule

### Double Entry:

```text
Total Debit = Total Credit
```

### Validation:

- wajib sebelum insert/update journal

---

## 8. Immutability Strategy (Soft)

- Sistem tidak memaksa immutable (seperti ERP strict)
- Tapi:
  - semua perubahan tercatat
  - bisa ditelusuri (audit)

---

## 9. Admin Override

- Admin memiliki akses khusus:

```text
journal.edit_locked
```

### Rule:

- Admin bisa edit data di period CLOSED
- Tapi:
  - tetap tercatat di audit log
  - disarankan isi "reason"

---

## 10. Update Strategy

Saat update transaksi:

❌ Dilarang:

```text
overwrite tanpa jejak
```

✅ Wajib:

```text
- simpan old_values
- simpan new_values
- simpan user_id
```

---

## 11. Delete Strategy

❌ Tidak ada hard delete
✅ Gunakan:

```text
status = void
```

---

## 12. System Positioning

Sistem ini adalah:

✅ Flexible Accounting System
✅ Role-Based Access Control
✅ Audit-Aware System
❌ Bukan strict immutable ERP

---

## 13. Future Upgrade Path

Sistem dapat dikembangkan ke:

- strict lock system
- reversal journal
- full audit versioning
- compliance-ready accounting

---

# 🚀 FINAL SUMMARY

Sistem ini menggabungkan:

✔ fleksibilitas user (editable data)
✔ kontrol sistem (period + permission)
✔ keamanan data (audit log)

Sehingga:

> Data boleh berubah, tapi **tidak pernah tanpa jejak**
