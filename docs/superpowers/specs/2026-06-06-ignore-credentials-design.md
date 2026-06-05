# Design Spec: Ignore Service Account Credentials and Remove from Version Control

## Context & Problem
The Google Service Account credential file `google-sa-cred.json` was stored inside the `backend/storage/` directory and tracked in Git. Storing active private keys in version control poses a security risk.

## Proposed Design
We will keep the file `backend/storage/google-sa-cred.json` locally on-disk, but we will untrack it from version control and configure git to ignore it.

### 1. Untrack the Credential File
We will remove `backend/storage/google-sa-cred.json` from the Git index without deleting the physical file:
```bash
git rm --cached backend/storage/google-sa-cred.json
```

### 2. Configure `.gitignore`
We will update `backend/.gitignore` to ignore service account JSON credential files in the `storage/` directory:
```gitignore
# Prevent service account credentials from being committed
/storage/*-cred.json
/storage/*credentials*.json
```

## Verification
- Verify that `backend/storage/google-sa-cred.json` remains on disk locally.
- Verify that `git status` shows `backend/storage/google-sa-cred.json` is untracked and ignored.
