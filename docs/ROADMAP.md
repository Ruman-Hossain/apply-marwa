# Roadmap

**81 selected items, sequenced into nine phases.** Generated 2 October 2026
from the scope board, against plugin 1.2.0 / schema v11.

Read the sequencing rationale before the phases. The order is not a
preference — several items are genuinely impossible until others land, and
two of the phases exist to stop work being wasted.

---

## How this is ordered

**Three rules decided the sequence.**

**1. Nothing is built on a platform that cannot be recovered.**
There are currently no backups, no staging copy, and no error logging. Every
change so far has been tested in production. Eleven schema migrations have
run against live data with no way back. That is the single largest risk in
the project and it is not a feature, which is why Phase 0 contains almost
nothing anybody asked for.

**2. The communications layer gates roughly a third of the list.**
Mail does not deliver. That already breaks account confirmation, stage
notifications, employer registration alerts and job alerts — and it will
silently break interview scheduling, employer decisions, document review
notices and bulk messaging as those are added. Building more notification
surfaces on a channel that does not work means debugging all of them at once
later. Phase 2 exists so that never happens.

**3. Legal capture comes before more data collection.**
There is no privacy notice, no consent record and no terms acceptance log,
and the portal is already collecting passport numbers, parents' names and
medical-adjacent data. Every day more of that accumulates without a recorded
basis. Phase 1 is small and should not wait.

**What this means in practice:** phases 0–2 are ordered by necessity. Phases
3–8 are ordered by value and can be resequenced freely if Marwa's priorities
change. Within a phase, items are ordered by dependency.

---

## Effort key

| Size | Meaning |
|---|---|
| **S** | Under a day |
| **M** | 1–3 days |
| **L** | 4–10 days |
| **XL** | Two weeks or more, or a project of its own |

Estimates assume one developer who already knows this codebase. They are
build-and-test, not build-and-launch; add review and Marwa's own sign-off.

---

## Phase 0 — Make the platform recoverable
**Why first:** everything after this is risk taken on a system with no undo.
**Blocks:** nothing technically, but every later phase is more dangerous without it.

| # | Item | Size | Notes |
|---|---|---|---|
| 0.1 | `pf-backup` Automated backups outside the web root | S | Backuply or equivalent, destination off-server. The cheapest insurance available and currently absent. |
| 0.2 | `pf-logs` Error logging and alerting | S | The mail fault went unnoticed for hours because nothing watches. Simple History plus an email or Slack alert on fatals. |
| 0.3 | `pf-staging` A staging copy | M | A subdomain clone. Stops schema migrations being tested on live candidate data. See `LOCAL-SETUP.md` — the local environment is the first half of this. |
| 0.4 | `in-smtp` Fix outgoing mail | S–M | Most likely an unverified Brevo sender or an SMTP key where a v3 API key is wanted. Needs the actual error text from a test send. **Blocks phase 2 entirely.** |
| 0.5 | `tr-retain` Set the document retention period | S | A decision, not a build. The mechanism runs daily and is set to zero, meaning keep everything forever. Must match whatever the privacy notice will say, so settle it before 1.1. |

**Also in this phase, not on the scope board:** remove the demo data, delete
the build tools, reset the admin display name from "DEMO ROW — build test",
and put the plugin under version control.

---

## Phase 1 — The legal floor
**Why here:** the portal collects sensitive personal data today with no
recorded basis. This is small, and it gets smaller the sooner it is done.

| # | Item | Size | Notes |
|---|---|---|---|
| 1.1 | `tr-privacy` Privacy notice and consent capture | M | Record what each person agreed to and when, versioned so a later change is traceable. |
| 1.2 | `tr-terms` Terms acceptance log | S | Same mechanism; ship together. |
| 1.3 | `tr-report` Report-a-fraud form | S | The fraud guide tells people to tell Marwa and gives them no way to. Routes to the office with the posting attached. |
| 1.4 | `tr-export` Candidate data export on request | M | Everything held about one person, as a file. Depends on 1.1 for scope. |
| 1.5 | `tr-erase` Right-to-erasure workflow | M | With an explicit line on what must be kept for audit and why. Depends on 1.4. |
| 1.6 | `id-reset` Password reset | S | People are locked out today with no route back. Needs 0.4. |

---

## Phase 2 — The communications layer
**Why here:** gates employer decisions, interview scheduling, alerts, document
review notices and every later message the portal needs to send. Build the
channel once, properly, rather than four times badly.

| # | Item | Size | Notes |
|---|---|---|---|
| 2.1 | `co-inbox` Notification centre inside the portal | M | **Build this before the external channels.** It works when mail and SMS fail, and it becomes the record of what was sent. Everything after writes to it. |
| 2.2 | `co-mail` Transactional email proven end to end | S | Every message the portal already tries to send — confirmation codes, stage changes, employer registration — re-tested against a real inbox once 0.4 lands, and each one logged. This is the acceptance test for 0.4, not a separate build. |
| 2.3 | `co-tpl` Email templates editable by admin | M | So staff change wording without a developer. Depends on `co-inbox` for the message model. |
| 2.4 | `co-log` Delivery log | S | What was sent, to whom, whether it arrived. Falls out of `co-inbox` and `co-tpl` almost free. |
| 2.5 | `in-sms` SMS gateway | M | A Bangladeshi provider. **Arguably more valuable than email for this audience** — most of these workers check a phone, not an inbox. |
| 2.6 | `co-sms` SMS notifications | S | Routing layer on top of `in-sms`. Pick which events go by SMS; they cost money per message. |
| 2.7 | `id-otp` Sign in by SMS code | M | Removes passwords entirely — the pattern this audience already knows from bKash. Depends on `in-sms`. |
| 2.8 | `in-wa` WhatsApp Business API | L | Approval process with Meta, template pre-registration. Start the application early; it waits on them, not on us. |
| 2.9 | `co-wa` WhatsApp notifications | M | Depends on `in-wa`. |
| 2.10 | `co-alerts` Switch job alerts on | S | Already built. Flip the setting once `co-log` proves delivery. |
| 2.11 | `co-thread` Candidate and staff messaging | L | Replaces the phone call that currently carries everything. Depends on `co-inbox`. |
| 2.12 | `co-bulk` Message a segment | M | "All welders with passports." Depends on `co-thread` and on rate limiting so it cannot be used as a spam cannon. |

---

## Phase 3 — Candidate depth
**Why here:** the candidate file is what Marwa actually sells to an employer.
Everything here either makes the file more convincing or makes it less work to
produce.

| # | Item | Size | Notes |
|---|---|---|---|
| 3.1 | `pr-photo` Profile photograph | S | Employers ask for it, and it is what makes a generated CV look finished rather than templated. |
| 3.2 | `pr-expiry` Document expiry reminders | M | Tell someone their passport or medical lapses in sixty days. Needs phase 2 to have a channel to tell them through. |
| 3.3 | `pr-cvtpl` CV templates per destination | M | Japanese and Gulf employers expect different documents. **Marwa picks the template from the vacancy, not the candidate** — the candidate has no way of knowing which is right. |
| 3.4 | `id-sessions` Session and device list | S | |
| 3.5 | `id-selfdelete` Account deletion by the candidate | S | Depends on 1.5; reuses the same workflow. |
| 3.6 | `id-social` Google sign-in | M | Fewer forgotten passwords, one more third-party dependency. Lower value once 2.7 exists. |
| 3.7 | `se-recent` Recently viewed | S | |
| 3.8 | `se-savedsearch` Saved searches | M | Low value at the current board size; rises as it grows. |
| 3.9 | `se-similar` Similar jobs on a posting | M | Needs a similarity rule — same sector plus same destination is enough to start. |
| 3.10 | `se-auto` Search autocomplete | M | Trades and destinations from live data. |
| 3.11 | `dv-a11y` Accessibility audit and fixes | M | Partly there by accident; never audited. Contrast, focus order, labels, screen-reader behaviour. |
| 3.12 | `se-reco` Jobs recommended to me | L | Ranked against the profile rather than filtered by it. Reuses `Eligibility::check()` and the completeness weights, so the rule is already half written. **Do it last in this phase** — it is only convincing once 3.1 and 3.2 have deepened the profile it ranks against. |

---

## Phase 4 — Employer workspace
**Why here:** the employer side is the newest and thinnest. It also unlocks
staff time, which is the scarcest resource at Marwa.

| # | Item | Size | Notes |
|---|---|---|---|
| 4.1 | `em-decide` Accept or decline a candidate in the portal | M | Today this happens by email and a staff member types the result in. Moving it inside the portal removes the transcription step and the delay. Depends on 2.1. |
| 4.2 | `em-times` Propose interview times | M | Employer offers slots, Marwa confirms. Writes to the existing interview fields on the application. |
| 4.3 | `in-cal` Calendar sync for interviews | M | ICS invitations first; a two-way Google sync only if someone asks. Depends on 4.2. |
| 4.4 | `in-meet` Video interview links | S | Generate the link with the interview record rather than pasting it into an email. Depends on 4.2. |
| 4.5 | `em-msg` Message Marwa inside the portal | M | Keeps the thread attached to the file. Reuses `co-thread`. |
| 4.6 | `em-users` Several users on one employer account | M | A Japanese employer will have an HR contact and a technical interviewer. One shared login is how credentials get shared over email. |
| 4.7 | `jc-dup` Duplicate a vacancy | S | Most postings are a near-copy of the last one. Highest time-saved per hour of build on this list. |
| 4.8 | `jc-tpl` Templates per trade | M | Pre-filled requirements for a welder, a carer, a driver. Builds on 4.7. |
| 4.9 | `jc-import` Bulk import from a spreadsheet | L | For a demand letter covering forty roles at once. Needs a careful preview step — a bad import on a live board is visible to the public immediately. |
| 4.10 | `jc-approval` Approval step before a vacancy goes live | M | A second pair of eyes on anything carrying the licence number. Becomes necessary the moment 4.9 exists. |
| 4.11 | `em-browse` Employer access to candidate profiles | L | **See DECISIONS.md before building.** Ticked, but the open version of this undoes a core protection. A scoped version is proposed there. |
| 4.12 | `em-logo` Employer logo upload | S | Blocked on a decision, not on code: see DECISIONS.md on which employers may be named publicly. |
| 4.13 | `em-stats` Employer-facing statistics | M | How many put forward, how many deployed. Cheap once the pipeline data is being read for phase 7 anyway. |

---

## Phase 5 — Pipeline completion
**Why here:** the pipeline is the part a job board does not have, and it
currently stops at visa. Finishing it is what turns a portal into a record of
what Marwa actually did.

| # | Item | Size | Notes |
|---|---|---|---|
| 5.1 | `ap-bulk` Bulk stage changes | M | Move thirty files at once after an employer decision. Must write one audit row per file, not one for the batch. |
| 5.2 | `ap-reasons` Rejection reason library | S | Consistent, reportable, and kinder than free text. Feeds phase 7. |
| 5.3 | `ap-offer` Offer letter generation | M | Same renderer as the CV. Depends on 3.3. |
| 5.4 | `ap-deploy` Deployment record | M | Flight, arrival, in-country contact. The file does not end at visa. |
| 5.5 | `ap-followup` Post-deployment follow-up | L | Did they arrive, are they being paid, are the conditions what was promised. **The strongest trust signal an agency can build** — and the one thing no job board can copy, because no job board knows where the worker went. Depends on 5.4 and on a channel from phase 2. |

---

## Phase 6 — Reach
**Why here:** almost entirely unbuilt, cheap, and the only part of this list
that brings new candidates in rather than serving the ones already there.

| # | Item | Size | Notes |
|---|---|---|---|
| 6.1 | `cn-schema` JobPosting structured data | M | **Highest-return item on the entire list.** Gets vacancies into Google Jobs, which is where people now search. |
| 6.2 | `cn-sitemap` XML sitemap for vacancies and guides | S | Pairs with 6.1; one without the other wastes most of the benefit. |
| 6.3 | `se-seourl` Readable filter URLs | M | `/jobs/japan/welder` rather than a query string. The filters are already links and already stateless in the URL, so this is a rewrite layer, not a rebuild. |
| 6.4 | `cn-og` Social share cards | S | Sharing happens on WhatsApp and Facebook, so the card is often the whole advertisement. |
| 6.5 | `cn-bangla` Bangla throughout | XL | **Doubles reach without making a single new claim.** The largest item in this phase by some distance; the strings are already wrapped for translation, so the work is translation and review, not code. |
| 6.6 | `jc-i18n` Vacancies in Bangla as well as English | M | Depends on 6.5. Needs a decision about who translates a posting — a mistranslated requirement is a candidate who travels and is turned away. |
| 6.7 | `cn-stories` Worker stories | M | Powerful and risky. Only real, consented, verifiable people. See DECISIONS.md. |
| 6.8 | `cn-faq` FAQ pages | S | Write from the questions staff actually get asked, not from imagination. |

---

## Phase 7 — Insight
**Why here:** none of this is urgent, and all of it changes decisions. It comes
after the pipeline is complete because an incomplete pipeline produces
misleading numbers.

| # | Item | Size | Notes |
|---|---|---|---|
| 7.1 | `an-funnel` Placement funnel | M | Applications through to deployments, by trade and destination. Depends on 5.4 to have an end to measure to. |
| 7.2 | `an-time` Time in each stage | M | Finds where files actually stall. The audit log already holds everything needed. |
| 7.3 | `an-source` Where candidates come from | M | Search, referral, WhatsApp, walk-in. Needs a capture field added at registration first. |
| 7.4 | `an-staff` Staff activity | S | Who is moving files and how quickly. Read straight off the audit log. |
| 7.5 | `an-export` Export to Excel | M | Every list. This is the escape hatch for every question the screens do not answer, so it is worth more than its size suggests. |
| 7.6 | `an-sched` Scheduled reports by email | S | Depends on phase 2. |
| 7.7 | `in-ga` Web analytics | S | Do it after 6.1 so there is traffic worth measuring. Keep it off the pages holding candidate data. |

---

## Phase 8 — Platform maturity
**Why last:** nobody asks for these, and they are what decides whether the
thing survives its second year. Items here can be pulled forward
individually — none of them blocks anything.

| # | Item | Size | Notes |
|---|---|---|---|
| 8.1 | `pf-tests` Automated tests | L | Start with the three places a silent failure would be worst: `Profile::save()` field preservation, application uniqueness, and document permission checks. |
| 8.2 | `pf-ci` Deployment pipeline | M | Rather than uploading a zip by hand. Depends on the repo from phase 0. |
| 8.3 | `pf-wpjm` Replace WP Job Manager | L | Roughly six hundred lines. Removes the last third-party dependency once the posting wizard owns vacancies outright. Do it before 8.1 if the tests would otherwise be written against code that is about to change. |
| 8.4 | `id-2fa` Two-factor for staff | S | Not really a build — Wordfence already does it. Listed late only because nothing depends on it; it can be done any afternoon. |
| 8.5 | `dv-pwa` Installable web app | M | Home-screen icon, offline reading. |
| 8.6 | `dv-offline` Draft a profile offline | L | Finish it when there is signal. Real value for this audience; real complexity. Depends on 8.5. |
| 8.7 | `pr-parse` Build a profile from an uploaded CV | L | Saves a lot of typing on a phone. Accuracy on Bangladeshi CVs will be mixed — treat the output as a draft the candidate confirms, never as fact. |
| 8.8 | `pr-video` Video CV | XL | Strongest single idea for Japan. Storage, upload on poor connections, and a review step so nothing unsuitable reaches an employer. Worth doing properly or not at all. |
| 8.9 | `in-pay` Payment gateway | M | **See DECISIONS.md.** Legitimate for employer service fees; never for jobseekers. |
| 8.10 | `dv-native` Native mobile app | XL | A project of its own, not a phase item. Revisit after 8.5 — a PWA answers most of the need at a fraction of the cost. |
| 8.11 | `pf-cache` Caching strategy | M | Not needed at this size; needed at ten times it. Revisit when a job list query stops being instant. |
| 8.12 | `in-bmet` Government systems | L | BMET registration and clearance, where an interface exists. Investigate before estimating — it may not be programmatically available at all. |
| 8.13 | `in-acct` Accounting system | M | Only once 8.9 has a reason to exist. |

---

## Sequencing summary

```
Phase 0  Recoverable platform      ~1 week      blocks everything
Phase 1  Legal floor               ~1 week      urgent, small
Phase 2  Communications            ~3-4 weeks   gates a third of the list
Phase 3  Candidate depth           ~2-3 weeks
Phase 4  Employer workspace        ~3 weeks
Phase 5  Pipeline completion       ~2-3 weeks
Phase 6  Reach                     ~4-6 weeks   Bangla dominates this
Phase 7  Insight                   ~2-3 weeks
Phase 8  Platform maturity         ongoing
```

Roughly **five to six months** for one developer, excluding Marwa's own
content work (guides, Bangla review, FAQ) and excluding 8.10.

## If only three weeks exist

Phase 0, phase 1, and five items from later phases: `co-inbox`, `in-sms`,
`co-sms`, `jc-dup`, `cn-schema`. That gets you a recoverable platform, a legal
basis for the data already held, a channel that actually reaches people, the
single biggest staff time-saver, and the thing that brings new candidates in.

## Companion documents

| File | Use it for |
|---|---|
| `SCOPE.md` | The full 152-feature catalogue with build status and selections. Every item code used here is defined there. |
| `DECISIONS.md` | Why the unusual choices were made, the five decisions still owed, and the two selected items I advise against as described. |
| `ARCHITECTURE.md` | Data model, roles, screens, request flow, and the known sharp edges. Read before changing code. |
| `LOCAL-SETUP.md` | How to run the portal on your own machine and deploy to the server. |

Item codes (`co-inbox`, `cn-schema`) are stable; phase numbers are not. When
referring to work, use the code.
