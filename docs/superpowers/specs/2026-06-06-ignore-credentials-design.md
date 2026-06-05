# Design Spec: Ignore Service Account Credentials and Remove from Version Control

## Context & Problem
The Google Service Account credential file `google-sa-cred.json` was stored inside the `backend/storage/` directory and tracked in Git. Storing active private keys in version control poses a security risk.

## Proposed Design
We will move the credentials file into a dedicated secrets folder (`backend/storage/secrets/`), untrack it from version control, and configure git to ignore the entire secrets folder.

### 1. File Organization
Create a dedicated `secrets` directory:
- `backend/storage/secrets/`

Move the credentials file:
- From: `backend/storage/google-sa-cred.json`
- To: `backend/storage/secrets/google-sa-cred.json`

### 2. Untrack the Old Credential File
Remove the old file path from the Git index:
```bash
git rm --cached backend/storage/google-sa-cred.json
```

### 3. Configure `.gitignore`
Update `backend/.gitignore` to ignore the entire `secrets` directory:
```gitignore
# Ignore secrets directory
/storage/secrets/
```

### 4. Update Environment Variables (`backend/.env`)
Update the path to the credentials JSON:
```env
GOOGLE_SERVICE_ACCOUNT_JSON=storage/secrets/google-sa-cred.json
```

## Verification
- Verify that `backend/storage/secrets/google-sa-cred.json` exists locally.
- Verify that `git status` shows `backend/storage/secrets/` is ignored.
- Verify that the sync functionality works as expected with the new path.
