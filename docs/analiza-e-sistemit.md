# Analiza e sistemit — Mundësi për Rininë

## Qëllimi dhe përdoruesit

Platforma u jep të rinjve një vend për të gjetur mundësi të publikuara. Vizitori shfleton katalogun. Përdoruesi me llogari mund të ruajë mundësi dhe të aplikojë. Anëtari i organizatës propozon mundësi dhe menaxhon aplikimet e organizatës së vet. Moderatori shqyrton propozimet, ndërsa Super Admin verifikon organizatat.

## Gjendja aktuale

Repoja përmban skeletin Laravel 12 në `backend/`. [Bootstrap-i](../backend/bootstrap/app.php) regjistron kontrollin teknik `GET /up` dhe prefiksin `/api/v1`. [Routes e API-së](../backend/routes/api.php) dhe [provider-i i repository-ve](../backend/app/Providers/RepositoryServiceProvider.php) presin modulin e parë. Klasat bazë për Controller, Service, Repository dhe Resources janë të pranishme. Frontend-i është jashtë fushëveprimit të punës aktuale.

## Arkitektura e backend-it

Backend-i është një aplikacion Laravel me REST API, i organizuar sipas shtresave të Probit School.

```text
Route → Middleware → FormRequest kur duhet validim
                   → Controller → Service
                   → Repository Interface → Eloquent Repository → Databazë

Controller → Resource ose Collection → ApiResponseTrait → JSON
```

- **Route dhe Middleware** përcaktojnë endpoint-in dhe kërkesat e autentikimit. **Policy** kontrollon lejen mbi një rekord konkret kur moduli e kërkon.
- **FormRequest dhe Controller** validojnë hyrjen dhe e lidhin veprimin me Service-in. Controller-i zgjedh statusin dhe përgjigjen HTTP.
- **Service** zbaton rregullat e biznesit dhe koordinon transaksionet kur një veprim prek disa shkrime.
- **Repository** mban query-t dhe operacionet e databazës. Service injekton interface-in, ndërsa `RepositoryServiceProvider` e lidh me implementimin Eloquent.
- **Resource dhe Collection** përcaktojnë fushat e ekspozuara. [ApiResponseTrait](../backend/app/Http/Traits/Helpers/ApiResponseTrait.php) mbështjell përgjigjet e suksesshme me `message` dhe `result`. Për listat e paginuara, `result` ruan `data`, `links` dhe `meta`.

## Modulet dhe varësitë

| Moduli | Përgjegjësia | Varësia kryesore |
| --- | --- | --- |
| Katalogu publik | Liston mundësitë aktive dhe shfaq detajet publike. | `Opportunity` dhe rregulli i dukshmërisë. |
| Llogaritë dhe organizatat | Identitet, anëtarësi dhe verifikim organizatash. | `User`, `Organization` dhe lidhja e anëtarësisë. |
| Menaxhimi dhe moderimi | Draft, dërgim për shqyrtim, vendim dhe publikim. | Llogaritë, organizatat, lejet dhe historiku i moderimit. |
| Aplikimet | Dorëzim, menaxhim dhe akses i autorizuar në CV. Klikimet e jashtme regjistrohen si evente. | Llogaritë dhe mundësitë. |
| Ruajtjet dhe njoftimet | Mundësi të ruajtura, preferenca dhe kujtesa. | Llogaritë dhe mundësitë. |
| Raportimi dhe auditimi | Statistika të agreguara dhe historik i veprimeve administrative. | Eventet dhe historiku i moduleve. |

Lidhja qendrore e modelit të synuar është `Organization 1–N Opportunity 1–N Application`. Aplikimi lidhet edhe me përdoruesin që e ka dorëzuar. Organizata mund të ekzistojë pa llogari të lidhur. Kur shtohen endpoint-et e mbrojtura, aksesin te një aplikim e përcaktojnë pronësia e aplikantit ose anëtarësia e autorizuar në organizatën e mundësisë.

## Rendi fillestar i implementimit

1. Ndërto [katalogun publik të mundësive](modulet/01-katalogu-publik-i-mundesive.md) me listë dhe detaje, të verifikuara me të dhëna testuese.
2. Shto autentikimin, organizatat dhe Policies për të hapur rrjedhën e krijimit të drafteve dhe moderimit.
3. Shto aplikimet pasi të jetë gati rrjedha e publikimit dhe të përcaktohen rregullat e aplikimit.

Databaza e prodhimit dhe strategjia e ruajtjes së përkthimeve mbeten vendime të hapura. Skema në analizën e modulit të parë është propozim për implementim.
