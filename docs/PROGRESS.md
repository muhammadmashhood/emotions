# Progress

Milestone checklist from SPEC.md section 15. Each entry lists what was built, how to verify it, and known gaps.

- [x] **M0 — Foundations**
- [ ] **M1 — Tenancy, users, roles**
- [ ] **M2 — Typeform connection and client**
- [ ] **M3 — Linking, structure parsing, versions, mapping editor**
- [ ] **M4 — Ingestion and scoring**
- [ ] **M5 — Hospital dashboard and evaluations**
- [ ] **M6 — Admin overview and operations**
- [ ] **M7 — Phase 3 features**
- [ ] **M8 — Hardening and handover**

## M0 — Foundations

**Built:** Laravel 11 (v11.57) with Breeze Livewire/Volt stack (registration and email verification removed, invite-only), Tailwind with the design tokens from spec section 11 and Public Sans, Chart.js + SortableJS via npm, Pest 3 (+ Livewire plugin), Larastan level 6, Pint, Docker Compose (app, nginx, mysql, redis, worker, scheduler, mailpit), GitHub Actions CI, `.env.example` per section 13.

**Verify:**
```
docker run -d --name emotion-test-mysql -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=emotion_test -p 127.0.0.1:3307:3306 mysql:8.0
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
docker compose up -d --build   # then open http://localhost:8080/login
```

**Known gaps:** none.
