# Reliability & AWS Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent UI deadlocks via background job failure handlers and provide a battle-tested AWS deployment configuration.

**Architecture:** 
- Implement Laravel's `failed()` job hooks for state synchronization.
- Create a comprehensive AWS deployment Markdown guide.
- Update main project README to link the guide.

**Tech Stack:** Laravel, Markdown.

---

### Task 1: Backend - Job Failure Handlers

**Files:**
- Modify: `backend/app/Jobs/ProcessCritiqueJob.php`
- Modify: `backend/app/Jobs/ProcessRepositoryJob.php`
- Modify: `backend/app/Jobs/ProcessContextFileJob.php`

- [ ] **Step 1: Add failed handler to ProcessCritiqueJob**

```php
// backend/app/Jobs/ProcessCritiqueJob.php

    public function failed(\Throwable $exception): void
    {
        $this->draft->update(['status' => 'failed']);
        \Illuminate\Support\Facades\Log::error("Critique Job Failed Permanently: " . $exception->getMessage());
    }
```

- [ ] **Step 2: Add failed handler to ProcessRepositoryJob**

```php
// backend/app/Jobs/ProcessRepositoryJob.php

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => 'error']);
        \Illuminate\Support\Facades\Log::error("Repository Job Failed Permanently: " . $exception->getMessage());
    }
```

- [ ] **Step 3: Add failed handler to ProcessContextFileJob**

```php
// backend/app/Jobs/ProcessContextFileJob.php

    public function failed(\Throwable $exception): void
    {
        $this->project->update(['status' => 'error']);
        \Illuminate\Support\Facades\Log::error("Context File Job Failed Permanently: " . $exception->getMessage());
    }
```

- [ ] **Step 4: Commit handlers**

```bash
git add backend/app/Jobs/ProcessCritiqueJob.php backend/app/Jobs/ProcessRepositoryJob.php backend/app/Jobs/ProcessContextFileJob.php
git commit -m "feat(backend): add failed handlers to background jobs for UI stability"
```

---

### Task 2: Documentation - AWS Deployment Guide

**Files:**
- Create: `DEPLOYMENT_AWS.md`

- [ ] **Step 1: Create DEPLOYMENT_AWS.md with the approved configuration**

Content should include:
- RDS Instance recommendations (`t3.medium`).
- Fargate/EC2 resource specs.
- PHP-FPM `pm.max_children = 60`.
- Nginx/ALB `proxy_read_timeout = 300`.
- Supervisor/Worker command: `php artisan queue:work --processes=8 --tries=3 --timeout=600`.

- [ ] **Step 2: Commit deployment guide**

```bash
git add DEPLOYMENT_AWS.md
git commit -m "docs: add AWS deployment guide for internal team usage"
```

---

### Task 3: Documentation - README Update

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Reference the AWS guide in README.md**

Add a section under "Hosting & Deployment":
```markdown
### AWS (Enterprise/Internal)
For internal team deployments (10-20 users) requiring high-concurrency AI streaming, see the [AWS Deployment Guide](./DEPLOYMENT_AWS.md).
```

- [ ] **Step 2: Commit README update**

```bash
git add README.md
git commit -m "docs: reference AWS deployment guide in main README"
```
