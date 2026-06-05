# Design Spec: Ignore Service Account Credentials

## Context & Problem
The Google Service Account credential file `google-sa-cred.json` was stored inside the `backend/storage/` directory and accidentally tracked in Git. Storing active private keys in version control poses a major security risk.

## Proposed Design
We will update `backend/.gitignore` to ignore common naming patterns for service account JSON credential files stored in the `storage/` directory.

### `.gitignore` Pattern
```gitignore
# Prevent service account credentials from being committed
/storage/*-cred.json
/storage/*credentials*.json
/storage/google-sa-cred.json
```

## Verification
- Verify that `backend/storage/google-sa-cred.json` is untracked locally and matches the ignore patterns (will be verified using `git status` / `git check-ignore`).
