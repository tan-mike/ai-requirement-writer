# Ignore Service Account Credentials Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Relocate the Google service account JSON key to a dedicated secrets folder, untrack the old credentials file from Git, update environment configuration, and ignore the secrets folder.

**Architecture:** Use git commands to untrack the file, move the file to a new subdirectory (`storage/secrets/`), update references in `.env`, and add git ignore rules to `.gitignore`.

**Tech Stack:** Laravel, Git, Environment Configuration.

---

### Task 1: Create Secrets Directory and Move Credentials File

**Files:**
- Create: `backend/storage/secrets/google-sa-cred.json` (moved from old location)
- Delete: `backend/storage/google-sa-cred.json`

- [ ] **Step 1: Create the secrets directory**

Run: `mkdir -p backend/storage/secrets`
Expected: Directory `backend/storage/secrets` is created.

- [ ] **Step 2: Move the credential file to the secrets directory**

Run: `mv backend/storage/google-sa-cred.json backend/storage/secrets/google-sa-cred.json`
Expected: File `backend/storage/secrets/google-sa-cred.json` exists, and `backend/storage/google-sa-cred.json` is deleted.

- [ ] **Step 3: Verify the file exists in the new location**

Run: `ls -la backend/storage/secrets/google-sa-cred.json`
Expected: File details for `google-sa-cred.json` are listed.

---

### Task 2: Untrack the old credentials file from Git

**Files:**
- Modify: Git Index

- [ ] **Step 1: Remove the old file from Git index tracking**

Run: `git rm --cached backend/storage/google-sa-cred.json`
Expected: Output showing `rm 'backend/storage/google-sa-cred.json'` from the Git index.

- [ ] **Step 2: Verify git status shows the deletion of tracking**

Run: `git status`
Expected: `deleted: backend/storage/google-sa-cred.json` listed under "Changes to be committed".

---

### Task 3: Update Gitignore and Environment Configuration

**Files:**
- Modify: `backend/.gitignore`
- Modify: `backend/.env`

- [ ] **Step 1: Add ignore rules for secrets directory to `.gitignore`**

In `backend/.gitignore`, add `/storage/secrets/` to the ignore list.

```gitignore
# Ignore secrets directory
/storage/secrets/
```

- [ ] **Step 2: Update `GOOGLE_SERVICE_ACCOUNT_JSON` path in `.env`**

In `backend/.env`, modify line 86 to point to the new location:

```env
GOOGLE_SERVICE_ACCOUNT_JSON=storage/secrets/google-sa-cred.json
```

- [ ] **Step 3: Verify that the credential file is successfully ignored by git**

Run: `git status`
Expected: `backend/storage/secrets/` does not show up as untracked files in the working directory.

- [ ] **Step 4: Commit changes**

Run:
```bash
git add backend/.gitignore backend/.env
git commit -m "chore: move service account credentials to ignored secrets folder"
```
Expected: Commit successful.
