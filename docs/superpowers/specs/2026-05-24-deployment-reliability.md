# Reliability & AWS Deployment Design

**Goal:** Prevent UI deadlocks during background job failures and provide a battle-tested AWS deployment configuration for 10-20 internal users using the `database` queue driver.

---

## 1. Background Job Resiliency

### Objective
Ensure that if a background AI task fails permanently (after all retries), the database is updated so the frontend can show an error state instead of a loading spinner.

### Implementations
- **File:** `backend/app/Jobs/ProcessCritiqueJob.php`
    - Add `failed(\Throwable $exception)` hook.
    - Update `RequirementDraft` status to `failed`.
- **File:** `backend/app/Jobs/ProcessRepositoryJob.php`
    - Add `failed(\Throwable $exception)` hook.
    - Update `Project` status to `error`.
- **File:** `backend/app/Jobs/ProcessContextFileJob.php`
    - Add `failed(\Throwable $exception)` hook.
    - Update `Project` status to `error`.

---

## 2. AWS Production Configuration (Internal 10-20 Users)

### Infrastructure Requirements
- **Database:** AWS RDS MySQL (Recommended: `t3.medium`).
- **Compute:** AWS ECS Fargate or EC2 (Recommended: 2 vCPU, 4GB RAM).
- **Environment:**
    - `QUEUE_CONNECTION=database`
    - `DB_CONNECTION=mysql`

### Optimization for Concurrent AI Streaming
1. **PHP-FPM (`/usr/local/etc/php-fpm.d/www.conf`):**
    ```ini
    pm = static
    pm.max_children = 60
    ```
2. **Nginx / ALB (Load Balancer):**
    - Set `proxy_read_timeout` to `300`.
    - Set `fastcgi_read_timeout` to `300`.
    - *Rationale:* AI generations for Technical Specs can take over 60 seconds.
3. **Queue Workers:**
    - Run the following command via a supervisor or ECS Service:
    - `php artisan queue:work --processes=8 --tries=3 --timeout=600`
    - *Rationale:* 8 workers allow two concurrent users to each run 4 parallel AI critiques (Lead + 3 Reviewers) without queuing delay.

---

## 3. Verification Plan
- **Failure Test:** Manually throw an exception in a job and verify the UI changes from "Loading" to "Failed/Error".
- **Concurrency Test:** Simulate 5 concurrent generation requests and verify Nginx does not time out.
