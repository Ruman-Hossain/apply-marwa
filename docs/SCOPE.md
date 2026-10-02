# Scope catalogue

Every feature a job portal of this kind can have, scored against what
apply.marwaoverseas.com has today, with Ruman's selections recorded.

Source of the selections: the Scope Board artifact,
<https://claude.ai/artifact/2jS1QA467Mhpr4MJX3GqrE>, read on 2 October 2026.
The board remains the live tick surface; this file is the snapshot the
roadmap was built from. If the board is re-ticked, regenerate this file
rather than editing it by hand.

## Totals

| | Count |
| --- | --- |
| Features catalogued | 152 |
| Built and working | 71 |
| Not built | 81 |
| Not built and wanted (ticked) | 81 |
| Not built and not wanted | 0 |
| Built but blocked on something external | 4 |
| Wanted against my advice | 2 |

Everything not already built was ticked. That is a complete-portal brief,
not a trim, and the roadmap is sequenced accordingly: 81 items over
roughly five to six months of one developer's time. The two items marked
*Advised against* were ticked as well; see `DECISIONS.md` before either is
started, because both change what the portal is, not just what it does.

## Legend

| Mark | Meaning |
| --- | --- |
| `[x]` | Wanted — Ruman ticked it on the board |
| `[ ]` | Not ticked |
| Built | Working on the site today |
| Open | Not built |
| Blocked | Code exists; something outside the code stops it |
| Advised against | Buildable, but I recommend not building it as described |

## 1. Identity and access

Who gets in, as what, and how that is proved. Everything else hangs off this, so it is first.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `id-reg` | Candidate registration | Built | Mobile number and email, with a honeypot and rate limiting |
| — | `id-verify` | Email confirmation by code | Built | 30-minute expiry, 8-failure lockout, deliberately vague errors |
| — | `id-rbac` | Role-based permissions | Built | Candidate, staff, manager, employer — enforced in code, not just hidden in the UI |
| — | `id-empreg` | Employer self-registration | Built | Public enquiry form creating a pending record |
| — | `id-empapprove` | Employer approval by admin | Built | Activation blocked until required documents are accepted |
| `[x]` | `id-reset` | Password reset | Open | Forgot-password by emailed link |
| `[x]` | `id-otp` | Login by SMS code | Open | No password at all — the pattern most of this audience already uses for bKash |
| `[x]` | `id-social` | Google or Facebook sign-in | Open | Fewer forgotten passwords; adds a third-party dependency |
| `[x]` | `id-2fa` | Two-factor for staff | Open | Wordfence can do this; it is a settings job, not a build |
| `[x]` | `id-sessions` | Session and device list | Open | See where an account is signed in, and sign other devices out |
| `[x]` | `id-selfdelete` | Account deletion by the candidate | Open | With a clear statement of what is erased and what is kept for audit |

Built 5 of 11.

## 2. Candidate profile

The record Marwa actually sells. Depth here is what distinguishes an agency from a noticeboard.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `pr-sections` | Sectioned profile with navigation | Built | Seven sections, left rail, tab-safe saving |
| — | `pr-edu` | Education history | Built | Repeatable, with the Bangladeshi certificate ladder |
| — | `pr-train` | Training courses | Built | TTC and private courses |
| — | `pr-cert` | Certificates and exams | Built | JFT-Basic, JLPT, SSW skills tests, BMET, BNMC, Prometric, Dataflow |
| — | `pr-work` | Work history | Built | Employer, country, role, dates |
| — | `pr-skills` | Skills with level and years | Built |  |
| — | `pr-ref` | References | Built |  |
| — | `pr-lang` | Language levels | Built | Japanese and English, on the ladders that matter |
| — | `pr-travel` | Passport and travel readiness | Built | Status, number, available-from date |
| — | `pr-body` | Height, weight, blood group | Built | For the medical and for kit sizing |
| — | `pr-complete` | Completeness scoring | Built | Weighted, with the next missing thing named |
| — | `pr-vis` | Visibility control | Built | Whether Marwa may show the file to employers |
| — | `pr-cv` | CV generated from the profile | Built | No writing required of the candidate |
| — | `pr-cvdl` | CV download as PDF | Built | Through the browser, so Bangla and Japanese render correctly |
| — | `pr-vault` | Document vault | Built | Outside the web root, random filenames, permission-checked |
| `[x]` | `pr-cvtpl` | CV templates per destination | Open | A Japanese employer expects a different document from a Gulf one |
| `[x]` | `pr-video` | Video CV | Open | Strongest single idea for Japan; costs storage, upload and moderation |
| `[x]` | `pr-expiry` | Document expiry reminders | Open | Tell someone their passport or medical lapses in 60 days |
| `[x]` | `pr-parse` | Build a profile from an uploaded CV | Open | Parse a PDF and pre-fill; saves a lot of typing on a phone |
| `[x]` | `pr-photo` | Profile photograph | Open | Employers ask; it also makes the CV look finished |

Built 15 of 20.

## 3. Job catalogue

How a vacancy is created, described and retired. The structure here is what makes search and matching possible at all.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `jc-wizard` | Four-step posting wizard | Built | Vacancy, pay and conditions, who can apply, review |
| — | `jc-req` | Structured requirements | Built | Age, experience, passport, languages, named exams |
| — | `jc-ben` | Overseas benefits set | Built | Accommodation, food, tickets, overtime, end-of-service — not office perks |
| — | `jc-pay` | Pay with a hide switch | Built | Recorded internally even when not shown |
| — | `jc-state` | Vacancy state machine | Built | Draft, open, closed, filled, archived |
| — | `jc-count` | Positions count | Built | A vacancy for forty is a different proposition from one for one |
| — | `jc-empdraft` | Employer-submitted drafts | Built | Always a draft; staff complete and publish |
| `[x]` | `jc-dup` | Duplicate a vacancy | Open | Most postings are a near-copy of the last one |
| `[x]` | `jc-tpl` | Templates per trade | Open | Pre-filled requirements for a welder, a carer, a driver |
| `[x]` | `jc-import` | Bulk import from a spreadsheet | Open | For a demand letter covering forty roles at once |
| `[x]` | `jc-i18n` | Vacancies in Bangla as well as English | Open |  |
| `[x]` | `jc-approval` | Approval step before a vacancy goes live | Open | A second pair of eyes on anything carrying the licence number |

Built 7 of 12.

## 4. Search and discovery

How somebody finds the one job they can actually take, on a metered connection.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `se-kw` | Keyword search | Built |  |
| — | `se-facet` | Faceted filters with counts | Built | Destination, trade, contract, closing, posted, age, no-experience |
| — | `se-exact` | Counts that respect other filters | Built | Tap a number and you get exactly that many |
| — | `se-narrow` | Type-to-narrow inside long facets | Built |  |
| — | `se-sort` | Sort and page size | Built | Newest, closing soonest, A–Z |
| — | `se-views` | List and grid | Built |  |
| — | `se-hover` | Requirements preview on the card | Built | Overlays rather than reflowing the row |
| — | `se-nojs` | Works with JavaScript off | Built | Every filter is a link; every state is in the URL |
| — | `se-save` | Keep a job for later | Built |  |
| `[x]` | `se-savedsearch` | Saved searches | Open | Lower value until the board is large |
| `[x]` | `se-similar` | Similar jobs on a posting | Open |  |
| `[x]` | `se-reco` | Jobs recommended to me | Open | Ranked against the profile rather than filtered |
| `[x]` | `se-recent` | Recently viewed | Open |  |
| `[x]` | `se-auto` | Search autocomplete | Open | Trade names, destinations |
| `[x]` | `se-seourl` | Readable filter URLs | Open | /jobs/japan/welder rather than a query string — matters for search traffic |

Built 9 of 15.

## 5. Application pipeline

The part a marketplace does not have. Marwa moves the file, so Marwa can show where it is.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `ap-apply` | Apply from the profile | Built | One tap; no re-typing |
| — | `ap-cv` | Attach a CV when applying | Built |  |
| — | `ap-elig` | Free eligibility checklist | Built | What you still need, before you apply, never blocking |
| — | `ap-dupe` | One application per person per vacancy | Built | Enforced in the database, not just the form |
| — | `ap-withdraw` | Withdraw an application | Built |  |
| — | `ap-stages` | Ten-stage pipeline | Built | Received through to deployed, with candidate-facing wording |
| — | `ap-audit` | Full audit log | Built | Who moved what, when, and why |
| — | `ap-interview` | Interview date, time and place | Built | Shown to the candidate |
| `[x]` | `ap-bulk` | Bulk stage changes | Open | Move thirty files at once after an employer decision |
| `[x]` | `ap-reasons` | Rejection reason library | Open | Consistent, reportable, and kinder than free text |
| `[x]` | `ap-offer` | Offer letter generation | Open |  |
| `[x]` | `ap-deploy` | Deployment record | Open | Flight, arrival, in-country contact — the file does not end at visa |
| `[x]` | `ap-followup` | Post-deployment follow-up | Open | Did they arrive, are they being paid. The strongest trust signal an agency can build. |

Built 8 of 13.

## 6. Employer workspace

What the other side of the transaction sees. Narrower than a marketplace on purpose.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `em-area` | Employer area with sign-in routing | Built |  |
| — | `em-profile` | Company profile, editable in part | Built | Compliance fields stay read-only |
| — | `em-docs` | Document vetting with an admin-defined list | Built | You decide what is compulsory |
| — | `em-compliance` | Compliance record | Built | Demand letter, attestation, agreement |
| — | `em-forward` | Candidates put forward, with CVs | Built | Shortlisted onward only |
| — | `em-nocontact` | Contact details withheld | Built | The inversion of the marketplace model |
| — | `em-submit` | Submit a vacancy as a draft | Built |  |
| `[x]` | `em-decide` | Accept or decline a candidate in the portal | Open | Currently done by email and recorded by staff |
| `[x]` | `em-times` | Propose interview times | Open |  |
| `[x]` | `em-msg` | Message Marwa inside the portal | Open | Keeps the thread with the file |
| `[x]` | `em-users` | Several users on one employer account | Open |  |
| `[x]` | `em-logo` | Employer logo upload | Open |  |
| `[x]` | `em-stats` | Employer-facing statistics | Open | How many put forward, how many deployed |
| `[x]` | `em-browse` | Let employers search the candidate database | Advised against | Advise against: it turns Marwa into a database vendor and exposes workers to direct approach |

Built 7 of 14.

## 7. Communications

Currently the weakest layer in the system, and the one blocking everything else.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| `[x]` | `co-mail` | Transactional email | Blocked | Confirmation codes, stage changes, alerts — configured but not delivering |
| `[x]` | `co-alerts` | Job alerts by trade | Blocked | Built; shipped switched off until mail is proven |
| `[x]` | `co-sms` | SMS notifications | Open | Reaches people who do not check email. For this audience, arguably more important than email. |
| `[x]` | `co-wa` | WhatsApp notifications | Open | How this audience actually communicates |
| `[x]` | `co-inbox` | Notification centre inside the portal | Open | Works even when email fails |
| `[x]` | `co-tpl` | Email templates editable by admin | Open | So staff can change wording without a developer |
| `[x]` | `co-thread` | Candidate and staff messaging | Open | Replaces the phone call that currently carries everything |
| `[x]` | `co-bulk` | Message a segment | Open | All welders with passports, for example |
| `[x]` | `co-log` | Delivery log | Open | What was sent, to whom, and whether it arrived |

Built 0 of 9.

## 8. Content, SEO and reach

How people arrive at all. Cheap, and almost entirely unbuilt.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `cn-guides` | Guides section | Built | Post type, index, menu, home band |
| — | `cn-written` | Four guides published | Built | Fraud, Japan SSW, interviews, documents |
| — | `cn-noindex` | Private pages kept out of search | Built |  |
| `[x]` | `cn-schema` | JobPosting structured data | Open | Gets vacancies into Google Jobs — the single highest-value SEO item for a job site |
| `[x]` | `cn-sitemap` | XML sitemap for vacancies and guides | Open |  |
| `[x]` | `cn-og` | Social share cards | Open | Matters because sharing happens on WhatsApp and Facebook |
| `[x]` | `cn-bangla` | Bangla throughout | Open | Doubles reach without a single new claim |
| `[x]` | `cn-faq` | FAQ pages | Open |  |
| `[x]` | `cn-stories` | Worker stories | Open | Powerful and risky: only real, consented, verifiable ones |

Built 3 of 9.

## 9. Analytics and reporting

What the business cannot currently see about itself.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `an-pool` | Pool counts by trade | Built | Against open vacancies — answers whether a demand letter can be accepted |
| — | `an-status` | System status | Built | Tables, roles, storage health |
| `[x]` | `an-funnel` | Placement funnel | Open | Applications to deployments, by trade and destination |
| `[x]` | `an-source` | Where candidates come from | Open | Search, referral, WhatsApp, walk-in |
| `[x]` | `an-time` | Time in each stage | Open | Finds where files actually stall |
| `[x]` | `an-staff` | Staff activity | Open | Who is moving files, and how quickly |
| `[x]` | `an-export` | Export to Excel | Open | Every list, for anything the screens do not answer |
| `[x]` | `an-sched` | Scheduled reports by email | Open |  |

Built 2 of 8.

## 10. Trust, safety and compliance

For a licensed agency this is not a feature area. It is the product.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `tr-fraud` | Anti-fraud notice on every posting | Built | How the fraud works, and how to check the licence |
| — | `tr-licence` | Licence number displayed and explained | Built |  |
| — | `tr-nofee` | No-fee policy stated everywhere | Built |  |
| — | `tr-acl` | Document access control | Built | Owner or permitted staff only |
| — | `tr-root` | Files stored outside the web root | Built |  |
| — | `tr-caps` | Capability checks in code | Built | Not only hidden menus |
| — | `tr-audit` | Immutable audit trail | Built |  |
| `[x]` | `tr-retain` | Retention mechanism | Blocked | Built and running; the period is unset, which is a decision nobody has made |
| `[x]` | `tr-privacy` | Privacy notice and consent capture | Open | Record what each person agreed to, and when |
| `[x]` | `tr-terms` | Terms acceptance log | Open |  |
| `[x]` | `tr-report` | Report-a-fraud form | Open | Currently the guide says tell Marwa, with no route to do it |
| `[x]` | `tr-export` | Candidate data export on request | Open |  |
| `[x]` | `tr-erase` | Right-to-erasure workflow | Open | With a clear line on what must be kept for audit |

Built 7 of 13.

## 11. Platform and operations

Nobody asks for these. They are what decides whether the thing survives its second year.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `pf-schema` | Custom tables with versioned migrations | Built | Thirteen tables, eleven schema versions |
| — | `pf-office` | Multi-office scoping | Built | Dhaka and Athens do not share a book of work |
| — | `pf-tenant` | Agency tenancy field | Built | So a second agency never means a migration |
| — | `pf-demo` | Demo data tooling | Built | Create and remove, with a verification audit |
| — | `pf-rate` | Rate limiting on sign-in | Built |  |
| `[x]` | `pf-backup` | Automated backups outside the web root | Open | The single cheapest insurance available |
| `[x]` | `pf-staging` | A staging copy to test on | Open | Currently every change is tested in production |
| `[x]` | `pf-logs` | Error logging and alerting | Open | The mail fault went unnoticed for hours because nothing watches |
| `[x]` | `pf-tests` | Automated tests | Open |  |
| `[x]` | `pf-ci` | Deployment pipeline | Open | Rather than uploading a zip by hand |
| `[x]` | `pf-cache` | Caching strategy | Open | Not needed at this size; needed at ten times it |
| `[x]` | `pf-wpjm` | Replace WP Job Manager | Open | Six hundred lines; removes a dependency once the wizard owns posting |

Built 5 of 12.

## 12. Integrations

What the portal talks to. Each one is a dependency, so each needs a reason.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| `[x]` | `in-smtp` | Transactional email provider | Blocked | Connected to Brevo; not delivering |
| `[x]` | `in-sms` | SMS gateway | Open | A Bangladeshi provider; the most useful integration available |
| `[x]` | `in-wa` | WhatsApp Business API | Open |  |
| `[x]` | `in-cal` | Calendar sync for interviews | Open |  |
| `[x]` | `in-meet` | Video interview links | Open | Generated with the interview record |
| `[x]` | `in-ga` | Web analytics | Open |  |
| `[x]` | `in-bmet` | Government systems | Open | BMET registration and clearance, where an interface exists |
| `[x]` | `in-acct` | Accounting system | Open |  |
| `[x]` | `in-pay` | Payment gateway | Advised against | Advise against: there is nothing to charge a worker for, and the no-fee promise is the portal's strongest claim |

Built 0 of 9.

## 13. Reach and devices

Who can physically use it. The constraint that shaped most decisions already made.

| Want | ID | Feature | Status | Note |
| --- | --- | --- | --- | --- |
| — | `dv-resp` | Responsive at every width | Built |  |
| — | `dv-nojs` | Usable with JavaScript off | Built |  |
| — | `dv-light` | Low-bandwidth pages | Built | No framework, inline critical CSS, no web fonts on the critical path |
| `[x]` | `dv-a11y` | Accessibility pass | Open | Contrast, focus states, screen-reader labels — partly there, never audited |
| `[x]` | `dv-pwa` | Installable web app | Open | Home-screen icon, works offline for reading |
| `[x]` | `dv-offline` | Draft a profile offline | Open | Finish it when there is signal |
| `[x]` | `dv-native` | Native mobile app | Open | A large commitment; the web app answers most of the need |

Built 3 of 7.

## Where the roadmap puts each wanted item

`docs/ROADMAP.md` groups the 81 unbuilt items into nine phases.
Phase order is driven by three rules stated there: nothing is built on an
unrecoverable platform, the communications layer gates about a third of the
list, and consent capture comes before any further data collection.
