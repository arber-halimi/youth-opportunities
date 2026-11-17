# 01 — Katalogu publik i mundësive

**Statusi** — Analizë për implementim. API-ja e modulit është ende në plan.

## Qëllimi dhe kufijtë

Si vizitor, dua të shoh mundësitë që janë aktualisht të hapura dhe të lexoj detajet e një mundësie pa llogari. Ky feature përfshin listën e paginuar, detajet sipas ID-së dhe kushtin e përbashkët të dukshmërisë.

Autentikimi, organizatat, krijimi i mundësive, moderimi, kërkimi me tekst, filtrat sipas kategorisë, aplikimet dhe frontend-i trajtohen veçmas. Për verifikimin e kësaj rrjedhe përdoren rekorde të krijuara nga testet.

## Të dhënat dhe dukshmëria

Skema minimale e propozuar për `opportunities` përfshin `id`, `title`, `description`, `status`, `published_at`, `deadline_at`, `created_at` dhe `updated_at`. Modeli `Opportunity` deklaron cast-et e datave. Për këtë fazë kërkohet vetëm dallimi ndërmjet një rekordi me status `published` dhe statuseve të tjera. Kalimet e statuseve specifikohen kur të ndërtohet moderimi.

Repository përdor të njëjtin query bazë për listën dhe detajet. Një rekord është publik vetëm kur plotëson të tre kushtet.

1. `status = published`.
2. `published_at` ka vlerë dhe është më e vogël ose e barabartë me kohën aktuale.
3. `deadline_at` është më e madhe se koha aktuale, ose është bosh nëse lejohen mundësitë pa afat.

**Propozim për kufirin e afatit** — Përdor UTC për ruajtje dhe krahasim. Mundësia mbyllet në çastin e afatit. Për `deadline_at = null`, propozimi fillestar është që mundësia të mbetet e hapur pa afat. Ky rast duhet përcaktuar para migrimit.

## Kontrata e propozuar e API-së

| Endpoint | Hyrja | Përgjigjja |
| --- | --- | --- |
| `GET /api/v1/opportunities` | `page` numër i plotë nga 1, `per_page` numër i plotë nga 1 deri në 100. Parazgjedhja e propozuar është 10 rekorde. | `200` me `message` dhe `result.data`, `result.links`, `result.meta`. Lista bosh ruan të njëjtën formë. Parametrat e pavlefshëm kthejnë `422`. |
| `GET /api/v1/opportunities/{id}` | ID numerike. | `200` me `message` dhe `result` për një mundësi publike. Për rekord që mungon ose që nuk është publik, kthen `404` në JSON. |

Fushat publike të propozuara janë `id`, `title`, `description`, `published_at` dhe `deadline_at`. Renditja e listës është sipas `published_at` zbritës, pastaj `id` zbritës për publikime me të njëjtën datë. Linket e paginimit ruajnë `per_page`.

## Rendi i implementimit sipas shtresave

| Hapi | Shtresa | Puna |
| --- | --- | --- |
| 1 | Migration dhe Model | Krijo tabelën dhe `Opportunity`, përcakto tipet e fushave, cast-et dhe nullability sipas rregullit të afatit. |
| 2 | Repository Interface | Deklaro `paginatePublic(int $perPage)` dhe `findPublicById(int $id)` në `IOpportunityRepository`. |
| 3 | Eloquent Repository | Krijo `OpportunityRepository` duke zgjeruar `BaseRepository`. Përdor query-n e përbashkët të dukshmërisë, pastaj apliko paginimin ose kërkimin sipas ID-së. |
| 4 | Provider dhe Service | Regjistro binding-un te `RepositoryServiceProvider`. Injekto interface-in te `OpportunityService` dhe delego leximet publike te metodat e posaçme të repository-t. |
| 5 | Request dhe Resources | Valido `page` dhe `per_page` te `ListOpportunitiesRequest`. Përcakto fushat publike te `PublicOpportunityResource` dhe përdore atë te `PublicOpportunityCollection`. |
| 6 | Controller dhe Routes | Lidh `index` dhe `show` te `PublicControllers/PublicOpportunityController`. Përdor metodat e `ApiResponseTrait` për përgjigjet e suksesshme dhe `404` kur Service kthen `null`. Regjistro route-t në [routes/api.php](../../backend/routes/api.php). |
| 7 | Testet e feature-it | Verifiko kontratën dhe dukshmërinë nga API-ja. Për rastet kufitare të datave përdor kohë të fiksuar në test. |

Përfundo rrjedhën e `index` me testet përpara se të shtosh `show`. Metoda `show` përdor `findPublicById()` për të zbatuar të njëjtin filtër edhe kur një vizitor e di ID-në.

## Kriteret e pranimit

- [ ] Vizitori pa llogari merr një mundësi aktive në listë dhe në detaje.
- [ ] Drafti, rekordi në shqyrtim, rekordi pa datë publikimi, publikimi në të ardhmen dhe rekordi i skaduar mungojnë nga lista dhe kthejnë `404` në detaje.
- [ ] Mundësia me afat të barabartë me kohën aktuale trajtohet si e mbyllur. Rasti pa afat mbulohet sipas vendimit të pranuar.
- [ ] Lista bosh kthen `200` me `result.data = []` dhe me metadata paginimi.
- [ ] Lista përdor renditje të qëndrueshme. Faqja pasuese ruan madhësinë e zgjedhur të faqes.
- [ ] `page` ose `per_page` jashtë kufijve kthejnë `422` në JSON. ID-ja që mungon kthen `404` në JSON.
- [ ] Përgjigjet ekspozojnë vetëm fushat e miratuara për katalogun publik.

[Kthehu te analiza e sistemit](../analiza-e-sistemit.md)
