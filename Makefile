.PHONY: test deploy deploy-bis

#==============================================================================
# CONFIGURATION SSH (À copier dans votre ~/.ssh/config)
# ==============================================================================
# Host messager-prod
#     HostName 34.155.205.113
#     User ninsuo
#     IdentityFile ~/.ssh/google_compute_engine
#
# Host messager-bis
#     HostName 34.155.248.193
#     User ninsuo
#     IdentityFile ~/.ssh/google_compute_engine
# ==============================================================================

# Variables
PROD_HOST ?= messager-prod
BIS_HOST  ?= messager-bis

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

deploy-prod:
	./scripts/gcp/deploy.sh $(PROD_HOST)

deploy-bis:
	./scripts/gcp/deploy-bis.sh $(BIS_HOST)

instances:
	gcloud compute instances list --project=messager-486910

push:
	./scripts/gcp/push-images.sh

ssh-prod:
	ssh $(PROD_HOST)

ssh-bis:
	ssh $(BIS_HOST)
