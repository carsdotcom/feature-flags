.PHONY: up down shell install test

up:
	docker run --rm --detach \
 		--volume=$(PWD):/src \
		--workdir /src \
 		--name feature-flag-testing-container \
 		dijtomaszewski/php5.5-cli

down:
	docker stop feature-flag-testing-container

shell:
	docker exec -it feature-flag-testing-container bash

install:
	@echo "Creating container..."
	docker run --rm --detach \
		--volume $(PWD):/src \
		--workdir /src \
		--name feature-flag-testing-container \
		dijtomaszewski/php5.5-cli
	@echo "Running composer install"
	docker exec feature-flag-testing-container composer install
	@echo "Stopping and removing container"
	docker stop feature-flag-testing-container
	@echo "Composer dependencies installed."

test:
	@echo "Creating container..."
	@docker run --rm --detach \
		--volume $(PWD):/src \
		--workdir /src \
		--name feature-flag-testing-container \
		dijtomaszewski/php5.5-cli
	@echo "Running tests..."
	@docker exec feature-flag-testing-container ./vendor/bin/phpunit
	@echo "Stopping and removing container..."
	@docker stop feature-flag-testing-container
	@echo "done!"

coverage