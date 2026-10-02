# Decisions

Two kinds of entry. **Settled** records a choice already made and the reason,
so nobody re-opens it by accident six months from now. **Owed** records a
decision the build is waiting on, who can make it, and what is blocked until
it is made.

Date format throughout: day month year. Last reviewed 2 October 2026.

---

## Owed — needed before the next phase can finish

### 1. The two items ticked against my advice

Both were ticked on the scope board. I have not built either, and I will not
start either without a specific instruction, because each changes what the
portal *is* rather than what it does. Alternatives are offered so the
underlying need can still be met.

#### `em-browse` — let employers search the candidate database

**Why I advise against it as described.** The portal's entire differentiating
claim is that Marwa holds the file and the employer does not get direct access
to the worker. That is what `em-nocontact` enforces, and it is what makes
`tr-nofee` credible: a worker who cannot be approached directly cannot be
charged by a middleman who found them in a database. Open the database to
employer search and three things follow. Marwa becomes a database vendor
competing with bdjobs on bdjobs' terms, where bdjobs has 6.2 million CVs and
Marwa has hundreds. Workers become reachable directly, which is precisely the
route the fraud guide tells them to refuse. And the licence exposure changes:
an employer who contacts a worker found on a licensed agency's portal, and then
charges them, has done so using Marwa's name.

**Scoped alternatives, in increasing order of exposure.**

| Option | What the employer sees | Exposure |
| --- | --- | --- |
| A — search within forwarded files only | Only candidates Marwa has already put forward to that employer | None beyond what `em-forward` already shows |
| B — anonymised curated pool | Trade, age band, experience, exams, destination readiness. No name, photo, phone, passport or address. Contact only through Marwa | Low; nothing identifying leaves |
| C — anonymised pool, employer may request a named file | As B, plus a "request this file" button that creates a staff task | Moderate; Marwa still decides every release |
| D — open search of the database | Everything in the visible profile | What I advise against |

**My recommendation:** build A, then B. B gives an employer the real thing they
want — evidence that Marwa has forty welders with passports before they send a
demand letter — without handing over a single phone number. `pr-vis`
(visibility control) already exists and is the switch B would respect.

**Decision needed from:** Ruman. **Blocks:** nothing immediately; `em-browse`
sits in a late phase. **Default if no decision:** build A and B, leave C and D
unbuilt.

#### `in-pay` — payment gateway

**Why I advise against it as described.** There is nothing the portal should
charge a jobseeker for, and a payment field on a recruitment portal is the
exact thing the anti-fraud notice on every posting tells workers to run from.
Shipping both at once is self-contradicting.

**The distinction that matters.** Employer-side money is ordinary business.
Worker-side money is not.

| Flow | Status |
| --- | --- |
| Employer pays Marwa a service fee, invoiced | Legitimate. Build when there is a reason |
| Employer pays by card or bank in the portal | Legitimate. Needs `in-acct` more than it needs a gateway |
| Candidate pays a registration, listing or processing fee | Never. It contradicts `tr-nofee` and invites the fraud the portal warns about |
| Candidate pays a documented third-party cost (medical, BMET fee) | Possible later, but it should be shown as *what this costs and who to pay*, not collected by Marwa |

**My recommendation:** scope `in-pay` to employer invoicing only, and keep the
candidate side of the portal with no payment surface of any kind. If
candidate-visible costs ever need to appear, they appear as information, never
as a payment button.

**Decision needed from:** Ruman. **Blocks:** nothing; `in-pay` is last in the
roadmap. **Default if no decision:** build nothing here. Billing was already
deferred ("for now just avoid billing").

---

### 2. Document retention period

`tr-retain` is built and running. The period is unset, so nothing is ever
purged. That is not a safe resting state for a system holding passport scans.

**What is needed:** a number of months, per document class. A reasonable
starting shape, for Ruman or a lawyer to accept or change:

| Document class | Suggested retention | Reason |
| --- | --- | --- |
| Passport and ID scans | 24 months after last activity | Long enough to re-use for a second placement |
| Medical certificates | 12 months | They expire anyway |
| Education and training certificates | 60 months | Rarely change; costly for a worker to re-obtain |
| Employer compliance papers | 84 months | Licence audit horizon |
| Application and status log | Indefinite, anonymised after 60 months | Audit trail must survive the file |

**Blocks:** `tr-privacy` and `tr-erase` cannot be written honestly until this
is set, because both have to state what is kept and for how long.

### 3. Which employers may be named publicly

`em-logo` and the employer-facing parts of the vacancy page both assume a
company can be named. Some employers will not permit it, and `name_public`
exists on the employer table for exactly this.

**What is needed:** a default (named, or withheld unless permitted) and a
record of permission per employer. **Blocks:** `em-logo`, `cn-schema` (Google
Jobs wants a hiring organisation), `cn-og`.

**My recommendation:** default to withheld. Marwa's own name carries the
licence; the employer's name adds little to a worker's decision and a lot to a
competitor's intelligence.

### 4. The two drafted guides

Two guide drafts carry `gap()` placeholders where facts were unavailable. They
are unpublished and must stay unpublished until the gaps are filled with real
information. **Blocks:** nothing. **Needed from:** Marwa's own records.

### 5. Outgoing mail

Not a decision so much as the one unresolved fault, recorded here because it
has silently broken four features. SMTP is configured against Brevo and is not
delivering. I need the error text GoSMTP shows on a test send. Until mail
works, `co-mail`, `co-alerts`, `id-reset` and `id-verify`'s resend path are all
unreliable, and the demo seeder has to suppress mail to run at all.

---

## Settled

### Architecture

**Custom tables rather than post meta.** Thirteen tables prefixed `ma_`, with
versioned `dbDelta` migrations at schema version 11. A candidate file has
roughly sixty fields across seven repeatable sections; as post meta that is
hundreds of rows per candidate and no way to query "welders with a passport and
JFT-Basic" without a join nobody wants to write. Cost: no free WordPress admin
screens, so every screen is built. Accepted.

**Capability-based permissions, not hidden menus.** Four roles — candidate,
staff, manager, employer — with named capabilities checked in code. A hidden
menu is not access control. This was tightened on 2 October when the employer
role turned out to hold only `read`.

**Agency and office scoping from the start.** `pf-tenant` and `pf-office` exist
so a second agency or a third office is a row, not a migration. Dhaka and
Athens do not share a book of work.

**Documents outside the web root, random filenames, permission-checked on every
read.** A passport scan with a guessable URL is a data breach waiting for a
crawler.

**No paid themes or plugins.** Astra free, an `astra-child` child theme, WP Job
Manager free core. Standing instruction.

### Product

**The portal withholds employer contact details.** `em-nocontact`. This is the
inversion of the marketplace model and the reason the no-fee promise holds.
See `em-browse` above for the pressure this will come under.

**Eligibility checking never blocks an application.** `ap-elig` tells a
candidate what they still need and then lets them apply anyway. A checklist
that refuses people is a checklist they learn to lie to.

**Pay is recorded internally even when hidden.** `jc-pay`. The business needs
the number; the posting does not always get to show it.

**Employer-submitted vacancies are always drafts.** `jc-empdraft`. Anything
carrying the licence number gets a staff pass first.

**No fabricated content anywhere.** No invented worker numbers, partners,
testimonials, certifications. Where a fact is unavailable, a flagged
placeholder goes in and the page stays unpublished. Demo employers use
invented company names, never real companies.

**Job alerts ship switched off.** `co-alerts`. Built, but an alert system that
silently fails is worse than none.

### Process

**Build alongside, switch over once verified.** Nothing on
marwaoverseas.com is touched. Old pages are kept as drafts, never deleted.

**Demo data is creatable and removable from the plugin.** `pf-demo`, with a
verification audit. The password comes from a `MA_DEMO_PASSWORD` constant in
`wp-config.php` and is never written into a shipped file.

**Versioned releases, not random ones.** Standing instruction. Current
release 1.2.0.

### Things I will not do, whoever asks

Recorded here so they do not have to be re-litigated.

- I do not create user accounts on a live domain, and I do not enter
  passwords. Account creation on apply.marwaoverseas.com is Ruman's to do.
- I do not post content to third-party sites on Marwa's behalf, and I do not
  submit a vacancy — real or sample — to another job board.
- I do not invent facts about Marwa Overseas to fill a page.
