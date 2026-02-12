.PHONY: test deploy deploy-bis status-bis

# Variables
BIS_HOST ?= messager-std-bis.europe-west9-b.messager-486910
PROD_HOST ?= messager-group-chq9.europe-west9-a.messager-486910

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
	docker compose restart worker1 worker2 worker3 worker4

deploy:
	./scripts/gcp/deploy.sh $(PROD_HOST)

deploy-bis:
	./scripts/gcp/deploy-bis.sh $(BIS_HOST)

instances:
	gcloud compute instances list --project=messager-486910

push:
	./scripts/gcp/push-images.sh

ssh:
	ssh $(PROD_HOST)

ssh-bis:
	ssh $(BIS_HOST)

status-bis:
	ssh $(BIS_HOST) "sudo docker compose -f ~/messager/compose.yaml -f ~/messager/compose.prod.yaml -f ~/messager/compose.bis.yaml ps"

logs-bis:
	ssh $(BIS_HOST) "sudo docker compose -f ~/messager/compose.yaml -f ~/messager/compose.prod.yaml -f ~/messager/compose.bis.yaml logs -f --tail=100"
