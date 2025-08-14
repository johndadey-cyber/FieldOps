# FieldOps Makefile — clean test output + smoke QA

PHP        := php
PHPUNIT    := ./vendor/bin/phpunit
PHPSTAN    := ./vendor/bin/phpstan
SERVE_HOST := 127.0.0.1
SERVE_PORT := 8010
DOCROOT    := public

.PHONY: serve serve-bg stop kill lint unit integration test all smoke

## Start dev server in the foreground (Ctrl+C to stop)
serve:
	@echo "Starting PHP dev server on http://$(SERVE_HOST):$(SERVE_PORT) …"
	@$(PHP) -S $(SERVE_HOST):$(SERVE_PORT) -t $(DOCROOT)

## Start dev server in the background
serve-bg:
	@$(PHP) -S $(SERVE_HOST):$(SERVE_PORT) -t $(DOCROOT) >/dev/null 2>&1 & echo $$! > .server_pid
	@echo "Server started on http://$(SERVE_HOST):$(SERVE_PORT) (PID $$(cat .server_pid))"

## Stop background server
stop kill:
	@if [ -f .server_pid ]; then kill $$(cat .server_pid) 2>/dev/null || true; rm -f .server_pid; echo "Server stopped."; else echo "No background server running."; fi

## Static analysis
lint:
	$(PHPSTAN) analyse --memory-limit=1G

## Unit tests only
unit:
	$(PHPUNIT) tests/Unit --testdox

## Integration tests only (no dev server)
integration:
	$(PHPUNIT) tests/Integration --testdox

## All tests
test: unit integration

## Full pipeline
all: lint test

## Smoke test (create → update → delete a job via HTTP)
smoke: serve-bg
	@chmod +x scripts/smoke_job_writes.sh
	@BASE_URL="http://$(SERVE_HOST):$(SERVE_PORT)" scripts/smoke_job_writes.sh
	@$(MAKE) stop
