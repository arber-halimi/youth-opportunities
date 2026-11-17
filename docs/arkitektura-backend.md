# Arkitektura e backend-it

Backend-i i Mundësi për Rininë është një aplikacion Laravel me REST API nën `/api/v1`. Struktura sipas shtresave ndjek modelin e Probit School. Ky dokument përcakton ku shkruhet kodi i çdo feature-i dhe si verifikohet rrjedha nga API-ja deri te databaza.

[Analiza e sistemit](analiza-e-sistemit.md) · [Katalogu publik](modulet/01-katalogu-publik-i-mundesive.md) · [Autentikimi me JWT](modules/02-autentikimi-me-jwt.md)

## Rrjedha e një kërkese

```text
Route → Middleware → FormRequest (kur ka hyrje për validim)
      → Controller (autorizon përmes Policy kur veprimi e kërkon)
      → Service → Repository Interface → Eloquent Repository → Databazë

Controller → Resource ose Collection → ApiResponseTrait → JSON
```

Service koordinon veprimin. Repository përmban mënyrën se si lexohen ose ruhen të dhënat. Laravel injekton implementimin Eloquent përmes `RepositoryServiceProvider`. Për veprime si login-i dhe rikuperimi i fjalëkalimit, Service përdor guard-in ose Password Broker-in e Laravel-it. Ato tashmë menaxhojnë aksesin e tyre në të dhëna.

## Përgjegjësia e secilës shtresë

| Shtresa | Ku vendoset | Çka shkruajmë |
| --- | --- | --- |
| **Migration** | `database/migrations/` | Skemën, tipet e kolonave, nullability, default-et, foreign keys, kufizimet unike dhe indekset e nevojshme. Për një skemë që është aplikuar, ndryshimi bëhet me migrim të ri. |
| **Model** | `app/Models/` | Relacionet, cast-et dhe konfigurimin e Eloquent, përfshirë `SoftDeletes` kur e kërkon moduli. |
| **Repository interface** | `app/Repositories/I<Feature>Repository.php` | Metodat që i duhen Service-it, me tipe kthimi të qarta. Kur kontrata përfshin operacionet bazë, zgjeron `IRepository`. |
| **Repository Eloquent** | `app/Repositories/Eloquent/<Feature>Repository.php` | Query-t, shkrimet, filtrat, eager loading dhe paginimin. Përdor modelin e injektuar dhe `$this->model->newQuery()`. Zgjeron `BaseRepository` kur ripërdor operacionet bazë. |
| **Provider** | `app/Providers/RepositoryServiceProvider.php` | Regjistron binding-un nga interface-i te implementimi. |
| **Service** | `app/Services/<Feature>Service.php` | Rregullat e biznesit, koordinimin mes repository-ve, transaksionet dhe veprimet pas ruajtjes. Kthen të dhëna ose rezultat të veprimit. Mund të zgjasë `BaseService` kur ka një repository bazë. |
| **FormRequest** | `app/Http/Requests/` | Validimin e payload-it ose të parametrave, përfshirë filtrat dhe paginimin. Në shkrime dhe filtra përdoren vlerat nga `validated()`. |
| **Middleware dhe Policy** | `app/Http/Middleware/` dhe `app/Policies/` | Middleware trajton autentikimin dhe kufizimin e kërkesave. Policy vendos nëse përdoruesi mund të kryejë veprimin mbi rekordin konkret. |
| **Controller** | `app/Http/Controllers/` | Pranon kërkesën, zbaton autorizimin aty ku duhet, thërret Service-in dhe zgjedh përgjigjen. Zgjeron `ApiController` kur përdor përgjigjet e përbashkëta. Controller-at publikë vendosen te `PublicControllers/` kur moduli ka API publike të veçantë. |
| **Resource dhe Collection** | `app/Http/Resources/` | Resource përcakton fushat e një rekordi. Collection formaton listën dhe ruan metadata e paginimit. Përdor klasat bazë `AbstractJsonResource` dhe `AbstractJsonCollection`. |
| **Route** | `routes/api.php` | Metodën HTTP, rrugën, middleware dhe veprimin e Controller-it. Prefiksi `/api/v1` vendoset në bootstrap. |

Krijo skedarë sipas nevojës së veprimit. Një endpoint pa hyrje për validim mund të përdorë `Request`. Një veprim që punon vetëm me guard-in e Laravel-it mund ta përdorë atë direkt nga Service. Shto Policy kur aksesi varet nga rekordi, edhe kur route-i tashmë kërkon JWT.

## Shembull — struktura e katalogut publik

```text
backend/
├── app/
│   ├── Models/Opportunity.php
│   ├── Repositories/
│   │   ├── IOpportunityRepository.php
│   │   └── Eloquent/OpportunityRepository.php
│   ├── Providers/RepositoryServiceProvider.php
│   ├── Services/OpportunityService.php
│   └── Http/
│       ├── Controllers/PublicControllers/PublicOpportunityController.php
│       ├── Requests/ListOpportunitiesRequest.php
│       └── Resources/
│           ├── PublicOpportunityResource.php
│           └── PublicOpportunityCollection.php
├── database/migrations/
├── routes/api.php
└── tests/Feature/
```

Për `GET /api/v1/opportunities`, FormRequest validon madhësinë e faqes, Service thërret `paginatePublic()` dhe Repository filtron statusin, datën e publikimit dhe afatin. `GET /api/v1/opportunities/{id}` përdor të njëjtin filtër publik përmes `findPublicById()`. Kur rekordi nuk e plotëson filtrin, Controller-i kthen `404` edhe nëse ID-ja ekziston.

Kur shtohet menaxhimi i mundësive, Controller-i i mbrojtur mund të përdorë të njëjtin Service. `OpportunityPolicy` kontrollon anëtarësinë në organizatën e rekordit, ndërsa Resource i atij veprimi përcakton fushat që i duhen përdoruesit të autorizuar.

## Kontrata dhe rregullat e përbashkëta

- **Autentikimi dhe autorizimi.** Sipas dizajnit të modulit Auth, route-t e mbrojtura do të përdorin `auth:api` me JWT dhe kontrollin e `auth_version`. Policy përdor gjendjen aktuale të përdoruesit dhe të rekordit për role, anëtarësi dhe pronësi. Kontrolli për email të verifikuar vendoset te veprimet që e kërkojnë.
- **Përgjigjet.** Përdor `message` dhe `result` për përgjigjet e suksesshme me të dhëna. Listat e paginuara ruajnë `result.data`, `result.links` dhe `result.meta`, edhe kur lista është bosh. `204` ka trup bosh.
- **Gabimet.** Kthe `401` për autentikim të pavlefshëm, `403` për mungesë lejeje, `404` kur rekordi mungon ose nuk është i dukshëm sipas kontratës, `422` për validim dhe `429` kur tejkalohet kufiri i kërkesave. Lëri gabimet e papritura te trajtimi i përbashkët i Laravel-it. API-ja kthen JSON dhe detajet teknike ruhen në log pa kredenciale apo tokenë.
- **Leximet.** Filtro dukshmërinë dhe pronësinë në query para paginimit. Përdor renditje të qëndrueshme dhe ngarko relacionet që përdor Resource. Mbaje të njëjtin kusht të dukshmërisë për listën dhe detajet.
- **Shkrimet.** Service hap transaksion kur disa shkrime duhet të kenë sukses së bashku. Repository kryen shkrimet, ndërsa kufizimet e databazës garantojnë integritetin edhe kur kërkesat vijnë njëkohësisht. Dërgo njoftimet ose nis jobs që varen nga shkrimi pas commit-it.
- **Ruajtja e të dhënave private.** Resource zgjedh fushat që ekspozohen. Skedarët privatë, si CV-të kur të shtohet moduli i aplikimeve, shërbehen pas kontrollit të aksesit.

[Bootstrap-i Laravel](../backend/bootstrap/app.php) · [ApiController](../backend/app/Http/Controllers/ApiController.php) · [ApiResponseTrait](../backend/app/Http/Traits/Helpers/ApiResponseTrait.php) · [RepositoryServiceProvider](../backend/app/Providers/RepositoryServiceProvider.php)

## Rendi i implementimit për çdo feature

1. **Përcakto veprimin.** Shkruaj kush e përdor, çfarë pranon API-ja, çfarë kthen dhe cilat rregulla duhet të zbatohen.
2. **Fillo me të dhënat kur ndryshojnë.** Krijo migrimin dhe Modelin ose ripërdor skemën që ekziston.
3. **Shkruaj kontratën dhe query-t.** Krijo interface-in, implementimin Eloquent dhe binding-un për operacionet që i duhen veprimit.
4. **Zbato rrjedhën në Service.** Vendos rregullat e biznesit dhe kufijtë e transaksionit. Përdor shërbimet ekzistuese të Laravel-it kur mbulojnë veprimin.
5. **Lidhe me API-në.** Shto FormRequest, autorizimin, Resource, Controller dhe route sipas nevojës.
6. **Verifiko veprimin nga API-ja.** Mbulo suksesin, validimin, aksesin dhe efektin në databazë. Pastaj vazhdo me veprimin tjetër të të njëjtit modul.

Puno një rrjedhë të plotë në të njëjtën kohë. Për Auth, fillo me **login → `/me` → logout**. Për katalogun, fillo me **listën publike** dhe pastaj shto **detajet** duke ripërdorur filtrin.

### Kontrolli para përfundimit

- [ ] Rruga, metoda HTTP dhe fushat e përgjigjes përputhen me analizën e modulit.
- [ ] Validimi, autentikimi dhe autorizimi kontrollohen në shtresat përkatëse.
- [ ] Query-t janë në repository dhe rregullat e biznesit në Service.
- [ ] Testet mbulojnë rrjedhën e suksesshme, rastet e pavlefshme dhe aksesin e ndaluar kur aplikohet.
- [ ] Kur veprimi prek disa shkrime, testohet që dështimi nuk lë ndryshime të pjesshme.
- [ ] Dokumentacioni i modulit përputhet me sjelljen e implementuar.
