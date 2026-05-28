# Local Docker Deployment Guide

Follow these steps to build and run the **AI Requirement Writer** as a single, unified container on your local machine.

## Prerequisites

- **Docker Desktop:** Installed and running.
- **Google Gemini API Key:** [Get one here](https://aistudio.google.com/app/apikey).
- **Database:** You can use a local MySQL server OR run one in Docker (recommended for isolation).

---

## Step 1: Build the Unified Image

From the root directory of the project, run the following command to build the multi-stage image. This will compile the Next.js frontend, install Laravel dependencies, and package everything into an Alpine Linux container.

```bash
docker build -t ai-requirement-writer .
```

---

## Step 2: Setup the Database

### Option A: Running MySQL in Docker (Recommended)
This is the easiest way to ensure a clean, isolated environment.

1. **Create a network:**
   ```bash
docker network create ai-writer-net
```

2. **Launch MySQL:**
   ```bash
docker run -d \
  --name ai-db \
  --network ai-writer-net \
  -e MYSQL_DATABASE=ai_writer \
  -e MYSQL_ROOT_PASSWORD=root_pass \
  mysql:8
```

### Option B: Using your existing Local MySQL
If you have MySQL running directly on your Mac:
- Use `DB_HOST=host.docker.internal` in the run command below.
- Ensure your MySQL user allows connections from `%` (any host).

---

## Step 3: Run the Application

Execute the following command to start the container. Replace `your_gemini_key_here` with your actual API key.

### If using Option A (MySQL in Docker):
```bash
docker run -it --rm \
  -p 8080:80 \
  --name ai-app \
  --network ai-writer-net \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=ai-db \
  -e DB_PORT=3306 \
  -e DB_DATABASE=ai_writer \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=root_pass \
  -e GEMINI_API_KEY=your_gemini_key_here \
  -e QUEUE_WORKER_COUNT=2 \
  ai-requirement-writer
```

### If using Option B (Local Mac MySQL):
```bash
docker run -it --rm \
  -p 8080:80 \
  --name ai-app \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=host.docker.internal \
  -e DB_PORT=3306 \
  -e DB_DATABASE=your_local_db_name \
  -e DB_USERNAME=your_local_user \
  -e DB_PASSWORD=your_local_pass \
  -e GEMINI_API_KEY=your_gemini_key_here \
  -e QUEUE_WORKER_COUNT=2 \
  ai-requirement-writer
```

---

## Step 4: Access the App

Once the logs show `supervisord started`, open your browser to:

**[http://localhost:8080](http://localhost:8080)**

- **Frontend:** Served automatically by Nginx.
- **Backend API:** Proxied automatically to `/api`.
- **Database:** Migrations are run automatically on startup.
- **Workers:** 2 workers (configured via `QUEUE_WORKER_COUNT`) will be processing AI tasks.

---

## Troubleshooting

- **Check Logs:** Run `docker logs -f ai-app` to see live output from Nginx, PHP, Node, and the Workers.
- **Reset Database:** To start fresh with the Docker database, run `docker rm -f ai-db` and repeat Step 2.
- **Rebuild:** If you change any code, you must repeat **Step 1** to update the image.
