# Frontend i autentikimit

Ky aplikacion React përdor API-në Laravel për regjistrim, login, profil, logout, verifikim emaili dhe rikuperim fjalëkalimi.

## Nisja lokale

Nise Laravel-in nga dosja `backend`.

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Në një terminal tjetër, nga dosja `frontend`.

```powershell
npm install
npm run dev
```

Hap `http://localhost:5173`. Vite kalon kërkesat `/api/*` te Laravel. JWT-ja mbahet në memorien e aplikacionit deri në logout, skadim ose ringarkim të faqes.

Për linket e emailit, Laravel përdor `FRONTEND_URL=http://localhost:5173` dhe `APP_URL=http://localhost:8000` si vlera lokale. Kur `APP_URL` ndryshon, cakto origin-in e tij te `VITE_VERIFICATION_ORIGIN`. Mund të përdorësh `VITE_API_ORIGIN` kur API-ja shërbehet nga një origin i veçantë. Për dorëzim real të njoftimeve, konfiguro transportin e emailit te Laravel.

`npm run build` krijon skedarët në `dist/`. Host-i i frontend-it duhet t'i ridrejtojë rrugët e aplikacionit te `index.html`. Për API në të njëjtin origin, konfiguro edhe kalimin e `/api/*` te Laravel.
