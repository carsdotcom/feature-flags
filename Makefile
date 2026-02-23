.PHONY: up down shell install test build

DOCKER_IMAGE = feature-flags-php
CONTAINER_NAME = feature-flag-testing-container

build:
	docker build -t $(DOCKER_IMAGE) .

up:
	@docker ps -a --filter "name=$(CONTAINER_NAME)" --format "{{.Names}}" | grep -q "^$(CONTAINER_NAME)$$" || \
	docker run --rm --detach \
		--volume=$(PWD):/src \
		--workdir /src \
		--name $(CONTAINER_NAME) \
		$(DOCKER_IMAGE) tail -f /dev/null

down:
	docker stop $(CONTAINER_NAME)

shell: up
	docker exec -it $(CONTAINER_NAME) bash

install:
	@echo "Creating container..."
	@$(MAKE) up
	@echo "Running composer install"
	docker exec $(CONTAINER_NAME) composer install
	@echo "Stopping and removing container"
	docker stop $(CONTAINER_NAME)
	@echo "Composer dependencies installed."

test:
	@echo "Creating container..."
	@$(MAKE) up
	@echo "Running tests..."
	@docker exec $(CONTAINER_NAME) ./vendor/bin/phpunit
	@echo "Stopping and removing container..."
	@docker stop $(CONTAINER_NAME)
	@echo "done!"
