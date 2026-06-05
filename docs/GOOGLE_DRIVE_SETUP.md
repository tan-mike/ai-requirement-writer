# Google Drive & Service Account Setup Guide

This guide explains how to configure a Google Cloud Service Account and prepare your Google Drive folders so the **AI Requirement Writer** can automatically sync Contexts and Skills (Personas).

---

## Part 1: Create a Google Cloud Project & Service Account

To allow the application to securely access Google Drive without requiring user logins (OAuth), we use a Service Account.

### Step 1: Create a Project
1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Click the project dropdown in the top navigation bar and select **New Project**.
3. Name your project (e.g., "AI Requirement Writer Sync") and click **Create**.
4. Make sure you have your new project selected in the top dropdown.

### Step 2: Enable the Google Drive API
1. In the Cloud Console, go to **APIs & Services > Library**.
2. Search for **Google Drive API**.
3. Click on it and select **Enable**.

### Step 3: Create the Service Account
1. Go to **IAM & Admin > Service Accounts**.
2. Click **+ CREATE SERVICE ACCOUNT** at the top.
3. Give it a name (e.g., "drive-sync-bot") and a description.
4. Click **Create and Continue**.
5. You can skip the "Grant this service account access to project" step (leave roles blank) and click **Continue**, then **Done**.
6. **Important:** Note the email address generated for this service account (it will look something like `drive-sync-bot@your-project-id.iam.gserviceaccount.com`). You will need this later.

### Step 4: Generate the JSON Key
1. Click on the email address of the service account you just created.
2. Go to the **Keys** tab.
3. Click **Add Key > Create new key**.
4. Choose **JSON** and click **Create**.
5. The file will automatically download to your computer. Keep it secure.

---

## Part 2: Prepare Google Drive

Now, you need to create the folders where you will store your markdown files and give the Service Account permission to read them.

### Step 1: Create the Folders
1. Open [Google Drive](https://drive.google.com/).
2. Create two new folders (they can be anywhere, e.g., inside a shared team drive or your personal drive):
   - One for **Skills/Personas** (e.g., "AI Writer - Skills")
   - One for **Contexts** (e.g., "AI Writer - Contexts")

### Step 2: Get the Folder IDs
You need the unique ID for each folder to tell the application where to look.
1. Open the "Skills" folder in Google Drive.
2. Look at the URL in your browser. It will look like this:
   `https://drive.google.com/drive/folders/1A2B3C4D5E6F7G8H9I0J`
3. The long string of characters (`1A2B3C4D5E6F7G8H9I0J`) is the **Folder ID**. Copy this.
4. Repeat this process for the "Contexts" folder.

### Step 3: Share Folders with the Service Account
The Service Account is like a separate, headless user. It cannot see your folders unless you explicitly share them.
1. Right-click the "Skills" folder and select **Share**.
2. In the "Add people and groups" field, paste the **Service Account Email** you copied in Part 1, Step 3 (e.g., `drive-sync-bot@...`).
3. Set the permission to **Viewer** (the app only needs to read the files).
4. Click **Share** (uncheck "Notify people" as it's a bot).
5. Repeat this process to share the "Contexts" folder with the same email.

---

## Part 3: Configure the Application

Now, connect your downloaded JSON key and Folder IDs to the application.

### Local Development (.env)
If you are running the app manually via PHP/Artisan:

1. Move your downloaded JSON file into the `backend/storage/` directory (e.g., `backend/storage/google-credentials.json`).
2. Open your `backend/.env` file and add:
   ```env
   # Path to the JSON file
   GOOGLE_SERVICE_ACCOUNT_JSON=storage/google-credentials.json
   
   # The Folder IDs you copied from the URLs
   GOOGLE_DRIVE_FOLDER_ID=your_skills_folder_id_here
   GOOGLE_DRIVE_CONTEXTS_FOLDER_ID=your_contexts_folder_id_here
   ```

### Docker Deployment
If you are running the application via Docker, you can pass the credentials directly as an environment variable by converting the JSON file into a single string.

1. Open your downloaded JSON file, minify it (remove line breaks), and copy the entire string.
2. Pass it into your `docker run` command:
   ```bash
   docker run ... \
     -e GOOGLE_SERVICE_ACCOUNT_JSON='{"type": "service_account", "project_id": "...", ...}' \
     -e GOOGLE_DRIVE_FOLDER_ID=your_skills_folder_id_here \
     -e GOOGLE_DRIVE_CONTEXTS_FOLDER_ID=your_contexts_folder_id_here \
     ai-requirement-writer
   ```

---

## Part 4: File Format Guidelines

When uploading files to these Google Drive folders, ensure they follow these rules:

- **Format:** Must be plain text files with a `.md` extension.
- **Structure:** Must use YAML frontmatter at the very top of the file to define metadata.

### Example: Skill (Persona) File
Placed in the `GOOGLE_DRIVE_FOLDER_ID` (Skills folder).

```markdown
---
name: Enterprise Security Auditor
role: reviewer
cost_multiplier: 1.5
is_global: true
is_active: true
---

You are a strict enterprise security auditor specializing in OWASP Top 10 and GDPR compliance.
Review the provided requirements and ensure no PII is mishandled.
```

### Example: Context File
Placed in the `GOOGLE_DRIVE_CONTEXTS_FOLDER_ID` (Contexts folder).

```markdown
---
name: Corporate Data Privacy Policy
type: mandate
is_global: true
---

All new applications must adhere to the 2026 Corporate Data Privacy Policy.
Data cannot be stored outside the EU region without explicit consent.
```
*(Note: If `is_global: true`, this context is automatically applied to all projects. If `false`, administrators must manually attach it to a Curated Project.)*
