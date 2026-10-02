# Marwa Overseas — candidate portal

Working folder for **apply.marwaoverseas.com**, the candidate and employer
portal for Marwa Overseas (recruiting licence RL 2831, offices in Dhaka and
Athens).

This folder is the project's own record and the source of truth for its code.
Everything an incoming developer, a reviewer, or Marwa's own staff would need
to understand what exists and what comes next is in `docs/`.

## What is here

| Path | What it is |
|---|---|
| `docs/ROADMAP.md` | The sequenced plan. Nine phases, dependencies, effort, and why the order is what it is. **Start here.** |
| `docs/SCOPE.md` | All 152 features of a full recruitment platform, with build status and Ruman's selections. Generated from the scope board — regenerate rather than hand-edit. |
| `docs/DECISIONS.md` | Architectural decisions with their reasoning, the five decisions still owed, and the two selected items I advise against as described. **Read before starting `em-browse` or `in-pay`.** |
| `docs/ARCHITECTURE.md` | How the system is put together — data model, roles, storage, request flow, known sharp edges. |
| `docs/LOCAL-SETUP.md` | How to run the portal on your own machine, and how to deploy from here to the server. |
| `plugin/marwa-apply/` | The plugin source. This is the only place it should be edited. |
| `theme/astra-child/` | The child theme source. |
| `tools/` | `build.sh` to cut a release zip, plus two one-off helper scripts. |
| `dist/` | Built zips. Git-ignored — rebuild with `bash tools/build.sh`. |
| `reference/` | The research this was built from: a teardown of Bdjobs, the build checklist, and the per-role capability list. Open the `.html` files in a browser. |

## Current state

- Plugin version **1.2.0**, database schema **v11**.
- **71 of 152** catalogued features built. All 81 unbuilt items are selected, so
  the brief is a complete portal rather than a trim.
- Running on WordPress with the Astra theme and WP Job Manager (free core).
- **Outgoing mail does not work.** This blocks account confirmation, stage
  notifications and job alerts. It is item one in the roadmap for that reason.
- No staging copy and no backups yet. `docs/LOCAL-SETUP.md` is the first step
  out of that, and Phase 0 of the roadmap is the rest.

## How this portal differs from a job board

Worth understanding before changing anything, because several decisions look
odd without it.

Marwa is a **licensed recruiting agency**, not a listings marketplace. The
agency is the channel between a worker and an overseas employer, which means:

- A candidate's phone number and address are **never** shown to an employer.
  On a job board those details are the product. Here they are the thing the
  worker is protected from having to give out.
- An employer cannot post a vacancy until their paperwork is verified. Every
  posting carries the agency's licence number.
- Nothing is ever charged to a jobseeker. Recruitment fraud in Bangladesh is
  committed by people impersonating licensed agencies and asking for fees, so
  the no-fee promise is both a protection and the portal's strongest claim.

`docs/DECISIONS.md` records these as decisions with their reasoning, so a
future change that would undo one is at least a deliberate choice.
