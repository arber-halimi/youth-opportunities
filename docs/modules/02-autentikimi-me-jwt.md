# 02 — Autentikimi me JWT, verifikimi i emailit dhe rikuperimi

**Karta** — [BE][P0] 08  
**Statusi** — Analizë për implementim. Kjo kartë është task-u i parë i zgjedhur për implementim.

## Qëllimi

Përdoruesi regjistrohet, verifikon emailin, kyçet, lexon profilin e vet, del nga llogaria dhe rikuperon fjalëkalimin. Për autentikimin e API-së përdorim modelin e Probit School me paketën `tymon/jwt-auth`, guard-in `auth:api` dhe një JWT për çdo login.

Klienti dërgon JWT-në në `Authorization: Bearer <token>`. Logout-i revokon tokenin aktual përmes blacklist-it të paketës. Pas skadimit, përdoruesi kyçet përsëri. Rifreskimi automatik dhe menaxhimi i sesioneve sipas pajisjes mbeten jashtë kësaj karte. Ky variant përdor vetëm `users` dhe `password_reset_tokens`.

## Modeli i databazës

### `users`

| Fusha | Tipi | Rregulli |
| --- | --- | --- |
| `id` | BIGINT unsigned | Primary key me auto-increment. Identifikon përdoruesin në claim-in `sub`. |
| `name` | VARCHAR(255) | Emri i përdoruesit. |
| `email` | VARCHAR(255) | Unik. Përdor të njëjtin normalizim në regjistrim, login dhe rikuperim. |
| `password` | VARCHAR(255) | Hash-i i fjalëkalimit. |
| `email_verified_at` | TIMESTAMP nullable | Plotësohet kur verifikohet adresa aktuale. |
| `auth_version` | INT unsigned | Nis me `0`. Rritet pas reset-it të suksesshëm të fjalëkalimit. |
| `created_at`, `updated_at` | TIMESTAMP | Timestamps të Laravel-it. |

### `password_reset_tokens`

| Fusha | Tipi | Rregulli |
| --- | --- | --- |
| `email` | VARCHAR(255) | Primary key. Password Broker e lidh tokenin me llogarinë përmes emailit. |
| `token` | VARCHAR(255) | Hash-i i tokenit të rikuperimit, i menaxhuar nga Laravel. |
| `created_at` | TIMESTAMP nullable | Password Broker e përdor për kontrollin e afatit. |

JWT-ja e aksesit dërgohet te klienti. Databaza ruan gjendjen e llogarisë dhe versionin e autentikimit. Tabela e rikuperimit ndjek skemën standarde të [Laravel Password Broker](https://laravel.com/docs/12.x/passwords).

## Kontrata e propozuar e API-së

Endpoint-et më poshtë janë nën `/api/v1/auth`.

| Metoda dhe rruga | Aksesi | Sjellja |
| --- | --- | --- |
| `POST /register` | Publik | Validon emrin, emailin dhe fjalëkalimin e konfirmuar. Krijon llogarinë dhe nis njoftimin e verifikimit. Kthen `201` me përdoruesin. |
| `POST /login` | Publik, me kufizim kërkesash | Kthen `200` me `result.access_token`, `result.token_type` dhe `result.expires_in` në sekonda. Kredencialet e pavlefshme kthejnë të njëjtin `401`. |
| `GET /me` | Bearer JWT | Kthen përdoruesin aktual dhe gjendjen e verifikimit. |
| `POST /logout` | Bearer JWT | Revokon tokenin e përdorur në kërkesë dhe kthen `204`. |
| `POST /email/verify/{id}/{hash}` | Bearer JWT dhe URL e nënshkruar | Kontrollon afatin dhe përputhjen me llogarinë e kyçur. Plotëson `email_verified_at` dhe kthen `204`. Linku i pavlefshëm kthen `403`. |
| `POST /email/verification-notification` | Bearer JWT, me kufizim kërkesash | Ridërgon linkun për llogarinë e paverifikuar dhe kthen `204`. |
| `POST /forgot-password` | Publik, me kufizim kërkesash | Pranon emailin dhe kthen të njëjtin `200` për adresë të njohur ose të panjohur. |
| `POST /reset-password` | Publik, me kufizim kërkesash | Pranon emailin, tokenin dhe fjalëkalimin e konfirmuar. Pas suksesit kthen `204`. Tokeni i pavlefshëm ose i skaduar kthen `422`. |

Hyrja e pavlefshme kthen `422` në JSON. JWT-ja e skaduar, e revokuar ose me version të vjetër kthen `401`. Për një veprim që kërkon email të verifikuar, një përdorues i autentikuar me email të paverifikuar merr `403`. `/me`, logout-i dhe veprimet e verifikimit mbeten të arritshme për llogarinë e paverifikuar.

## Rendi i implementimit

### 1. Krijo migrimin `users`

Nga dosja `backend/`, gjenero migrimin me `php artisan make:migration create_users_table`. Metoda `up()` krijon kolonat më sipër dhe `down()` përdor `Schema::dropIfExists('users')`. Ky është hapi i parë i kodimit. Verifiko që migrimi është aplikuar në databazën lokale dhe që `email` është unik.

### 2. Instalo paketën dhe konfiguro guard-in

```powershell
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

- Krijo `App\Models\User` si `Authenticatable`. Përdor `Notifiable` dhe implemento `JWTSubject` e `MustVerifyEmail`. Cast-et janë `datetime` për `email_verified_at` dhe `integer` për `auth_version`.
- `getJWTIdentifier()` kthen primary key të përdoruesit. `getJWTCustomClaims()` kthen `['auth_version' => $this->auth_version]`. Mbaje fjalëkalimin jashtë serializimit dhe kufizo fushat e përgjigjes te `UserResource`.
- Te `config/auth.php`, guard-i `api` përdor driver-in `jwt` dhe provider-in Eloquent `users`, me modelin `App\Models\User`.
- Mbaje `JWT_SECRET` në konfigurimin lokal të injoruar nga Git. Propozimi për nisje është `JWT_TTL=60` minuta dhe blacklist aktiv. Nëse ka disa instanca API-je, ato përdorin një cache të përbashkët për revokimin.

[Instalimi i JWT Auth](https://jwt-auth.readthedocs.io/en/latest/laravel-installation/) · [Konfigurimi i paketës](https://github.com/tymondesigns/jwt-auth/blob/2.x/config/config.php)

### 3. Ndërto rrjedhën login → me → logout

| Shtresa | Puna |
| --- | --- |
| `LoginRequest` | Validon dhe normalizon emailin dhe validon fjalëkalimin. |
| `AuthService` | Përdor `auth('api')->attempt($credentials)` për login dhe `auth('api')->logout()` për revokimin e tokenit aktual. Guard-i i Laravel-it kryen leximin e kredencialeve përmes provider-it. |
| `AuthController` dhe `UserResource` | Controller-i përkthen rezultatin në status dhe format HTTP. Resource zgjedh fushat publike të llogarisë. |
| Middleware | Kërko Bearer header. Pas `auth:api`, `EnsureCurrentAuthVersion` krahason claim-in e tokenit të verifikuar me versionin aktual të përdoruesit. Refuzon tokenin kur claim-i mungon ose vlera ndryshon. |
| [`routes/api.php`](../../backend/routes/api.php) | Regjistron route-t me prefiksin ekzistues `/api/v1` dhe cakton kufizimin e kërkesave për login. |

Për ta verifikuar rrjedhën, krijo një përdorues testues me fjalëkalim të hashuar, bëj login dhe thirr `/me` me JWT-në. Pas `/logout`, përsërit `/me` me të njëjtën JWT dhe prit `401`. [Metodat e guard-it JWT](https://jwt-auth.readthedocs.io/en/latest/auth-guard/)

### 4. Shto regjistrimin dhe verifikimin e emailit

Krijo `RegisterRequest`, `IUserRepository` dhe implementimin Eloquent. Regjistro binding-un te [`RepositoryServiceProvider`](../../backend/app/Providers/RepositoryServiceProvider.php). `AuthService` hashon fjalëkalimin dhe e krijon llogarinë përmes repository-t. Pas ruajtjes, nise njoftimin e verifikimit. Unikaliteti i emailit garantohet nga databaza edhe kur dy kërkesa regjistrohen njëkohësisht.

Përdor URL-në e nënshkruar me afat dhe kontrollo ID-në dhe hash-in e emailit të llogarisë së kyçur. Konfigurimi i URL-së së nënshkruar kërkon një `APP_KEY` aktive. Përsëritja e një verifikimi të vlefshëm për një llogari tashmë të verifikuar duhet të jetë pa efekt shtesë.

Për integrimin e ardhshëm, emaili do të hapë faqen e verifikimit dhe ajo do ta thërrasë API-në me JWT, duke ruajtur URL-në e plotë të nënshkruar. Deri atëherë, testet e backend-it e thërrasin drejtpërdrejt endpoint-in me URL-në e nënshkruar dhe header-in Bearer. [Verifikimi i emailit në Laravel](https://laravel.com/docs/12.x/verification)

### 5. Shto rikuperimin e fjalëkalimit

Krijo migrimin `password_reset_tokens`, konfiguro broker-in `users` dhe shto `ForgotPasswordRequest` e `ResetPasswordRequest`. `PasswordResetService` thërret `Password::sendResetLink()` dhe `Password::reset()`.

Kur tokeni i reset-it është i vlefshëm, shkruaj hash-in e ri dhe rrit `auth_version` atomikisht në të njëjtin përditësim të përdoruesit. Password Broker konsumon tokenin pas suksesit. Një JWT e lëshuar me versionin e mëparshëm refuzohet në kërkesën pasuese, përfshirë tokenët nga pajisje të tjera. Për testet e backend-it, merre tokenin nga njoftimi i kapur në test dhe dërgoje te endpoint-i i reset-it.

## Kriteret e pranimit

- [ ] Regjistrimi krijon llogarinë me fjalëkalim të hashuar, `auth_version = 0` dhe email të paverifikuar.
- [ ] Login-i i vlefshëm lëshon JWT dhe `/me` kthen përdoruesin e duhur. Kredencialet e gabuara marrin `401` me mesazh të njëjtë.
- [ ] `/me` refuzon tokenët që mungojnë, janë modifikuar, kanë skaduar, janë revokuar ose kanë version të vjetër.
- [ ] Logout-i revokon vetëm tokenin e përdorur në kërkesë. Një JWT tjetër e vlefshme e llogarisë vazhdon të punojë.
- [ ] Linku i verifikimit funksionon për llogarinë përkatëse. Nënshkrimi i modifikuar, afati i skaduar ose llogaria tjetër refuzohen.
- [ ] Forgot password jep përgjigje të njëjtë për email ekzistues dhe të panjohur.
- [ ] Reset-i i vlefshëm përditëson fjalëkalimin, konsumon tokenin dhe revokon aksesin nga JWT-të e lëshuara para reset-it.
- [ ] Kufizimet e kërkesave dhe përgjigjet `401`, `403`, `422` e `429` verifikohen në testet HTTP.
