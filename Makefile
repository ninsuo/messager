.PHONY: test

init-db:
	docker compose exec php php bin/console doctrine:database:drop -e dev -f --if-exists -n
	docker compose exec php php bin/console doctrine:database:create -e dev -n
	docker compose exec php php bin/console doctrine:migrations:migrate -e dev -n
	docker compose exec php php bin/console doctrine:fixtures:load -e dev -n

test:
	docker compose exec php php bin/console doctrine:database:drop -e test -f --if-exists -n
	docker compose exec php php bin/console doctrine:database:create -e test -n
	docker compose exec php php bin/console doctrine:migrations:migrate -e test -n
	docker compose exec php php bin/console doctrine:fixtures:load -e test -n
	docker compose exec php vendor/bin/phpunit -c phpunit.dist.xml

restart-workers:
	docker compose restart worker1 worker2 worker3
