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
	vendor/bin/phpstan analyse

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	vendor/bin/phpstan analyse --generate-baseline

.PHONY: phpunit
phpunit: vendor                                                                 ## run phpunit tests
	XDEBUG_MODE=coverage vendor/bin/phpunit

.PHONY: infection
infection: vendor                                                               ## run infection
	XDEBUG_MODE=coverage vendor/bin/infection --threads=7

.PHONY: static
static: phpstan cs                                               ## run static analyser

test: phpunit                                                                   ## run tests

.PHONY: dev
dev: static test                                                                ## run dev tools

.PHONY: docs
docs: docs-extract-php docs-php-lint docs-phpcs docs-inject-php

.PHONY: docs-extract-php
docs-extract-php:
	bin/docs-extract-php-code

.PHONY: docs-inject-php
docs-inject-php:
	bin/docs-inject-php-code

.PHONY: docs-format																## format docs
docs-format: docs-phpcs docs-inject-php

.PHONY: docs-php-lint															## lint docs code
docs-php-lint: docs-extract-php
	php -l docs_php/*.php | grep 'Parse error: ' || true

.PHONY: docs-phpcs
docs-phpcs: docs-extract-php
	vendor/bin/phpcbf docs_php --exclude=SlevomatCodingStandard.TypeHints.DeclareStrictTypes,SlevomatCodingStandard.ControlStructures.EarlyExit || true
