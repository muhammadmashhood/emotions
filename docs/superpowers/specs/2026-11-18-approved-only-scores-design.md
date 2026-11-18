# Approved-only scores design

**Date:** 2026-11-18

## Intent

eMotion staff (Super Admin, QA) reject surveys that should not count. Rejected surveys must stay on record for staff, stay invisible to hospital users, and never enter any score. Today hospital users already see approved surveys only, but staff dashboards average over every status, so staff and client can see different figures for the same hospital and period.

## Goals

- Every score, average, count, ranking, heat-map cell and benchmark line is computed from approved evaluations only, for every viewer.
- Pending-review surveys stay out of all numbers until approved.
- Staff can still find and review rejected surveys.

## Non-goals

- Changing how hospital users see data (already approved-only).
- Changing the review workflow itself.

## Anti-goals

- Hiding rejected or pending surveys from staff lists or the evaluation page.
- Losing scores on rejection (a rejected survey may be re-approved).

## Approaches considered

1. **Filter at the source of every number (chosen).** `DashboardQuery::base()` feeds every aggregate. Apply the hospital visibility rule (approved, plus the partial-response rule) there for every viewer. Lists use `EvaluationListQuery`, which stays as is.
2. Blank `overall_pct` on rejection. Counts would still include rejected and pending rows; data loss on re-approval.
3. Make `viewableBy` identical for staff and hospital users. Would hide pending/rejected rows from staff lists and pages.

## Design

- `DashboardQuery::base()` always applies `hospitalVisible()` (status approved and the partial-response rule), regardless of viewer.
- Admin overview gains a "Rejected" KPI counting rejected submissions, linking to the evaluations list filtered to rejected.
- CSV export includes a `Status` column when the exporting user is eMotion staff.
- Evaluations list for staff keeps showing all statuses with badge and filter (existing behaviour).

## Decisions made by the builder

- Partial-response rule applies to staff numbers too, so both sides compute from the identical set.
- Rejected rows stay in the staff list by default.
