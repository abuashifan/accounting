1. Migration Customer & Vendor tables
2. Migration add columns to invoices table
3. Model: Customer, Vendor (dengan relasi)
4. Model: Update Invoice (tambah relasi ke Customer/Vendor)
5. Service: CustomerService (CRUD logic with validation)
6. Service: VendorService (CRUD logic with validation)
7. Controller: CustomerController (API endpoints)
8. Controller: VendorController (API endpoints)
9. Routes: Register new API routes
10. Update InvoiceService:
    - Tambah validasi pemilihan party
    - Update create/update method
    - Update post method untuk integrasi
11. Update JournalService:
    - Map accounts berdasarkan tipe invoice
12. Testing:
    - Create customer & vendor
    - Create sales invoice with customer
    - Create purchase invoice with vendor
    - Post both invoices
    - Check stock movement
    - Check journal entries
    - Validation: attempt post without customer/vendor
    - Attempt delete customer with invoices
13. Deployment: git commit & deploy
