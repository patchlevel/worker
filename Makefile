help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

vendor: composer.lock
	composer install

.PHONY: phpcs-check
cs-check: vendor                                                                ## run phpcs
	vendor/bin/phpcs

.PHONY: phpcs-fix
cs: vendor                                                                      ## run phpcs fixer
	vendor/bin/phpcbf

.PHONY: phpstan
phpstan: vendor                                                                 ## run phpstan static code analyser
	php -d memory_limit=512M vendor/bin/phpstan analyse

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	php -d memory_limit=512M vendor/bin/phpstan analyse --generate-baseline

.PHONY: phpunit
phpunit: vendor                                                                 ## run phpunit tests
	XDEBUG_MODE=coverage vendor/bin/phpunit

.PHONY: infection
infection: vendor                                                               ## run infection
	vendor/bin/infection

.PHONY: static
static: psalm phpstan phpcs-check                                               ## run static analyser

test: phpunit                                                                   ## run tests

.PHONY: dev
dev: static test                                                                ## run dev tools
