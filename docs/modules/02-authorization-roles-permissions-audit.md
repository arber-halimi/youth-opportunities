# Karta 10 — Rolet, lejet, Policies dhe auditimi bazë

**Statusi** — Analizë dhe plan implementimi.

[Karta 10 në Trello](https://trello.com/c/ULjBjrF7/10-bep0-10-roles-permissions-policies-dhe-audit-baz%C3%AB) · [Autentikimi me JWT](02-autentikimi-me-jwt.md) · [Arkitektura e backend-it](../arkitektura-backend.md)

## Qëllimi dhe rolet

JWT-ja identifikon përdoruesin. Autorizimi përcakton çfarë veprimi mund të kryejë dhe mbi cilin rekord. Backend-i kontrollon lejet aktuale nga databaza. Një ndryshim roli zbatohet në kërkesën pasuese edhe me të njëjtën JWT.

| Aktori | Aksesi |
| --- | --- |
| Vizitor | Lexon mundësitë aktive dhe të dhënat publike pa llogari. |
| Përdorues i regjistruar (`user`) | Menaxhon të dhënat e veta, ruan mundësi, aplikon dhe sheh aplikimet e veta kur të shtohen modulet përkatëse. |
| Anëtar ose pronar i organizatës (`member`, `owner`) | Vepron brenda organizatës ku ka anëtarësi. Ndarja e saktë e lejeve mes anëtarit dhe pronarit përcaktohet te karta 11. |
| Admin (`admin`) | Moderon mundësitë dhe lexon raportet e agreguara. |
| Super Admin (`super_admin`) | Ka të gjitha lejet e platformës, përfshirë menaxhimin e roleve, pezullimin dhe riaktivizimin e llogarive, si dhe aprovimin e organizatave. |

## Skema aktuale dhe ndryshimet

Skema MySQL `youthopportunities` përmban `users`, `password_reset_tokens`, `opportunities` dhe `migrations`. `users` ruan emailin, hash-in e fjalëkalimit, `email_verified_at` dhe `auth_version`. `opportunities` ruan përmbajtjen, statusin, publikimin dhe afatin. Lidhja me organizatën shtohet kur të ndërtohet ai modul.

| Tabela ose kolona | Fushat dhe kufizimet e propozuara |
| --- | --- |
| `roles` | `id` dhe `key` unik për `user`, `admin` dhe `super_admin`. |
| `permissions` | `id` dhe `key` unik për çdo veprim. |
| `role_user` | Foreign keys `user_id` dhe `role_id`. Kombinimi është unik. |
| `permission_role` | Foreign keys `permission_id` dhe `role_id`. Kombinimi është unik. |
| `audit_logs` | `id`, `actor_user_id`, `action`, `subject_type`, `subject_id`, `changes` dhe `created_at`. `changes` ruan vetëm fushat e lejuara për veprimin. |
| `users.suspended_at` | Timestamp nullable. `NULL` përfaqëson llogari aktive. |

Seeder-i i jep `admin` lejet `opportunities.moderate` dhe `reports.view`. `super_admin` merr edhe `users.roles.manage`, `users.status.manage` dhe `organizations.approve`. Rolet organizative ruhen më vonë te `organization_users` me `organization_id`, `user_id` dhe rolin brenda asaj organizate. Një përdorues mund të jetë pronar në një organizatë dhe anëtar në një tjetër.

Migrimet janë shtesë. Llogaritë ekzistuese marrin `user` kur s’kanë rol dhe fillojnë me `suspended_at = NULL`. Regjistrimi publik cakton `user` nga backend-i në të njëjtin transaksion me krijimin e llogarisë. Pezullimi vendos `suspended_at` dhe rrit `auth_version` në të njëjtin përditësim, që JWT-të e mëparshme të mbeten të pavlefshme edhe pas riaktivizimit.

## Rendi i implementimit

1. **Migrimet dhe të dhënat fillestare.** Krijo tabelat dhe kolonën e pezullimit. Shto një seeder që mund të ekzekutohet përsëri pa dublikate dhe plotëso rolin bazë për llogaritë ekzistuese. Përgatit një komandë administrative që cakton Super Admin-in e parë për një llogari të identifikuar.
2. **Kontrolli i aksesit.** Lidh `User` me rolet dhe lejet. Shto `UserPolicy::updateRoles` dhe `UserPolicy::updateStatus`. Pas `auth:api`, kontrollo `auth_version` dhe statusin e llogarisë. FormRequest validon hyrjen, Policy autorizon aktorin, Controller thërret Service-in dhe Repository kryen shkrimet.
3. **Ndryshimi i roleve dhe auditimi.** Shto `PUT /api/v1/admin/users/{user}/roles`. Prano listën `roles` me identifikuesit e roleve. Ruaj caktimet e reja dhe auditimin me rolet para dhe pas në të njëjtin transaksion. Kur auditimi dështon, transaksioni kthen prapa edhe ndryshimin.
4. **Pezullimi dhe riaktivizimi.** Shto `PATCH /api/v1/admin/users/{user}/status` me `status: active` ose `status: suspended`. Audito vetëm kalimet reale të statusit. Login-i i një llogarie të pezulluar merr `401` me të njëjtën përgjigje si për kredenciale të pavlefshme. Propozimi është të mbrojmë Super Admin-in e fundit aktiv nga pezullimi ose heqja e rolit, edhe kur dy kërkesa vijnë njëkohësisht.
5. **Pronësia në nivel organizate.** Me kartën 11, lidhe anëtarësinë me Policies e rekordeve private. Repository filtron listat sipas organizatës para paginimit. Lidhja e mundësive ekzistuese me organizatat duhet plotësuar para se `opportunities.organization_id` të bëhet i detyrueshëm.

Për veprimet administrative, `401` tregon autentikim të pavlefshëm dhe `403` tregon që aktorit i mungon leja. Payload-i i pavlefshëm kthen `422`. Auditimi ruan aktorin, subjektin, kohën dhe ndryshimin. Fjalëkalimet, JWT-të dhe tokenët e rikuperimit përjashtohen nga të dhënat e auditimit.

## Kriteret e pranimit

- [ ] Seeder-i mund të përsëritet dhe llogaritë ekzistuese vazhdojnë të kyçen me rolin bazë.
- [ ] Regjistrimi publik cakton `user` edhe nëse klienti provon të dërgojë një rol tjetër.
- [ ] Kërkesa pa JWT merr `401`. Përdoruesi ose Admin-i që provon të menaxhojë role ose status merr `403`.
- [ ] Super Admin-i mund të ndryshojë rolet dhe statusin. Lejet e reja zbatohen në kërkesën pasuese.
- [ ] Ndryshimi i rolit dhe çdo kalim i statusit krijojnë audit. Dështimi i auditimit kthen prapa ndryshimin. Përsëritja pa ndryshim statusi nuk krijon audit të dytë.
- [ ] Pezullimi refuzon login-in dhe JWT-të e vjetra. Pas riaktivizimit, përdoruesi mund të marrë JWT të re.
- [ ] Propozimi për mbrojtjen e Super Admin-it të fundit verifikohet edhe me kërkesa njëkohësisht.
- [ ] Me endpoint-in privat të kartës 11, anëtari i organizatës A mund të punojë në A dhe merr `403` për një rekord privat të B-së.
