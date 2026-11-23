# Variables
COMPOSE_FILE := docker/docker-compose.yaml
PROJECT_DIR := .
DOCKER_COMPOSE := docker compose -f $(COMPOSE_FILE) --project-directory $(PROJECT_DIR)

# ECR Variables
AWS_REGION := ap-southeast-5
AWS_ACCOUNT_ID := 703671920114
ECR_URI := $(AWS_ACCOUNT_ID).dkr.ecr.$(AWS_REGION).amazonaws.com
IMAGE_NAME := fwta/ai-writer
FULL_IMAGE_TAG := $(ECR_URI)/$(IMAGE_NAME):latest

# ECS Variables
ECS_REGION=ap-southeast-5
# ECS UAT Variables
UAT_CLUSTER=fwta-training-ecs-cluster
UAT_TASK_FAMILY=fwta_radio_ads_jobsarawak_uat-ai-writer-uat
UAT_CONTAINER_NAME=ai-writer-uat
# ECS PRD Variables
PRD_CLUSTER=fwta-production-ecs-cluster
PRD_TASK_FAMILY=fwta_radio_ads_jobsarawak_production-ai-writer-prd
PRD_CONTAINER_NAME=ai-writer-prd

# Default target
.PHONY: help
help:
	@echo "Available commands:"
	@echo "  make up              - Start all containers in background"
	@echo "  make down            - Stop and remove all containers"
	@echo "  make restart         - Restart all containers"
	@echo "  make stop            - stop all containers"
	@echo "  make build           - Build or rebuild services"
	@echo "  make logs            - Show logs for all services"
	@echo "  make ps              - List running containers"
	@echo "  make shell           - Open a shell in the app container"
	@echo "  make migrate         - Run database migrations"
	@echo "  make fresh           - Refresh database (migrate:fresh)"
	@echo "  make seed            - Seed the database"
	@echo "  make clear-cache     - Clear Laravel cache"
	@echo "  make ecr-login       - Login to AWS ECR"
	@echo "  make push-ecr        - Build, tag, and push image to ECR"
	@echo "  make ecs-uat-migrate - Run php artisan migrate --force on ECS UAT"
	@echo "  make ecs-uat-seed    - Run php artisan db:seed on ECS UAT"
	@echo "  make ecs-prd-migrate - Run php artisan migrate --force on ECS PRD"
	@echo "  make ecs-prd-seed    - Run php artisan db:seed on ECS PRD"
	@echo "  make release-fix     - Generate empty commit to trigger patch release"
	@echo "  make release-feat    - Generate empty commit to trigger minor release"
	@echo "  make release-major   - Generate empty commit to trigger major release"

.PHONY: up
up:
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down:
	$(DOCKER_COMPOSE) down

.PHONY: stop
stop:
	$(DOCKER_COMPOSE) stop

.PHONY: restart
restart:
	$(DOCKER_COMPOSE) restart

.PHONY: build
build:
	$(DOCKER_COMPOSE) build

.PHONY: logs
logs:
	$(DOCKER_COMPOSE) logs -f

.PHONY: ps
ps:
	$(DOCKER_COMPOSE) ps

.PHONY: shell
shell:
	$(DOCKER_COMPOSE) exec app bash

.PHONY: migrate
migrate:
	$(DOCKER_COMPOSE) exec app php artisan migrate --force

.PHONY: fresh
fresh:
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh --force

.PHONY: seed
seed:
	$(DOCKER_COMPOSE) exec app php artisan db:seed --force

.PHONY: clear-cache
clear-cache:
	$(DOCKER_COMPOSE) exec app php artisan optimize:clear

.PHONY: ecr-login
ecr-login:
	aws ecr get-login-password --region $(AWS_REGION) | docker login --username AWS --password-stdin $(ECR_URI)

.PHONY: push-ecr
push-ecr:
	docker build --pull --platform linux/amd64 -t $(IMAGE_NAME) -f docker/Dockerfile .
	docker tag $(IMAGE_NAME):latest $(FULL_IMAGE_TAG)
	docker push $(FULL_IMAGE_TAG)

ecs-uat-migrate:
	@echo "Running php artisan migrate on ECS cluster $(UAT_CLUSTER)..."
	aws ecs run-task \
		--cluster $(UAT_CLUSTER) \
		--task-definition $(UAT_TASK_FAMILY) \
		--launch-type EC2 \
		--overrides '{"containerOverrides": [{"name": "$(UAT_CONTAINER_NAME)", "command": ["php", "artisan", "migrate", "--force"]}]}' \
		--region $(ECS_REGION)

ecs-uat-db-seed:
	@echo "Running php artisan db:seed on ECS cluster $(UAT_CLUSTER)..."
	aws ecs run-task \
		--cluster $(UAT_CLUSTER) \
		--task-definition $(UAT_TASK_FAMILY) \
		--launch-type EC2 \
		--overrides '{"containerOverrides": [{"name": "$(UAT_CONTAINER_NAME)", "command": ["php", "artisan", "db:seed"]}]}' \
		--region $(ECS_REGION)

ecs-prd-migrate:
	@echo "Running php artisan migrate on ECS cluster $(PRD_CLUSTER)..."
	aws ecs run-task \
		--cluster $(PRD_CLUSTER) \
		--task-definition $(PRD_TASK_FAMILY) \
		--launch-type EC2 \
		--overrides '{"containerOverrides": [{"name": "$(PRD_CONTAINER_NAME)", "command": ["php", "artisan", "migrate", "--force"]}]}' \
		--region $(ECS_REGION)

ecs-prd-db-seed:
	@echo "Running php artisan db:seed on ECS cluster $(PRD_CLUSTER)..."
	aws ecs run-task \
		--cluster $(PRD_CLUSTER) \
		--task-definition $(PRD_TASK_FAMILY) \
		--launch-type EC2 \
		--overrides '{"containerOverrides": [{"name": "$(PRD_CONTAINER_NAME)", "command": ["php", "artisan", "db:seed"]}]}' \
		--region $(ECS_REGION)

.PHONY: release-fix release-feat release-major
release-fix:
	git commit --allow-empty -m "hotfix: manual trigger for patch release"
	git push origin $(shell git rev-parse --abbrev-ref HEAD)

release-feat:
	git commit --allow-empty -m "feature: manual trigger for minor release"
	git push origin $(shell git rev-parse --abbrev-ref HEAD)

release-major:
	git commit --allow-empty -m "breaking!: manual trigger for major release"
	git push origin $(shell git rev-parse --abbrev-ref HEAD)