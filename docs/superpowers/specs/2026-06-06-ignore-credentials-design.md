# Design Spec: Off-Disk Service Account Credentials Migration

## Context & Problem
The Google Service Account credential file `google-sa-cred.json` was stored inside the `backend/storage/` directory and accidentally tracked in Git. Storing active private keys in version control or even on-disk poses a major security risk.

## Proposed Design
We will migrate to an off-disk credential storage mechanism. The JSON key content will be stored directly inside the environment variables, avoiding keeping files containing secrets on the filesystem.

### 1. Environment Variable Configuration (`backend/.env`)
We will store the raw JSON content of the service account credential directly as a single-line string in `GOOGLE_SERVICE_ACCOUNT_JSON`:
```env
GOOGLE_SERVICE_ACCOUNT_JSON='{"type": "service_account", "project_id": "plenary-ability-483507-b3", ...}'
```

### 2. Physical File Deletion
The physical credential file at `backend/storage/google-sa-cred.json` will be completely deleted from disk and untracked from Git.

### 3. `.gitignore` Hardening
We will update `backend/.gitignore` to ignore common naming patterns for service account JSON credential files stored in the `storage/` directory as a fallback safety measure:
```gitignore
# Prevent service account credentials from being committed
/storage/*-cred.json
/storage/*credentials*.json
```

## Verification
- Verify that `backend/storage/google-sa-cred.json` is deleted and untracked.
- Verify that the application successfully syncs using the JSON string configuration in `GOOGLE_SERVICE_ACCOUNT_JSON`.
